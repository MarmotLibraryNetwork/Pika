/*
 * Copyright (C) 2023  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import org.marc4j.marc.*;

import java.util.HashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Class to handle loading data from MARC records
 *
 * Created by mnoble on 6/16/2017.
 */
class MarcUtil {
	/**
	 * Get Set of Strings as indicated by tagStr. For each field spec in the
	 * tagStr that is NOT about bytes (i.e. not a 008[7-12] type fieldspec), the
	 * result string is the concatenation of all the specific subfields.
	 *
	 * @param record
	 *          - the marc record object
	 * @param tagStr
	 *          string containing which field(s)/subfield(s) to use. This is a
	 *          series of: marc "tag" string (3 chars identifying a marc field,
	 *          e.g. 245) optionally followed by characters identifying which
	 *          subfields to use. Separator of colon indicates a separate value,
	 *          rather than concatenation. 008[5-7] denotes bytes 5-7 of the 008
	 *          field (0 based counting) 100[a-cf-z] denotes the bracket pattern
	 *          is a regular expression indicating which subfields to include.
	 *          Note: if the characters in the brackets are digits, it will be
	 *          interpreted as particular bytes, NOT a pattern. 100abcd denotes
	 *          subfields a, b, c, d are desired.
	 * @return the contents of the indicated marc field(s)/subfield(s), as a set
	 *         of Strings.
	 */
	static Set<String> getFieldList(Record record, String tagStr) {
		String[] tags = tagStr.split(":");
		Set<String> result = new LinkedHashSet<>();
		for (String tag1 : tags) {
			// Check to ensure tag length is at least 3 characters
			if (tag1.length() < 3) {
				System.err.println("Invalid tag specified: " + tag1);
				continue;
			}

			// Get Field Tag
			String tag = tag1.substring(0, 3);
			boolean linkedField = false;
			if (tag.equals("LNK")) {
				tag = tag1.substring(3, 6);
				linkedField = true;
			}
			// Process Subfields
			String subfield = tag1.substring(3);
			boolean havePattern = false;
			int subend = 0;
			// brackets indicate parsing for individual characters or as pattern
			int bracket = tag1.indexOf('[');
			if (bracket != -1) {
				String sub[] = tag1.substring(bracket + 1).split("[\\]\\[\\-, ]+");
				try {
					// if bracket expression is digits, expression is treated as character
					// positions
					int substart = Integer.parseInt(sub[0]);
					subend = (sub.length > 1) ? Integer.parseInt(sub[1]) + 1
							: substart + 1;
					String subfieldWObracket = subfield.substring(0, bracket - 3);
					result.addAll(getSubfieldDataAsSet(record, tag, subfieldWObracket, substart, subend));
				} catch (NumberFormatException e) {
					// assume brackets expression is a pattern such as [a-z]
					havePattern = true;
				}
			}
			if (subend == 0) // don't want specific characters.
			{
				String separator = null;
				if (subfield.indexOf('\'') != -1) {
					separator = subfield.substring(subfield.indexOf('\'') + 1,
							subfield.length() - 1);
					subfield = subfield.substring(0, subfield.indexOf('\''));
				}

				if (havePattern)
					if (linkedField)
						result.addAll(getLinkedFieldValue(record, tag, subfield, separator));
					else
						result.addAll(getAllSubfields(record, tag + subfield, separator));
				else if (linkedField)
					result.addAll(getLinkedFieldValue(record, tag, subfield, separator));
				else
					result.addAll(getSubfieldDataAsSet(record, tag, subfield, separator));
			}
		}
		return result;
	}

	/**
	 * Get the specified substring of subfield values from the specified MARC
	 * field, returned as a set of strings to become lucene document field values
	 *
	 * @param record
	 *          - the marc record object
	 * @param fldTag
	 *          - the field name, e.g. 008
	 * @param subfield
	 *          - the string containing the desired subfields
	 * @param beginIx
	 *          - the beginning index of the substring of the subfield value
	 * @param endIx
	 *          - the ending index of the substring of the subfield value
	 * @return the result set of strings
	 */
	@SuppressWarnings("unchecked")
	private static Set<String> getSubfieldDataAsSet(Record record, String fldTag, String subfield, int beginIx, int endIx) {
		Set<String> resultSet = new LinkedHashSet<>();

		// Process Leader
		if (fldTag.equals("000")) {
			resultSet.add(record.getLeader().toString().substring(beginIx, endIx));
			return resultSet;
		}

		// Loop through Data and Control Fields
		List<VariableField> varFlds = record.getVariableFields(fldTag);
		for (VariableField vf : varFlds) {
			if (!isControlField(fldTag) && subfield != null) {
				// Data Field
				DataField dfield = (DataField) vf;
				if (subfield.length() > 1) {
					// automatic concatenation of grouped subFields
					StringBuilder buffer = new StringBuilder("");
					List<Subfield> subFields = dfield.getSubfields();
					for (Subfield sf : subFields) {
						if (subfield.indexOf(sf.getCode()) != -1
								&& sf.getData().length() >= endIx) {
							if (buffer.length() > 0)
								buffer.append(" ");
							buffer.append(sf.getData().substring(beginIx, endIx));
						}
					}
					resultSet.add(buffer.toString());
				} else {
					// get all instances of the single subfield
					List<Subfield> subFlds = dfield.getSubfields(subfield.charAt(0));
					for (Subfield sf : subFlds) {
						if (sf.getData().length() >= endIx)
							resultSet.add(sf.getData().substring(beginIx, endIx));
					}
				}
			} else // Control Field
			{
				String cfldData = ((ControlField) vf).getData();
				if (cfldData.length() >= endIx)
					resultSet.add(cfldData.substring(beginIx, endIx));
			}
		}
		return resultSet;
	}

