package org.pika;

import org.apache.commons.codec.binary.Base64;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLSession;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.SocketTimeoutException;
import java.net.URL;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;

public class HooplaExportMain {
	private static Logger  logger                    = Logger.getLogger(HooplaExportMain.class);
	private static String  serverName;
	private static String  hooplaAPIBaseURL;
	private static Long    lastExportTime;
	private static Long    startTimeStamp;
	private static boolean updateTitlesInDBHadErrors = false;

	//Reporting information
	private static long              hooplaExportLogId;
	private static PreparedStatement addNoteToHooplaExportLogStmt;

	public static void main(String[] args) {
		if (args.length == 0) {
			System.out.println("Server name must be specified in the command line");
			System.exit(1);
		}
		serverName = args[0];
		String  singleRecordToProcess = null;
		boolean doFullReload          = false;
		if (args.length > 1) {
			String firstArg = args[1].replaceAll("\\s", "");

			//Check to see if we got a full reload parameter
			if (firstArg.matches("^fullReload(=true|1)?$")) {
				doFullReload = true;

			} else if (firstArg.matches("^singleRecord")) {
				if (args.length == 3) {
					singleRecordToProcess = args[2].replaceAll("MWT", "");
				} else {
					//get input from user
					//  open up standard input
					try (BufferedReader br = new BufferedReader(new InputStreamReader(System.in))) {
						System.out.print("Enter the Hoopla record Id to process (MWT is optional, not required) : ");
						singleRecordToProcess = br.readLine().replaceAll("MWT", "").trim();
					} catch (IOException e) {
						System.out.println("Error while reading input from user." + e.toString());
						System.exit(1);
					}
				}
			}
		}

		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.hoopla_export.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			log4jFile = new File("../../sites/default/conf/log4j.hoopla_export.properties");
			if (log4jFile.exists()) {
				PropertyConfigurator.configure(log4jFile.getAbsolutePath());
			} else {
				System.out.println("Could not find log4j configuration " + log4jFile.toString());
			}
		}

		Date startTime = new Date();
		logger.info(startTime.toString() + ": Starting Hoopla Export");
		startTimeStamp = startTime.getTime() / 1000;

		// Read the base INI file to get information about the server (current directory/cron/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Connect to the pika database
		Connection pikaConn = null;
		try {
			String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			if (databaseConnectionInfo != null) {
				pikaConn = DriverManager.getConnection(databaseConnectionInfo);
			} else {
				logger.error("No Pika database connection info");
				System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
			}
		} catch (Exception e) {
			logger.error("Error connecting to Pika database " + e.toString());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}

		// Extract a single Record
		if (singleRecordToProcess != null && !singleRecordToProcess.isEmpty()) {
			if (exportSingleHooplaRecord(pikaConn, singleRecordToProcess)) {
				System.out.println("Record " + singleRecordToProcess + " was successfully extracted.");
			} else {
				System.out.println("Record " + singleRecordToProcess + " failed to get extracted.");
			}
			System.exit(0);
		}

		//Start a hoopla export log entry
		try {
			logger.info("Creating log entry for index");
			ResultSet generatedKeys;
			try (PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO hoopla_export_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS)) {
				createLogEntryStatement.setLong(1, startTimeStamp);
				createLogEntryStatement.setLong(2, startTimeStamp);
				createLogEntryStatement.setString(3, "Initialization complete");
				createLogEntryStatement.executeUpdate();
				generatedKeys = createLogEntryStatement.getGeneratedKeys();
				if (generatedKeys.next()) {
					hooplaExportLogId = generatedKeys.getLong(1);
				}
			}

