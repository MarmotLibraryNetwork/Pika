package org.pika;

import org.apache.log4j.Logger;
import org.ini4j.Profile;

import java.sql.*;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   7/22/2020
 */
public class GroupedWorkVersion4To5Migration implements IProcessHandler {
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Grouped Work Version 4 To 5 Migration");
		processLog.saveToDatabase(pikaConn, logger);

		updateReadingHistoryIds(pikaConn, logger, processLog);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void updateReadingHistoryIds(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		long id              = 0;
		long matchBySourceId = 0;
		long matchByMap      = 0;
		long noMatch         = 0;
		long total           = 0;

		try {
			pikaConn.setAutoCommit(false);
			PreparedStatement updateReadingHistoryWorkId = pikaConn.prepareStatement("UPDATE user_reading_history_work SET groupedWorkPermanentId = ? WHERE id = ?");

			PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT \n" +
					"user_reading_history_work.id,\n" +
					"groupedWorkPermanentId\n" +
					",permanent_id\n" +
					",groupedWorkPermanentIdVersion5\n" +
					"FROM user_reading_history_work\n" +
					"LEFT JOIN grouped_work_primary_identifiers ON (source = type AND identifier = if(source = 'hoopla',concat('MWT', sourceId), sourceId))\n" +
					"LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)\n" +
					"LEFT JOIN grouped_work_versions_map ON (groupedWorkPermanentId = groupedWorkPermanentIdVersion4)\n" +
					"WHERE groupedWorkPermanentId != ''\n", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet resultSet = preparedStatement.executeQuery();

			int roundCount = 0;
			while (resultSet.next()) {
				roundCount++;
				id = resultSet.getLong("id");
				String groupedWorkPermanentId              = resultSet.getString("groupedWorkPermanentId");
				String newGroupedWorkIdViaRecordMatching   = resultSet.getString("permanent_id");
				String newGroupedWorkIdViaVersionIdMapping = resultSet.getString("groupedWorkPermanentIdVersion5");
				if (groupedWorkPermanentId != null && !groupedWorkPermanentId.isEmpty()) {
					if (newGroupedWorkIdViaRecordMatching != null && !newGroupedWorkIdViaRecordMatching.isEmpty()) {
						// Match by Source and Source Id
						updateReadingHistoryWorkId.setString(1, newGroupedWorkIdViaRecordMatching);
						matchBySourceId++;

					} else if (newGroupedWorkIdViaVersionIdMapping != null && !newGroupedWorkIdViaVersionIdMapping.isEmpty()) {
						// Match by the Grouped Work Versions Map
						updateReadingHistoryWorkId.setString(1, newGroupedWorkIdViaVersionIdMapping);
						matchByMap++;
					} else {
						// No Matching; Remove the Id
						updateReadingHistoryWorkId.setString(1, "");
						noMatch++;
					}
					updateReadingHistoryWorkId.setLong(2, id);
					updateReadingHistoryWorkId.addBatch();
					total++;

					if (roundCount == 1000){
						updateReadingHistoryWorkId.executeBatch();
						processLog.addUpdates(roundCount);
						processLog.saveToDatabase(pikaConn, logger);
						roundCount = 0; //Reset our round counting
					}
				}
			}
			updateReadingHistoryWorkId.executeBatch();
			processLog.addUpdates(roundCount);

			pikaConn.commit();

			final String note = "Updated a total of " + total + "\n<br>" +
					"Matched by Source Id :" + matchBySourceId + "\n<br>" +
					"Matched by Version Map :" + matchByMap + "\n<br>" +
					"Removed Id due to no matches :" + noMatch + "\n<br>";
			processLog.addNote(note);
			logger.info(note);


		} catch (SQLException e) {
			logger.error("Error While updating reading history. Last Id fetched " + id, e);
			processLog.addNote(e.toString());
			processLog.incErrors();
			try {
				pikaConn.rollback();
			} catch (SQLException throwables) {
				logger.error("Error rolling back database commit", throwables);
			}
		}
	}

}
