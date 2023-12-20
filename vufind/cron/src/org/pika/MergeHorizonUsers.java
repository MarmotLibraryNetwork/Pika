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
import org.ini4j.Ini;
import org.ini4j.Profile;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * Tool to clean up users that have entries in the user table for both the barcode and Horizon unique id
 * Pika
 * User: Mark Noble
 * Date: 9/28/2015
 * Time: 10:27 AM
 */
public class MergeHorizonUsers implements IProcessHandler {
	PreparedStatement mergeUserLinksStmt;
	PreparedStatement mergeUserLinks2Stmt;
	PreparedStatement mergeUserLinks3Stmt;
	PreparedStatement mergeUserLinks4Stmt;
	PreparedStatement mergeUserListStmt;
	PreparedStatement mergeNotInterestedStmt;
	PreparedStatement mergeUserReadingHistoryStmt;
	PreparedStatement mergeUserRolesStmt;
	PreparedStatement mergeUserTagsStmt;
	PreparedStatement mergeSearchesStmt;
	PreparedStatement mergeBrowseCategoriesStmt;
	PreparedStatement mergeMaterialsRequestsStmt;
	PreparedStatement mergeUserReviewsStmt;
	PreparedStatement removeDuplicateUserStmt;

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Merge Horizon Users");
		processLog.saveToDatabase(pikaConn, logger);

		//Get a list of users that are in the database twice
		int       numDuplicateUsers = 0;
		try {
			PreparedStatement duplicateUsersStmt       = pikaConn.prepareStatement("SELECT barcode, COUNT(id) AS numDuplicates FROM user WHERE barcode IS NOT NULL GROUP BY barcode HAVING numDuplicates > 1");
			PreparedStatement getDuplicateUserInfoStmt = pikaConn.prepareStatement("SELECT id, ilsUserId, barcode FROM user WHERE barcode = ?");
			mergeUserLinksStmt          = pikaConn.prepareStatement("UPDATE user_link SET primaryAccountId = ? WHERE primaryAccountId = ?");
			mergeUserLinks2Stmt         = pikaConn.prepareStatement("UPDATE user_link SET linkedAccountId = ? WHERE linkedAccountId = ?");
			mergeUserLinks3Stmt         = pikaConn.prepareStatement("UPDATE user_link_blocks SET primaryAccountId = ? WHERE primaryAccountId = ?");
			mergeUserLinks4Stmt         = pikaConn.prepareStatement("UPDATE user_link_blocks SET blockedLinkAccountId = ? WHERE blockedLinkAccountId = ?");
			mergeUserListStmt           = pikaConn.prepareStatement("UPDATE user_list SET user_id = ? WHERE user_id = ?");
			mergeNotInterestedStmt      = pikaConn.prepareStatement("UPDATE user_not_interested SET userId = ? WHERE userId = ?");
			mergeUserReadingHistoryStmt = pikaConn.prepareStatement("UPDATE user_reading_history_work SET userId = ? WHERE userId = ?");
			mergeUserRolesStmt          = pikaConn.prepareStatement("UPDATE user_roles SET userId = ? WHERE userId = ?");
			mergeUserTagsStmt           = pikaConn.prepareStatement("UPDATE user_tags SET userId = ? WHERE userId = ?");
			mergeSearchesStmt           = pikaConn.prepareStatement("UPDATE search SET user_id = ? WHERE user_id = ?");
			mergeBrowseCategoriesStmt   = pikaConn.prepareStatement("UPDATE browse_category SET userId = ? WHERE userId = ?");
			mergeMaterialsRequestsStmt  = pikaConn.prepareStatement("UPDATE materials_request SET createdBy = ? WHERE createdBy = ?");
			mergeUserReviewsStmt        = pikaConn.prepareStatement("UPDATE user_work_review SET userId = ? WHERE userId = ?");
			removeDuplicateUserStmt     = pikaConn.prepareStatement("DELETE FROM user WHERE id = ? LIMIT 1");
			ResultSet duplicateUsersRS  = duplicateUsersStmt.executeQuery();
			while (duplicateUsersRS.next()) {
				numDuplicateUsers++;
				String barcode = duplicateUsersRS.getString("barcode");
				try {
					getDuplicateUserInfoStmt.setString(1, barcode);
					ResultSet duplicateUserInfo = getDuplicateUserInfoStmt.executeQuery();

					long preferredIlsUserId = -1L;
					long duplicateIlsUserId = -1L;
					long preferredUserId    = -1L;
					long duplicateUserId    = -1L;

					while (duplicateUserInfo.next()) {
						String userId = duplicateUserInfo.getString("ilsUserId").trim();
						if (userId.equals(barcode)) {
							duplicateIlsUserId = duplicateUserInfo.getLong("ilsUserId");
							duplicateUserId    = duplicateUserInfo.getLong("id");
						} else {
							preferredIlsUserId = duplicateUserInfo.getLong("ilsUserId");
							preferredUserId    = duplicateUserInfo.getLong("id");
						}
					}
					if (preferredIlsUserId == -1L || duplicateIlsUserId == -1L) {
						logger.error("Could not determine preferred and duplicate id for barcode " + barcode);
					} else {
						//Merge enrichment for the users
						int numChanges = 0;
						numChanges += mergeUserLinks(preferredUserId, duplicateUserId);
						numChanges += mergeUserLists(preferredUserId, duplicateUserId);
						numChanges += mergeUserNotInterested(preferredUserId, duplicateUserId);
						numChanges += mergeUserReadingHistory(preferredUserId, duplicateUserId);
						numChanges += mergeUserRoles(preferredUserId, duplicateUserId);
						numChanges += mergeUserTags(preferredUserId, duplicateUserId);
						numChanges += mergeSearches(preferredUserId, duplicateUserId);
						numChanges += mergeBrowseCategories(preferredUserId, duplicateUserId);
						numChanges += mergeMaterialsRequests(preferredUserId, duplicateUserId);
						numChanges += mergeUserReviews(preferredUserId, duplicateUserId);

						logger.info("Made " + numChanges + " changes for user barcode " + barcode);


						//Remove the duplicate user
						removeDuplicateUserStmt.setLong(1, duplicateUserId);
						int userDeleted = removeDuplicateUserStmt.executeUpdate();
						if (userDeleted > 0) {
							processLog.incUpdated();
						}
					}
				} catch (SQLException e) {
					processLog.incErrors();
					processLog.addNote("Error processing barcode " + barcode + ". " + e);
					logger.error("Error processing barcode " + barcode, e);
					processLog.saveToDatabase(pikaConn, logger);
				}
			}
		} catch (Exception e) {
			processLog.incErrors();
			processLog.addNote("Error loading duplicate users. " + e);
			logger.error("Error loading duplicate users", e);
			processLog.saveToDatabase(pikaConn, logger);
		}
		String message = "Processed " + numDuplicateUsers + " users with more than one instance in the system.";
		logger.info(message);
		processLog.addNote(message);

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private int mergeUserReviews(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeUserReviewsStmt.setLong(1, preferredUserId);
		mergeUserReviewsStmt.setLong(2, duplicateUserId);
		return mergeUserReviewsStmt.executeUpdate();
	}

