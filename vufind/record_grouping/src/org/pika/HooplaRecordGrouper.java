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

import org.apache.logging.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.util.*;

/**
 * Groups records from Hoopla.  Contains special processing to load formats.
 *
 * Pika
 * User: Mark Noble
 * Date: 7/6/2016
 * Time: 10:26 AM
 */
class HooplaRecordGrouper extends MarcRecordGrouper {
	/**
	 * Creates a record grouping processor that saves results to the database.
	 *  @param pikaConn       - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
 * @param logger         - A logger to store debug and error messages to.
	 */
	HooplaRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger) {
		this(pikaConn, profile, logger, false);
	}

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn       - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 */
	HooplaRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger, boolean fullRegrouping) {
		super(pikaConn, profile, logger, fullRegrouping);
	}

	/**
	 *  Determine the grouping category for a Hoopla record
	 * @param identifier
	 * @param marcRecord
	 * @param profile
	 * @param workForTitle
	 * @return the grouping category
	 */
	@Override
	protected String setGroupingCategoryForWork(RecordIdentifier identifier, Record marcRecord, IndexingProfile profile, GroupedWorkBase workForTitle) {
		List<DataField> fields099        = getDataFields(marcRecord, "099");
		String          groupingCategory = "book";
		if (fields099 != null && !fields099.isEmpty()) {
			DataField       cur099    = fields099.iterator().next();
			String          format    = cur099.getSubfield('a').getData().toLowerCase();
			switch (format) {
				case "evideo hoopla":
					groupingCategory = "movie";
					break;
				case "emusic hoopla":
					groupingCategory = "music";
					break;
				case "ecomic hoopla":
					groupingCategory = "comic";
					break;
				default:
					logger.error("Unknown Hoopla format for {} : {}", identifier, format);
				case "eaudiobook hoopla":
				case "ebook hoopla":
					groupingCategory = "book";

					// Determine if this should be the young reader grouping category instead
						// Hoopla doesn't appear to have much edition information in the MARC, except "Unabridged." when applicable
	//				List<DataField> editions = marcRecord.getDataFields("250");
	//				for (DataField edition : editions) {
	//					if (edition != null) {
	//						if (edition.getSubfield('a') != null) {
	//							String editionData = edition.getSubfield('a').getData().toLowerCase();
	//							if (editionData.contains("young reader")) {
	//								groupingCategory = "young";
	//								break;
	//							}
	//						}
	//					}
	//				}
					String subTitle = MarcUtil.getFirstFieldVal(marcRecord, "245b");
					// Check subTitle first as that is where we will more likely find a match
					if (subTitle != null && subTitle.replace("'", "").toLowerCase().contains("young readers edition")) {
						groupingCategory = "young";
						break;
					}
					String title = MarcUtil.getFirstFieldVal(marcRecord, "245a");
					if (title != null && title.replace("'", "").toLowerCase().contains("young readers edition")) {
						groupingCategory = "young";
						break;
					}
			}
		} else {
			boolean isBingePass = false;
			List<DataField> seriesStatements = marcRecord.getDataFields("490");
			if (seriesStatements != null && !seriesStatements.isEmpty()) {
				for (DataField cur490 : seriesStatements){
					String field = cur490.getSubfieldsAsString("a");
					if (field.equalsIgnoreCase("bingepass") || field.equalsIgnoreCase("seasonpass")){
						// Some records only have SeasonPass in the 490, but the extract Kind for these is also BINGEPASS
						isBingePass = true;
						// Use default groupingCategory of book for now
						break;
					}
				}
			}
			if (!isBingePass) {
				logger.warn("Hoopla record {} has no 099 or 490a (BingePass) for grouping category", identifier);
			}
		}

		workForTitle.setGroupingCategory(groupingCategory, identifier);
		return groupingCategory;
	}

}
