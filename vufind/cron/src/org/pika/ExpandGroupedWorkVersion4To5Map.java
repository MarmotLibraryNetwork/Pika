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

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;

/**
 * Pika
 *
 * @author pbrammeier
 * 				Date:   8/12/2020
 */
public class ExpandGroupedWorkVersion4To5Map implements IProcessHandler {

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Expand Grouped Work Version 4 To 5 Map");
		processLog.saveToDatabase(pikaConn, logger);

		populateMap(pikaConn, logger, processLog);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void populateMap(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		String sql;

		// Simple Mapping
		sql = "SELECT\n" +
						"grouped_work_old.permanent_id AS oldID\n" +
						", GROUP_CONCAT(DISTINCT grouped_work.permanent_id) AS newID\n" +
						"FROM grouped_work_old \n" +
						"LEFT JOIN grouped_work_primary_identifiers_old ON (grouped_work_primary_identifiers_old.grouped_work_id = grouped_work_old.id)\n" +
						"LEFT JOIN grouped_work_primary_identifiers ON (grouped_work_primary_identifiers.identifier = grouped_work_primary_identifiers_old.identifier AND grouped_work_primary_identifiers.type = grouped_work_primary_identifiers_old.type)\n" +
						"LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)\n" +
						"WHERE grouped_work_old.permanent_id NOT IN (SELECT groupedWorkPermanentIdVersion4 FROM grouped_work_versions_map)\n" +
						"GROUP BY grouped_work_old.permanent_id\n" +
						"HAVING COUNT(DISTINCT grouped_work.permanent_id) = 1";
		String insertSQL   = "INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4, groupedWorkPermanentIdVersion5) VALUES (?,?)";
		try (
						PreparedStatement preparedStatement = pikaConn.prepareStatement(sql);
						PreparedStatement insertStatement = pikaConn.prepareStatement(insertSQL);
						ResultSet resultSet = preparedStatement.executeQuery()
		) {
			int updates = 0;
			while (resultSet.next()) {
				String version4Id = resultSet.getString(1);
				String version5Id = resultSet.getString(2);
				try {
					insertStatement.setString(1, version4Id);
					insertStatement.setString(2, version5Id);
					insertStatement.executeUpdate();
					updates++;
				} catch (SQLException exception) {
					//Ignore duplicate warning here; rather than in the SQL statement itself
					if (exception.toString().contains("Duplicate")) {
						logger.info(exception);
					} else {
						throw exception;
					}
				}


			}
			processLog.addUpdates(updates);
			processLog.addNote("One to one mapping added " + updates + " entries");
		} catch (SQLException e) {
			logger.error("Error while populating version map with one to one matches.", e);
			processLog.addNote("Error in simple mapping : " + e);
			processLog.incErrors();
		}

	}

}