	/**
	 * Get the specified subfields from the specified MARC field, returned as a
	 * set of strings to become lucene document field values
	 *
	 * @param fldTag
	 *          - the field name, e.g. 245
	 * @param subfieldsStr
	 *          - the string containing the desired subfields
	 * @param separator
	 *          - the separator string to insert between subfield items (if null,
	 *          a " " will be used)
	 * @return a Set of String, where each string is the concatenated contents of
	 *          all the desired subfield values from a single instance of the
	 *          fldTag
	 */
	@SuppressWarnings("unchecked")
	static Set<String> getSubfieldDataAsSet(Record record, String fldTag, String subfieldsStr, String separator) {
		Set<String> resultSet = new LinkedHashSet<>();

		// Process Leader
		if (fldTag.equals("000")) {
			resultSet.add(record.getLeader().toString());
			return resultSet;
		}

		// Loop through Data and Control Fields
		// int iTag = new Integer(fldTag).intValue();
		List<VariableField> varFlds = record.getVariableFields(fldTag);
		if (varFlds == null){
			return resultSet;
		}
		for (VariableField vf : varFlds) {
			if (!isControlField(fldTag) && subfieldsStr != null) {
				// DataField
				DataField dfield = (DataField) vf;

				if (subfieldsStr.length() > 1 || separator != null) {
					// concatenate subfields using specified separator or space
					StringBuilder buffer = new StringBuilder("");
					List<Subfield> subFields = dfield.getSubfields();
					for (Subfield sf : subFields) {
						if (subfieldsStr.indexOf(sf.getCode()) != -1) {
							if (buffer.length() > 0) {
								buffer.append(separator != null ? separator : " ");
							}
							buffer.append(sf.getData().trim());
						}
					}
					if (buffer.length() > 0){
						resultSet.add(buffer.toString());
					}
				} else if (subfieldsStr.length() == 1) {
					// get all instances of the single subfield
					List<Subfield> subFields = dfield.getSubfields(subfieldsStr.charAt(0));
					for (Subfield sf : subFields) {
						resultSet.add(sf.getData().trim());
					}
				} else {
					//logger.warn("No subfield provided when getting getSubfieldDataAsSet for " + fldTag);
				}
			} else {
				// Control Field
				resultSet.add(((ControlField) vf).getData().trim());
			}
		}
		return resultSet;
	}

	private static Pattern controlFieldPattern = Pattern.compile("00[0-9]");
	private static boolean isControlField(String fieldTag) {
		return controlFieldPattern.matcher(fieldTag).matches();
	}

