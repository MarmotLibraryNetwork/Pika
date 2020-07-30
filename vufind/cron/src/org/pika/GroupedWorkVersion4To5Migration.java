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

		updateUserData(pikaConn, logger, processLog, "librarian_reviews");
		updateUserData(pikaConn, logger, processLog, "user_tags");
		updateUserData(pikaConn, logger, processLog, "user_not_interested");
		updateUserData(pikaConn, logger, processLog, "user_work_review");
		updateUserData(pikaConn, logger, processLog, "user_list_entry");

		updateReadingHistoryIds(pikaConn, logger, processLog);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void updateUserData(Connection pikaConn, Logger logger, CronProcessLogEntry processLog, String tableName) {
		long   id                = -1L;
		int    updated           = 0;
		int    deleted           = 0;
		int    total             = 0;
		String userDataSQL       = "SELECT id, groupedWorkPermanentId, groupedWorkPermanentIdVersion5 FROM " + tableName + " LEFT JOIN grouped_work_versions_map ON (groupedWorkPermanentId = groupedWorkPermanentIdVersion4)";
		String updateUserDataSQL = "UPDATE " + tableName + " SET groupedWorkPermanentId = ? WHERE (  " + tableName + ".id = ? )";
		String deleteUserDataSQL = "DELETE FROM " + tableName + " WHERE (  " + tableName + ".id = ? ) LIMIT 1";
		try (
				PreparedStatement fetchUserData = pikaConn.prepareStatement(userDataSQL);
				PreparedStatement updateUserData = pikaConn.prepareStatement(updateUserDataSQL);
				PreparedStatement deleteUserData = pikaConn.prepareStatement(deleteUserDataSQL);
				ResultSet userdata = fetchUserData.executeQuery()
		) {
			pikaConn.setAutoCommit(false);
			int roundCount = 0;
			while (userdata.next()) {

				id = userdata.getLong("id");
				String groupedWorkPermanentId = userdata.getString("groupedWorkPermanentId");
				if (groupedWorkPermanentId != null && !groupedWorkPermanentId.isEmpty() && !groupedWorkPermanentId.contains(":")) {
					//contains check to exclude archive pids from user lists
					total++;
					roundCount++;
					String groupedWorkPermanentIdVersion5 = userdata.getString("groupedWorkPermanentIdVersion5");
					if (groupedWorkPermanentIdVersion5 != null && !groupedWorkPermanentIdVersion5.isEmpty()) {
						updateUserData.setString(1, groupedWorkPermanentIdVersion5);
						updateUserData.setLong(2, id);
						updateUserData.addBatch();
						updated++;
					} else {
						//TODO: sloppy matching
						logger.info("Deleting user entry " + id + "since no match for grouped work version 4 id : " + groupedWorkPermanentId);
						deleteUserData.setLong(1, id);
						deleteUserData.addBatch();
						deleted++;
					}
				}
				if (roundCount == 1000) {
					updateUserData.executeBatch();
					deleteUserData.executeBatch();
					processLog.addUpdates(roundCount);
					processLog.saveToDatabase(pikaConn, logger);
					roundCount = 0; //Reset our round counting
				}

			}
			updateUserData.executeBatch();
			deleteUserData.executeBatch();
			processLog.addUpdates(roundCount);


			pikaConn.commit();

			final String note = "User data for " + tableName + "\n<br>" +
					"Total   : " + total + "\n<br>" +
					"Updated : " + updated + "\n<br>" +
					"Deleted : " + deleted + "\n<br>";
			processLog.addNote(note);
			logger.info(note);
			processLog.saveToDatabase(pikaConn, logger);

		} catch (SQLException e) {
			logger.error("Error While updating user data. Last Id fetched " + id, e);
			processLog.addNote(e.toString());
			processLog.incErrors();
			try {
				pikaConn.rollback();
			} catch (SQLException throwables) {
				logger.error("Error rolling back database commit", throwables);
			}
		}
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

					if (roundCount == 1000) {
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

			final String note = "User Reading History" + "\n<br>" +
					"Updated a total of " + total + "\n<br>" +
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
