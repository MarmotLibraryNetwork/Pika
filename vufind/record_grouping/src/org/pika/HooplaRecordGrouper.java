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
	 *
	 * @param dbConnection   - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 * @param fullRegrouping - Whether or not we are doing full regrouping or if we are only grouping changes.
	 */
	HooplaRecordGrouper(Connection dbConnection, IndexingProfile profile, Logger logger, boolean fullRegrouping) {
		super(dbConnection, profile, logger, fullRegrouping);
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
