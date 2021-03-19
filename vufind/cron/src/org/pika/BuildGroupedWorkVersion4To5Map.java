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

import com.mysql.jdbc.exceptions.MySQLIntegrityConstraintViolationException;
import org.apache.log4j.Logger;
import org.ini4j.Profile;

import java.sql.*;
import java.text.DateFormat;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   7/23/2020
 */
public class BuildGroupedWorkVersion4To5Map implements IProcessHandler {
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Build Grouped Work Version 4 To 5 Map");
		processLog.saveToDatabase(pikaConn, logger);

		populateMap(pikaConn, logger, processLog);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void populateMap(Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		String sql;

		// Simple Mapping
		sql = "UPDATE IGNORE grouped_work_versions_map\n" +
				"INNER JOIN\n" +
				"(\n" +
				"SELECT \n" +
				"grouped_work_old.permanent_id AS oldID\n" +
				"    , GROUP_CONCAT(DISTINCT grouped_work.permanent_id) AS newID\n" +
				"    \n" +
				"    FROM grouped_work_versions_map\n" +
				"    LEFT JOIN grouped_work_old ON (grouped_work_versions_map.groupedWorkPermanentIdVersion4 = grouped_work_old.permanent_id)\n" +
				"    LEFT JOIN grouped_work_primary_identifiers_old ON (grouped_work_primary_identifiers_old.grouped_work_id = grouped_work_old.id)\n" +
				"    LEFT JOIN grouped_work_primary_identifiers ON (grouped_work_primary_identifiers.identifier = grouped_work_primary_identifiers_old.identifier AND grouped_work_primary_identifiers.type = grouped_work_primary_identifiers_old.type)\n" +
				"    LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)\n" +
				"    WHERE grouped_work_versions_map.groupedWorkPermanentIdVersion5 IS NULL\n" +
				"    GROUP BY grouped_work_old.permanent_id\n" +
				"    HAVING COUNT(DISTINCT grouped_work.permanent_id) = 1\n" +
				") As relatedRecordMappingOneToOneMatches\n" +
				"ON groupedWorkPermanentIdVersion4 = relatedRecordMappingOneToOneMatches.oldID\n" +
				"SET groupedWorkPermanentIdVersion5 = relatedRecordMappingOneToOneMatches.newID\n" +
				"WHERE groupedWorkPermanentIdVersion5 IS NULL";
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement(sql);
		) {
			int updates = preparedStatement.executeUpdate();
			processLog.addUpdates(updates);
			processLog.addNote("One to one mapping added " + updates + " entries");
		} catch (SQLException e) {
			logger.error("Error while populating version map with one to one matches.", e);
			processLog.addNote("Error in simple mapping : " + e);
			processLog.incErrors();
		}

