package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.*;

import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.regex.PatternSyntaxException;

/**
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 9/29/2014
 * Time: 12:01 PM
 */
abstract class MarcRecordProcessor {
	protected      Logger             logger;
	protected      GroupedWorkIndexer indexer;
	private        boolean            fullReindex;
	private static Pattern            mpaaRatingRegex1    = Pattern.compile("(?:.*?)[rR]ated:?\\s(G|PG-13|PG|R|NC-17|NR|X)(?:.*)", Pattern.CANON_EQ);
	private static Pattern            mpaaRatingRegex2    = Pattern.compile("(?:.*?)[rR]ating:?\\s(G|PG-13|PG|R|NC-17|NR|X)(?:.*)", Pattern.CANON_EQ);
	private static Pattern            mpaaRatingRegex3    = Pattern.compile("(?:.*?)(G|PG-13|PG|R|NC-17|NR|X)\\sRated(?:.*)", Pattern.CANON_EQ);
	private static Pattern            mpaaNotRatedRegex   = Pattern.compile("Rated\\sNR\\.?|Not Rated\\.?|NR");
	private        HashSet<String>    unknownSubjectForms = new HashSet<>();

	MarcRecordProcessor(GroupedWorkIndexer indexer, Logger logger, boolean fullReindex) {
		this.indexer = indexer;
		this.logger = logger;
		this.fullReindex = fullReindex;
	}

	/**
	 * Load MARC record from disk based on identifier
	 * Then call updateGroupedWorkSolrDataBasedOnMarc to do the actual update of the work
	 *
	 * @param groupedWork the work to be updated
	 * @param identifier the identifier to load information for
	 */
	public abstract void processRecord(GroupedWorkSolr groupedWork, String identifier);