	private static HashMap<String, Pattern> subfieldPatterns = new HashMap<>();
	/**
	 * Given a tag for a field, and a list (or regex) of one or more subfields get
	 * any linked 880 fields and include the appropriate subfields as a String
	 * value in the result set.
	 *
	 * @param tag
	 *          - the marc field for which 880s are sought.
	 * @param subfield
	 *          - The subfield(s) within the 880 linked field that should be
	 *          returned [a-cf-z] denotes the bracket pattern is a regular
	 *          expression indicating which subfields to include from the linked
	 *          880. Note: if the characters in the brackets are digits, it will
	 *          be interpreted as particular bytes, NOT a pattern 100abcd denotes
	 *          subfields a, b, c, d are desired from the linked 880.
	 * @param separator
	 *          - the separator string to insert between subfield items (if null,
	 *          a " " will be used)
	 *
	 * @return set of Strings containing the values of the designated 880
	 *         field(s)/subfield(s)
	 */
	@SuppressWarnings("unchecked")
	private static Set<String> getLinkedFieldValue(Record record, String tag, String subfield, String separator) {
		// assume brackets expression is a pattern such as [a-z]
		Set<String> result = new LinkedHashSet<>();
		Pattern subfieldPattern = null;
		if (subfield.indexOf('[') != -1) {
			subfieldPattern = subfieldPatterns.get(subfield);
			if (subfieldPattern == null){
				subfieldPattern = Pattern.compile(subfield);
				subfieldPatterns.put(subfield, subfieldPattern);
			}
		}
		List<DataField> fields = record.getDataFields("880");
		for (DataField dfield : fields) {
			Subfield link = dfield.getSubfield('6');
			if (link != null && link.getData().startsWith(tag)) {
				List<Subfield> subList = dfield.getSubfields();
				StringBuilder buf = new StringBuilder("");
				for (Subfield subF : subList) {
					boolean addIt = false;
					if (subfieldPattern != null) {
						Matcher matcher = subfieldPattern.matcher("" + subF.getCode());
						// matcher needs a string, hence concat with empty
						// string
						if (matcher.matches()) {
							addIt = true;
						}
					} else {
						// a list a subfields
						if (subfield.indexOf(subF.getCode()) != -1) {
							addIt = true;
						}
					}
					if (addIt) {
						if (buf.length() > 0) {
							buf.append(separator != null ? separator : " ");
						}
						buf.append(subF.getData().trim());
					}
				}
				if (buf.length() > 0) {
					result.add(cleanData(buf.toString()));
				}
			}
		}
		return (result);
	}

	/**
	 * extract all the subfields requested in requested marc fields. Each instance
	 * of each marc field will be put in a separate result (but the subfields will
	 * be concatenated into a single value for each marc field)
	 *
	 * @param fieldSpec
	 *          - the desired marc fields and subfields as given in the
	 *          xxx_index.properties file
	 * @param separator
	 *          - the character to use between subfield values in the solr field
	 *          contents
	 * @return Set of values (as strings) for solr field
	 */
	@SuppressWarnings("unchecked")
	static Set<String> getAllSubfields(Record record, String fieldSpec, String separator) {
		Set<String> result = new LinkedHashSet<>();

		String[] fldTags = fieldSpec.split(":");
		for (String fldTag1 : fldTags) {
			// Check to ensure tag length is at least 3 characters
			if (fldTag1.length() < 3) {
				System.err.println("Invalid tag specified: " + fldTag1);
				continue;
			}

			String fldTag = fldTag1.substring(0, 3);

			String subfldTags = fldTag1.substring(3);

			List<DataField> marcFieldList = record.getDataFields(fldTag);
			if (!marcFieldList.isEmpty()) {
				for (DataField marcField : marcFieldList) {

					StringBuilder buffer = getSpecifiedSubfieldsAsString(marcField, subfldTags, separator);
					if (buffer.length() > 0) {
						result.add(cleanData(buffer.toString()));
					}
				}
			}
		}

		return result;
	}

	static StringBuilder getSpecifiedSubfieldsAsString(DataField marcField, String validSubfields, String separator) {
		StringBuilder buffer = new StringBuilder("");
		List<Subfield> subFields = marcField.getSubfields();
		for (Subfield subfield : subFields) {
			if (validSubfields.isEmpty() || validSubfields.contains("" + subfield.getCode())){
				if (buffer.length() > 0) {
					buffer.append(separator != null ? separator : " ");
				}
				buffer.append(subfield.getData().trim());
			}
		}
		return buffer;
	}

	static List<DataField> getDataFields(Record marcRecord, String tag) {
		return marcRecord.getDataFields(tag);
	}

	static List<DataField> getDataFields(Record marcRecord, String[] tags) {
		return marcRecord.getDataFields(tags);
	}

	static ControlField getControlField(Record marcRecord, String tag){
		List variableFields = marcRecord.getControlFields(tag);
		ControlField variableFieldReturn = null;
		for (Object variableField : variableFields){
			if (variableField instanceof ControlField){
				variableFieldReturn = (ControlField)variableField;
			}
		}
		return variableFieldReturn;
	}

	/**
	 * Loops through all datafields and creates a field for "keywords"
	 * searching. Shameless stolen from Vufind Indexer Custom Code
	 *
	 * @param lowerBound
	 *          - the "lowest" marc field to include (e.g. 100)
	 * @param upperBound
	 *          - one more than the "highest" marc field to include (e.g. 900 will
	 *          include up to 899).
	 * @return a string containing ALL subfields of ALL marc fields within the
	 *         range indicated by the bound string arguments.
	 */
	@SuppressWarnings("unchecked")
	static String getAllSearchableFields(Record record, int lowerBound, int upperBound) {
		StringBuilder buffer = new StringBuilder("");

		List<DataField> fields = record.getDataFields();
		for (DataField field : fields) {
			// Get all fields starting with the 100 and ending with the 839
			// This will ignore any "code" fields and only use textual fields
			int tag = localParseInt(field.getTag(), -1);
			if ((tag >= lowerBound) && (tag < upperBound)) {
				// Loop through subfields
				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					if (buffer.length() > 0)
						buffer.append(" ");
					buffer.append(subfield.getData());
				}
			}
		}

