package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.util.LinkedHashSet;

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
	 * @param dbConnection   - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 * @param fullRegrouping - Whether or not we are doing full regrouping or if we are only grouping changes.
	 */
	SideLoadedRecordGrouper(Connection dbConnection, IndexingProfile profile, Logger logger, boolean fullRegrouping) {
		super(dbConnection, profile, logger, fullRegrouping);
	}

	protected String setGroupingCategoryForWork(RecordIdentifier identifier, Record marcRecord, IndexingProfile profile, GroupedWorkBase workForTitle) {
		String groupingCategory;
		FormatDetermination formatDetermination = new FormatDetermination(profile, translationMaps, logger);
		formatDetermination.loadEContentFormatInformation(identifier, marcRecord);
		LinkedHashSet<String> groupingFormats = translationMaps.get("formatsToGroupingCategory").translateCollection(formatDetermination.rawFormats, identifier.toString());
		groupingFormats = translationMaps.get("category").translateCollection(groupingFormats, identifier.toString());
		if (groupingFormats.size() > 1){
			//TODO: check if translating collection values reduced the category down to one
			groupingCategory = "book"; // fall back option for now
			logger.warn("More than one grouping category for " + identifier + " : " + String.join(",", groupingFormats));
		} else if (groupingFormats.size() == 0){
			logger.warn("No grouping category for " + identifier);
			groupingCategory = "book"; // fall back option for now
		} else {
			groupingCategory = groupingFormats.iterator().next(); //First Format
		}

		workForTitle.setGroupingCategory(groupingCategory);
		return groupingCategory;
	}


}