	private int mergeMaterialsRequests(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeMaterialsRequestsStmt.setLong(1, preferredUserId);
		mergeMaterialsRequestsStmt.setLong(2, duplicateUserId);
		return mergeMaterialsRequestsStmt.executeUpdate();
	}

	private int mergeBrowseCategories(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeBrowseCategoriesStmt.setLong(1, preferredUserId);
		mergeBrowseCategoriesStmt.setLong(2, duplicateUserId);
		return mergeBrowseCategoriesStmt.executeUpdate();
	}

	private int mergeSearches(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeSearchesStmt.setLong(1, preferredUserId);
		mergeSearchesStmt.setLong(2, duplicateUserId);
		return mergeSearchesStmt.executeUpdate();
	}

	private int mergeUserTags(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeUserTagsStmt.setLong(1, preferredUserId);
		mergeUserTagsStmt.setLong(2, duplicateUserId);
		return mergeUserTagsStmt.executeUpdate();
	}

	private int mergeUserRoles(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeUserRolesStmt.setLong(1, preferredUserId);
		mergeUserRolesStmt.setLong(2, duplicateUserId);
		return mergeUserRolesStmt.executeUpdate();
	}

	private int mergeUserReadingHistory(Long preferredUserId, Long duplicateUserId) throws SQLException {
		mergeUserReadingHistoryStmt.setLong(1, preferredUserId);
		mergeUserReadingHistoryStmt.setLong(2, duplicateUserId);
		return mergeUserReadingHistoryStmt.executeUpdate();
	}

	private int mergeUserNotInterested(Long preferredId, Long duplicateId) throws SQLException {
		mergeNotInterestedStmt.setLong(1, preferredId);
		mergeNotInterestedStmt.setLong(2, duplicateId);
		return mergeNotInterestedStmt.executeUpdate();
	}

	private int mergeUserLists(Long preferredId, Long duplicateId) throws SQLException {
		mergeUserListStmt.setLong(1, preferredId);
		mergeUserListStmt.setLong(2, duplicateId);
		return mergeUserListStmt.executeUpdate();
	}

	private int mergeUserLinks(Long preferredId, Long duplicateId) throws SQLException {
		int numChanges;
		mergeUserLinksStmt.setLong(1, preferredId);
		mergeUserLinksStmt.setLong(2, duplicateId);
		numChanges = mergeUserLinksStmt.executeUpdate();

		mergeUserLinks2Stmt.setLong(1, preferredId);
		mergeUserLinks2Stmt.setLong(2, duplicateId);
		numChanges += mergeUserLinks2Stmt.executeUpdate();

		mergeUserLinks3Stmt.setLong(1, preferredId);
		mergeUserLinks3Stmt.setLong(2, duplicateId);
		numChanges += mergeUserLinks3Stmt.executeUpdate();

		mergeUserLinks4Stmt.setLong(1, preferredId);
		mergeUserLinks4Stmt.setLong(2, duplicateId);
		numChanges += mergeUserLinks4Stmt.executeUpdate();
		return numChanges;
	}
}