			addNoteToHooplaExportLogStmt = pikaConn.prepareStatement("UPDATE hoopla_export_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for hoopla extract process", e);
			System.exit(0);
		}

		//Get the last Extract time
		PikaSystemVariables systemVariables = new PikaSystemVariables(logger, pikaConn);
		lastExportTime = systemVariables.getLongValuedVariable("lastHooplaExport");

		//Do the Exporting
		if (exportHooplaData(pikaConn, lastExportTime, doFullReload)) {
			// On success, update the last Extract time
			systemVariables.setVariable("lastHooplaExport", startTimeStamp);
		}

		addNoteToHooplaExportLog("Finished exporting hoopla data " + new Date().toString());
		logger.info("Finished exporting hoopla data " + new Date().toString());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - startTime.getTime();
		logger.info("Elapsed Minutes " + (elapsedTime / 60000));

		try (PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE hoopla_export_log SET endTime = ? WHERE id = ?")) {
			finishedStatement.setLong(1, endTime / 1000);
			finishedStatement.setLong(2, hooplaExportLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update hoopla export log with completion time.", e);
		}

		try {
			pikaConn.close();
		} catch (Exception e) {
			logger.error("Error closing database ", e);
			System.exit(1);
		}
	}

	private static boolean exportSingleHooplaRecord(Connection pikaConn, String singleRecordToExport) {
		try {
			//Find a library id to get data from
			String hooplaLibraryId = getHooplaLibraryId(pikaConn);
			if (hooplaLibraryId == null) {
				logger.error("No hoopla library id found");
				return false;
			}
			String accessToken = getAccessToken();
			if (accessToken == null || accessToken.isEmpty()) {
				logger.error("Failed to get an Access Token for the API.");
				return false;
			}

			long hooplaId = Long.parseLong(singleRecordToExport);

			//Formulate the first call depending on if we are doing a full reload or not
			String          url          = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content?limit=1&startToken=" + (hooplaId - 1);
			URLPostResponse response     = getURL(url, accessToken);
			JSONObject      responseJSON = new JSONObject(response.getMessage());
			if (responseJSON.has("titles")) {
				JSONArray responseTitles = responseJSON.getJSONArray("titles");
				if (responseTitles != null && responseTitles.length() > 0) {
					JSONObject curTitle = responseTitles.getJSONObject(0);
					long       titleId  = curTitle.getLong("titleId");
					if (titleId == hooplaId) {
						updateTitlesInDB(pikaConn, responseTitles);
						return !updateTitlesInDBHadErrors;
					} else {
						logger.error("Returned title " + titleId + "from API was not the title asked for: " + hooplaId);
					}
				}
			}
			logger.error("API did not find info for the Id: " + hooplaId);
		} catch (NumberFormatException e) {
			logger.error("Invalid Hoopla Record Id: " + singleRecordToExport, e);
		} catch (Exception e) {
			logger.error("Error exporting hoopla data", e);
		}
		return false;
	}

	/**
	 * Method that fetches and processes data from the Hoopla API.
	 *
	 * @param pikaConn     Connection to the Pika Database.
	 * @param startTime    The time to limit responses to from the Hoopla API.  Fetch changes since this time.
	 * @param doFullReload Fetch all the data in the Hoopla API
	 * @return Return if the updating completed with out errors
	 */
	private static boolean exportHooplaData(Connection pikaConn, Long startTime, boolean doFullReload) {
		try {
			//Find a library id to get data from
			String hooplaLibraryId = getHooplaLibraryId(pikaConn);
			if (hooplaLibraryId == null) {
				logger.error("No hoopla library id found");
				addNoteToHooplaExportLog("No hoopla library id found");
				return false;
			} else {
				addNoteToHooplaExportLog("Hoopla library id is " + hooplaLibraryId);
			}

			String accessToken = getAccessToken();
			if (accessToken == null || accessToken.isEmpty()) {
				addNoteToHooplaExportLog("Failed to get an Access Token for the API.");
				return false;
			}

			if (doFullReload) {
				addNoteToHooplaExportLog("Doing a full reload of Hoopla data.");
			}

			//Formulate the first call depending on if we are doing a full reload or not
			String url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content";
			if (!doFullReload && startTime != null) {
				url += "?startTime=" + startTime;
				addNoteToHooplaExportLog("Fetching updates since " + startTime);
			}

			// Initial Call
			int             numProcessed = 0;
			URLPostResponse response     = getURL(url, accessToken);
			JSONObject      responseJSON = new JSONObject(response.getMessage());
			if (responseJSON.has("titles")) {
				JSONArray responseTitles = responseJSON.getJSONArray("titles");
				if (responseTitles != null && responseTitles.length() > 0) {
					numProcessed += updateTitlesInDB(pikaConn, responseTitles);
				} else {
					logger.warn("Hoopla Extract call had no titles for updating: " + url);
					if (startTime != null) {
						addNoteToHooplaExportLog("Hoopla had no updates since " + startTime);
					} else if (doFullReload) {
						addNoteToHooplaExportLog("Hoopla gave no information for a full Reload");
						logger.error("Hoopla gave no information for a full Reload. " + url);
					}
					// If working on a short time frame, it is possible there are no updates. But we expect to do this no more that once a day at this point
					// so we expect there to be changes.
					// Having this warning will give us a hint if there is something wrong with the data in the calls
				}

				// Addition Calls if needed
				String startToken = null;
				if (responseJSON.has("nextStartToken")) {
					startToken = responseJSON.getString("nextStartToken");
				}
				while (startToken != null) {
					url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content?startToken=" + startToken;
					if (!doFullReload && startTime != null) {
						url += "&startTime=" + startTime;
					}
					response     = getURL(url, accessToken);
					responseJSON = new JSONObject(response.getMessage());
					if (responseJSON.has("titles")) {
						responseTitles = responseJSON.getJSONArray("titles");
						if (responseTitles != null && responseTitles.length() > 0) {
							numProcessed += updateTitlesInDB(pikaConn, responseTitles);
						}
					}
					if (responseJSON.has("nextStartToken")) {
						startToken = responseJSON.getString("nextStartToken");
					} else {
						startToken = null;
					}
					if (numProcessed % 10000 == 0) {
						addNoteToHooplaExportLog("Processed " + numProcessed + " records from hoopla");
					}
				}
				addNoteToHooplaExportLog("Processed a total of " + numProcessed + " records from hoopla");

			}
		} catch (Exception e) {
			logger.error("Error exporting hoopla data", e);
			addNoteToHooplaExportLog("Error exporting hoopla data " + e.toString());
			return false;
		}
		// UpdateTitlesInDB can also have errors. If it does it sets updateTitlesInDBHadErrors to true;
		return !updateTitlesInDBHadErrors;
	}

	private static PreparedStatement updateHooplaTitleInDB              = null;
	private static PreparedStatement markGroupedWorkForBibAsChangedStmt = null;

	private static void markGroupedWorkForReindexing(Connection pikaConn, long hooplaTitleId) {
		try {
			if (markGroupedWorkForBibAsChangedStmt == null) {
				markGroupedWorkForBibAsChangedStmt = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'hoopla' and identifier = ?)");
			}
			String marcRecordID = "MWT" + hooplaTitleId;
			markGroupedWorkForBibAsChangedStmt.setLong(1, startTimeStamp);
			markGroupedWorkForBibAsChangedStmt.setString(2, marcRecordID);
			markGroupedWorkForBibAsChangedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.warn("Failed to mark grouped Work for reindexing ", e);
		}
	}

	private static int updateTitlesInDB(Connection pikaConn, JSONArray responseTitles) {
		int numUpdates = 0;
		try {
			if (updateHooplaTitleInDB == null) {
				updateHooplaTitleInDB = pikaConn.prepareStatement("INSERT INTO hoopla_export (hooplaId, active, title, kind, pa, demo, profanity, rating, abridged, children, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY " +
						"UPDATE active = VALUES(active), title = VALUES(title), kind = VALUES(kind), pa = VALUES(pa), demo = VALUES(demo), profanity = VALUES(profanity), " +
						"rating = VALUES(rating), abridged = VALUES(abridged), children = VALUES(children), price = VALUES(price)");
			}
			for (int i = 0; i < responseTitles.length(); i++) {
				JSONObject curTitle = responseTitles.getJSONObject(i);
				long       titleId  = curTitle.getLong("titleId");
				updateHooplaTitleInDB.setLong(1, titleId);
				updateHooplaTitleInDB.setBoolean(2, curTitle.getBoolean("active"));
				updateHooplaTitleInDB.setString(3, curTitle.getString("title"));
				updateHooplaTitleInDB.setString(4, curTitle.getString("kind"));
				updateHooplaTitleInDB.setBoolean(5, curTitle.getBoolean("pa"));
				updateHooplaTitleInDB.setBoolean(6, curTitle.getBoolean("demo"));
				updateHooplaTitleInDB.setBoolean(7, curTitle.getBoolean("profanity"));
				updateHooplaTitleInDB.setString(8, curTitle.has("rating") ? curTitle.getString("rating") : "");
				updateHooplaTitleInDB.setBoolean(9, curTitle.getBoolean("abridged"));
				updateHooplaTitleInDB.setBoolean(10, curTitle.getBoolean("children"));
				updateHooplaTitleInDB.setDouble(11, curTitle.getDouble("price"));

				int updated = updateHooplaTitleInDB.executeUpdate();
				if (updated > 0) {
					numUpdates++;
					markGroupedWorkForReindexing(pikaConn, titleId);
				}
			}

		} catch (Exception e) {
			logger.error("Error updating hoopla data in Pika database", e);
			addNoteToHooplaExportLog("Error updating hoopla data in Pika database " + e.toString());
			updateTitlesInDBHadErrors = true;
		}
		return numUpdates;
	}

	private static String getAccessToken() {
		String hooplaUsername = PikaConfigIni.getIniValue("Hoopla", "HooplaAPIUser");
		String hooplaPassword = PikaConfigIni.getIniValue("Hoopla", "HooplaAPIpassword");
		if (hooplaUsername == null || hooplaPassword == null) {
			logger.error("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
			addNoteToHooplaExportLog("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
			return null;
		}
		hooplaAPIBaseURL = PikaConfigIni.getIniValue("Hoopla", "APIBaseURL");
		if (hooplaAPIBaseURL == null) {
			hooplaAPIBaseURL = "http://hoopla-erc.hoopladigital.com";
		}
		String          getTokenUrl = hooplaAPIBaseURL + "/v2/token";
		URLPostResponse response    = postToTokenURL(getTokenUrl, hooplaUsername + ":" + hooplaPassword);
		if (response.isSuccess()) {
			try {
				JSONObject responseJSON = new JSONObject(response.getMessage());
				if (responseJSON.has("access_token")) {
					return responseJSON.getString("access_token");
				}
			} catch (JSONException e) {
				addNoteToHooplaExportLog("Could not parse JSON for token " + response.getMessage());
				logger.error("Could not parse JSON for token " + response.getMessage(), e);
			}
		} else {
			logger.error("Failed to get a response while requesting an access token for Hoopla");
			addNoteToHooplaExportLog("Failed to get a response while requesting an access token for Hoopla");
		}
		return null;
	}

	private static URLPostResponse postToTokenURL(String url, String authentication) {
		URLPostResponse   retVal;
		HttpURLConnection conn = null;
		try {
			URL emptyIndexURL = new URL(url);
			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			conn.setConnectTimeout(10000);
			conn.setReadTimeout(300000);
			if (authentication != null) {
				conn.setRequestProperty("Authorization", "Basic " + Base64.encodeBase64String(authentication.getBytes()));
			}
			//logger.debug("Posting To URL " + url + (postData != null && postData.length() > 0 ? "?" + postData : ""));

			if (conn instanceof HttpsURLConnection) {
				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
				sslConn.setHostnameVerifier(new HostnameVerifier() {

					@Override
					public boolean verify(String hostname, SSLSession session) {
						//Do not verify host names
						return true;
					}
				});
			}
			conn.setDoInput(true);
			conn.setRequestMethod("POST");

			StringBuilder response = new StringBuilder();
			if (conn.getResponseCode() == 200) {
				// Get the response
				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
				String         line;
				while ((line = rd.readLine()) != null) {
					response.append(line);
				}

				rd.close();
				retVal = new URLPostResponse(true, 200, response.toString());
			} else {
				logger.info("Received error " + conn.getResponseCode() + " posting to " + url);
				// Get any errors
				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
				String         line;
				while ((line = rd.readLine()) != null) {
					response.append(line);
				}

				rd.close();

				if (response.length() == 0) {
					//Try to load the regular body as well
					// Get the response
					BufferedReader rd2 = new BufferedReader(new InputStreamReader(conn.getInputStream()));
					while ((line = rd2.readLine()) != null) {
						response.append(line);
					}

					rd.close();
				}
				retVal = new URLPostResponse(false, conn.getResponseCode(), response.toString());
			}

		} catch (SocketTimeoutException e) {
			logger.error("Timeout connecting to URL (" + url + ")", e);
			retVal = new URLPostResponse(false, -1, "Timeout connecting to URL (" + url + ")");
		} catch (MalformedURLException e) {
			logger.error("URL to post (" + url + ") is malformed", e);
			retVal = new URLPostResponse(false, -1, "URL to post (" + url + ") is malformed");
		} catch (IOException e) {
			logger.error("Error posting to url \r\n" + url, e);
			retVal = new URLPostResponse(false, -1, "Error posting to url \r\n" + url + "\r\n" + e.toString());
		} finally {
			if (conn != null) conn.disconnect();
		}
		return retVal;
	}

	private static URLPostResponse getURL(String url, String accessToken) {
		URLPostResponse   retVal;
		HttpURLConnection conn = null;
		try {
			URL emptyIndexURL = new URL(url);
			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			conn.setConnectTimeout(10000);
			conn.setReadTimeout(300000);
			conn.setRequestProperty("Authorization", "Bearer " + accessToken);
			conn.setRequestProperty("Accept", "application/json");

			if (conn instanceof HttpsURLConnection) {
				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
				sslConn.setHostnameVerifier((hostname, session) -> {
					//Do not verify host names
					return true;
				});
			}
			conn.setDoInput(true);
			conn.setRequestMethod("GET");

			StringBuilder response = new StringBuilder();
			if (conn.getResponseCode() == 200) {
				// Get the response
				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
				}
				retVal = new URLPostResponse(true, 200, response.toString());
			} else {
				logger.info("Received error " + conn.getResponseCode() + " posting to " + url);
				try {
					// Get any errors
					String line;
					if (conn.getErrorStream() != null) {
						try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
							while ((line = rd.readLine()) != null) {
								response.append(line);
							}
						}
					}

					if (response.length() == 0) {
						//Try to load the regular body as well
						// Get the response
						try (BufferedReader rd2 = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
							while ((line = rd2.readLine()) != null) {
								response.append(line);
							}
						}
					}
					retVal = new URLPostResponse(false, conn.getResponseCode(), response.toString());
				} catch (IOException e) {
					logger.error("Error reading error or input stream", e);
					retVal = new URLPostResponse(false, -1, "Error reading error or input stream for \r\n" + url + "\r\n" + e.toString());
				}
			}

		} catch (SocketTimeoutException e) {
			logger.error("Timeout connecting to URL (" + url + ")", e);
			retVal = new URLPostResponse(false, -1, "Timeout connecting to URL (" + url + ")");
		} catch (MalformedURLException e) {
			logger.error("URL to get (" + url + ") is malformed", e);
			retVal = new URLPostResponse(false, -1, "URL to get (" + url + ") is malformed");
		} catch (IOException e) {
			logger.error("Error getting url \r\n" + url, e);
			retVal = new URLPostResponse(false, -1, "Error getting url \r\n" + url + "\r\n" + e.toString());
		} finally {
			if (conn != null) conn.disconnect();
		}
		return retVal;
	}

	private static String getHooplaLibraryId(Connection pikaConn) {
		ResultSet getLibraryIdRS;
		try (PreparedStatement getLibraryIdStmt = pikaConn.prepareStatement("SELECT hooplaLibraryID FROM library WHERE hooplaLibraryID IS NOT NULL AND hooplaLibraryID != 0 LIMIT 1")) {
			getLibraryIdRS = getLibraryIdStmt.executeQuery();
			if (getLibraryIdRS.next()) {
				return getLibraryIdRS.getString("hooplaLibraryID");
			}
		} catch (SQLException e) {
			logger.error("Failed to retrieve a Hoopla library id", e);
		}
		return null;
	}


	private static StringBuffer     notes      = new StringBuffer();
	private static SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

	private static void addNoteToHooplaExportLog(String note) {
		try {
			Date date = new Date();
			notes.append("<br>").append(dateFormat.format(date)).append(": ").append(note);
			addNoteToHooplaExportLogStmt.setString(1, trimTo(65535, notes.toString()));
			addNoteToHooplaExportLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToHooplaExportLogStmt.setLong(3, hooplaExportLogId);
			addNoteToHooplaExportLogStmt.executeUpdate();
			logger.info(note);
		} catch (SQLException e) {
			logger.error("Error adding note to Export Log", e);
		}
	}

	private static String trimTo(int maxCharacters, String stringToTrim) {
		if (stringToTrim == null) {
			return null;
		}
		if (stringToTrim.length() > maxCharacters) {
			stringToTrim = stringToTrim.substring(0, maxCharacters);
		}
		return stringToTrim.trim();
	}

}