		return buffer.toString();
	}

	static String getFirstFieldVal(Record record, String fieldSpec) {
		Set<String> result = MarcUtil.getFieldList(record, fieldSpec);
		if (result.isEmpty()){
			return null;
		}else{
			return result.iterator().next();
		}
	}

	/**
	 * return an int for the passed string
	 *
	 * @param str The String value of the integer to prompt
	 * @param defValue
	 *          - default value, if string doesn't parse into int
	 */
	private static int localParseInt(String str, int defValue) {
		int value = defValue;
		try {
			value = Integer.parseInt(str);
		} catch (NumberFormatException nfe) {
			// provided value is not valid numeric string
			// Ignoring it and moving happily on.
		}
		return (value);
	}

	/**
	 * Below methods are taken from General utility functions for org.solrmarc
	 *
	 * @author Wayne Graham
	 * @version $Id: Utils.java 1581 2011-12-19 21:21:52Z rh9ec@virginia.edu $
	 */
	private static final Pattern cleanJrSrPattern = Pattern.compile(".*[JS]r\\.$");
	private static final Pattern cleaner1Pattern = Pattern.compile(".*\\w\\w\\.$");
	private static final Pattern cleaner2Pattern = Pattern.compile(".*\\p{L}\\p{L}\\.$");
	private static final Pattern cleaner3Pattern = Pattern.compile(".*\\w\\p{InCombiningDiacriticalMarks}?\\w\\p{InCombiningDiacriticalMarks}?\\.$");
	private static final Pattern cleaner4Pattern = Pattern.compile(".*\\p{Punct}\\.$");
	/**
	 * Removes trailing characters (space, comma, slash, semicolon, colon),
	 * trailing period if it is preceded by at least three letters, and single
	 * square bracket characters if they are the start and/or end chars of the
	 * cleaned string
	 *
	 * @param origStr
	 *          String to clean
	 * @return cleaned string
	 */
	public static String cleanData(String origStr) {
		String currResult = origStr;
		String prevResult;
		do {
			prevResult = currResult;
			currResult = currResult.trim();

			currResult = currResult.replaceAll(" *([,/;:])$", "");

			// trailing period removed in certain circumstances
			if (currResult.endsWith(".")) {
				if (cleanJrSrPattern.matcher(currResult).matches()) {
					// dont strip period off of Jr. or Sr.
				} else if (cleaner1Pattern.matcher(currResult).matches()) {
					currResult = currResult.substring(0, currResult.length() - 1);
				} else if (cleaner2Pattern.matcher(currResult).matches()) {
					currResult = currResult.substring(0, currResult.length() - 1);
				} else if (cleaner3Pattern.matcher(currResult).matches()) {
					currResult = currResult.substring(0, currResult.length() - 1);
				} else if (cleaner4Pattern.matcher(currResult).matches()) {
					currResult = currResult.substring(0, currResult.length() - 1);
				}
			}

			currResult = removeOuterBrackets(currResult);

			if (currResult.length() == 0) return currResult;

		} while (!currResult.equals(prevResult));

		// if (!currResult.equals(origStr))
		// System.out.println(origStr + " -> "+ currResult);

		return currResult;
	}
	/**
	 * Remove single square bracket characters if they are the start and/or end
	 * chars (matched or unmatched) and are the only square bracket chars in the
	 * string.
	 */
	private static String removeOuterBrackets(String origStr) {
		if (origStr == null || origStr.length() == 0) return origStr;

		String result = origStr.trim();

		if (result.length() > 0) {
			boolean openBracketFirst = result.charAt(0) == '[';
			boolean closeBracketLast = result.endsWith("]");
			if (openBracketFirst && closeBracketLast && result.indexOf('[', 1) == -1 && result.lastIndexOf(']', result.length() - 2) == -1)
				// only square brackets are at beginning and end
				result = result.substring(1, result.length() - 1);
			else if (openBracketFirst && result.indexOf(']') == -1)
				// starts with '[' but no ']'; remove open bracket
				result = result.substring(1);
			else if (closeBracketLast && result.indexOf('[') == -1)
				// ends with ']' but no '['; remove close bracket
				result = result.substring(0, result.length() - 1);
		}

		return result.trim();
	}

}
