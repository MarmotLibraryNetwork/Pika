package org.pika;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;


import java.io.IOException;
import java.io.InputStream;
import java.net.MalformedURLException;
import java.net.URL;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
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

	public void doCronProcess(String serverName, Ini configIni, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Update Reading History");
		processLog.saveToDatabase(pikaConn, logger);
		
		this.logger = logger;
		logger.info("Updating Reading History");
		processLog.addNote("Updating Reading History");

		pikaUrl = configIni.get("Site", "url");
		if (pikaUrl == null || pikaUrl.length() == 0) {
			logger.error("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.incErrors();
			processLog.addNote("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			return;
		}

		// Connect to the Pika MySQL database
		try {
			// Get a list of all patrons that have reading history turned on.
			PreparedStatement getUsersStmt                            = pikaConn.prepareStatement("SELECT id, cat_username, cat_password, initialReadingHistoryLoaded FROM user WHERE trackReadingHistory=1 ORDER BY initialReadingHistoryLoaded", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement updateInitialReadingHistoryLoaded       = pikaConn.prepareStatement("UPDATE user SET initialReadingHistoryLoaded = 1 WHERE id = ?");
			PreparedStatement getCheckedOutTitlesForUser              = pikaConn.prepareStatement("SELECT id, groupedWorkPermanentId, source, sourceId, title FROM user_reading_history_work WHERE userId=? and checkInDate is null", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement updateReadingHistoryStmt                = pikaConn.prepareStatement("UPDATE user_reading_history_work SET checkInDate=? WHERE id = ?");
//			PreparedStatement lookForPreviousEntriesBeforeInitialLoad = pikaConn.prepareStatement("SELECT COUNT(id) FROM user_reading_history_work WHERE userId = ?");
//			PreparedStatement deletePreviousEntriesBeforeInitialLoad  = pikaConn.prepareStatement("DELETE FROM user_reading_history_work WHERE userId = ?");
			insertReadingHistoryStmt = pikaConn.prepareStatement("INSERT INTO user_reading_history_work (userId, groupedWorkPermanentId, source, sourceId, title, author, format, checkOutDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			
			ResultSet userResults = getUsersStmt.executeQuery(); // Fetches patrons that haven't had the initial load done first
			while (userResults.next()) {
				// For each patron
				Long    userId                            = userResults.getLong("id");
				String  cat_username                      = userResults.getString("cat_username");
				String  cat_password                      = userResults.getString("cat_password");
				boolean initialReadingHistoryLoaded       = userResults.getBoolean("initialReadingHistoryLoaded");
				boolean errorLoadingInitialReadingHistory = false;
				if (!initialReadingHistoryLoaded){
					//Get the initial reading history from the ILS
					try {
						if (loadInitialReadingHistoryForUser(userId, cat_username, cat_password)) {
							updateInitialReadingHistoryLoaded.setLong(1, userId);
							updateInitialReadingHistoryLoaded.executeUpdate();
						}else{
							errorLoadingInitialReadingHistory = true;
							logger.warn("Failed loading Initial Reading History for user " + cat_username);
						}
					}catch (SQLException e){
						logger.error("Error loading initial reading history", e);
						errorLoadingInitialReadingHistory = true;
					}
				}

				if (!errorLoadingInitialReadingHistory) {
					//Get a list of titles that are currently checked out
					getCheckedOutTitlesForUser.setLong(1, userId);
					ResultSet                  checkedOutTitlesRS = getCheckedOutTitlesForUser.executeQuery();
					ArrayList<CheckedOutTitle> checkedOutTitles   = new ArrayList<>();
					while (checkedOutTitlesRS.next()) {
						CheckedOutTitle curCheckout = new CheckedOutTitle();
						curCheckout.setId(checkedOutTitlesRS.getLong("id"));
						curCheckout.setGroupedWorkPermanentId(checkedOutTitlesRS.getString("groupedWorkPermanentId"));
						curCheckout.setSource(checkedOutTitlesRS.getString("source"));
						curCheckout.setSourceId(checkedOutTitlesRS.getString("sourceId"));
						curCheckout.setTitle(checkedOutTitlesRS.getString("title"));
						checkedOutTitles.add(curCheckout);
					}

					logger.info("Loading Reading History for patron " + cat_username);
					processTitlesForUser(userId, cat_username, cat_password, checkedOutTitles);

					//Any titles that are left in checkedOutTitles were checked out previously and are no longer checked out.
					Long curTime = new Date().getTime() / 1000;
					for (CheckedOutTitle curTitle : checkedOutTitles) {
						updateReadingHistoryStmt.setLong(1, curTime);
						updateReadingHistoryStmt.setLong(2, curTitle.getId());
						updateReadingHistoryStmt.executeUpdate();
					}
				}

				processLog.incUpdated();
				processLog.saveToDatabase(pikaConn, logger);
				try {
					Thread.sleep(1000);
				}catch (Exception e){
					logger.warn("Sleep was interrupted while processing reading history for user.");
				}
			}
			userResults.close();
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

	private boolean loadInitialReadingHistoryForUser(Long userId, String cat_username, String cat_password) throws SQLException {
		boolean hadError = false;
		boolean additionalRoundRequired;
		String nextRound = "";
		if (cat_username != null && !cat_username.isEmpty() ) {
			if (cat_password != null && !cat_password.isEmpty()) {
				try {
					logger.info("Loading initial reading history from ils for " + cat_username);
					do {
						additionalRoundRequired = false;
						// Call the patron API to get their checked out items
		//			URL patronApiUrl = new URL(pikaUrl + "/API/UserAPI?method=getPatronReadingHistory&username=" + encode(cat_username) + "&password=" + encode(cat_password));

						String loadReadingHistoryUrl = pikaUrl + "/API/UserAPI?method=loadReadingHistoryFromIls&username=" + encode(cat_username) + "&password=" + encode(cat_password)
								+ (isNumeric(nextRound) ? "&nextRound=" + nextRound : "");
						URL    patronApiUrl          = new URL(loadReadingHistoryUrl);
						// loadReadingHistoryFromIls is intended to be a faster call, or at least contain only enough information to add entries into the database.
						// (The regular getPatronReadingHistory included a lot of information that is not needed to update the database.
						Object patronDataRaw = patronApiUrl.getContent();
						if (patronDataRaw instanceof InputStream) {
							String patronDataJson = "";
							try {
								patronDataJson = Util.convertStreamToString((InputStream) patronDataRaw);
								if (patronDataJson != null && patronDataJson.length() > 0) {
									logger.debug(patronApiUrl.toString());
									logger.debug("Json for patron reading history " + patronDataJson);

									JSONObject patronData = new JSONObject(patronDataJson);
									JSONObject result     = patronData.getJSONObject("result");
									if (result.getBoolean("success") && result.has("readingHistory")) {
										if (result.has("nextRound")){
											nextRound = result.getString("nextRound");
											additionalRoundRequired = true;
											logger.info("Another round of calling the User API is required. Next Round is : " + nextRound);
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
										} else if (result.get("readingHistory").getClass() == JSONArray.class) {
											JSONArray readingHistoryItems = result.getJSONArray("readingHistory");
											for (int i = 0; i < readingHistoryItems.length(); i++) {
												processReadingHistoryTitle(readingHistoryItems.getJSONObject(i), userId);
											}
										} else {
											processLog.incErrors();
											processLog.addNote("Unexpected JSON for patron reading history " + result.get("readingHistory").getClass());
											logger.info("Unexpected JSON for patron reading history " + result.get("readingHistory").getClass());
											hadError = true;
										}
									} else {
										logger.warn("Call to loadReadingHistoryFromIls returned a success code of false for " + cat_username);
										hadError = true;
									}
								} else {
									logger.error("Empty response loading initial history for " + cat_username);
									hadError = true;
								}
							} catch (IOException e) {
								logger.error("Error reading input stream for " + cat_username, e);
								hadError = true;
							} catch (JSONException e) {
								logger.error("Unable to load patron information for " + cat_username + ", exception loading response ", e);
								logger.error(patronDataJson);
								processLog.incErrors();
								processLog.addNote("Unable to load patron information from for " + cat_username + " exception loading response " + e.toString());
								hadError = true;
							}
						} else {
							logger.error("Unable to load patron information from for " + cat_username + ": expected to get back an input stream, received a "
									+ patronDataRaw.getClass().getName());
							processLog.incErrors();
							hadError = true;
						}
					} while (additionalRoundRequired && !hadError);
				} catch (MalformedURLException e) {
					logger.error("Bad url for patron API " + e.toString());
					processLog.incErrors();
					hadError = true;
				} catch (IOException e) {
					logger.error("Unable to retrieve information from patron API for " + cat_username, e);
					processLog.incErrors();
					hadError = true;
				}
			} else {
				hadError = true;
				logger.error("cat_password was empty for patron " + cat_username);
			}
		} else {
			hadError = true;
			logger.error("A pika user's cat_username was empty.");
		}
		return !hadError;
	}

	private boolean processReadingHistoryTitle(JSONObject readingHistoryTitle, Long userId) throws JSONException {
		String source        = "ILS";
		String sourceId      = "";
		String author        = "";
		String format        = "";
		String groupedWorkId = "";
		String title         = "";
		if (readingHistoryTitle.has("recordId")) {
			sourceId = readingHistoryTitle.getString("recordId");
		}
		if (readingHistoryTitle.has("title")) {
			title = readingHistoryTitle.getString("title");
		}
		if (readingHistoryTitle.has("author")) {
			author = readingHistoryTitle.getString("author");
		}
		if ((sourceId == null || sourceId.length() == 0) && (title == null || title.length() == 0) && (author == null || author.length() == 0)){
			//Don't try to add records we know nothing about.
			return false;
		}
		if (readingHistoryTitle.has("permanentId")) {
			groupedWorkId = readingHistoryTitle.getString("permanentId");
			if (groupedWorkId == null) {
				groupedWorkId = "";
			}
		}
		if (readingHistoryTitle.has("format")) {
			format = readingHistoryTitle.getString("format");
			if (format.startsWith("[")){
				//This is an array of formats, just grab one
				format = format.replace("[", "");
				format = format.replace("]", "");
				format = format.replace("\"", "");
				String[] formats = format.split(",");
				format = formats[0];
			}
		}
		SimpleDateFormat checkoutDateFormat = new SimpleDateFormat("MM-dd-yyyy");

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
			String checkoutDate = readingHistoryTitle.getString("checkout");
			long checkoutTime = new Date().getTime();
			if (isNumeric(checkoutDate)){ // Timestamp
				checkoutTime = Long.parseLong(checkoutDate);
			}else{
				try {
					checkoutTime = checkoutDateFormat.parse(checkoutDate).getTime() / 1000;
				} catch (ParseException e) {
					logger.error("Error loading checkout date " + checkoutDate + " was not the expected format", e);
				}
			}

			insertReadingHistoryStmt.setLong(8, checkoutTime);
			insertReadingHistoryStmt.executeUpdate();
			processLog.incUpdated();
			return true;
		}catch (SQLException e){
			logger.error("Error adding title for user " + userId + " " + title, e);
			processLog.incErrors();
			return false;
		}
	}

	private void processTitlesForUser(Long userId, String cat_username, String cat_password, ArrayList<CheckedOutTitle> checkedOutTitles) throws SQLException{
		try {
			// Call the patron API to get their checked out items
			URL    patronApiUrl  = new URL(pikaUrl + "/API/UserAPI?method=getPatronCheckedOutItems&username=" + encode(cat_username) + "&password=" + encode(cat_password));
			Object patronDataRaw = patronApiUrl.getContent();
			if (patronDataRaw instanceof InputStream) {
				String patronDataJson = Util.convertStreamToString((InputStream) patronDataRaw);
				logger.debug(patronApiUrl.toString());
				logger.debug("Json for patron checked out items " + patronDataJson);
				try {
					JSONObject patronData = new JSONObject(patronDataJson);
					JSONObject result     = patronData.getJSONObject("result");
					if (result.getBoolean("success") && result.has("checkedOutItems")) {
						if (result.get("checkedOutItems").getClass() == JSONObject.class){
							JSONObject checkedOutItems = result.getJSONObject("checkedOutItems");
							@SuppressWarnings("unchecked")
							Iterator<String> keys = (Iterator<String>) checkedOutItems.keys();
							while (keys.hasNext()) {
								String curKey = keys.next();
								JSONObject checkedOutItem = checkedOutItems.getJSONObject(curKey);
								processCheckedOutTitle(checkedOutItem, userId, checkedOutTitles);
								
							}
						}else if (result.get("checkedOutItems").getClass() == JSONArray.class){
							JSONArray checkedOutItems = result.getJSONArray("checkedOutItems");
							for (int i = 0; i < checkedOutItems.length(); i++){
								processCheckedOutTitle(checkedOutItems.getJSONObject(i), userId, checkedOutTitles);
							}
						}else{
							processLog.incErrors();
							processLog.addNote("Unexpected JSON for patron checked out items received " + result.get("checkedOutItems").getClass());
						}
					} else {
						logger.info("Call to getPatronCheckedOutItems returned a success code of false for " + cat_username);
					}
				} catch (JSONException e) {
					logger.error("Unable to load patron information from for " + cat_username + " exception loading response ", e);
					logger.error(patronDataJson);
					processLog.incErrors();
					processLog.addNote("Unable to load patron information from for " + cat_username + " exception loading response " + e.toString());
				}
			} else {
				logger.error("Unable to load patron information from for " + cat_username + ": expected to get back an input stream, received a "
						+ patronDataRaw.getClass().getName());
				processLog.incErrors();
			}
		} catch (MalformedURLException e) {
			logger.error("Bad url for patron API " + e.toString());
			processLog.incErrors();
		} catch (IOException e) {
			logger.error("Unable to retrieve information from patron API for " + cat_username, e);
			processLog.incErrors();
		}
	}
	
	private boolean processCheckedOutTitle(JSONObject checkedOutItem, long userId, ArrayList<CheckedOutTitle> checkedOutTitles) throws JSONException, SQLException, IOException{
		try {
			// System.out.println(checkedOutItem.toString());
			String source = checkedOutItem.getString("checkoutSource");
			String sourceId = "?";
			switch (source) {
				case "OverDrive":
					sourceId = checkedOutItem.getString("overDriveId");
					break;
				case "ILS":
					sourceId = checkedOutItem.getString("id");
					break;
				case "Hoopla":
					sourceId = checkedOutItem.getString("hooplaId");
					break;
				case "eContent":
					source = checkedOutItem.getString("recordType");
					sourceId = checkedOutItem.getString("id");
					break;
				default:
					logger.error("Unknown source updating reading history: " + source);
			}

			//Check to see if this is an existing checkout.  If it is, skip inserting
			if (checkedOutTitles != null) {
				for (CheckedOutTitle curTitle : checkedOutTitles) {
					boolean sourceMatches   = Util.compareStrings(curTitle.getSource(), source);
					boolean sourceIdMatches = Util.compareStrings(curTitle.getSourceId(), sourceId);
					boolean titleMatches    = Util.compareStrings(curTitle.getTitle(), checkedOutItem.getString("title"));
					if (
							(sourceMatches && sourceIdMatches) ||
							titleMatches
						 ) {
						checkedOutTitles.remove(curTitle);
						return true;
					}
				}
			}

		//This is a newly checked out title
			insertReadingHistoryStmt.setLong(1, userId);
			String groupedWorkId = checkedOutItem.has("groupedWorkId") ? checkedOutItem.getString("groupedWorkId") : "";
			if (groupedWorkId == null){
				groupedWorkId = "";
			}
			insertReadingHistoryStmt.setString(2, groupedWorkId);
			insertReadingHistoryStmt.setString(3, source);
			insertReadingHistoryStmt.setString(4, sourceId);
			insertReadingHistoryStmt.setString(5, Util.trimTo(150, checkedOutItem.getString("title")));
			insertReadingHistoryStmt.setString(6, checkedOutItem.has("author") ? Util.trimTo(75, checkedOutItem.getString("author")) : "");
			insertReadingHistoryStmt.setString(7, checkedOutItem.has("format") ? Util.trimTo(50, checkedOutItem.getString("format")) : "");
			long checkoutTime = new Date().getTime() / 1000;
			insertReadingHistoryStmt.setLong(8, checkoutTime);
			insertReadingHistoryStmt.executeUpdate();
			processLog.incUpdated();
			return true;
		}catch (Exception e){
			if (checkedOutItem.has("title")) {
				logger.error("Error adding title for user " + userId + " " + checkedOutItem.getString("title"), e);
			}else{
				logger.error("Error adding title for user " + userId + " " + checkedOutItem.toString(), e);
			}
			processLog.incErrors();
			return false;
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
				result.replace(i, i + 1,
						"%" + Integer.toHexString(input.charAt(i)).toUpperCase());
			}
		}
		return result.toString();
	}

}
