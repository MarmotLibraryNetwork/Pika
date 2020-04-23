package org.pika;

import java.io.File;
import java.io.FilenameFilter;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.GregorianCalendar;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;

public class DatabaseCleanup implements IProcessHandler {

	@Override
	public void doCronProcess(String servername, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Database Cleanup");
		processLog.saveToDatabase(pikaConn, logger);

		removeExpiredSessions(pikaConn, logger, processLog);
		removeOldSearches(pikaConn, logger, processLog);
		removeSpammySearches(pikaConn, logger, processLog);
		removeLongSearches(pikaConn, logger, processLog);
		cleanupReadingHistory(pikaConn, logger, processLog);
		cleanupIndexingReports(pikaConn, logger, processLog);
		removeOldMaterialsRequests(pikaConn, logger, processLog);
		removeUserDataForDeletedUsers(pikaConn, logger, processLog);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void removeUserDataForDeletedUsers(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		try {
			int numUpdates = pikaConn.prepareStatement("DELETE FROM user_link WHERE primaryAccountId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user links where the primary account does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_link WHERE linkedAccountId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user links where the linked account does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_link_blocks WHERE primaryAccountId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user link blocks where the primary account does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_link_blocks WHERE blockedLinkAccountId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user link blocks where the blocked account does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_list WHERE public = 0 and user_id NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_list where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_not_interested WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_not_interested where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_reading_history_work WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_reading_history_work where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_roles WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_roles where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM search WHERE user_id NOT IN (SELECT id FROM user) and user_id != 0").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " search where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_staff_settings WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_staff_settings where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_tags WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_tags where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("DELETE FROM user_work_review WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " user_work_review where the user does not exist");
			}

			numUpdates = pikaConn.prepareStatement("UPDATE browse_category SET userID = null WHERE userId NOT IN (SELECT id FROM user)").executeUpdate();
			if (numUpdates > 0){
				processLog.incUpdated();
				processLog.addNote("Deleted " + numUpdates + " browse categories where the user does not exist");
			}
		}catch (Exception e){
			processLog.incErrors();
			processLog.addNote("Unable to cleanup user data for deleted users. " + e.toString());
			logger.error("Error cleaning up user data for deleted users", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void removeOldMaterialsRequests(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		try{
			//Get a list of a libraries
			PreparedStatement librariesListStmt = pikaConn.prepareStatement("SELECT libraryId, materialsRequestDaysToPreserve FROM library WHERE materialsRequestDaysToPreserve > 0");
			PreparedStatement libraryLocationsStmt = pikaConn.prepareStatement("SELECT locationId FROM location WHERE libraryId = ?");
			PreparedStatement requestToDeleteStmt = pikaConn.prepareStatement("DELETE FROM materials_request WHERE id = ?");

			ResultSet librariesListRS = librariesListStmt.executeQuery();

			long numDeletions = 0;
			//Loop through libraries
			while (librariesListRS.next()){
				//Get the number of days to preserve from the variables table
				Long libraryId = librariesListRS.getLong("libraryId");
				Long daysToPreserve = librariesListRS.getLong("materialsRequestDaysToPreserve");

				if (daysToPreserve < 366){
					daysToPreserve = 366L;
				}

				//Get a list of locations for the library
				libraryLocationsStmt.setLong(1, libraryId);

				ResultSet libraryLocationsRS = libraryLocationsStmt.executeQuery();
				String libraryLocations = "";
				while (libraryLocationsRS.next()){
					if (libraryLocations.length() > 0){
						libraryLocations += ", ";
					}
					libraryLocations += libraryLocationsRS.getString("locationId");
				}

				if (libraryLocations.length() > 0) {
					//Delete records for that library
					PreparedStatement requestsToDeleteStmt = pikaConn.prepareStatement("SELECT materials_request.id FROM materials_request INNER JOIN materials_request_status ON materials_request.status = materials_request_status.id INNER JOIN user ON createdBy = user.id WHERE isOpen = 0 AND user.homeLocationId IN (" + libraryLocations + ") AND dateCreated < ?");

					Long now = new Date().getTime() / 1000;
					Long earliestDateToPreserve = now - (daysToPreserve * 24 * 60 * 60);
					requestsToDeleteStmt.setLong(1, earliestDateToPreserve);

					ResultSet requestsToDeleteRS = requestsToDeleteStmt.executeQuery();
					while (requestsToDeleteRS.next()) {
						requestToDeleteStmt.setLong(1, requestsToDeleteRS.getLong(1));
						int numUpdates = requestToDeleteStmt.executeUpdate();
						processLog.addUpdates(numUpdates);
						numDeletions += numUpdates;
					}
					requestsToDeleteStmt.close();
				}
			}
			librariesListRS.close();
			librariesListStmt.close();
			processLog.addNote("Removed " + numDeletions + " old materials requests.");
		}catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to remove old materials requests. " + e.toString());
			logger.error("Error deleting long searches", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void cleanupIndexingReports(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove indexing reports
		try{
			//Get the data directory where reports are stored
			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//Get a list of dates that should be kept
			SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd");
			GregorianCalendar today = new GregorianCalendar();
			ArrayList<String> validDatesToKeep = new ArrayList<>();
			//Keep the last 7 days
			for (int i = 0; i < 7; i++) {
				validDatesToKeep.add(dateFormatter.format(today.getTime()));
				today.add(Calendar.DATE, -1);
			}
			//Keep the last 12 months
			today.setTime(new Date());
			today.set(Calendar.DAY_OF_MONTH, 1);
			for (int i = 0; i < 12; i++) {
				validDatesToKeep.add(dateFormatter.format(today.getTime()));
				today.add(Calendar.MONTH, -1);
			}

			//List all csv files in the directory
			File[] filesToCheck = dataDir.listFiles((dir, name) -> name.matches(".*\\d{4}-\\d{2}-\\d{2}\\.csv"));

			//Check to see if we should keep or delete the file
			Pattern getDatePattern = Pattern.compile("(\\d{4}-\\d{2}-\\d{2})", Pattern.CANON_EQ);
			for (File curFile : filesToCheck){
				//Get the date from the file
				Matcher fileMatcher = getDatePattern.matcher(curFile.getName());
				if (fileMatcher.find()) {
					String date = fileMatcher.group();
					if (!validDatesToKeep.contains(date)){
						curFile.delete();
					}
				}
			}

		} catch (Exception e){
			processLog.incErrors();
			processLog.addNote("Error removing old indexing reports. " + e.toString());
			logger.error("Error removing old indexing reports", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void removeLongSearches(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove long searches
		try {
			PreparedStatement removeSearchStmt = pikaConn.prepareStatement("DELETE FROM search_stats_new WHERE length(phrase) > 256");

			int rowsRemoved = removeSearchStmt.executeUpdate();

			processLog.addNote("Removed " + rowsRemoved + " long searches");
			processLog.incUpdated();

			processLog.saveToDatabase(pikaConn, logger);
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to delete long searches. " + e.toString());
			logger.error("Error deleting long searches", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void removeSpammySearches(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove spammy searches
		try (
			PreparedStatement removeSearchStmt = pikaConn.prepareStatement("DELETE FROM search_stats_new WHERE phrase LIKE '%http:%' OR phrase LIKE '%https:%' OR phrase LIKE '%mailto:%'");
		){
			int rowsRemoved = removeSearchStmt.executeUpdate();

			processLog.addNote("Removed " + rowsRemoved + " spammy searches");
			processLog.incUpdated();

			processLog.saveToDatabase(pikaConn, logger);

		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to delete spammy searches. " + e.toString());
			logger.error("Error deleting spammy searches", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void removeOldSearches(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove old searches
		try {
			int rowsRemoved = 0;
			ResultSet numSearchesRS = pikaConn.prepareStatement("SELECT count(id) FROM search WHERE created < (CURDATE() - INTERVAL 2 DAY) AND saved = 0").executeQuery();
			numSearchesRS.next();
			long numSearches = numSearchesRS.getLong(1);
			long batchSize = 100000;
			long numBatches = (numSearches / batchSize) + 1;
			processLog.addNote("Found " + numSearches + " expired searches that need to be removed.  Will process in " + numBatches + " batches");
			processLog.saveToDatabase(pikaConn, logger);
			for (int i = 0; i < numBatches; i++){				PreparedStatement searchesToRemove = pikaConn.prepareStatement("SELECT id FROM search WHERE created < (CURDATE() - INTERVAL 2 DAY) AND saved = 0 LIMIT 0, " + batchSize, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				PreparedStatement removeSearchStmt = pikaConn.prepareStatement("DELETE FROM search WHERE id = ?");

				ResultSet searchesToRemoveRs = searchesToRemove.executeQuery();
				while (searchesToRemoveRs.next()){
					long curId = searchesToRemoveRs.getLong("id");
					removeSearchStmt.setLong(1, curId);
					rowsRemoved += removeSearchStmt.executeUpdate();
				}
				processLog.incUpdated();
				processLog.saveToDatabase(pikaConn, logger);
			}
			processLog.addNote("Removed " + rowsRemoved + " expired searches");
			processLog.saveToDatabase(pikaConn, logger);
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to delete expired searches. " + e.toString());
			logger.error("Error deleting expired searches", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private void removeExpiredSessions(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove expired sessions
		try{
			//Make sure to normalize the time based to be milliseconds, not microseconds
			long now                             = new Date().getTime() / 1000;
			long defaultTimeout                  = PikaConfigIni.getLongIniValue("Session", "lifetime");
			long earliestDefaultSessionToKeep    = now - defaultTimeout;
			long rememberMeTimeout               = PikaConfigIni.getLongIniValue("Session", "rememberMeLifetime");
			long earliestRememberMeSessionToKeep = now - rememberMeTimeout;
			long numStandardSessionsDeleted      = pikaConn.prepareStatement("DELETE FROM session WHERE last_used < " + earliestDefaultSessionToKeep + " and remember_me = 0").executeUpdate();
			long numRememberMeSessionsDeleted    = pikaConn.prepareStatement("DELETE FROM session WHERE last_used < " + earliestRememberMeSessionToKeep + " and remember_me = 1").executeUpdate();
			processLog.addNote("Deleted " + numStandardSessionsDeleted + " expired Standard Sessions");
			processLog.addNote("Deleted " + numRememberMeSessionsDeleted + " expired Remember Me Sessions");
			processLog.saveToDatabase(pikaConn, logger);
		}catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to delete expired sessions. " + e.toString());
			logger.error("Error deleting expired sessions", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
	}


	protected void cleanupReadingHistory(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		//Remove reading history entries that are duplicate based on being renewed
		//Get a list of duplicate titles
		try {
			//Add a filter so that we are looking at 1 week resolution rather than exact.
			//TODO: duplicates for entries *with out* grouped work Id
			PreparedStatement duplicateRecordsToPreserveStmt = pikaConn.prepareStatement("SELECT COUNT(id) AS numRecords, userId, groupedWorkPermanentId, source, sourceId, FLOOR(checkOutDate/604800) AS checkoutWeek , MIN(id) AS idToPreserve FROM user_reading_history_work WHERE deleted = 0 AND groupedWorkPermanentId != '' GROUP BY userId, groupedWorkPermanentId, FLOOR(checkOutDate/604800) HAVING numRecords > 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement deleteDuplicateRecordStmt      = pikaConn.prepareStatement("UPDATE user_reading_history_work SET deleted = 1 WHERE userId = ? AND groupedWorkPermanentId = ? AND FLOOR(checkOutDate/604800) = ? AND id != ?");
			ResultSet         duplicateRecordsRS             = duplicateRecordsToPreserveStmt.executeQuery();
			int               numDuplicateRecords            = 0;
			while (duplicateRecordsRS.next()) {
				deleteDuplicateRecordStmt.setLong(1, duplicateRecordsRS.getLong("userId"));
				deleteDuplicateRecordStmt.setString(2, duplicateRecordsRS.getString("groupedWorkPermanentId"));
				deleteDuplicateRecordStmt.setLong(3, duplicateRecordsRS.getLong("checkoutWeek"));
				deleteDuplicateRecordStmt.setLong(4, duplicateRecordsRS.getLong("idToPreserve"));
				deleteDuplicateRecordStmt.executeUpdate();

				//int numDeletions = deleteDuplicateRecordStmt.executeUpdate();
				/*if (numDeletions == 0){
					//This happens if the items have already been marked as deleted
					logger.debug("Warning did not delete any records for user " + duplicateRecordsRS.getLong("userId"));
				}*/
				numDuplicateRecords++;
			}
			processLog.addNote("Removed " + numDuplicateRecords + " records that were duplicates (check 1)");

			//Now look for additional duplicates where the check in date is within a week
			//TODO: duplicates for entries *with out* grouped work Id
			duplicateRecordsToPreserveStmt = pikaConn.prepareStatement("SELECT COUNT(id) AS numRecords, userId, groupedWorkPermanentId, source, sourceId, FLOOR(checkInDate/604800) checkInWeek, MIN(id) AS idToPreserve FROM user_reading_history_work WHERE deleted = 0 AND groupedWorkPermanentId != '' GROUP BY userId, groupedWorkPermanentId, FLOOR(checkInDate/604800) having numRecords > 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			deleteDuplicateRecordStmt      = pikaConn.prepareStatement("UPDATE user_reading_history_work SET deleted = 1 WHERE userId = ? AND groupedWorkPermanentId = ? AND FLOOR(checkInDate/604800) = ? AND id != ?");
			duplicateRecordsRS             = duplicateRecordsToPreserveStmt.executeQuery();
			numDuplicateRecords            = 0;
			while (duplicateRecordsRS.next()) {
				deleteDuplicateRecordStmt.setLong(1, duplicateRecordsRS.getLong("userId"));
				deleteDuplicateRecordStmt.setString(2, duplicateRecordsRS.getString("groupedWorkPermanentId"));
				deleteDuplicateRecordStmt.setLong(3, duplicateRecordsRS.getLong("checkInWeek"));
				deleteDuplicateRecordStmt.setLong(4, duplicateRecordsRS.getLong("idToPreserve"));
				deleteDuplicateRecordStmt.executeUpdate();

				//int numDeletions = deleteDuplicateRecordStmt.executeUpdate();
				/*if (numDeletions == 0){
					//This happens if the items have already been marked as deleted
					logger.debug("Warning did not delete any records for user " + duplicateRecordsRS.getLong("userId"));
				}*/
				numDuplicateRecords++;
			}
			processLog.addNote("Removed " + numDuplicateRecords + " records that were duplicates (check 2)");
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Unable to delete duplicate reading history entries. " + e.toString());
			logger.error("Error deleting duplicate reading history entries", e);
			processLog.saveToDatabase(pikaConn, logger);
		}

		//Remove invalid reading history entries
		try (PreparedStatement updateBadSourceIdStmt = pikaConn.prepareStatement("UPDATE `user_reading_history_work` SET `sourceId` = REPLACE(sourceId, 'ils:', '') WHERE sourceId like 'ils:%'")){
			int               numUpdates                             = updateBadSourceIdStmt.executeUpdate();
			processLog.addNote("Fix " + numUpdates + " invalid sourceIds for reading history entries");
			processLog.incUpdated();
		} catch (SQLException e) {
			final String errorMessage = "Error fixing invalid sourceIds for reading history entries";
			logger.error(errorMessage, e);
			processLog.incErrors();
			processLog.addNote(errorMessage + e.toString());
			processLog.saveToDatabase(pikaConn, logger);
		}

	}

}
