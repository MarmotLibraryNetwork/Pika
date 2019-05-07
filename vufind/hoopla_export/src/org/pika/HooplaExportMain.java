package org.pika;

import org.apache.commons.codec.binary.Base64;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile;
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
	private static Logger  logger                   = Logger.getLogger(HooplaExportMain.class);
	private static String  serverName;
	private static Ini     configIni;
	private static String  hooplaAPIBaseURL;
	private static Long    lastExportTime;
	private static Long    startTimeStamp;
	private static Long    lastExportTimeVariableId = null;
	private static boolean hadErrors                = false;

	//Reporting information
	private static long              hooplaExportLogId;
	private static PreparedStatement addNoteToHooplaExportLogStmt;

	public static void main(String[] args) {
		serverName = args[0];
		args = Arrays.copyOfRange(args, 1, args.length);
		boolean doFullReload = false;
		if (args.length == 1) {
			//Check to see if we got a full reload parameter
			String firstArg = args[0].replaceAll("\\s", "");
			if (firstArg.matches("^fullReload(=true|1)?$")) {
				doFullReload = true;
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
		configIni = loadConfigFile("config.ini");

		//Connect to the pika database
		Connection pikaConn = null;
		try {
			String databaseConnectionInfo = cleanIniValue(configIni.get("Database", "database_vufind_jdbc"));
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (Exception e) {
			System.out.println("Error connecting to pika database " + e.toString());
			System.exit(1);
		}

		//Start a hoopla export log entry
		try {
			logger.info("Creating log entry for index");
			PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO hoopla_export_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS);
			createLogEntryStatement.setLong(1, startTimeStamp);
			createLogEntryStatement.setLong(2, startTimeStamp);
			createLogEntryStatement.setString(3, "Initialization complete");
			createLogEntryStatement.executeUpdate();
			ResultSet generatedKeys = createLogEntryStatement.getGeneratedKeys();
			if (generatedKeys.next()) {
				hooplaExportLogId = generatedKeys.getLong(1);
			}

			addNoteToHooplaExportLogStmt = pikaConn.prepareStatement("UPDATE hoopla_export_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for hoopla extract process", e);
			System.exit(0);
		}

		//Get the last grouping time
		try {
			PreparedStatement loadLastHooplaExtractTime = pikaConn.prepareStatement("SELECT * from variables WHERE name = 'lastHooplaExport'");
			ResultSet         lastHooplaExtractTimeRS   = loadLastHooplaExtractTime.executeQuery();
			if (lastHooplaExtractTimeRS.next()) {
				try {
					lastExportTimeVariableId = lastHooplaExtractTimeRS.getLong("id");
					lastExportTime = lastHooplaExtractTimeRS.getLong("value");
				} catch (Exception e) {
					//Initially this is set to false, so we get an error.  If that happens, just set lastExport time to null
					lastExportTime = null;
				}
			}
			lastHooplaExtractTimeRS.close();
			loadLastHooplaExtractTime.close();
		} catch (Exception e) {
			logger.error("Error loading last hoopla export time", e);
			addNoteToHooplaExportLog("Error loading last hoopla export time " + e.toString());
			System.exit(1);
		}

		//Do work here
		exportHooplaData(pikaConn, lastExportTime, doFullReload);

		if (!hadErrors) {
			updateHooplaExportTime(pikaConn, startTimeStamp);
		}

		logger.info("Finished exporting hoopla data " + new Date().toString());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - startTime.getTime();
		logger.info("Elapsed Minutes " + (elapsedTime / 60000));

		try {
			PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE hoopla_export_log SET endTime = ? WHERE id = ?");
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

	private static void exportHooplaData(Connection pikaConn, Long startTime, boolean doFullReload) {
		try {
			//Find a library id to get data from
			String hooplaLibraryId = getHooplaLibraryId(pikaConn);
			if (hooplaLibraryId == null) {
				logger.error("No hoopla library id found");
				addNoteToHooplaExportLog("No hoopla library id found");
				hadErrors = true;
				return;
			} else {
				addNoteToHooplaExportLog("Hoopla library id is " + hooplaLibraryId);
			}

			String accessToken = getAccessToken();
			if (accessToken == null) {
				hadErrors = true;
				return;
			}

			if (doFullReload) {
				addNoteToHooplaExportLog("Doing a full reload of Hoopla data.");
				//TODO: Should we truncate the Pika table?
			}

			//Formulate the first call depending on if we are doing a full reload or not
			String url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content";
			if (!doFullReload && startTime != null) {
				url += "?startTime=" + startTime;
			}

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
					}
					// If working on a short time frame, it is possible there are no updates. But we expect to do this no more that once a day at this point
					// so we expect there to be changes.
					// Having this warning will give us a hint if there is something wrong with the data in the calls
				}

				String startToken = null;
				if (responseJSON.has("nextStartToken")) {
					startToken = responseJSON.getString("nextStartToken");
				}
				while (startToken != null) {
					url = hooplaAPIBaseURL + "/api/v1/libraries/" + hooplaLibraryId + "/content?startToken=" + startToken;
					response = getURL(url, accessToken);
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
			}
		} catch (Exception e) {
			logger.error("Error exporting hoopla data", e);
			addNoteToHooplaExportLog("Error exporting hoopla data " + e.toString());
			hadErrors = true;
		}
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
			logger.error("Error updating hoopla data in database", e);
			addNoteToHooplaExportLog("Error updating hoopla data in database " + e.toString());
			hadErrors = true;
		}
		return numUpdates;
	}

	private static String getAccessToken() {
		String hooplaUsername = cleanIniValue(configIni.get("Hoopla", "HooplaAPIUser"));
		String hooplaPassword = cleanIniValue(configIni.get("Hoopla", "HooplaAPIpassword"));
		if (hooplaUsername == null || hooplaPassword == null) {
			logger.error("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
			addNoteToHooplaExportLog("Please set HooplaAPIUser and HooplaAPIpassword in config.pwd.ini");
			return null;
		}
		hooplaAPIBaseURL = cleanIniValue(configIni.get("Hoopla", "APIBaseURL"));
		if (hooplaAPIBaseURL == null) {
			hooplaAPIBaseURL = "http://hoopla-erc.hoopladigital.com";
		}
		String          getTokenUrl = hooplaAPIBaseURL + "/v2/token";
		URLPostResponse response    = postToTokenURL(getTokenUrl, hooplaUsername + ":" + hooplaPassword);
		if (response.isSuccess()) {
			try {
				JSONObject responseJSON = new JSONObject(response.getMessage());
				return responseJSON.getString("access_token");
			} catch (JSONException e) {
				addNoteToHooplaExportLog("Could not parse JSON for token " + response.getMessage());
				logger.error("Could not parse JSON for token " + response.getMessage(), e);
				return null;
			}
		} else {
			logger.error("Failed to get a response while requesting an access token for Hoopla");
			addNoteToHooplaExportLog("Failed to get a response while requesting an access token for Hoopla");
			return null;
		}
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
				sslConn.setHostnameVerifier(new HostnameVerifier() {

					@Override
					public boolean verify(String hostname, SSLSession session) {
						//Do not verify host names
						return true;
					}
				});
			}
			conn.setDoInput(true);
			conn.setRequestMethod("GET");

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
				try {
					// Get any errors
					String line;
					if (conn.getErrorStream() != null) {
						BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}

						rd.close();
					}

					if (response.length() == 0) {
						//Try to load the regular body as well
						// Get the response
						BufferedReader rd2 = new BufferedReader(new InputStreamReader(conn.getInputStream()));
						while ((line = rd2.readLine()) != null) {
							response.append(line);
						}

						rd2.close();
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

	private static String getHooplaLibraryId(Connection pikaConn) throws SQLException {
		PreparedStatement getLibraryIdStmt = pikaConn.prepareStatement("SELECT hooplaLibraryID FROM library WHERE hooplaLibraryID IS NOT NULL AND hooplaLibraryID != 0 LIMIT 1");
		ResultSet         getLibraryIdRS   = getLibraryIdStmt.executeQuery();
		if (getLibraryIdRS.next()) {
			return getLibraryIdRS.getString("hooplaLibraryID");
		} else {
			return null;
		}
	}

	private static void updateHooplaExportTime(Connection pikaConn, long startTime) {
		//Update the last grouping time in the variables table
		try {
			if (lastExportTimeVariableId != null) {
				PreparedStatement updateVariableStmt = pikaConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setLong(1, startTime);
				updateVariableStmt.setLong(2, lastExportTimeVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else {
				PreparedStatement insertVariableStmt = pikaConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('lastHooplaExport', ?)");
				insertVariableStmt.setLong(1, startTime);
				insertVariableStmt.executeUpdate();
				insertVariableStmt.close();
			}
		} catch (Exception e) {
			logger.error("Error setting last grouping time", e);
		}
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

	private static Ini loadConfigFile(String filename) {
		//First load the default config file
		String configName = "../../sites/default/conf/" + filename;
		logger.info("Loading configuration from " + configName);
		File configFile = new File(configName);
		if (!configFile.exists()) {
			logger.error("Could not find configuration file " + configName);
			System.exit(1);
		}

		// Parse the configuration file
		Ini ini = new Ini();
		try {
			ini.load(new FileReader(configFile));
		} catch (InvalidFileFormatException e) {
			logger.error("Configuration file is not valid.  Please check the syntax of the file.", e);
		} catch (FileNotFoundException e) {
			logger.error("Configuration file could not be found.  You must supply a configuration file in conf called config.ini.", e);
		} catch (IOException e) {
			logger.error("Configuration file could not be read.", e);
		}

		//Now override with the site specific configuration
		String siteSpecificFilename = "../../sites/" + serverName + "/conf/" + filename;
		logger.info("Loading site specific config from " + siteSpecificFilename);
		File siteSpecificFile = new File(siteSpecificFilename);
		if (!siteSpecificFile.exists()) {
			logger.error("Could not find server specific config file");
			System.exit(1);
		}
		try {
			Ini siteSpecificIni = new Ini();
			siteSpecificIni.load(new FileReader(siteSpecificFile));
			for (Profile.Section curSection : siteSpecificIni.values()) {
				for (String curKey : curSection.keySet()) {
					//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					ini.put(curSection.getName(), curKey, curSection.get(curKey));
				}
			}
			//Also load password files if they exist
			String siteSpecificPassword = "../../sites/" + serverName + "/conf/config.pwd.ini";
			logger.info("Loading password config from " + siteSpecificPassword);
			File siteSpecificPasswordFile = new File(siteSpecificPassword);
			if (siteSpecificPasswordFile.exists()) {
				Ini siteSpecificPwdIni = new Ini();
				siteSpecificPwdIni.load(new FileReader(siteSpecificPasswordFile));
				for (Profile.Section curSection : siteSpecificPwdIni.values()) {
					for (String curKey : curSection.keySet()) {
						ini.put(curSection.getName(), curKey, curSection.get(curKey));
					}
				}
			}
		} catch (InvalidFileFormatException e) {
			logger.error("Site Specific config file is not valid.  Please check the syntax of the file.", e);
		} catch (IOException e) {
			logger.error("Site Specific config file could not be read.", e);
		}

		return ini;
	}

	private static String cleanIniValue(String value) {
		if (value == null) {
			return null;
		}
		value = value.trim();
		if (value.startsWith("\"")) {
			value = value.substring(1);
		}
		if (value.endsWith("\"")) {
			value = value.substring(0, value.length() - 1);
		}
		return value;
	}
}