	protected void loadSubjects(GroupedWorkSolr groupedWork, Record record){
		List<DataField> subjectFields = MarcUtil.getDataFields(record, new String[]{"600", "610", "611", "630", "648", "650", "651", "655", "690"});

		HashSet<String> subjects = new HashSet<>();
		for (DataField curSubjectField : subjectFields){
			switch (curSubjectField.getTag()) {
				case "600": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if ((curSubfieldCode >= 'a' && curSubfieldCode <= 'z' && curSubfieldCode != 'i' && curSubfieldCode != 'w')) { // any letter but i & w
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'a' || curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'd') {
								groupedWork.addEra(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "610": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if ((curSubfieldCode >= 'a' && curSubfieldCode <= 'z' && curSubfieldCode != 'i' && curSubfieldCode != 'w')) { // any letter but i & w
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "611": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if (curSubfieldCode >= 'a' && curSubfieldCode <= 'z' && "bijmow".indexOf(curSubfieldCode) == -1) { // any letter but b, i, j, m, o, or w
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "630": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if (curSubfieldCode >= 'a' && curSubfieldCode <= 'z' && "cdeijqw".indexOf(curSubfieldCode) == -1) { // any letter but b, c, d, e, i, j, m, o, or w
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "648": {
					String curSubject = "";
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if (curSubfieldCode == 'x') {
							groupedWork.addTopicFacet(curSubfieldData);
						} else if (curSubfieldCode == 'v') {
							groupedWork.addGenreFacet(curSubfieldData);
						} else if (curSubfieldCode == 'z') {
							groupedWork.addGeographicFacet(curSubfieldData);
						} else if (curSubfieldCode == 'a' || curSubfieldCode == 'y') {
							groupedWork.addEra(curSubfieldData);
						}
					}
					subjects.add(curSubject);
					break;
				}
				case "650": {
					// Assume subject is an LC subject by default
					boolean isLCSubject    = true;
					boolean isBisacSubject = false;
					if (curSubjectField.getSubfield('2') != null) {
						String  bisacCode       = curSubjectField.getSubfield('2').getData().trim();
						boolean hasBisacMarkers = bisacCode.equalsIgnoreCase("bisacsh") || bisacCode.equalsIgnoreCase("bisacmt") || bisacCode.equalsIgnoreCase("bisacrt");
						if (hasBisacMarkers) {
							// Since the subject has a Bisac Marker, it is a bisac subject and not an LC subject
							isLCSubject    = false;
							isBisacSubject = true;
						}
					}
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if ("abcdevxyz".indexOf(curSubfieldCode) >= 0) {
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'a' || curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
								if (isLCSubject) {
									groupedWork.addLCSubject(curSubfieldData);
								}
								if (isBisacSubject) {
									groupedWork.addBisacSubject(curSubfieldData);
								}
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "651": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if ("abcdevxyz".indexOf(curSubfieldCode) >= 0) {
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							groupedWork.addTopic(curSubfieldData);
							if (curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
								groupedWork.addGeographic(curSubfieldData);
							} else if (curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
								groupedWork.addGeographic(curSubfieldData);
							} else if (curSubfieldCode == 'a' || curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
								groupedWork.addGeographic(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
								groupedWork.addGeographic(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "655": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if ("abcvxyz".indexOf(curSubfieldCode) >= 0) {
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);

							if (curSubfieldCode == 'x') {
								groupedWork.addTopicFacet(curSubfieldData);
								groupedWork.addGenre(curSubfieldData);
							} else if (curSubfieldCode == 'a' || curSubfieldCode == 'v') {
								groupedWork.addGenreFacet(curSubfieldData);
								groupedWork.addGenre(curSubfieldData);
							} else if (curSubfieldCode == 'z') {
								groupedWork.addGeographicFacet(curSubfieldData);
								groupedWork.addGenre(curSubfieldData);
							} else if (curSubfieldCode == 'y') {
								groupedWork.addEra(curSubfieldData);
								groupedWork.addGenre(curSubfieldData);
							} else if (curSubfieldCode == 'b') {
								groupedWork.addGenre(curSubfieldData);
							}
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
				case "690": {
					StringBuilder curSubject = new StringBuilder();
					for (Subfield curSubfield : curSubjectField.getSubfields()) {
						char   curSubfieldCode = curSubfield.getCode();
						String curSubfieldData = curSubfield.getData().trim();
						if (curSubfieldCode == 'a' ||
								(curSubfieldCode >= 'x' && curSubfieldCode <= 'z')) {
							if (curSubject.length() > 0) curSubject.append(" -- ");
							curSubject.append(curSubfieldData);
							groupedWork.addTopic(curSubfieldData);
						}
					}
					subjects.add(curSubject.toString());
					break;
				}
			}
		}
		groupedWork.addSubjects(subjects);

	}

	void updateGroupedWorkSolrDataBasedOnStandardMarcData(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier, String format) {
		loadTitles(groupedWork, record, format, identifier);
		loadAuthors(groupedWork, record, identifier);
		loadSubjects(groupedWork, record);
		/*List<DataField> seriesFields = getDataFields(record, "490");
		HashSet<String> allSeries = new HashSet<>();
		for (DataField seriesField : seriesFields){
			if (seriesField.getIndicator1() == '0' || seriesField.getIndicator1() == '1'){
				if (seriesField.getSubfield('a') != null){
					allSeries.add()
				}

			}
		}*/
		List<DataField> seriesFields = MarcUtil.getDataFields(record, "830");
		HashSet<String> seriesWithVolumes = new HashSet<>();
		for (DataField seriesField : seriesFields){
			String series = Util.trimTrailingPunctuation(MarcUtil.getSpecifiedSubfieldsAsString(seriesField, "ap","")).toString();
			//Remove anything in parens since it's normally just the format
			series = series.replaceAll("\\s+\\(.*?\\)", "");
			//Remove the word series at the end since this gets cataloged inconsistently
			series = series.replaceAll("(?i)\\s+series$", "");
			if (seriesField.getSubfield('v') != null){
				//Separate out the volume so we can link specially
				series += "|" + seriesField.getSubfield('v').getData();
			}
			seriesWithVolumes.add(series);
		}
		seriesFields = MarcUtil.getDataFields(record, "800");
		for (DataField seriesField : seriesFields){
			String series = Util.trimTrailingPunctuation(MarcUtil.getSpecifiedSubfieldsAsString(seriesField, "pqt","")).toString();
			//Remove anything in parens since it's normally just the format
			series = series.replaceAll("\\s+\\(.*?\\)", "");
			//Remove the word series at the end since this gets cataloged inconsistently
			series = series.replaceAll("(?i)\\s+series$", "");

			if (seriesField.getSubfield('v') != null){
				//Separate out the volume so we can link specially
				series += "|" + seriesField.getSubfield('v').getData();
			}
			seriesWithVolumes.add(series);
		}
		groupedWork.addSeriesWithVolume(seriesWithVolumes);

		groupedWork.addSeries(MarcUtil.getFieldList(record, "830ap:800pqt"));
		groupedWork.addSeries2(MarcUtil.getFieldList(record, "490a"));
		groupedWork.addDateSpan(MarcUtil.getFieldList(record, "362a"));
		groupedWork.addContents(MarcUtil.getFieldList(record, "505a:505t"));
		groupedWork.addIssns(MarcUtil.getFieldList(record, "022a"));
		groupedWork.addOclcNumbers(MarcUtil.getFieldList(record, "035a"));
		groupedWork.addIsbns(MarcUtil.getFieldList(record, "020a"), format);
		List<DataField> upcFields = MarcUtil.getDataFields(record, "024");
		for (DataField upcField : upcFields){
			if (upcField.getIndicator1() == '1' && upcField.getSubfield('a') != null){
				groupedWork.addUpc(upcField.getSubfield('a').getData());
			}
		}

		loadAwards(groupedWork, record);
		loadBibCallNumbers(groupedWork, record, identifier);
		loadLiteraryForms(groupedWork, record, printItems, identifier);
		loadTargetAudiences(groupedWork, record, printItems, identifier);
		loadFountasPinnell(groupedWork, record, identifier);
		groupedWork.addMpaaRating(getMpaaRating(record));
		//Do not load ar data from MARC since we now get it directly from Renaissance Learning
		/*groupedWork.setAcceleratedReaderInterestLevel(getAcceleratedReaderInterestLevel(record));
		groupedWork.setAcceleratedReaderReadingLevel(getAcceleratedReaderReadingLevel(record));
		groupedWork.setAcceleratedReaderPointValue(getAcceleratedReaderPointLevel(record));*/
		groupedWork.addKeywords(MarcUtil.getAllSearchableFields(record, 100, 900));
	}

	private void loadFountasPinnell(GroupedWorkSolr groupedWork, Record record, String identifier) {
		Set<String> targetAudiences = MarcUtil.getFieldList(record, "521a");
		for (String targetAudience : targetAudiences){
			if (targetAudience.startsWith("Guided reading level: ")){
				String fountasPinnellValue = targetAudience.replace("Guided reading level: ", "");
				fountasPinnellValue = fountasPinnellValue.replace(".", "").toUpperCase();
				groupedWork.setFountasPinnell(fountasPinnellValue);
				break;
			}
		}
	}

	private void loadAwards(GroupedWorkSolr groupedWork, Record record){
		Set<String> awardFields = MarcUtil.getFieldList(record, "586a");
		HashSet<String> awards = new HashSet<>();
		for (String award : awardFields){
			//Normalize the award name
			if (award.contains("Caldecott")) {
				award = "Caldecott Medal";
			}else if (award.contains("Pulitzer") || award.contains("Puliter")){
				award = "Pulitzer Prize";
			}else if (award.contains("Newbery")){
				award = "Newbery Medal";
			}else {
				if (award.contains(":")) {
					String[] awardParts = award.split(":");
					award = awardParts[0].trim();
				}
				//Remove dates
				award = award.replaceAll("\\d{2,4}", "");
				//Remove punctuation
				award = award.replaceAll("[^\\w\\s]", "");
			}
			awards.add(award.trim());
		}
		groupedWork.addAwards(awards);
	}


	protected abstract void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier);

	void loadEditions(GroupedWorkSolr groupedWork, Record record, HashSet<RecordInfo> ilsRecords) {
		Set<String> editions = MarcUtil.getFieldList(record, "250a");
		if (editions.size() > 0) {
			String edition = editions.iterator().next();
			for (RecordInfo ilsRecord : ilsRecords) {
				ilsRecord.setEdition(edition);
			}
		}
		groupedWork.addEditions(editions);
	}

	void loadPhysicalDescription(GroupedWorkSolr groupedWork, Record record, HashSet<RecordInfo> ilsRecords) {
		Set<String> physicalDescriptions = MarcUtil.getFieldList(record, "300abcefg:530abcd");
		if (physicalDescriptions.size() > 0){
			String physicalDescription = physicalDescriptions.iterator().next();
			for(RecordInfo ilsRecord : ilsRecords){
				ilsRecord.setPhysicalDescription(physicalDescription);
			}
		}
		groupedWork.addPhysical(physicalDescriptions);
	}

	private String getCallNumberSubject(Record record, String fieldSpec) {
		String val = MarcUtil.getFirstFieldVal(record, fieldSpec);

		if (val != null) {
			String[] callNumberSubject = val.toUpperCase().split("[^A-Z]+");
			if (callNumberSubject.length > 0) {
				return callNumberSubject[0];
			}
		}
		return null;
	}

	private String getMpaaRating(Record record) {
		String val = MarcUtil.getFirstFieldVal(record, "521a");

		if (val != null) {
			if (mpaaNotRatedRegex.matcher(val).matches()) {
				return "Not Rated";
			}
			try {
				Matcher mpaaMatcher1 = mpaaRatingRegex1.matcher(val);
				if (mpaaMatcher1.find()) {
					// System.out.println("Matched matcher 1, " + mpaaMatcher1.group(1) +
					// " Rated " + getId());
					return mpaaMatcher1.group(1) + " Rated";
				} else {
					Matcher mpaaMatcher2 = mpaaRatingRegex2.matcher(val);
					if (mpaaMatcher2.find()) {
						// System.out.println("Matched matcher 2, " + mpaaMatcher2.group(1)
						// + " Rated " + getId());
						return mpaaMatcher2.group(1) + " Rated";
					} else {
						Matcher mpaaMatcher3 = mpaaRatingRegex3.matcher(val);
						if (mpaaMatcher3.find()) {
							// System.out.println("Matched matcher 2, " + mpaaMatcher2.group(1)
							// + " Rated " + getId());
							return mpaaMatcher3.group(1) + " Rated";
						} else {

							return null;
						}
					}
				}
			} catch (PatternSyntaxException ex) {
				// Syntax error in the regular expression
				return null;
			}
		} else {
			return null;
		}
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		Set<String> targetAudiences = new LinkedHashSet<>();
		try {
			String leader = record.getLeader().toString();

			ControlField ohOhEightField = (ControlField) record.getVariableField("008");
			ControlField ohOhSixField = (ControlField) record.getVariableField("006");

			// check the Leader at position 6 to determine the type of field
			char recordType = Character.toUpperCase(leader.charAt(6));
			char bibLevel = Character.toUpperCase(leader.charAt(7));
			// Figure out what material type the record is
			if ((recordType == 'A' || recordType == 'T')
					&& (bibLevel == 'A' || bibLevel == 'C' || bibLevel == 'D' || bibLevel == 'M') /* Books */
					|| (recordType == 'M') /* Computer Files */
					|| (recordType == 'C' || recordType == 'D' || recordType == 'I' || recordType == 'J') /* Music */
					|| (recordType == 'G' || recordType == 'K' || recordType == 'O' || recordType == 'R') /* Visual  Materials */
					) {
				char targetAudienceChar;
				if (ohOhSixField != null && ohOhSixField.getData().length() > 5) {
					targetAudienceChar = Character.toUpperCase(ohOhSixField.getData()
							.charAt(5));
					if (targetAudienceChar != ' ') {
						targetAudiences.add(Character.toString(targetAudienceChar));
					}
				}
				if (targetAudiences.size() == 0 && ohOhEightField != null
						&& ohOhEightField.getData().length() > 22) {
					targetAudienceChar = Character.toUpperCase(ohOhEightField.getData()
							.charAt(22));
					if (targetAudienceChar != ' ') {
						targetAudiences.add(Character.toString(targetAudienceChar));
					}
				} else if (targetAudiences.size() == 0) {
					targetAudiences.add("Unknown");
				}
			} else {
				targetAudiences.add("Unknown");
			}
		} catch (Exception e) {
			// leader not long enough to get target audience
			logger.debug("ERROR in getTargetAudience ", e);
			targetAudiences.add("Unknown");
		}

		if (targetAudiences.size() == 0) {
			targetAudiences.add("Unknown");
		}

		groupedWork.addTargetAudiences(indexer.translateSystemCollection("target_audience", targetAudiences, identifier));
		groupedWork.addTargetAudiencesFull(indexer.translateSystemCollection("target_audience_full", targetAudiences, identifier));
	}

	protected void loadLiteraryForms(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//First get the literary Forms from the 008.  These need translation
		LinkedHashSet<String> literaryForms = new LinkedHashSet<>();
		try {
			String leader = record.getLeader().toString();

			ControlField ohOhEightField = (ControlField) record.getVariableField("008");
			ControlField ohOhSixField = (ControlField) record.getVariableField("006");

			// check the Leader at position 6 to determine the type of field
			char recordType = Character.toUpperCase(leader.charAt(6));
			char bibLevel = Character.toUpperCase(leader.charAt(7));
			// Figure out what material type the record is
			if (((recordType == 'A' || recordType == 'T') && (bibLevel == 'A' || bibLevel == 'C' || bibLevel == 'D' || bibLevel == 'M')) /* Books */
					) {
				char literaryFormChar;
				if (ohOhSixField != null && ohOhSixField.getData().length() > 16) {
					literaryFormChar = Character.toUpperCase(ohOhSixField.getData().charAt(16));
					if (literaryFormChar != ' ') {
						literaryForms.add(Character.toString(literaryFormChar));
					}
				}
				if (literaryForms.size() == 0 && ohOhEightField != null && ohOhEightField.getData().length() > 33) {
					literaryFormChar = Character.toUpperCase(ohOhEightField.getData().charAt(33));
					if (literaryFormChar != ' ') {
						literaryForms.add(Character.toString(literaryFormChar));
					}
				}
				if (literaryForms.size() == 0) {
					literaryForms.add(" ");
				}
			} else {
				literaryForms.add("Unknown");
			}
		} catch (Exception e) {
			logger.error("Unexpected error", e);
		}
		if (literaryForms.size() > 1){
			//Uh oh, we have a problem
			logger.warn("Received multiple literary forms for a single marc record");
		}
		groupedWork.addLiteraryForms(indexer.translateSystemCollection("literary_form", literaryForms, identifier));
		groupedWork.addLiteraryFormsFull(indexer.translateSystemCollection("literary_form_full", literaryForms, identifier));

		//Now get literary forms from the subjects, these don't need translation
		HashMap<String, Integer> literaryFormsWithCount = new HashMap<>();
		HashMap<String, Integer> literaryFormsFull = new HashMap<>();
		//Check the subjects
		Set<String> subjectFormData = MarcUtil.getFieldList(record, "650v:651v");
		for(String subjectForm : subjectFormData){
			subjectForm = Util.trimTrailingPunctuation(subjectForm);
			if (subjectForm.equalsIgnoreCase("Fiction")
					|| subjectForm.equalsIgnoreCase("Young adult fiction" )
					|| subjectForm.equalsIgnoreCase("Juvenile fiction" )
					|| subjectForm.equalsIgnoreCase("Junior fiction" )
					|| subjectForm.equalsIgnoreCase("Comic books, strips, etc")
					|| subjectForm.equalsIgnoreCase("Comic books,strips, etc")
					|| subjectForm.equalsIgnoreCase("Children's fiction" )
					|| subjectForm.equalsIgnoreCase("Fictional Works" )
					|| subjectForm.equalsIgnoreCase("Cartoons and comics" )
					|| subjectForm.equalsIgnoreCase("Folklore" )
					|| subjectForm.equalsIgnoreCase("Legends" )
					|| subjectForm.equalsIgnoreCase("Stories" )
					|| subjectForm.equalsIgnoreCase("Fantasy" )
					|| subjectForm.equalsIgnoreCase("Mystery fiction")
					|| subjectForm.equalsIgnoreCase("Romances")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Fiction");
			}else if (subjectForm.equalsIgnoreCase("Biography")){
				addToMapWithCount(literaryFormsWithCount, "Non Fiction");
				addToMapWithCount(literaryFormsFull, "Non Fiction");
			}else if (subjectForm.equalsIgnoreCase("Novela juvenil")
					|| subjectForm.equalsIgnoreCase("Novela")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Fiction");
				addToMapWithCount(literaryFormsFull, "Novels");
			}else if (subjectForm.equalsIgnoreCase("Drama")
					|| subjectForm.equalsIgnoreCase("Dramas")
					|| subjectForm.equalsIgnoreCase("Juvenile drama")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Fiction");
				addToMapWithCount(literaryFormsFull, "Dramas");
			}else if (subjectForm.equalsIgnoreCase("Poetry")
					|| subjectForm.equalsIgnoreCase("Juvenile Poetry")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Poetry");
			}else if (subjectForm.equalsIgnoreCase("Humor")
					|| subjectForm.equalsIgnoreCase("Juvenile Humor")
					|| subjectForm.equalsIgnoreCase("Comedy")
					|| subjectForm.equalsIgnoreCase("Wit and humor")
					|| subjectForm.equalsIgnoreCase("Satire")
					|| subjectForm.equalsIgnoreCase("Humor, Juvenile")
					|| subjectForm.equalsIgnoreCase("Humour")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Fiction");
				addToMapWithCount(literaryFormsFull, "Humor, Satires, etc.");
			}else if (subjectForm.equalsIgnoreCase("Correspondence")
					){
				addToMapWithCount(literaryFormsWithCount, "Non Fiction");
				addToMapWithCount(literaryFormsFull, "Letters");
			}else if (subjectForm.equalsIgnoreCase("Short stories")
					){
				addToMapWithCount(literaryFormsWithCount, "Fiction");
				addToMapWithCount(literaryFormsFull, "Fiction");
				addToMapWithCount(literaryFormsFull, "Short stories");
			}else if (subjectForm.equalsIgnoreCase("essays")
					){
				addToMapWithCount(literaryFormsWithCount, "Non Fiction");
				addToMapWithCount(literaryFormsFull, "Essays");
			}else if (subjectForm.equalsIgnoreCase("Personal narratives, American")
					|| subjectForm.equalsIgnoreCase("Personal narratives, Polish")
					|| subjectForm.equalsIgnoreCase("Personal narratives, Sudanese")
					|| subjectForm.equalsIgnoreCase("Personal narratives, Jewish")
					|| subjectForm.equalsIgnoreCase("Personal narratives")
					|| subjectForm.equalsIgnoreCase("Guidebooks")
					|| subjectForm.equalsIgnoreCase("Guide-books")
					|| subjectForm.equalsIgnoreCase("Handbooks, manuals, etc")
					|| subjectForm.equalsIgnoreCase("Problems, exercises, etc")
					|| subjectForm.equalsIgnoreCase("Case studies")
					|| subjectForm.equalsIgnoreCase("Handbooks")
					|| subjectForm.equalsIgnoreCase("Biographies")
					|| subjectForm.equalsIgnoreCase("Interviews")
					|| subjectForm.equalsIgnoreCase("Autobiography")
					|| subjectForm.equalsIgnoreCase("Cookbooks")
					|| subjectForm.equalsIgnoreCase("Dictionaries")
					|| subjectForm.equalsIgnoreCase("Encyclopedias")
					|| subjectForm.equalsIgnoreCase("Encyclopedias, Juvenile")
					|| subjectForm.equalsIgnoreCase("Dictionaries, Juvenile")
					|| subjectForm.equalsIgnoreCase("Nonfiction")
					|| subjectForm.equalsIgnoreCase("Non-fiction")
					|| subjectForm.equalsIgnoreCase("Juvenile non-fiction")
					|| subjectForm.equalsIgnoreCase("Maps")
					|| subjectForm.equalsIgnoreCase("Catalogs")
					|| subjectForm.equalsIgnoreCase("Recipes")
					|| subjectForm.equalsIgnoreCase("Diaries")
					|| subjectForm.equalsIgnoreCase("Designs and Plans")
					|| subjectForm.equalsIgnoreCase("Reference books")
					|| subjectForm.equalsIgnoreCase("Travel guide")
					|| subjectForm.equalsIgnoreCase("Textbook")
					|| subjectForm.equalsIgnoreCase("Atlas")
					|| subjectForm.equalsIgnoreCase("Atlases")
					|| subjectForm.equalsIgnoreCase("Study guides")
					) {
				addToMapWithCount(literaryFormsWithCount, "Non Fiction");
				addToMapWithCount(literaryFormsFull, "Non Fiction");
			}else{
				if (!unknownSubjectForms.contains(subjectForm)){
					//logger.warn("Unknown subject form " + subjectForm);
					unknownSubjectForms.add(subjectForm);
				}
			}
		}

		//Check the subjects
		Set<String> subjectGenreData = MarcUtil.getFieldList(record, "655a");
		for(String subjectForm : subjectGenreData) {
			subjectForm = Util.trimTrailingPunctuation(subjectForm).toLowerCase();
			if (subjectForm.startsWith("instructional film")
					|| subjectForm.startsWith("educational film")
					) {
				addToMapWithCount(literaryFormsWithCount, "Non Fiction");
				addToMapWithCount(literaryFormsFull, "Non Fiction");
			}
		}
		groupedWork.addLiteraryForms(literaryFormsWithCount);
		groupedWork.addLiteraryFormsFull(literaryFormsFull);
	}

	private void addToMapWithCount(HashMap<String, Integer> map, String elementToAdd){
		if (map.containsKey(elementToAdd)){
			map.put(elementToAdd, map.get(elementToAdd) + 1);
		}else{
			map.put(elementToAdd, 1);
		}
	}

	void loadPublicationDetails(GroupedWorkSolr groupedWork, Record record, HashSet<RecordInfo> ilsRecords) {
		//Load publishers
		Set<String> publishers = this.getPublishers(record);
		groupedWork.addPublishers(publishers);
		if (publishers.size() > 0){
			String publisher = publishers.iterator().next();
			for(RecordInfo ilsRecord : ilsRecords){
				ilsRecord.setPublisher(publisher);
			}
		}

		//Load publication dates
		Set<String> publicationDates = this.getPublicationDates(record);
		groupedWork.addPublicationDates(publicationDates);
		if (publicationDates.size() > 0){
			String publicationDate = publicationDates.iterator().next();
			for(RecordInfo ilsRecord : ilsRecords){
				ilsRecord.setPublicationDate(publicationDate);
			}
		}

	}

	Set<String> getPublicationDates(Record record) {
		@SuppressWarnings("unchecked")
		List<DataField> rdaFields = record.getDataFields("264");
		HashSet<String> publicationDates = new HashSet<>();
		String date;
		//Try to get from RDA data
		if (rdaFields.size() > 0){
			for (DataField dataField : rdaFields){
				if (dataField.getIndicator2() == '1'){
					Subfield subFieldC = dataField.getSubfield('c');
					if (subFieldC != null){
						date = subFieldC.getData();
						publicationDates.add(date);
					}
				}
			}
		}
		//Try to get from 260
		if (publicationDates.size() ==0) {
			publicationDates.addAll(MarcUtil.getFieldList(record, "260c"));
		}
		//Try to get from 008, but only need to do if we don't have anything else
		if (publicationDates.size() == 0) {
			publicationDates.add(MarcUtil.getFirstFieldVal(record, "008[7-10]"));
		}

		return publicationDates;
	}

	Set<String> getPublishers(Record record){
		Set<String> publisher = new LinkedHashSet<>();
		//First check for 264 fields
		@SuppressWarnings("unchecked")

		List<DataField> rdaFields = MarcUtil.getDataFields(record, "264");
		if (rdaFields.size() > 0){
			for (DataField curField : rdaFields){
				if (curField.getIndicator2() == '1'){
					Subfield subFieldB = curField.getSubfield('b');
					if (subFieldB != null){
						publisher.add(subFieldB.getData());
					}
				}
			}
		}
		publisher.addAll(MarcUtil.getFieldList(record, "260b"));
		return publisher;
	}

	void loadLanguageDetails(GroupedWorkSolr groupedWork, Record record, HashSet<RecordInfo> ilsRecords, String identifier) {
		// Note: ilsRecords are alternate manifestations for the same record, like for an order record or ILS econtent items

		String languageFields = "008[35-37]";
		Set<String>     languages           = MarcUtil.getFieldList(record, languageFields);
		HashSet<String> translatedLanguages = new HashSet<>();
		boolean         isFirstLanguage     = true;
		String primaryLanguage = null;
		Long            languageBoost        = 1L;
		Long            languageBoostSpanish = 1L;
		for (String language : languages) {
			String translatedLanguage = indexer.translateSystemValue("language", language, identifier);
			translatedLanguages.add(translatedLanguage);
			if (isFirstLanguage) {
				primaryLanguage = translatedLanguage;
			}
			isFirstLanguage = false;

			String languageBoostStr = indexer.translateSystemValue("language_boost", language, identifier);
			if (languageBoostStr != null) {
				Long languageBoostVal = Long.parseLong(languageBoostStr);
				if (languageBoostVal > languageBoost){
					languageBoost = languageBoostVal;
				}
			}
			String languageBoostEs = indexer.translateSystemValue("language_boost_es", language, identifier);
			if (languageBoostEs != null) {
				Long languageBoostVal = Long.parseLong(languageBoostEs);
				if (languageBoostVal > languageBoostSpanish){
					languageBoostSpanish = languageBoostVal;
				}
			}
		}

		String      translationFields = "041b:041d:041h:041j";
		Set<String> translations      = MarcUtil.getFieldList(record, translationFields);
		HashSet<String> translatedTranslations = new HashSet<>();
		for (String translation : translations) {
			String translatedLanguage = indexer.translateSystemValue("language", translation, identifier);
			translatedTranslations.add(translatedLanguage);
		}

		//Check to see if we have Unknown plus a valid value
		if (translatedLanguages.size() > 1 && translatedLanguages.contains("Unknown")){
			languages.remove("Unknown");
		}

		for (RecordInfo ilsRecord : ilsRecords) {
			if (primaryLanguage != null) {
				ilsRecord.setPrimaryLanguage(primaryLanguage);
			}
			ilsRecord.setLanguages(translatedLanguages);
			ilsRecord.setLanguageBoost(languageBoost);
			ilsRecord.setLanguageBoostSpanish(languageBoostSpanish);
			ilsRecord.setTranslations(translatedTranslations);
		}
	}

	private void loadAuthors(GroupedWorkSolr groupedWork, Record record, String identifier) {
		//auth_author = 100abcd, first
		groupedWork.setAuthAuthor(MarcUtil.getFirstFieldVal(record, "100abcd"));
		//author = a, first
		//MDN 2/6/2016 - Do not use 710 because it is not truly the author.  This has the potential
		//of showing some disconnects with how records are grouped, but improves the display of the author
		//710 is still indexed as part of author 2 #ARL-146
		//groupedWork.setAuthor(this.getFirstFieldVal(record, "100abcdq:110ab:710a"));
		groupedWork.setAuthor(MarcUtil.getFirstFieldVal(record, "100abcdq:110ab"));
		//author-letter = 100a, first
		groupedWork.setAuthorLetter(MarcUtil.getFirstFieldVal(record, "100a"));
		//auth_author2 = 700abcd
		groupedWork.addAuthAuthor2(MarcUtil.getFieldList(record, "700abcd"));
		//author2 = 110ab:111ab:700abcd:710ab:711ab:800a
		groupedWork.addAuthor2(MarcUtil.getFieldList(record, "110ab:111ab:700abcd:710ab:711ab:800a"));
		//author_additional = 505r:245c
		groupedWork.addAuthorAdditional(MarcUtil.getFieldList(record, "505r:245c"));
		//Load contributors with role
		List<DataField> contributorFields = MarcUtil.getDataFields(record, new String[]{"700","710"});
		HashSet<String> contributors = new HashSet<>();
		for (DataField contributorField : contributorFields){
			StringBuilder contributor = MarcUtil.getSpecifiedSubfieldsAsString(contributorField, "abcdetmnr", "");
			if (contributorField.getTag().equals("700") && contributorField.getSubfield('4') != null){
				String role = indexer.translateSystemValue("contributor_role", Util.trimTrailingPunctuation(contributorField.getSubfield('4').getData()), identifier);
				contributor.append("|").append(role);
			}
			contributors.add(contributor.toString());
		}
		groupedWork.addAuthor2Role(contributors);

		//author_display = 100a:110a:260b:710a:245c, first
		//#ARL-95 Do not show display author from the 710 or from the 245c since neither are truly authors
		//#ARL-200 Do not show display author from the 260b since it is also not the author
		String displayAuthor = MarcUtil.getFirstFieldVal(record, "100a:110ab");
		if (displayAuthor != null && displayAuthor.indexOf(';') > 0){
			displayAuthor = displayAuthor.substring(0, displayAuthor.indexOf(';') -1);
		}
		groupedWork.setAuthorDisplay(displayAuthor);
	}

	protected void loadTitles(GroupedWorkSolr groupedWork, Record record, String format, String identifier) {
		//title (full title done by index process by concatenating short and subtitle

		//title short
		String titleValue    = MarcUtil.getFirstFieldVal(record, "245a");
		String subTitleValue = MarcUtil.getFirstFieldVal(record, "245bnp"); //MDN 2/6/2016 add np to subtitle #ARL-163
		if (titleValue == null){
			if (fullReindex) {
				logger.warn(identifier + " has no title value (245a)");
			}
			titleValue = "";
		} else {
			if (subTitleValue != null) {
				String subTitleLowerCase = subTitleValue.toLowerCase();
				String titleLowerCase    = titleValue.toLowerCase();
				if (titleLowerCase.equals(subTitleLowerCase)){
					if (fullReindex) {
						logger.warn(identifier + " title (245a) '" + titleValue + "' is the same as the subtitle : " + subTitleValue);
					}
					subTitleValue = null; // null out so that it doesn't get added to sort or display titles
				} else {
					groupedWork.setSubTitle(subTitleValue);
					if (titleLowerCase.endsWith(subTitleLowerCase)) {
						// Remove subtitle from title in order to avoid repeats of sub-title in display & title fields in index
						if (fullReindex) {
							logger.warn(identifier + " title (245a) '" + titleValue + "' ends with the subtitle (245bnp) : " + subTitleValue );
						}
						titleValue = titleValue.substring(0, titleLowerCase.lastIndexOf(subTitleLowerCase));
					}
				}
				// Trim ending colon character and whitespace often appended for expected subtitle display, we'll add it back if we have a subtitle
				titleValue = titleValue.replaceAll("[\\s:]+$", ""); // remove ending white space; then remove any ending colon characters.
			}
		}

		String displayTitle = (subTitleValue == null || subTitleValue.isEmpty()) ? titleValue : titleValue + " : " + subTitleValue;

		String sortableTitle = titleValue;
		// Skip non-filing chars, if possible.
		if (!titleValue.isEmpty()) {
			DataField titleField = record.getDataField("245");
			if (titleField != null && titleField.getSubfield('a') != null) {
				int nonFilingInt = getInd2AsInt(titleField);
				if (nonFilingInt > 0 && titleValue.length() > nonFilingInt)  {
					sortableTitle = titleValue.substring(nonFilingInt);
				}
			}
			if (subTitleValue != null && !subTitleValue.isEmpty()) {
				sortableTitle += " " + subTitleValue;
			}
		}

		groupedWork.setTitle(titleValue, displayTitle, sortableTitle, format);
		//title full
		String authorInTitleField = MarcUtil.getFirstFieldVal(record, "245c");
		String standardAuthorData = MarcUtil.getFirstFieldVal(record, "100abcdq:110ab");
		if ((authorInTitleField != null && authorInTitleField.length() > 0) || (standardAuthorData == null || standardAuthorData.length() == 0)) {
			groupedWork.addFullTitles(MarcUtil.getAllSubfields(record, "245", " "));
		} else {
			//We didn't get an author from the 245, combine with the 100
			Set<String> titles = MarcUtil.getAllSubfields(record, "245", " ");
			for (String title : titles) {
				groupedWork.addFullTitle(title + " " + standardAuthorData);
			}
		}

		//title alt
		groupedWork.addAlternateTitles(MarcUtil.getFieldList(record, "130adfgklnpst:240a:246abnp:700tnr:730adfgklnpst:740a"));
		//title old
		groupedWork.addOldTitles(MarcUtil.getFieldList(record, "780ast"));
		//title new
		groupedWork.addNewTitles(MarcUtil.getFieldList(record, "785ast"));
	}

	private void loadBibCallNumbers(GroupedWorkSolr groupedWork, Record record, String identifier) {
		groupedWork.setCallNumberA(MarcUtil.getFirstFieldVal(record, "099a:090a:050a"));
		String firstCallNumber = MarcUtil.getFirstFieldVal(record, "099a[0]:090a[0]:050a[0]");
		if (firstCallNumber != null){
			groupedWork.setCallNumberFirst(indexer.translateSystemValue("callnumber", firstCallNumber, identifier));
		}
		String callNumberSubject = getCallNumberSubject(record, "090a:050a");
		if (callNumberSubject != null){
			groupedWork.setCallNumberSubject(indexer.translateSystemValue("callnumber_subject", callNumberSubject, identifier));
		}
	}

	void loadEContentUrl(Record record, ItemInfo itemInfo, String identifier) {
		List<DataField> urlFields = MarcUtil.getDataFields(record, "856");
		for (DataField urlField : urlFields) {
			//load url into the item
			if (urlField.getSubfield('u') != null) {
				//Try to determine if this is a resource or not.
				if (urlField.getIndicator1() == '4' || urlField.getIndicator1() == ' ' || urlField.getIndicator1() == '0' || urlField.getIndicator1() == '7') {
					if (urlField.getIndicator2() != '2') {
						// Avoid Cover Image Links
						// (some image links do not have a 2nd indicator of 2)
						// (a subfield 3 or z will often contain the text 'Image' or 'Cover Image' if the link is for an image)
						String subFieldZ = "";
						String subField3 = "";
						if (urlField.getSubfield('z') != null) {
							subFieldZ = urlField.getSubfield('z').getData().toLowerCase();
						}
						if (urlField.getSubfield('3') != null) {
							subField3 = urlField.getSubfield('3').getData().toLowerCase();
						}
						if (!subFieldZ.contains("image") && !subField3.contains("image")) {
							itemInfo.seteContentUrl(urlField.getSubfield('u').getData().trim());
							return;
						}
					}
				}
			}
		}
//		if (itemInfo.geteContentUrl() == null) {
//			logger.warn("Item for " + identifier + "  had no eContent URL set");
//		}
		//will turn back on after initial problem records have been cleaned up. pascal 8/30/2019
	}

	/**
	 * Get the title (245abnp) from a record, without non-filing chars as specified
	 * in 245 2nd indicator, and lower cased.
	 *
	 * @return 245a and 245b and 245n and 245p values concatenated, with trailing punctuation removed, and
	 *         with non-filing characters omitted. Null returned if no title can
	 *         be found.
	 */
	protected String getSortableTitle(Record record) {
		DataField titleField = record.getDataField("245");
		if (titleField == null || titleField.getSubfield('a') == null)
			return "";

		// Skip non-filing chars, if possible.
		int nonFilingInt = getInd2AsInt(titleField);
		String title    = MarcUtil.getFirstFieldVal(record, "245a");
		if (title == null){
			return null;
		} else if (title.length() > nonFilingInt) {
			title = title.substring(nonFilingInt);
		}

		if (title.length() == 0) {
			return null;
		}

		String subTitle = MarcUtil.getFirstFieldVal(record, "245bnp");
		if (subTitle != null && title.toLowerCase().endsWith(subTitle.toLowerCase())) {
			title = title.substring(0, title.lastIndexOf(subTitle));
			title = title.trim().replaceAll(":+$", ""); // remove ending white space; then remove any ending colon characters.
		}
		title += " "+ subTitle;
		title = title.toLowerCase();
		String title_alt  = MarcUtil.getFirstFieldVal(record, "245abnp"); //old style TOOD: temp
		if (title_alt.length() > nonFilingInt) {
			title_alt = title_alt.substring(nonFilingInt);
		}

		return title;
	}

	/**
	 * @param df
	 *          a DataField
	 * @return the integer (0-9, 0 if blank or other) in the 2nd indicator
	 */
	private int getInd2AsInt(DataField df) {
		char ind2char = df.getIndicator2();
		int result = 0;
		if (Character.isDigit(ind2char))
			result = Integer.valueOf(String.valueOf(ind2char));
		return result;
	}
}
