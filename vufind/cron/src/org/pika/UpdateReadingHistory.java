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

import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;


import java.io.IOException;
import java.io.InputStream;
import java.math.BigInteger;
import java.net.ConnectException;
import java.net.MalformedURLException;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.*;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.Iterator;

public class UpdateReadingHistory implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private String              pikaUrl;
	private Logger              logger;
	private PreparedStatement   insertReadingHistoryStmt;
	private String              userApiToken                     = "";
	private int                 initialHistoriesLoaded           = 0;
	private int                 initialHistoriesFailedToBeLoaded = 0;
	private int                 loadedHistoriesUpdated           = 0;
	private int                 loadeHistoriesFailedToUpdate     = 0;

	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Update Reading History");
		processLog.saveToDatabase(pikaConn, logger);

		this.logger = logger;
		logger.info("Updating Reading History");
		processLog.addNote("Updating Reading History");

		pikaUrl = PikaConfigIni.getIniValue("Site", "url");
		if (pikaUrl == null || pikaUrl.length() == 0) {
			logger.error("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.incErrors();
			processLog.addNote("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			return;
		}

		userApiToken = PikaConfigIni.getIniValue("System", "userApiToken");
		if (userApiToken == null || userApiToken.length() == 0) {
			logger.error("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			processLog.incErrors();
			processLog.addNote("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			return;
		}

		// Connect to the Pika MySQL database
		try (
//			PreparedStatement lookForPreviousEntriesBeforeInitialLoad = pikaConn.prepareStatement("SELECT COUNT(id) FROM user_reading_history_work WHERE userId = ?");
//			PreparedStatement deletePreviousEntriesBeforeInitialLoad  = pikaConn.prepareStatement("DELETE FROM user_reading_history_work WHERE userId = ?");
			// Get a list of all patrons that have reading history turned on.
				//Order by make it so that reading histories that haven't been processed yet are done first.
				PreparedStatement countUsersStmt                    = pikaConn.prepareStatement("SELECT COUNT(*) as readingHistoryUsers FROM user WHERE trackReadingHistory=1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				PreparedStatement getUsersStmt                      = pikaConn.prepareStatement("SELECT id, barcode, initialReadingHistoryLoaded FROM user WHERE trackReadingHistory=1 ORDER BY initialReadingHistoryLoaded", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				PreparedStatement updateInitialReadingHistoryLoaded = pikaConn.prepareStatement("UPDATE user SET initialReadingHistoryLoaded = 1 WHERE id = ?");
				PreparedStatement getUserCheckedOutTitlesAlreadyInReadingHistory        = pikaConn.prepareStatement("SELECT id, groupedWorkPermanentId, source, sourceId, title FROM user_reading_history_work WHERE userId=? AND checkInDate IS NULL", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				PreparedStatement updateReadingHistoryStmt          = pikaConn.prepareStatement("UPDATE user_reading_history_work SET checkInDate=? WHERE id = ?");
				ResultSet countUsersResults                         = countUsersStmt.executeQuery(); // Fetches patrons that haven't had the initial load done first
				ResultSet userResults                               = getUsersStmt.executeQuery(); // Fetches patrons that haven't had the initial load done first
		){
			insertReadingHistoryStmt = pikaConn.prepareStatement("INSERT INTO user_reading_history_work (userId, groupedWorkPermanentId, source, sourceId, title, author, format, checkOutDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			if (countUsersResults.next()){
				final int totalReadingHistoryUsers = countUsersResults.getInt("readingHistoryUsers");
				final String note = totalReadingHistoryUsers + " total users with reading history turned on.";
				logger.info(note);
				processLog.addNote(note);
				processLog.saveToDatabase(pikaConn, logger);
			}

			while (userResults.next()) {
				// For each patron
				long    userId                            = userResults.getLong("id");
				String  barcode                           = userResults.getString("barcode");
				boolean initialReadingHistoryLoaded       = userResults.getBoolean("initialReadingHistoryLoaded");
				boolean errorLoadingInitialReadingHistory = false;
				if (!initialReadingHistoryLoaded) {
					//Get the initial reading history from the ILS
					try {
						if (loadInitialReadingHistoryForUser(userId, barcode)) {
							updateInitialReadingHistoryLoaded.setLong(1, userId);
							updateInitialReadingHistoryLoaded.executeUpdate();
							initialHistoriesLoaded++;
						} else {
							errorLoadingInitialReadingHistory = true;
							logger.warn("Failed loading Initial Reading History for user id " + userId);
							initialHistoriesFailedToBeLoaded++;
						}
					} catch (SQLException e) {
						logger.error("Error loading initial reading history", e);
						errorLoadingInitialReadingHistory = true;
						initialHistoriesFailedToBeLoaded++;
					}
				}

				if (!errorLoadingInitialReadingHistory) {
					//Get a list of titles that are currently checked out
					getUserCheckedOutTitlesAlreadyInReadingHistory.setLong(1, userId);
					ArrayList<CheckedOutTitle> checkedOutTitlesAlreadyInReadingHistory;
					try (ResultSet checkedOutTitlesRS = getUserCheckedOutTitlesAlreadyInReadingHistory.executeQuery()) {
						checkedOutTitlesAlreadyInReadingHistory = new ArrayList<>();
						while (checkedOutTitlesRS.next()) {
							CheckedOutTitle curCheckout = new CheckedOutTitle();
							curCheckout.setId(checkedOutTitlesRS.getLong("id"));
							curCheckout.setGroupedWorkPermanentId(checkedOutTitlesRS.getString("groupedWorkPermanentId"));
							curCheckout.setSource(checkedOutTitlesRS.getString("source"));
							curCheckout.setSourceId(checkedOutTitlesRS.getString("sourceId"));
							curCheckout.setTitle(checkedOutTitlesRS.getString("title"));
							checkedOutTitlesAlreadyInReadingHistory.add(curCheckout);
						}
					}

					if (logger.isInfoEnabled()) {
						logger.info("Loading Reading History for patron user Id " + userId);
					}
					processTitlesForUser(userId, barcode, checkedOutTitlesAlreadyInReadingHistory);

					//Any titles that are left in checkedOutTitlesAlreadyInReadingHistory were checked out previously and are no longer checked out.
					Long curTime = new Date().getTime() / 1000;
					for (CheckedOutTitle curTitle : checkedOutTitlesAlreadyInReadingHistory) {
						updateReadingHistoryStmt.setLong(1, curTime);
						updateReadingHistoryStmt.setLong(2, curTitle.getId());
						updateReadingHistoryStmt.executeUpdate();
					}

					//TODO: properly delete entries that are marked as deleted and checked in
				}

//				processLog.incUpdated(); // other calls to this seem to be counting reading history entries created
				processLog.saveToDatabase(pikaConn, logger);
				try {
					// Add a brief pause between users to allow Solr & MySQL a chance to rest during Reading History Update
					Thread.sleep(400);
				} catch (Exception e) {
					logger.warn("Sleep was interrupted while processing reading history for user.");
				}
			}
			processLog.addNote("Completed Reading History Updates");
			String note = initialHistoriesLoaded + " Initial Reading Histories loaded";
			processLog.addNote(note);
			logger.info(note);
			note = initialHistoriesFailedToBeLoaded + " Initial Reading Histories that failed to load";
			processLog.addNote(note);
			logger.info(note);
			note = loadedHistoriesUpdated + " loaded Reading Histories Updated";
			processLog.addNote(note);
			logger.info(note);
			note = loadeHistoriesFailedToUpdate + " loaded Reading Histories that failed to update";
			processLog.addNote(note);
			logger.info(note);
		} catch (SQLException e) {
			logger.error("Unable get a list of users that need to have their reading list updated ", e);
			processLog.incErrors();
			processLog.addNote("Unable get a list of users that need to have their reading list updated " + e.toString());
		}

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private static boolean isNumeric(String str) {
		return str.matches("^\\d+$");
	}

	private boolean loadInitialReadingHistoryForUser(Long userId, String barcode) throws SQLException {
		boolean hadError  = false;
		boolean additionalRoundRequired;
		String  nextRound = "";
		int numInitialReadingHistoryEntries = 0;
		if (barcode != null && !barcode.isEmpty()) {
				try {
					String token = md5(barcode);
					if (logger.isInfoEnabled()) {
						logger.info("Loading initial reading history from ils for user Id " + userId);
					}
					do {
						additionalRoundRequired = false;
						// Call the patron API to get their checked out items
						String loadReadingHistoryUrl = pikaUrl + "/API/UserAPI?method=loadReadingHistoryFromIls&userId=" + userId + "&token=" + token
								+ (isNumeric(nextRound) ? "&nextRound=" + nextRound : "");
						URL patronApiUrl = new URL(loadReadingHistoryUrl);
						// loadReadingHistoryFromIls is intended to be a faster call, or at least contain only enough information to add entries into the database.
						// (The regular getPatronReadingHistory included a lot of information that is not needed to update the database.
						Object patronDataRaw = patronApiUrl.getContent();
						if (patronDataRaw instanceof InputStream) {
							String patronDataJson = "";
							try {
								patronDataJson = Util.convertStreamToString((InputStream) patronDataRaw);
								if (patronDataJson != null && patronDataJson.length() > 0) {
									if (logger.isDebugEnabled()) {
										logger.debug(patronApiUrl.toString());
										logger.debug("Json for patron reading history " + patronDataJson);
									}

									JSONObject patronData = new JSONObject(patronDataJson);
									JSONObject result     = patronData.getJSONObject("result");
									if (result.getBoolean("success") && result.has("readingHistory")) {
										if (result.has("nextRound")) {
											nextRound               = result.getString("nextRound");
											additionalRoundRequired = true;
											if (logger.isInfoEnabled()) {
												logger.info("Another round of calling the User API is required. Next Round is : " + nextRound);
											}
										}
										if (result.get("readingHistory").getClass() == JSONObject.class) {
											JSONObject readingHistoryItems = result.getJSONObject("readingHistory");
											@SuppressWarnings("unchecked")
											Iterator<String> keys = (Iterator<String>) readingHistoryItems.keys();
											while (keys.hasNext()) {
												String     curKey             = keys.next();
												JSONObject readingHistoryItem = readingHistoryItems.getJSONObject(curKey);
												processReadingHistoryTitle(readingHistoryItem, userId);

											}
											numInitialReadingHistoryEntries += readingHistoryItems.length();
										} else if (result.get("readingHistory").getClass() == JSONArray.class) {
											JSONArray readingHistoryItems = result.getJSONArray("readingHistory");
											for (int i = 0; i < readingHistoryItems.length(); i++) {
												processReadingHistoryTitle(readingHistoryItems.getJSONObject(i), userId);
											}
											numInitialReadingHistoryEntries += readingHistoryItems.length();
										} else {
											processLog.incErrors();
											processLog.addNote("Unexpected JSON for patron reading history " + result.get("readingHistory").getClass());
											if (logger.isInfoEnabled()) {
												logger.info("Unexpected JSON for patron reading history " + result.get("readingHistory").getClass());
											}
											hadError = true;
										}
									} else {
										logger.warn("Call to loadReadingHistoryFromIls returned a success code of false for user Id " + userId);
										hadError = true;
									}
								} else {
									logger.error("Empty response loading initial history for user Id " + userId);
									hadError = true;
								}
							} catch (IOException e) {
								logger.error("Error reading input stream for user Id " + userId, e);
								hadError = true;
							} catch (JSONException e) {
								final String message = "Unable to load patron information for user Id " + userId + ", exception loading response ";
								logger.error(message, e);
								logger.error(patronDataRaw); // Display the raw response when we have a JSON exception
								processLog.incErrors();
								processLog.addNote(message + e);
								hadError = true;
							}
						} else {
							logger.error("Unable to load patron information for user id " + userId + ": expected to get back an input stream, received a "
									+ patronDataRaw.getClass().getName());
							processLog.incErrors();
							hadError = true;
						}
					} while (additionalRoundRequired && !hadError);
				} catch (MalformedURLException e) {
					logger.error("Bad url for patron API " + e);
					processLog.incErrors();
					hadError = true;
				} catch (IOException e) {
					logger.error("Unable to retrieve information from patron API for user Id " + userId, e);
					processLog.incErrors();
					hadError = true;
				}
		} else {
			hadError = true;
			logger.error("A pika user's barcode was empty for user Id " + userId);
		}
		if (logger.isInfoEnabled()){
			logger.info("Loaded " + numInitialReadingHistoryEntries + " initial reading history entries for user Id " + userId);
		}
		return !hadError;
	}

	private void processReadingHistoryTitle(JSONObject readingHistoryTitle, Long userId) throws JSONException {
		String source              = "";
		String sourceId            = "";
		String author              = "";
		String format              = "";
		String groupedWorkId       = "";
		String title               = "";
//		String ilsReadingHistoryId = null;
		if (readingHistoryTitle.has("recordId")) {
			sourceId = readingHistoryTitle.getString("recordId");
		}
		if (readingHistoryTitle.has("title")) {
			title = readingHistoryTitle.getString("title");
		}
		if (readingHistoryTitle.has("author")) {
			author = readingHistoryTitle.getString("author");
		}
		if ((sourceId == null || sourceId.length() == 0) && (title == null || title.length() == 0) && (author == null || author.length() == 0)) {
			//Don't try to add records we know nothing about.
			//Note: Source & sourceID won't exist for InterLibrary Loan titles
			return;
		}
		if (title != null && title.contains("WISCAT LOAN")){
			// Ignore Northern Waters WISCAT ILL entries in Sierra Reading History because they contain no usable information
			return;
		}
		if (readingHistoryTitle.has("permanentId")) {
			groupedWorkId = readingHistoryTitle.getString("permanentId");
			if (groupedWorkId == null) {
				groupedWorkId = "";
			}
		}
		if (readingHistoryTitle.has("format")) {
			format = readingHistoryTitle.getString("format");
			if (format.startsWith("[")) {
				//This is an array of formats, just grab one
				format = format.replace("[", "");
				format = format.replace("]", "");
				format = format.replace("\"", "");
				String[] formats = format.split(",");
				format = formats[0];
			}
		}
//		if (readingHistoryTitle.has("ilsReadingHistoryId")) {
//			ilsReadingHistoryId = readingHistoryTitle.getString("ilsReadingHistoryId");
//		}
		if (readingHistoryTitle.has("source")){
			source = readingHistoryTitle.getString("source");
			if (source.isEmpty()){
				//Note that No source is a valid option for Interlibrary loan titles
				source = "";
			}
		}

		//This is a newly checked out title
		try {
			//Update fields in order are: userId, groupedWorkPermanentId, source, sourceId, title, author, format, checkOutDate
			insertReadingHistoryStmt.setLong(1, userId);
			insertReadingHistoryStmt.setString(2, groupedWorkId);
			insertReadingHistoryStmt.setString(3, source);
			insertReadingHistoryStmt.setString(4, sourceId);
			insertReadingHistoryStmt.setString(5, Util.trimTo(150, title));
			insertReadingHistoryStmt.setString(6, Util.trimTo(75, author));
			insertReadingHistoryStmt.setString(7, Util.trimTo(50, format));
			long   checkoutTime = new Date().getTime(); // default check out time is now if we have nothing else
			String checkoutDate = readingHistoryTitle.getString("checkout");
			if (isNumeric(checkoutDate)) { // Timestamp
				checkoutTime = Long.parseLong(checkoutDate);
			} else {
				try {
					SimpleDateFormat checkoutDateFormat = new SimpleDateFormat("MM-dd-yyyy");
					checkoutTime = checkoutDateFormat.parse(checkoutDate).getTime() / 1000;
				} catch (ParseException e) {
					logger.error("Error loading checkout date " + checkoutDate + " was not the expected format", e);
				}
			}

			insertReadingHistoryStmt.setLong(8, checkoutTime);
//			if (ilsReadingHistoryId == null || ilsReadingHistoryId.isEmpty()){
//				insertReadingHistoryStmt.setNull(9, Types.VARCHAR);
//			} else {
//				insertReadingHistoryStmt.setString(9, ilsReadingHistoryId);
//			}
			insertReadingHistoryStmt.executeUpdate();
			processLog.incUpdated();
		} catch (SQLException e) {
			logger.error("Error adding title for user " + userId + " " + title, e);
			processLog.incErrors();
		}
	}

	private void processTitlesForUser(Long userId, String barcode, ArrayList<CheckedOutTitle> checkedOutTitlesAlreadyInReadingHistory) throws SQLException {
		boolean attemptConnectingAgain = false;
		int attempts = 0;
		String token = md5(barcode);
		do {
			try {
				// Call the patron API to get their checked out items
				URL    patronApiUrl  = new URL(pikaUrl + "/API/UserAPI?method=getPatronCheckedOutItems&userId=" + userId + "&token=" + token);
				Object patronDataRaw = patronApiUrl.getContent();
				if (patronDataRaw instanceof InputStream) {
					String patronDataJson = Util.convertStreamToString((InputStream) patronDataRaw);
					if (logger.isDebugEnabled()) {
						logger.debug(patronApiUrl.toString());
						logger.debug("Json for patron checked out items " + patronDataJson);
					}
					try {
						JSONObject patronData = new JSONObject(patronDataJson);
						JSONObject result     = patronData.getJSONObject("result");
						if (result.getBoolean("success") && result.has("checkedOutItems")) {
							if (result.get("checkedOutItems").getClass() == JSONObject.class) {
								JSONObject checkedOutItems = result.getJSONObject("checkedOutItems");
								@SuppressWarnings("unchecked")
								Iterator<String> keys = (Iterator<String>) checkedOutItems.keys();
								while (keys.hasNext()) {
									String     curKey         = keys.next();
									JSONObject checkedOutItem = checkedOutItems.getJSONObject(curKey);
									processCheckedOutTitle(checkedOutItem, userId, checkedOutTitlesAlreadyInReadingHistory);
								}
								loadedHistoriesUpdated++;
							} else if (result.get("checkedOutItems").getClass() == JSONArray.class) {
								JSONArray checkedOutItems = result.getJSONArray("checkedOutItems");
								for (int i = 0; i < checkedOutItems.length(); i++) {
									processCheckedOutTitle(checkedOutItems.getJSONObject(i), userId, checkedOutTitlesAlreadyInReadingHistory);
								}
								loadedHistoriesUpdated++;
							} else {
								processLog.incErrors();
								processLog.addNote("Unexpected JSON for patron checked out items received " + result.get("checkedOutItems").getClass());
								loadeHistoriesFailedToUpdate++;
							}
						} else if (logger.isInfoEnabled()) {
							logger.info("Call to getPatronCheckedOutItems returned a success code of false for user Id " + userId);
							loadeHistoriesFailedToUpdate++;
						}
					} catch (JSONException e) {
						final String message = "Unable to load patron information for user Id " + userId + ", exception loading response ";
						logger.error(message, e);
						logger.error(patronDataJson);
						processLog.incErrors();
						processLog.addNote(message + e);
						loadeHistoriesFailedToUpdate++;
					}
				} else {
					logger.error("Unable to load patron information for user Id " + userId + ": expected to get back an input stream, received a "
									+ patronDataRaw.getClass().getName());
					processLog.incErrors();
					loadeHistoriesFailedToUpdate++;
				}
			} catch (ConnectException e) {
				// If connection is refused, pause and try again up to 3 times.
				attempts++;
				if (attempts >= 3){
					// Attempted 3 times, log error and move on to next user
					logger.error("Refused connection to User API after " + attempts + " attempts for user Id " + userId);
					attemptConnectingAgain = false;
					loadeHistoriesFailedToUpdate++;
				} else {
					attemptConnectingAgain = true;
					if (logger.isDebugEnabled()){
						logger.debug("Connection exception during User API call. Will attempt to connect again.");
						try {
							Thread.sleep(500);
						} catch (InterruptedException interruptedException) {
							//Ignore exception
						}
					}
				}
			} catch (MalformedURLException e) {
				logger.error("Bad url for patron API " + e);
				processLog.incErrors();
			} catch (IOException e) {
				logger.error("Unable to retrieve information from patron API for user Id " + userId, e);
				processLog.incErrors();
			}
		} while (attemptConnectingAgain);
	}

	private void processCheckedOutTitle(JSONObject checkedOutItem, long userId, ArrayList<CheckedOutTitle> checkedOutTitlesAlreadyInReadingHistory) throws JSONException, SQLException, IOException {
		String source   = "";
		String sourceId = "?"; // The record/Title Id
		try {
			// System.out.println(checkedOutItem.toString());
			source   = checkedOutItem.getString("checkoutSource").trim();

			switch (source) {
				case "OverDrive":
					sourceId = checkedOutItem.getString("overDriveId");
					break;
				case "ILS":
					source = "ils"; // Any driver that still capitalizes the traditional default catalog source should be made lowercase
				case "ils":
					sourceId = checkedOutItem.getString("recordId");
					//Specifically need the record id (sometime's the 'id' provided is the checkout's ID
					break;
				case "Hoopla":
					sourceId = checkedOutItem.getString("hooplaId");
					break;
				case "eContent":
					source = checkedOutItem.getString("recordType");
					sourceId = checkedOutItem.getString("id");
					break;
				default:
					logger.error("Unknown source updating reading history: '" + source + "'");
			}

			//Check to see if this is an existing checkout.  If it is, skip inserting
			if (checkedOutTitlesAlreadyInReadingHistory != null) {
				final boolean isWiscatIllCheckout = checkedOutItem.has("_callNumber") && checkedOutItem.getString("_callNumber").contains("WISCAT:");
				// Northern Waters ILL checkouts live on the same bib per library so will have duplicate source & sourceId
				// We can detect these ILL checkouts by looking at the special checkout field _callNumber

				for (CheckedOutTitle curTitle : checkedOutTitlesAlreadyInReadingHistory) {
					boolean sourceMatches   = Util.compareStrings(curTitle.getSource(), source);
					boolean sourceIdMatches = Util.compareStrings(curTitle.getSourceId(), sourceId);
					boolean titleMatches    = false;
					if (checkedOutItem.has("title")) {
						titleMatches = Util.compareStrings(curTitle.getTitle(), checkedOutItem.getString("title"));
					}
					if (isWiscatIllCheckout){
						if (sourceMatches && sourceIdMatches && titleMatches) {
							checkedOutTitlesAlreadyInReadingHistory.remove(curTitle);
							return;
						}
					} else {
						if ((sourceMatches && sourceIdMatches) || titleMatches) {
							checkedOutTitlesAlreadyInReadingHistory.remove(curTitle);
							return;
						}
					}
				}

			}

			//This is a newly checked out title
			insertReadingHistoryStmt.setLong(1, userId);
			String groupedWorkId = checkedOutItem.has("groupedWorkId") ? checkedOutItem.getString("groupedWorkId") : "";
			if (groupedWorkId == null) {
				groupedWorkId = "";
			}
			insertReadingHistoryStmt.setString(2, groupedWorkId);
			insertReadingHistoryStmt.setString(3, source);
			insertReadingHistoryStmt.setString(4, sourceId);
			insertReadingHistoryStmt.setString(5, checkedOutItem.has("title")  ? Util.trimTo(150, checkedOutItem.getString("title")) : "");
			insertReadingHistoryStmt.setString(6, checkedOutItem.has("author") ? Util.trimTo(75, checkedOutItem.getString("author")) : "");
			insertReadingHistoryStmt.setString(7, checkedOutItem.has("format") ? Util.trimTo(50, checkedOutItem.getString("format")) : "");
			long checkoutTime = new Date().getTime() / 1000;
			insertReadingHistoryStmt.setLong(8, checkoutTime);
			insertReadingHistoryStmt.executeUpdate();
			processLog.incUpdated();
		} catch (Exception e) {
			logger.error("Error adding title for user " + userId + " for id " + source +":"+ sourceId + ", " + ( checkedOutItem.has("title") ? checkedOutItem.getString("title") : checkedOutItem.toString()), e);
			processLog.incErrors();
		}
	}

	private static final String VALUES = "!#$&'()*+,/:;=?@[] \"%-.<>\\^_`{|}~";

	private static String encode(String input) {
		if (input == null || input.isEmpty()) {
			return input;
		}
		StringBuilder result = new StringBuilder(input);
		for (int i = input.length() - 1; i >= 0; i--) {
			if (VALUES.indexOf(input.charAt(i)) != -1) {
				result.replace(i, i + 1, "%" + Integer.toHexString(input.charAt(i)).toUpperCase());
			}
		}
		return result.toString();
	}

	/**
	 * Build token for User API Calls
	 * @param string barcode to hash with the userApiToken
	 * @return Hash to use as url token for User API calls that support tokens
	 */
	private String md5(String string){
		StringBuilder md5 = new StringBuilder();
		try {
			string = string + userApiToken;
			MessageDigest messageDigest = MessageDigest.getInstance("MD5");
			messageDigest.reset();
			messageDigest.update(string.getBytes(StandardCharsets.UTF_8));
			md5 = new StringBuilder(new BigInteger(1, messageDigest.digest()).toString(16));
			while (md5.length() < 32) {
				md5.insert(0, "0");
			}
		} catch (NoSuchAlgorithmException e) {
			logger.error("Error hashing string", e);
		}
		return md5.toString();
	}

}
