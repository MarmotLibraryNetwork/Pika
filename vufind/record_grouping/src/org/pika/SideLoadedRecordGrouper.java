/*
 * Copyright (C) 2020  Marmot Library Network
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

import org.apache.log4j.Logger;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.util.HashSet;

/**
 * Groups records that are not loaded into the ILS.  These are additional records that are processed directly in Pika
 *
 * Pika
 * User: Mark Noble
 * Date: 12/15/2015
 * Time: 5:29 PM
 */
class SideLoadedRecordGrouper extends MarcRecordGrouper {

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn       - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 */
	SideLoadedRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger) {
		super(pikaConn, profile, logger);
	}

	@Override
	protected String setGroupingCategoryForWork(RecordIdentifier identifier, Record marcRecord, IndexingProfile profile, GroupedWorkBase workForTitle) {
		String groupingCategory;
		HashSet<String> groupingCategories = new GroupingFormatDetermination(profile, translationMaps, logger).loadEContentFormatInformation(identifier, marcRecord);
		if (groupingCategories.size() > 1){
			groupingCategory = "book"; // fall back option for now
			logger.warn("More than one grouping category for " + identifier + " : " + String.join(",", groupingCategories));
		} else if (groupingCategories.size() == 0){
			logger.warn("No grouping category for " + identifier);
			groupingCategory = "book"; // fall back option for now
		} else {
			groupingCategory = groupingCategories.iterator().next(); //First Format
		}

		workForTitle.setGroupingCategory(groupingCategory, identifier);
		return groupingCategory;
	}

}
