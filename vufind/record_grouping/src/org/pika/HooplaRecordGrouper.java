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
		List<DataField> fields099 = getDataFields(marcRecord, "099");
		DataField       cur099    = fields099.iterator().next();
		String          groupingCategory;
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
				logger.error("Unknown Hoopla format for " + identifier + " : " + format);
			case "eaudiobook hoopla":
			case "ebook hoopla":
				groupingCategory = "book";
		}

		workForTitle.setGroupingCategory(groupingCategory, identifier);
		return groupingCategory;
	}

}
