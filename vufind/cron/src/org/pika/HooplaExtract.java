///*
// * Copyright (C) 2023  Marmot Library Network
// * This program is free software: you can redistribute it and/or modify
// * it under the terms of the GNU General Public License as published by
// * the Free Software Foundation, either version 3 of the License, or
// * (at your option) any later version.
// * This program is distributed in the hope that it will be useful,
// * but WITHOUT ANY WARRANTY; without even the implied warranty of
// * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// * GNU General Public License for more details.
// * You should have received a copy of the GNU General Public License
// * along with this program.  If not, see <https://www.gnu.org/licenses/>.
// */
//
//package org.pika;
//
//import org.apache.logging.log4j.Logger;
//import org.ini4j.Profile;
//import org.json.JSONArray;
//import org.json.JSONException;
//import org.json.JSONObject;
//
//import javax.net.ssl.HostnameVerifier;
//import javax.net.ssl.HttpsURLConnection;
//import javax.net.ssl.SSLSession;
//import java.io.BufferedReader;
//import java.io.IOException;
//import java.io.InputStreamReader;
//import java.net.HttpURLConnection;
//import java.net.MalformedURLException;
//import java.net.SocketTimeoutException;
//import java.net.URL;
//import java.sql.Connection;
//import java.sql.PreparedStatement;
//import java.sql.ResultSet;
//import java.sql.SQLException;
//import java.util.Date;
//
//import org.apache.commons.codec.binary.Base64;
//
///**
// * Pika
// *
// * @author pbrammeier
// * 		Date:   4/23/2020
// */
//public class HooplaExtract implements IProcessHandler{
//	private static Logger  logger;
//	private CronProcessLogEntry processLog;
////	private static String  serverName;
//	private static String  hooplaAPIBaseURL;
//	private static Long    lastExportTime;
//	private static Long    startTimeStamp;
//	private static boolean updateTitlesInDBHadErrors = false;
//
//	//Reporting information
////	private static long              hooplaExportLogId;
////	private static PreparedStatement addNoteToHooplaExportLogStmt;
//
//	@Override
//	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
//		Date startTime = new Date();
//		logger.info(startTime.toString() + ": Starting Hoopla Export");
//		startTimeStamp = startTime.getTime() / 1000;
//
//		this.logger = logger;
//		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Hoopla Extract");
//
//		String  singleRecordToProcess = null;
//		boolean doFullReload          = false;
//
//		String firstArg = processSettings.get("fullReload");
//		if (firstArg.matches("^(true|1)?$")) {
//			doFullReload = true;
//		} else {
//			firstArg = processSettings.get("singleRecord");
//			if (firstArg != null && !firstArg.isEmpty()){
//				singleRecordToProcess = firstArg.replaceAll("MWT", "");
//			}
//		}
//
//		// Extract a single Record
//		if (singleRecordToProcess != null && !singleRecordToProcess.isEmpty()) {
//			if (exportSingleHooplaRecord(pikaConn, singleRecordToProcess)) {
//				System.out.println("Record " + singleRecordToProcess + " was successfully extracted.");
//			} else {
//				System.out.println("Record " + singleRecordToProcess + " failed to get extracted.");
//			}
//			System.exit(0);
//		}
//
//		//Get the last Extract time
//		PikaSystemVariables systemVariables = new PikaSystemVariables(logger, pikaConn);
//		lastExportTime = systemVariables.getLongValuedVariable("lastHooplaExport");
//
//		//Do the Exporting
//		if (exportHooplaData(pikaConn, lastExportTime, doFullReload)) {
//			// On success, update the last Extract time
//			systemVariables.setVariable("lastHooplaExport", startTimeStamp);
//		}
//
//
//	}
//
//	private boolean exportSingleHooplaRecord(Connection pikaConn, String singleRecordToExport) {
//		try {
//			//Find a library id to get data from
//			String hooplaLibraryId = getHooplaLibraryId(pikaConn);
//			if (hooplaLibraryId == null) {
//				logger.error("No hoopla library id found");
//				return false;
//			}
//			String accessToken = getAccessToken();
//			if (accessToken == null || accessToken.isEmpty()) {
//				logger.error("Failed to get an Access Token for the API.");
//				return false;
//			}
//
//			long hooplaId = Long.parseLong(singleRecordToExport);
//
//			//Formulate the first call depending on if we are doing a full reload or not
//			String          url          = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content?limit=1&startToken=" + (hooplaId - 1);
//			URLPostResponse response     = getURL(url, accessToken);
//			JSONObject      responseJSON = new JSONObject(response.getMessage());
//			if (responseJSON.has("titles")) {
//				JSONArray responseTitles = responseJSON.getJSONArray("titles");
//				if (responseTitles != null && responseTitles.length() > 0) {
//					JSONObject curTitle = responseTitles.getJSONObject(0);
//					long       titleId  = curTitle.getLong("titleId");
//					if (titleId == hooplaId) {
//						updateTitlesInDB(pikaConn, responseTitles);
//						return !updateTitlesInDBHadErrors;
//					} else {
//						logger.error("Returned title " + titleId + "from API was not the title asked for: " + hooplaId);
//					}
//				}
//			}
//			logger.error("API did not find info for the Id: " + hooplaId);
//		} catch (NumberFormatException e) {
//			logger.error("Invalid Hoopla Record Id: " + singleRecordToExport, e);
//		} catch (Exception e) {
//			logger.error("Error exporting hoopla data", e);
//		}
//		return false;
//	}
//
//	/**
//	 * Method that fetches and processes data from the Hoopla API.
//	 *
//	 * @param pikaConn     Connection to the Pika Database.
//	 * @param startTime    The time to limit responses to from the Hoopla API.  Fetch changes since this time.
//	 * @param doFullReload Fetch all the data in the Hoopla API
//	 * @return Return if the updating completed with out errors
//	 */
//	private boolean exportHooplaData(Connection pikaConn, Long startTime, boolean doFullReload) {
//		try {
//			//Find a library id to get data from
//			String hooplaLibraryId = getHooplaLibraryId(pikaConn);
//			if (hooplaLibraryId == null) {
//				logger.error("No hoopla library id found");
//				addNoteToHooplaExportLog("No hoopla library id found");
//				return false;
//			} else {
//				addNoteToHooplaExportLog("Hoopla library id is " + hooplaLibraryId);
//			}
//
//			String accessToken = getAccessToken();
//			if (accessToken == null || accessToken.isEmpty()) {
//				addNoteToHooplaExportLog("Failed to get an Access Token for the API.");
//				return false;
//			}
//
//			if (doFullReload) {
//				addNoteToHooplaExportLog("Doing a full reload of Hoopla data.");
//			}
//
//			//Formulate the first call depending on if we are doing a full reload or not
//			String url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content";
//			if (!doFullReload && startTime != null) {
//				url += "?startTime=" + startTime;
//				addNoteToHooplaExportLog("Fetching updates since " + startTime);
//			}
//
//			// Initial Call
//			int             numProcessed = 0;
//			URLPostResponse response     = getURL(url, accessToken);
//			JSONObject      responseJSON = new JSONObject(response.getMessage());
//			if (responseJSON.has("titles")) {
//				JSONArray responseTitles = responseJSON.getJSONArray("titles");
//				if (responseTitles != null && responseTitles.length() > 0) {
//					numProcessed += updateTitlesInDB(pikaConn, responseTitles);
//				} else {
//					logger.warn("Hoopla Extract call had no titles for updating: " + url);
//					if (startTime != null) {
//						addNoteToHooplaExportLog("Hoopla had no updates since " + startTime);
//					} else if (doFullReload) {
//						addNoteToHooplaExportLog("Hoopla gave no information for a full Reload");
//						logger.error("Hoopla gave no information for a full Reload. " + url);
//					}
//					// If working on a short time frame, it is possible there are no updates. But we expect to do this no more that once a day at this point
//					// so we expect there to be changes.
//					// Having this warning will give us a hint if there is something wrong with the data in the calls
//				}
//
//				// Addition Calls if needed
//				String startToken = null;
//				if (responseJSON.has("nextStartToken")) {
//					startToken = responseJSON.getString("nextStartToken");
//				}
//				while (startToken != null) {
//					url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content?startToken=" + startToken;
//					if (!doFullReload && startTime != null) {
//						url += "&startTime=" + startTime;
//					}
//					response     = getURL(url, accessToken);
//					responseJSON = new JSONObject(response.getMessage());
//					if (responseJSON.has("titles")) {
//						responseTitles = responseJSON.getJSONArray("titles");
//						if (responseTitles != null && responseTitles.length() > 0) {
//							numProcessed += updateTitlesInDB(pikaConn, responseTitles);
//						}
//					}
//					if (responseJSON.has("nextStartToken")) {
//						startToken = responseJSON.getString("nextStartToken");
//					} else {
//						startToken = null;
//					}
//					if (numProcessed % 10000 == 0) {
//						addNoteToHooplaExportLog("Processed " + numProcessed + " records from hoopla");
//					}
//				}
//				addNoteToHooplaExportLog("Processed a total of " + numProcessed + " records from hoopla");
//
//			}
//		} catch (Exception e) {
//			logger.error("Error exporting hoopla data", e);
//			addNoteToHooplaExportLog("Error exporting hoopla data " + e.toString());
//			return false;
//		}
//		// UpdateTitlesInDB can also have errors. If it does it sets updateTitlesInDBHadErrors to true;
//		return !updateTitlesInDBHadErrors;
//	}
//
//	private PreparedStatement updateHooplaTitleInDB              = null;
//	private PreparedStatement markGroupedWorkForBibAsChangedStmt = null;
//
//	private void markGroupedWorkForReindexing(Connection pikaConn, long hooplaTitleId) {
//		try {
//			if (markGroupedWorkForBibAsChangedStmt == null) {
//				markGroupedWorkForBibAsChangedStmt = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'hoopla' and identifier = ?)");
//			}
//			String marcRecordID = "MWT" + hooplaTitleId;
//			markGroupedWorkForBibAsChangedStmt.setLong(1, startTimeStamp);
//			markGroupedWorkForBibAsChangedStmt.setString(2, marcRecordID);
//			markGroupedWorkForBibAsChangedStmt.executeUpdate();
//		} catch (SQLException e) {
//			logger.warn("Failed to mark grouped Work for reindexing ", e);
//		}
//	}
//
//	private int updateTitlesInDB(Connection pikaConn, JSONArray responseTitles) {
//		int numUpdates = 0;
//		try {
//			if (updateHooplaTitleInDB == null) {
//				updateHooplaTitleInDB = pikaConn.prepareStatement("INSERT INTO hoopla_export (hooplaId, active, title, kind, pa, demo, profanity, rating, abridged, children, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY " +
//						"UPDATE active = VALUES(active), title = VALUES(title), kind = VALUES(kind), pa = VALUES(pa), demo = VALUES(demo), profanity = VALUES(profanity), " +
//						"rating = VALUES(rating), abridged = VALUES(abridged), children = VALUES(children), price = VALUES(price)");
//			}
//			for (int i = 0; i < responseTitles.length(); i++) {
//				JSONObject curTitle = responseTitles.getJSONObject(i);
//				long       titleId  = curTitle.getLong("titleId");
//				updateHooplaTitleInDB.setLong(1, titleId);
//				updateHooplaTitleInDB.setBoolean(2, curTitle.getBoolean("active"));
//				updateHooplaTitleInDB.setString(3, curTitle.getString("title"));
//				updateHooplaTitleInDB.setString(4, curTitle.getString("kind"));
//				updateHooplaTitleInDB.setBoolean(5, curTitle.getBoolean("pa"));
//				updateHooplaTitleInDB.setBoolean(6, curTitle.getBoolean("demo"));
//				updateHooplaTitleInDB.setBoolean(7, curTitle.getBoolean("profanity"));
//				updateHooplaTitleInDB.setString(8, curTitle.has("rating") ? curTitle.getString("rating") : "");
//				updateHooplaTitleInDB.setBoolean(9, curTitle.getBoolean("abridged"));
//				updateHooplaTitleInDB.setBoolean(10, curTitle.getBoolean("children"));
//				updateHooplaTitleInDB.setDouble(11, curTitle.getDouble("price"));
//
//				int updated = updateHooplaTitleInDB.executeUpdate();
//				if (updated > 0) {
//					numUpdates++;
//					markGroupedWorkForReindexing(pikaConn, titleId);
//				}
//			}
//
//		} catch (Exception e) {
//			logger.error("Error updating hoopla data in Pika database", e);
//			addNoteToHooplaExportLog("Error updating hoopla data in Pika database " + e.toString());
//			updateTitlesInDBHadErrors = true;
//		}
//		return numUpdates;
//	}
//
//	private String getAccessToken() {
//		String hooplaUsername = PikaConfigIni.getIniValue("Hoopla", "HooplaAPIUser");
//		String hooplaPassword = PikaConfigIni.getIniValue("Hoopla", "HooplaAPIpassword");
//		if (hooplaUsername == null || hooplaPassword == null) {
//			logger.error("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
//			addNoteToHooplaExportLog("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
//			return null;
//		}
//		hooplaAPIBaseURL = PikaConfigIni.getIniValue("Hoopla", "APIBaseURL");
//		if (hooplaAPIBaseURL == null) {
//			hooplaAPIBaseURL = "http://hoopla-erc.hoopladigital.com";
//		}
//		String          getTokenUrl = hooplaAPIBaseURL + "/v2/token";
//		URLPostResponse response    = postToTokenURL(getTokenUrl, hooplaUsername + ":" + hooplaPassword);
//		if (response.isSuccess()) {
//			try {
//				JSONObject responseJSON = new JSONObject(response.getMessage());
//				if (responseJSON.has("access_token")) {
//					return responseJSON.getString("access_token");
//				}
//			} catch (JSONException e) {
//				addNoteToHooplaExportLog("Could not parse JSON for token " + response.getMessage());
//				logger.error("Could not parse JSON for token " + response.getMessage(), e);
//			}
//		} else {
//			logger.error("Failed to get a response while requesting an access token for Hoopla");
//			addNoteToHooplaExportLog("Failed to get a response while requesting an access token for Hoopla");
//		}
//		return null;
//	}
//
//	private URLPostResponse postToTokenURL(String url, String authentication) {
//		URLPostResponse   retVal;
//		HttpURLConnection conn = null;
//		try {
//			URL emptyIndexURL = new URL(url);
//			conn = (HttpURLConnection) emptyIndexURL.openConnection();
//			conn.setConnectTimeout(10000);
//			conn.setReadTimeout(300000);
//			if (authentication != null) {
//				conn.setRequestProperty("Authorization", "Basic " + Base64.encodeBase64String(authentication.getBytes()));
//			}
//			//logger.debug("Posting To URL " + url + (postData != null && postData.length() > 0 ? "?" + postData : ""));
//
//			if (conn instanceof HttpsURLConnection) {
//				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
//				sslConn.setHostnameVerifier(new HostnameVerifier() {
//
//					@Override
//					public boolean verify(String hostname, SSLSession session) {
//						//Do not verify host names
//						return true;
//					}
//				});
//			}
//			conn.setDoInput(true);
//			conn.setRequestMethod("POST");
//
//			StringBuilder response = new StringBuilder();
//			if (conn.getResponseCode() == 200) {
//				// Get the response
//				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
//				String         line;
//				while ((line = rd.readLine()) != null) {
//					response.append(line);
//				}
//
//				rd.close();
//				retVal = new URLPostResponse(true, 200, response.toString());
//			} else {
//				logger.info("Received error " + conn.getResponseCode() + " posting to " + url);
//				// Get any errors
//				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
//				String         line;
//				while ((line = rd.readLine()) != null) {
//					response.append(line);
//				}
//
//				rd.close();
//
//				if (response.length() == 0) {
//					//Try to load the regular body as well
//					// Get the response
//					BufferedReader rd2 = new BufferedReader(new InputStreamReader(conn.getInputStream()));
//					while ((line = rd2.readLine()) != null) {
//						response.append(line);
//					}
//
//					rd.close();
//				}
//				retVal = new URLPostResponse(false, conn.getResponseCode(), response.toString());
//			}
//
//		} catch (SocketTimeoutException e) {
//			logger.error("Timeout connecting to URL (" + url + ")", e);
//			retVal = new URLPostResponse(false, -1, "Timeout connecting to URL (" + url + ")");
//		} catch (MalformedURLException e) {
//			logger.error("URL to post (" + url + ") is malformed", e);
//			retVal = new URLPostResponse(false, -1, "URL to post (" + url + ") is malformed");
//		} catch (IOException e) {
//			logger.error("Error posting to url \r\n" + url, e);
//			retVal = new URLPostResponse(false, -1, "Error posting to url \r\n" + url + "\r\n" + e.toString());
//		} finally {
//			if (conn != null) conn.disconnect();
//		}
//		return retVal;
//	}
//
//	private URLPostResponse getURL(String url, String accessToken) {
//		URLPostResponse   retVal;
//		HttpURLConnection conn = null;
//		try {
//			URL emptyIndexURL = new URL(url);
//			conn = (HttpURLConnection) emptyIndexURL.openConnection();
//			conn.setConnectTimeout(10000);
//			conn.setReadTimeout(300000);
//			conn.setRequestProperty("Authorization", "Bearer " + accessToken);
//			conn.setRequestProperty("Accept", "application/json");
//
//			if (conn instanceof HttpsURLConnection) {
//				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
//				sslConn.setHostnameVerifier((hostname, session) -> {
//					//Do not verify host names
//					return true;
//				});
//			}
//			conn.setDoInput(true);
//			conn.setRequestMethod("GET");
//
//			StringBuilder response = new StringBuilder();
//			if (conn.getResponseCode() == 200) {
//				// Get the response
//				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
//					String line;
//					while ((line = rd.readLine()) != null) {
//						response.append(line);
//					}
//				}
//				retVal = new URLPostResponse(true, 200, response.toString());
//			} else {
//				logger.info("Received error " + conn.getResponseCode() + " posting to " + url);
//				try {
//					// Get any errors
//					String line;
//					if (conn.getErrorStream() != null) {
//						try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
//							while ((line = rd.readLine()) != null) {
//								response.append(line);
//							}
//						}
//					}
//
//					if (response.length() == 0) {
//						//Try to load the regular body as well
//						// Get the response
//						try (BufferedReader rd2 = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
//							while ((line = rd2.readLine()) != null) {
//								response.append(line);
//							}
//						}
//					}
//					retVal = new URLPostResponse(false, conn.getResponseCode(), response.toString());
//				} catch (IOException e) {
//					logger.error("Error reading error or input stream", e);
//					retVal = new URLPostResponse(false, -1, "Error reading error or input stream for \r\n" + url + "\r\n" + e.toString());
//				}
//			}
//
//		} catch (SocketTimeoutException e) {
//			logger.error("Timeout connecting to URL (" + url + ")", e);
//			retVal = new URLPostResponse(false, -1, "Timeout connecting to URL (" + url + ")");
//		} catch (MalformedURLException e) {
//			logger.error("URL to get (" + url + ") is malformed", e);
//			retVal = new URLPostResponse(false, -1, "URL to get (" + url + ") is malformed");
//		} catch (IOException e) {
//			logger.error("Error getting url \r\n" + url, e);
//			retVal = new URLPostResponse(false, -1, "Error getting url \r\n" + url + "\r\n" + e.toString());
//		} finally {
//			if (conn != null) conn.disconnect();
//		}
//		return retVal;
//	}
//
//	private String getHooplaLibraryId(Connection pikaConn) {
//		ResultSet getLibraryIdRS;
//		try (PreparedStatement getLibraryIdStmt = pikaConn.prepareStatement("SELECT hooplaLibraryID FROM library WHERE hooplaLibraryID IS NOT NULL AND hooplaLibraryID != 0 LIMIT 1")) {
//			getLibraryIdRS = getLibraryIdStmt.executeQuery();
//			if (getLibraryIdRS.next()) {
//				return getLibraryIdRS.getString("hooplaLibraryID");
//			}
//		} catch (SQLException e) {
//			logger.error("Failed to retrieve a Hoopla library id", e);
//		}
//		return null;
//	}
//
//	private void addNoteToHooplaExportLog(String note) {
//			processLog.addNote(note);
//			logger.info(note);
//	}
//
//	private static String trimTo(int maxCharacters, String stringToTrim) {
//		if (stringToTrim == null) {
//			return null;
//		}
//		if (stringToTrim.length() > maxCharacters) {
//			stringToTrim = stringToTrim.substring(0, maxCharacters);
//		}
//		return stringToTrim.trim();
//	}
//
//}
