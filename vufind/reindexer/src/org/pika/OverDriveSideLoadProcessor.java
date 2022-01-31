/*
 * Copyright (C) 2022  Marmot Library Network
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

import org.apache.logging.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.Set;

/**
 * Pika
 *
 * @author pbrammeier
 * 				Date:   1/05/2022
 */
public class OverDriveSideLoadProcessor extends SideLoadedEContentProcessor {
	OverDriveSideLoadProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	protected void loadTitles(GroupedWorkSolr groupedWork, Record record, String format, String identifier) {
		// This override should be the same as the super method, except for the suppression of subtitles that are
		// the overdrive series statements.

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
					if (fullReindex && logger.isInfoEnabled()) {
						logger.info(identifier + " title (245a) '" + titleValue + "' is the same as the subtitle : " + subTitleValue);
					}
					subTitleValue = null; // null out so that it doesn't get added to sort or display titles
				} else if (subTitleLowerCase.contains("series, book")){
					// If the overdrive subtitle is just a series statement, do not add to the index as the subtitle
					// In these cases, the subtitle takes the form "{series title} Series, Book {Book Number}
					subTitleValue = "";
				} else {
					groupedWork.setSubTitle(subTitleValue); //TODO: return the cleaned up value for the subtitle?
					if (titleLowerCase.endsWith(subTitleLowerCase)) {
						// Remove subtitle from title in order to avoid repeats of sub-title in display & title fields in index
						if (fullReindex && logger.isInfoEnabled()) {
							logger.info(identifier + " title (245a) '" + titleValue + "' ends with the subtitle (245bnp) : " + subTitleValue );
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
			//TODO: suppress overdrive subtitles that are series statements from full titles?
			groupedWork.addFullTitles(MarcUtil.getAllSubfields(record, "245", " "));
		} else {
			//We didn't get an author from the 245, combine with the 100
			Set<String> titles = MarcUtil.getAllSubfields(record, "245", " ");
			for (String title : titles) {
				groupedWork.addFullTitle(title + " " + standardAuthorData);
			}
		}

		//title alt
		groupedWork.addAlternateTitles(MarcUtil.getFieldList(record, "130adfgklnpst:240a:246abnp:700tnr:730adfgklnpst:740a:247ab"));
		//title old
//		groupedWork.addOldTitles(MarcUtil.getFieldList(record, "780ast"));
		//title new
//		groupedWork.addNewTitles(MarcUtil.getFieldList(record, "785ast"));
	}
}