		// Complex Mapping
		sql = "SELECT groupedWorkPermanentIdVersion4 FROM  grouped_work_versions_map \n" +
				" INNER JOIN grouped_work_old  ON (grouped_work_old.permanent_id=grouped_work_versions_map.groupedWorkPermanentIdVersion4)   \n" +
				" WHERE ( groupedWorkPermanentIdVersion4 IS NOT NULL AND groupedWorkPermanentIdVersion5 IS NULL );";
		String updateSql        = "UPDATE grouped_work_versions_map SET groupedWorkPermanentIdVersion5 = ? WHERE groupedWorkPermanentIdVersion4 = ?";
		String insertMergeSql   = "INSERT INTO grouped_work_merges (sourceGroupedWorkId,destinationGroupedWorkId,notes) VALUES (?,?,?)";
		String groupedWorkFetch = "SELECT * FROM grouped_work WHERE permanent_id = ?";
		String complexMappingSql = "SELECT\n" +
				"COUNT(DISTINCT grouped_work.permanent_id) AS newIDcount\n" +
				", GROUP_CONCAT(DISTINCT grouped_work.permanent_id ORDER BY LENGTH(grouped_work.full_title)) AS newIDs\n" +
				", GROUP_CONCAT(DISTINCT grouped_work.grouping_category) AS newGroupingCategories\n" +
				", GROUP_CONCAT(DISTINCT grouped_work.grouping_language) AS newGroupingLanguages\n" +
				", COUNT(DISTINCT grouped_work.author) AS newGroupingAuthorsCount\n" +
				", GROUP_CONCAT(DISTINCT grouped_work.full_title ORDER BY LENGTH(grouped_work.full_title)) AS newGroupingTitles\n" +
				", GROUP_CONCAT(DISTINCT grouped_work_primary_identifiers.type, ':', grouped_work_primary_identifiers.identifier ORDER BY LENGTH(grouped_work.full_title) SEPARATOR ', ') AS newPrimaryIdentifiers\n" +
				"\n" +
				"FROM grouped_work_versions_map\n" +
				"\n" +
				"LEFT JOIN grouped_work_old ON (grouped_work_versions_map.groupedWorkPermanentIdVersion4 = grouped_work_old.permanent_id)\n" +
				"LEFT JOIN grouped_work_primary_identifiers_old ON (grouped_work_primary_identifiers_old.grouped_work_id = grouped_work_old.id)\n" +
				"LEFT JOIN grouped_work_primary_identifiers ON (grouped_work_primary_identifiers.identifier = grouped_work_primary_identifiers_old.identifier AND grouped_work_primary_identifiers.type = grouped_work_primary_identifiers_old.type)\n" +
				"LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)\n" +
				"\n" +
				"WHERE groupedWorkPermanentIdVersion4 = ? \n" +
				"GROUP BY grouped_work_old.permanent_id";
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement(sql);
				PreparedStatement complexMappingStatement = pikaConn.prepareStatement(complexMappingSql);
				PreparedStatement groupedWorkFetchStatement = pikaConn.prepareStatement(groupedWorkFetch);
				PreparedStatement updateStatement = pikaConn.prepareStatement(updateSql);
				PreparedStatement insertMergeStatement = pikaConn.prepareStatement(insertMergeSql);
				ResultSet resultSet = preparedStatement.executeQuery()
		) {
			int roundCount = 0;
			while (resultSet.next()) {
				String groupedWorkPermanentIdVersion4 = resultSet.getString(1);
				if (groupedWorkPermanentIdVersion4 != null && !groupedWorkPermanentIdVersion4.isEmpty()) {
					complexMappingStatement.setString(1, groupedWorkPermanentIdVersion4);
					try (ResultSet complexMapResults = complexMappingStatement.executeQuery()) {
						if (complexMapResults.next()) {
							int newIDcount = complexMapResults.getInt("newIDcount");
							if (newIDcount > 1) {
								String[] newGroupingIds        = complexMapResults.getString("newIDs").split(",");
								String[] newGroupingCategories = complexMapResults.getString("newGroupingCategories").split(",");
								String[] newGroupingLanguages  = complexMapResults.getString("newGroupingLanguages").split(",");

								if (newGroupingCategories.length > 1 && newGroupingLanguages.length == 1) {
									//Graphic Novel Scenario (Use the book version of the grouped work)
									for (String groupedWorkId : newGroupingIds) {
										groupedWorkFetchStatement.setString(1, groupedWorkId);
										ResultSet workResult = groupedWorkFetchStatement.executeQuery();
										if (workResult.next()) {
											String category = workResult.getString("grouping_category");
											if (category.equalsIgnoreCase("book")) {
												updateStatement.setString(1, groupedWorkId);
												updateStatement.setString(2, groupedWorkPermanentIdVersion4);
												updateStatement.addBatch();
												roundCount++;
												break;
											}
										}

									}
								} else if (newGroupingCategories.length == 1 && newGroupingLanguages.length > 1) {
									// Multiple Languages Scenario (Use the english work)
									for (String groupedWorkId : newGroupingIds) {
										groupedWorkFetchStatement.setString(1, groupedWorkId);
										ResultSet workResult = groupedWorkFetchStatement.executeQuery();
										if (workResult.next()) {
											String language = workResult.getString("grouping_language");
											if (language.equalsIgnoreCase("eng")) {
												updateStatement.setString(1, groupedWorkId);
												updateStatement.setString(2, groupedWorkPermanentIdVersion4);
												updateStatement.addBatch();
												roundCount++;
												break;
											}
										}
									}
								} else if (Arrays.asList(newGroupingCategories).contains("comic") && Arrays.asList(newGroupingLanguages).contains("eng")) {
									// Multiple Languages and new comic grouping category scenario (use the english book version)
									for (String groupedWorkId : newGroupingIds) {
										groupedWorkFetchStatement.setString(1, groupedWorkId);
										ResultSet workResult = groupedWorkFetchStatement.executeQuery();
										if (workResult.next()) {
											String language = workResult.getString("grouping_language");
											String category = workResult.getString("grouping_category");
											if (language.equalsIgnoreCase("eng") && category.equalsIgnoreCase("book")) {
												updateStatement.setString(1, groupedWorkId);
												updateStatement.setString(2, groupedWorkPermanentIdVersion4);
												updateStatement.addBatch();
												roundCount++;
												break;
											}
										}
									}
								} else if (newGroupingCategories.length == 1 && newGroupingLanguages.length == 1 && !Arrays.equals(newGroupingCategories, new String[]{"movie"})) {
									// Multiple titles that should be merged
//								String[] newGroupingAuthors = complexMapResults.getString("newGroupingAuthors").split(",");
									int newGroupingAuthorsCount = complexMapResults.getInt("newGroupingAuthorsCount");
									if (newGroupingAuthorsCount == 1) {
										String[] newGroupingTitles = complexMapResults.getString("newGroupingTitles").split(",");
										// The SQL will sort these titles by shortest to longest; and the new Grouping Ids are in the corresponding order
										// We will merge these works together if each title begins with the shortest title

										boolean merge           = true;
										String  shortestTitle   = newGroupingTitles[0];
										String  shortestTitleId = newGroupingIds[0];
										for (String title : newGroupingTitles) {
											if (shortestTitle.length() > title.length() || !title.startsWith(shortestTitle)) {
												merge = false;
												break;
											}
										}

										if (merge) {

											for (String groupedWorkId : newGroupingIds) {
												if (!groupedWorkId.equals(shortestTitleId)) {
													// Add merge grouping entry


													// Building note for this specific merging entry
													groupedWorkFetchStatement.setString(1, groupedWorkId);
													ResultSet workResult = groupedWorkFetchStatement.executeQuery();
													String    note       = "";
													if (workResult.next()) {
														// Fetch title for specific work
														String title = workResult.getString("full_title");
														note = "Merge title '" + title + "' to '" + shortestTitle + "'\n\n";
													}

													final int    noteColumnLength = 250;
													String       recordIds        = complexMapResults.getString("newPrimaryIdentifiers");
													final String migrationNote    = "Grouped Work Version Migration  " + new SimpleDateFormat("M/dd/yyyy").format(new Date());
													final String recordIdsNote    = recordIds + "\n\n" + migrationNote;
													if (note.length() + recordIdsNote.length() <= noteColumnLength) {
														note = note + recordIdsNote;
													} else if (recordIdsNote.length() <= noteColumnLength) {
														note = recordIdsNote;
													} else {
														note = migrationNote;
													}

													try {
														insertMergeStatement.setString(1, groupedWorkId);
														insertMergeStatement.setString(2, shortestTitleId);
														insertMergeStatement.setString(3, note);
														insertMergeStatement.executeUpdate();
													} catch (SQLException exception) {
														//Ignore duplicate warning here; rather than in the SQL statement itsefl
														if (exception.toString().contains("Duplicate")) {
															logger.info(exception);
														} else {
															if (exception.toString().contains("notes")) {
																logger.error("notes : " + note);
															}
															throw exception;
														}
													}

													updateStatement.setString(1, groupedWorkId);
													updateStatement.setString(2, groupedWorkPermanentIdVersion4);
													updateStatement.addBatch();
													roundCount++;
												}

											}
										}
									}
								}

								// Execute the updates
								if (roundCount == 1000) {
									updateStatement.executeBatch();
									processLog.addUpdates(roundCount);
									processLog.saveToDatabase(pikaConn, logger);
									roundCount = 0; //Reset our round counting
								}


							}
						}
					}
				}
			}
			// Final round of updating
			updateStatement.executeBatch();
			processLog.addUpdates(roundCount);

		} catch (SQLException e) {
			logger.error("It broke!!", e);
			processLog.incErrors();

		}
	}

}

