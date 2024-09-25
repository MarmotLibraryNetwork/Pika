/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// IMPORTANT NOTE : Including the reindexer.jar in the IntelliJ module build and configuration appears to be necessary for the updateServer to initiate correctly.
// (Otherwise an java.lang.NoClassDefFoundError error is triggered.)

package org.pika;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.ByteBuffer;
import java.nio.channels.FileChannel;
import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.time.Instant;
import java.time.ZoneOffset;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.*;
import java.util.Date;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

// Import log4j classes.
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrClient;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.marc4j.*;
import org.marc4j.marc.*;
import org.marc4j.marc.Record;
import org.marc4j.marc.impl.SortedMarcFactoryImpl;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import javax.net.ssl.HttpsURLConnection;


public class PolarisExportMain {
	private static Logger               logger;
	private static String               serverName;
	private static PikaSystemVariables  systemVariables;
	private static Connection           pikaConn;
	private static Long                 lastPolarisExtractTime;
	private static Date                 lastExtractDate; // Will include buffered value
	private static IndexingProfile      indexingProfile;
	private static PolarisRecordGrouper recordGroupingProcessor;
	private static String               apiBaseUrl = null;

	private static final TreeSet<Long> suppressedIds         = new TreeSet<>(); //TODO: just a count?
	private static final TreeSet<Long> suppressedEcontentIds = new TreeSet<>(); // TODO: just a count?
	private static final TreeSet<Long> bibsWithErrors        = new TreeSet<>();


	//Reporting information
	private static long              exportLogId;
	private static PreparedStatement addNoteToExportLogStmt;
//	private static String            exportPath;

	private static final HashMap<String, TranslationMap> polarisExtractTranslationMaps = new HashMap<>();

	private static       boolean debug     = false;
	//private static int     minutesToProcessExport;
	private static final Date    startTime = new Date();

	// Connector to Solr for deleting index entries
	private static ConcurrentUpdateSolrClient updateServer = null;

	// API Parameters
	private static       String apiVersion = "v1";
	private final static String langId     = "1033";
	private final static String appId      = "100";
	private final static String orgId      = "1";

	private static String apiUser;
	private static String apiSecret;
	private static String polarisAPIToken;
	private static String polarisAPISecret;
	private static long   polarisAPIExpiration;

	private static final String logTable = "polaris_export_log";

	// Marc creation
	private static final MarcFactory marcFactory         = MarcFactory.newInstance();
	private static final Pattern     econtentURLsPattern = Pattern.compile("(?i)^https?://link\\.overdrive\\.com.*|^https?://api\\.overdrive\\.com.*|^https?://www\\.hoopladigital\\.com.*|^https?://clearviewlibrary\\.kanopy\\.com.*");
	//TODO: make pattern an config.ini setting

	// Fields not in the indexing profile
	private static char isHoldableSubfieldChar     = '5'; // Using Clearview export field
	private static char isDisplayInPACSubfieldChar = '4'; // Using Clearview export field


	public static void main(String[] args) {
		serverName = args[0];

		// Initialize the logger
		String sitePath         = "../../sites/" + serverName;
		File   log4jFile = new File(sitePath + "/conf/log4j2.polaris_extract.xml");
		if (log4jFile.exists()) {
			System.setProperty("log4j.pikaSiteName", serverName);
			System.setProperty("log4j.configurationFile", log4jFile.getAbsolutePath());
			logger = LogManager.getLogger();
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile);
			System.exit(1);
		}

		if (logger.isInfoEnabled()) {
			logger.info(startTime + " : Starting Polaris Extract");
		}

		// Read the base INI file to get information about the server (current directory/cron/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Get API credentials
		// these seem to be reversed for Clearview
		apiSecret = PikaConfigIni.getIniValue("Catalog", "clientKey");
		apiUser   = PikaConfigIni.getIniValue("Catalog", "clientSecret");
		if (apiUser == null || apiUser.isEmpty()){
			String message = "Ini setting Catalog clientSecret is not set or empty";
			logger.fatal(message);
			System.out.println(message);
			System.exit(1);
		}
		if (apiSecret == null || apiSecret.isEmpty()){
			String message = "Ini setting Catalog clientKey is not set or empty";
			logger.fatal(message);
			System.out.println(message);
			System.exit(1);
		}

		debug = PikaConfigIni.getBooleanIniValue("System", "debug");

		//Connect to the pika database
		try {
			String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			if (databaseConnectionInfo != null) {
				pikaConn = DriverManager.getConnection(databaseConnectionInfo);
			} else {
				logger.error("No Pika database connection info");
				System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
			}
		} catch (Exception e) {
			logger.error("Error connecting to Pika database {}", e);
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		//Check to see if the system is offline
		Boolean offline_mode_when_offline_login_allowed = systemVariables.getBooleanValuedVariable("offline_mode_when_offline_login_allowed");
		if (offline_mode_when_offline_login_allowed == null){
			offline_mode_when_offline_login_allowed = false;
		}
		if (offline_mode_when_offline_login_allowed || PikaConfigIni.getBooleanIniValue("Catalog", "offline")) {
			final String message = "Pika Offline Mode is currently on. Pausing for 1 min.";
			logger.info(message);
			initializeExportLogEntry();
			addNoteToExportLog(message);
			try {
				Thread.sleep(60000);
			} catch (Exception e) {
				logger.error("Sleep was interrupted while pausing in Polaris Extract.");
			}
			finalizeExportLogEntry();
			closeDBConnections(pikaConn);
			System.exit(0);
		}

		// Load translation maps for Polaris Extract
		String[] maps = {"circulationStatusToCode", "materialTypeToCode", "shelfLocationToCode"};
		for (String map: maps) {
			String mapFile = sitePath + "/translation_maps/"+ map + "_map.properties";
			File   curFile = new File(mapFile);
			if (curFile.exists()) {
				String mapName = curFile.getName().replace(".properties", "").replace("_map", "");
				polarisExtractTranslationMaps.put(mapName, loadTranslationMap(curFile, mapName));
			} else {
				logger.error("{} file not found.", mapFile);
				System.exit(0);
			}
		}

		String profileToLoad         = "ils";
		String singleRecordToProcess = null;
		if (args.length > 1) {
			if (args[1].equalsIgnoreCase("singleRecord")) {
				if (args.length == 3) {
					singleRecordToProcess = args[2].trim();
				} else {
					//get input from user
					//  open up standard input
					try (BufferedReader br = new BufferedReader(new InputStreamReader(System.in))) {
						System.out.print("Enter the full Polaris record Id to process : ");
						singleRecordToProcess = br.readLine().trim();
					} catch (IOException e) {
						System.out.println("Error while reading input from user." + e);
						System.exit(1);
					}
				}
			} else {
				profileToLoad = args[1];
			}
		}
		indexingProfile = IndexingProfile.loadIndexingProfile(pikaConn, profileToLoad, logger);

		String apiVersionStr = PikaConfigIni.getIniValue("Catalog", "api_version");
		if (apiVersionStr != null && !apiVersionStr.isEmpty()) {
			apiVersion = apiVersionStr;
		}

		String polarisUrl;
		try (
						PreparedStatement accountProfileStatement = pikaConn.prepareStatement("SELECT * FROM `account_profiles` WHERE `name` = '" + indexingProfile.sourceName + "'");
						ResultSet accountProfileResult = accountProfileStatement.executeQuery()
		) {
			if (accountProfileResult.next()) {
				polarisUrl = PikaConfigIni.trimTrailingPunctuation(accountProfileResult.getString("patronApiUrl"));
				//polarisUrl = PikaConfigIni.trimTrailingPunctuation(accountProfileResult.getString("vendorOpacUrl"));
				polarisUrl = polarisUrl.replace("public", "protected").trim();
				// All the bibliographic api calls are under protected instead of public used for patron api calls
				if (polarisUrl.endsWith("/")){
					polarisUrl = polarisUrl.substring(0, polarisUrl.length() - 1);
				}
				apiBaseUrl = polarisUrl + "/" + apiVersion + "/" + langId + "/" + appId + "/" + orgId;
			}
		} catch (SQLException e) {
			logger.error("Error retrieving account profile for " + indexingProfile.sourceName, e);
		}
		if (apiBaseUrl == null || apiBaseUrl.isEmpty()) {
			logger.error("Polaris API url must be set in account profile column patronApiUrl.");
			//.logger.error("Polaris API url must be set in account profile column vendorOpacUrl.");
			closeDBConnections(pikaConn);
			System.exit(1);
		}



		// Get stored token data so we don't have to re-authorize every time this process is ran
		Long expiration = systemVariables.getLongValuedVariable("polarisAPIExpiration");
		if (expiration != null && expiration > new Date().getTime()) {
			polarisAPIExpiration = expiration;
			polarisAPIToken      = systemVariables.getStringValuedVariable("polarisAPIToken");
			polarisAPISecret     = systemVariables.getStringValuedVariable("polarisAPISecret");
		}

		//Extract a Single Bib/Record
		if (singleRecordToProcess != null && !singleRecordToProcess.isEmpty()) {
			try {
				long id = Long.parseLong(singleRecordToProcess);
				logger.info("Extracting single record : {}", id);
				initializeRecordGrouper();
				setUpSqlStatements();
				updateMarcAndRegroupRecordIds(Collections.singletonList(id));
				String message = "Extract process for record " + singleRecordToProcess + " finished.";
				logger.info(message);
				System.out.println(message);
				System.exit(0);
			} catch (NumberFormatException e) {
				logger.error("Record " + singleRecordToProcess + " failed to get extracted.", e);
				System.exit(1);
			}
		}

		Boolean running = systemVariables.getBooleanValuedVariable("polaris_extract_running");
		if (running != null && running) {
			logger.warn("System variable 'polaris_extract_running' is already set to true. This may indicator another Polaris Extract process is running.");
		} else {
			updatePartialExtractRunning(true);
		}
		//Integer minutesToProcessFor = PikaConfigIni.getIntIniValue("Catalog", "minutesToProcessExport");
		//minutesToProcessExport = (minutesToProcessFor == null) ? 5 : minutesToProcessFor;

	initializeExportLogEntry();

		//Setup other systems we will use
		initializeRecordGrouper();
		setUpSqlStatements();

		ArrayList<Long> bibsToProcess = loadUnprocessedBibs();
		updatePolarisExtractLogNumToMarkedToProcess(bibsToProcess.size());
		if (!bibsToProcess.isEmpty()) {
			updateMarcAndRegroupRecordIds(bibsToProcess);
		}

		getBibsAndItemUpdatesFromPolaris();

		//Write any records that had errors to re-process
		markBibsToProcess();

		//Write stats to the log
//		updatePolarisExtractLogNumToProcess(numRecordsProcessed);

		// Wrap up
		addNoteToExportLog("Extraction processed " + suppressedIds.size() + " suppressed bibs and Grouping suppressed " + suppressedEcontentIds.size() + " eContent bibs");
		if (holdsCountsUpdated > 0){
			addNoteToExportLog("The holds counts for " + holdsCountsUpdated + " bibs processed in this extraction round were updated.");
		}
		long nextStartTime = startTime.getTime() / 1000;
		updateLastExportTime(nextStartTime);
		addNoteToExportLog("Setting last export time to " + nextStartTime + " (" + startTime + ")");

		addNoteToExportLog("Finished exporting Polaris data " + new Date());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - startTime.getTime();
		addNoteToExportLog("Elapsed Minutes " + (elapsedTime / 60000));

		finalizeExportLogEntry(endTime);

		updatePartialExtractRunning(false);

		closeDBConnections(pikaConn);
		Date currentTime = new Date();
		if (logger.isInfoEnabled()) {
			logger.info(currentTime + " : Finished Polaris Extract");
		}

	} // End of main

	private static void initializeExportLogEntry() {
		//Start an export log entry
		if (logger.isInfoEnabled()) {
			logger.info("Creating log entry for Polaris Extract");
		}
		try (PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO " + logTable + " (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS)) {
			createLogEntryStatement.setLong(1, startTime.getTime() / 1000);
			createLogEntryStatement.setLong(2, startTime.getTime() / 1000);
			createLogEntryStatement.setString(3, "Initialization of Polaris API Extract complete");
			createLogEntryStatement.executeUpdate();
			ResultSet generatedKeys = createLogEntryStatement.getGeneratedKeys();
			if (generatedKeys.next()) {
				exportLogId = generatedKeys.getLong(1);
			} else {
				logger.fatal("Failed to create log table entry.");
				System.exit(0);
			}

			addNoteToExportLogStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.fatal("Unable to create log entry Polaris Extract process", e);
			System.exit(0);
		}
	}

	private static void finalizeExportLogEntry() {
		finalizeExportLogEntry(new Date().getTime());
	}

	private static void finalizeExportLogEntry(long endTime) {
		try (PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE " + logTable + " SET endTime = ? WHERE id = ?")) {
			finishedStatement.setLong(1, endTime / 1000);
			finishedStatement.setLong(2, exportLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update polaris api export log with completion time.", e);
		}
	}


	private static final StringBuffer     notes      = new StringBuffer();
	private static final SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

	private static void addNoteToExportLog(String note) {
		try {
			Date date = new Date();
			notes.append("<br>").append(dateFormat.format(date)).append(": ").append(note);
			addNoteToExportLogStmt.setString(1, trimLogNotes(notes.toString()));
			addNoteToExportLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToExportLogStmt.setLong(3, exportLogId);
			addNoteToExportLogStmt.executeUpdate();
			logger.info(note);
		} catch (SQLException e) {
			logger.error("Error adding note to Export Log", e);
		}
	}

	private static String trimLogNotes(String stringToTrim) {
		if (stringToTrim == null) {
			return null;
		}
		if (stringToTrim.length() > 65535) {
			stringToTrim = stringToTrim.substring(0, 65535);
		}
		return stringToTrim.trim();
	}

	private static void closeDBConnections(Connection connection) {
		try {
			//Close the connection
			connection.close();
		} catch (Exception e) {
			System.out.println("Error closing connection: " + e);
			logger.error("Error closing connection to Pika DB", e);
		}
	}

	private static void initializeRecordGrouper() {
		recordGroupingProcessor = new PolarisRecordGrouper(pikaConn, indexingProfile, logger);
	}

	private static void updatePartialExtractRunning(boolean running) {
		systemVariables.setVariable("polaris_extract_running", running);
	}


	private static void getBibsAndItemUpdatesFromPolaris() {
		lastPolarisExtractTime = systemVariables.getLongValuedVariable("last_polaris_extract_time");
		if (lastPolarisExtractTime == null){
			String message = "'last_polaris_extract_time' not set. Set an initial value in the `variables` table.";
			logger.fatal(message);
			System.out.println(message);
			System.exit(1);
		}

		//Last Update time in UTC
		// Use a buffer value to cover gaps in extraction rounds
		Integer bufferInterval = PikaConfigIni.getIntIniValue("Catalog", "ilsAPIExtractBuffer");
		if (bufferInterval == null || bufferInterval < 0) {
			bufferInterval = 300; // 5 minutes
		}
		lastExtractDate = new Date((lastPolarisExtractTime - bufferInterval) * 1000);

		Date now       = new Date();
		Date yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
		//Date tomorrow  = new Date(now.getTime() + 24 * 60 * 60 * 1000); // Use for ending time range
		if (lastExtractDate.before(yesterday)) {
			//TODO: we do get nightly export, so should reset to arrival of the export
			logger.warn("Last Extract date was more than 24 hours ago.");
			// We used to only extract the last 24 hours because there would have been a full export marc file delivered,
			// but with this process that isn't a good assumption any longer.  Now we will just issue a warning
		}

		String lastExtractDateTimeFormatted = getPolarisAPIDateTimeString(lastExtractDate);
		String nowFormatted                 = getPolarisAPIDateTimeString(now);
		String deletionDateFormatted        = getPolarisAPIDateString(yesterday); // date component only, is needed for fetching deleted things
		// Use yesterday to ensure we don't have any timezone issues
		long   updateTime                   = now.getTime() / 1000;
		addNoteToExportLog("Loading records changed since " + lastExtractDate);

		processDeletedBibs(deletionDateFormatted, updateTime);
		getNewRecordsFromAPI(lastExtractDateTimeFormatted, nowFormatted, updateTime);
		//TODO: Processed replaced bibs : /synch/bibs/replacementids?startdate=
		getChangedRecordsFromAPI(lastExtractDateTimeFormatted, nowFormatted, updateTime);
		getUpdatedItemsFromAPI(lastExtractDateTimeFormatted);
		getDeletedItemsFromAPI(lastExtractDateTimeFormatted);
	}

	private static PreparedStatement getWorkForPrimaryIdentifierStmt;
	private static PreparedStatement getAdditionalPrimaryIdentifierForWorkStmt;
	private static PreparedStatement deletePrimaryIdentifierStmt;
	private static PreparedStatement markGroupedWorkAsChangedStmt;
	//	private static PreparedStatement deleteGroupedWorkStmt;
	private static PreparedStatement getPermanentIdByWorkIdStmt;
	private static PreparedStatement getBibIdFromItemIdStatement;

	private static void setUpSqlStatements() {
		try {
			getWorkForPrimaryIdentifierStmt           = pikaConn.prepareStatement("SELECT id, grouped_work_id FROM grouped_work_primary_identifiers WHERE type = ? AND identifier = ?");
			deletePrimaryIdentifierStmt               = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ? LIMIT 1");
			getAdditionalPrimaryIdentifierForWorkStmt = pikaConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers WHERE grouped_work_id = ?");
			markGroupedWorkAsChangedStmt              = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?");
			getPermanentIdByWorkIdStmt                = pikaConn.prepareStatement("SELECT permanent_id FROM grouped_work WHERE id = ?");
			isAlreadyDeletedExtractInfoStatement      = pikaConn.prepareStatement("SELECT 1 FROM ils_extract_info WHERE deleted IS NOT NULL AND indexingProfileId = ? AND ilsId = ?");
			updateExtractInfoStatement                = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined
			markDeletedExtractInfoStatement           = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted, deleted) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted), deleted=VALUES(deleted)"); // unique key is indexingProfileId and ilsId combined
			markSuppressedExtractInfoStatement        = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted, suppressed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted), suppressed=VALUES(suppressed)"); // unique key is indexingProfileId and ilsId combined
			//TODO: note Starting in MariaDB 10.3.3 function VALUES() becomes VALUE(). But documentation notes "The VALUES() function can still be used even from MariaDB 10.3.3, but only in INSERT ... ON DUPLICATE KEY UPDATE statements; it's a syntax error otherwise."
			//TODO: Tried Value() and got an error; probably have to update java library
			getBibIdFromItemIdStatement               = pikaConn.prepareStatement("SELECT `ilsId` FROM `ils_itemid_to_ilsid` WHERE `itemId` = ?");
			updateHoldsCountForBibStatement           = pikaConn.prepareStatement("INSERT INTO `ils_hold_summary` (`ilsId`,`numHolds`) VALUES (?,?) ON DUPLICATE KEY UPDATE ilsId=VALUES(ilsId), numHolds=VALUES(numHolds)");
			deleteHoldsCountStatement                 = pikaConn.prepareStatement("DELETE FROM `ils_hold_summary` WHERE `ilsId` = ? LIMIT 1");
//			deleteGroupedWorkStmt                     = pikaConn.prepareStatement("DELETE from grouped_work where id = ?");
		} catch (Exception e) {
			logger.error("Error setting up prepared statements for Record extraction processing", e);
		}
	}

	private static final Pattern microsoftDatePattern = Pattern.compile("/Date\\((\\d+)-\\d+\\)/");

	private static Long getTimeStampMillisecondFromMicrosoftDateString(String microsoftDateString) {
		Matcher matcher = microsoftDatePattern.matcher(microsoftDateString);
		if (matcher.find()) {
			String timestampMillisecond = matcher.group(1);
			return Long.parseLong(timestampMillisecond);
		}
		return null;
	}

	/**
	 * Build a string that is in the format for a dateTime expected by the Polaris API from a Date
	 * (in ISO 8601 format (yyyy-MM-dd'T'HH:mm:ssZ))
	 *
	 * @param dateTime the dateTime to format
	 * @return a string representing the dateTime in the expected format
	 */
	private static String getPolarisAPIDateTimeString(Date dateTime) {
		SimpleDateFormat dateTimeFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss");
		// TODO: Polaris deleted date-time format is : YYYY-MM-DDTHH:MM:SS
		//dateTimeFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		return dateTimeFormatter.format(dateTime);
	}

	private static String getPolarisAPIDateTimeString(Instant dateTime) {
		return getPolarisAPIDateTimeString(Date.from(dateTime));
	}

	private static PreparedStatement updateExtractInfoStatement;

	private static void markRecordForReExtraction(Long bibToUpdate) {
		updateLastExtractTimeForRecord(bibToUpdate.toString(), null);
	}

	private static void updateLastExtractTimeForRecord(String identifier) {
		updateLastExtractTimeForRecord(identifier, startTime.getTime() / 1000);
	}

	private static void updateLastExtractTimeForRecord(String identifier, Long lastExtractTime) {
		if (identifier != null && !identifier.isEmpty()) {
			try {
				updateExtractInfoStatement.setLong(1, indexingProfile.id);
				updateExtractInfoStatement.setString(2, identifier);
				if (lastExtractTime == null) {
					updateExtractInfoStatement.setNull(3, Types.INTEGER);
				} else {
					updateExtractInfoStatement.setLong(3, lastExtractTime);
				}
				int result = updateExtractInfoStatement.executeUpdate();
			} catch (SQLException e) {
				logger.error("Unable to update ils_extract_info table for {}", identifier, e);
			}
		}
	}

	private static PreparedStatement markDeletedExtractInfoStatement;
	private static PreparedStatement deleteHoldsCountStatement;

	private static boolean markRecordDeletedInExtractInfo(Long bibId) {
		String identifier = bibId.toString();
		try {
			markDeletedExtractInfoStatement.setLong(1, indexingProfile.id);
			markDeletedExtractInfoStatement.setString(2, identifier);
			markDeletedExtractInfoStatement.setLong(3, startTime.getTime() / 1000);
			markDeletedExtractInfoStatement.setDate(4, new java.sql.Date(startTime.getTime()));
			int result = markDeletedExtractInfoStatement.executeUpdate();
			try {
				deleteHoldsCountStatement.setLong(1, bibId);
				deleteHoldsCountStatement.executeUpdate();
			} catch (Exception e) {
				logger.error("Error delete holds count entry for {}", bibId, e);
			}
			return result == 1 || result == 2;
		} catch (Exception e) {
			logger.error("Failed to mark record {} as deleted in extract info table", bibId, e);
		}
		return false;
	}
	private static PreparedStatement markSuppressedExtractInfoStatement;

	private static boolean markRecordSuppressedInExtractInfo(Long bibId) {
		String identifier = bibId.toString();
		try {
			markSuppressedExtractInfoStatement.setLong(1, indexingProfile.id);
			markSuppressedExtractInfoStatement.setString(2, identifier);
			markSuppressedExtractInfoStatement.setLong(3, startTime.getTime() / 1000);
			markSuppressedExtractInfoStatement.setDate(4, new java.sql.Date(startTime.getTime()));
			int result = markSuppressedExtractInfoStatement.executeUpdate();
			return result == 1 || result == 2;
		} catch (Exception e) {
			logger.error("Failed to mark record {} as suppressed in extract info table", bibId, e);
		}
		return false;
	}

	private static PreparedStatement isAlreadyDeletedExtractInfoStatement;

	private static boolean isAlreadyMarkedDeleted(Long idFromAPI) {
		String bibId = idFromAPI.toString();
		try {
			isAlreadyDeletedExtractInfoStatement.setLong(1, indexingProfile.id);
			isAlreadyDeletedExtractInfoStatement.setString(2, bibId);
			try (ResultSet isAlreadyDeletedRS = isAlreadyDeletedExtractInfoStatement.executeQuery()) {
				if (isAlreadyDeletedRS.next()) {
					return true;
				}
			} catch (SQLException e) {
				logger.error("Failed to get result for deleted record in ils_extract_info table for " + bibId, e);
			}
		} catch (SQLException e) {
			logger.error("Failed to look up deleted record in ils_extract_info table for " + bibId, e);
		}
		return false;
	}

	/**
	 * Build a string that is in the format for a date (date only, no time component) expected by the Polaris API
	 * from a Date (in ISO 8601 format (yyyy-MM-dd'T'HH:mm:ssZZ))
	 *
	 * @param dateTime the dateTime to format
	 * @return a string representing the date (date only, no time component) in the expected format
	 */
	private static String getPolarisAPIDateString(Date dateTime) {
		SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd");
		dateFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		return dateFormatter.format(dateTime);
	}


	private static void updatePolarisExtractLogNumToMarkedToProcess(int numRecordsToProcess) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET numRecordsToProcess = ?, numErrors = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, numRecordsToProcess);
			setNumProcessedStmt.setLong(2, bibsWithErrors.size());
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updatePolarisExtractLogNumMarkedToProcessProcessed(int numRecordsProcessed) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET numErrors = ?, numRecordsProcessed = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numRecordsProcessed);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updatePolarisExtractLogNumItemsDeleted(int numItemsDeleted) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET `numErrors` = ?, `numItemsDeleted` = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numItemsDeleted);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updatePolarisExtractLogNumItemsUpdated(int numItemsUpdated) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET `numErrors` = ?, `numItemsUpdated` = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numItemsUpdated);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updatePolarisExtractLogNumAdded(int numRecordsAdded) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET `numErrors` = ?, `numRecordsAdded` = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numRecordsAdded);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static int totalNumBibsUpdated = 0;

	/**
	 * Updates the logs with the total bibs updated so far; and number of bibs with errors so far
	 *
	 * @param numRecordsUpdated Number of Bibs updated this round
	 */
	private static void updatePolarisExtractLogNumUpdatedCumulative(int numRecordsUpdated) {
		totalNumBibsUpdated += numRecordsUpdated;
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET numErrors = ?, numRecordsUpdated = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, totalNumBibsUpdated);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updatePolarisExtractLogNumDeleted(int numRecordsDeleted) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE " + logTable + " SET numErrors = ?, numRecordsDeleted = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numRecordsDeleted);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updateLastExportTime(long exportStartTime/*, long numBibsRemainingToProcess*/) {
		try {
			Long lastPolarisExtractTimeConfirm = systemVariables.getLongValuedVariable("last_polaris_extract_time");
			if (lastPolarisExtractTimeConfirm.equals(lastPolarisExtractTime)) {
				systemVariables.setVariable("last_polaris_extract_time", Long.toString(exportStartTime));
				//systemVariables.setVariable("remaining_polaris_records", Long.toString(numBibsRemainingToProcess));
				//Update the last extract time
			} else {
				logger.warn("Last Polaris Extract time was changed in database during extraction. Not updating last extract time");
			}
		} catch (Exception e) {
			logger.error("There was an error updating the database, not setting last extract time.", e);
		}
	}


	private static ArrayList<Long> loadUnprocessedBibs() {
		ArrayList<Long> bibsToProcess = new ArrayList<>();
		try (
						PreparedStatement bibsToProcessStatement = pikaConn.prepareStatement("SELECT `ilsId` FROM `ils_extract_info` WHERE `lastExtracted` IS NULL AND `indexingProfileId` = " + indexingProfile.id, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
						ResultSet bibsToProcessResults = bibsToProcessStatement.executeQuery()
		) {
			while (bibsToProcessResults.next()) {
				Long bibId = bibsToProcessResults.getLong("ilsId");
				bibsToProcess.add(bibId);
			}

		} catch (SQLException e) {
			logger.error("Error loading changed bibs to process", e);
		}
		return bibsToProcess;
	}


	private static Long convertMarcXmlAndWriteMarcRecord(String marcXML, long bibId){
		return convertMarcXmlAndWriteMarcRecord(marcXML, bibId, true);
	}

	private static Long convertMarcXmlAndWriteMarcRecord(String marcXML, long bibId, boolean checkEcontentSupression){
		byte[]        bytes   = marcXML.getBytes(StandardCharsets.UTF_8);
		MarcXmlReader marcXmlReader;
		try (ByteArrayInputStream inputStream = new ByteArrayInputStream(bytes)) {
			marcXmlReader = new MarcXmlReader(inputStream);
			while (marcXmlReader.hasNext()) {
				try {
					logger.debug("Starting to process the next marcXML record");
					Record marcRecord = marcXmlReader.next();
					logger.debug("Got the next marc record data");

					if (checkEcontentSupression && isSuppressedEcontent(marcRecord)){
						logger.debug("Fetched record {} is suppressed eContent bib", bibId);
						suppressedEcontentIds.add(bibId);
						// Since bib is eContent suppressed by grouping, update the last extract time.
						updateLastExtractTimeForRecord(Long.toString(bibId)); //TODO: mark as suppressed in ils extract info instead? markRecordSuppressedInExtractInfo()
						return null;
					} else {
						Record newRecord = new SortedMarcFactoryImpl().newRecord(marcRecord.getLeader()); // Use the SortedMarcFactoryImpl (which puts the tags in numerical order

						for (ControlField curField : marcRecord.getControlFields()) {
							newRecord.addVariableField(curField);
						}

						for (DataField curField : marcRecord.getDataFields()) {
							boolean addField = true;
							if (curField.getTag().equals("852")) {
								String    itemRecordNumber = curField.getSubfieldsAsString("d");
								DataField dataField        = marcFactory.newDataField(indexingProfile.itemTag, ' ', ' ');
								logger.debug("Creating item record {} from marcxml 852 tag on bib {}", itemRecordNumber, bibId);
								for (Subfield curSubField : curField.getSubfields()) {
									char   subfieldCode  = curSubField.getCode();
									String subfieldValue = curSubField.getData();
									switch (subfieldCode) {
										case 'a': // Assigned Branch
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.locationSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for Assigned Branch for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'b': // Assigned Collection
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.collectionSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for Assigned Collection for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'c': // Shelf location
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.shelvingLocationSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for shelf location for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'd': // Item record number
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.itemRecordNumberSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for Item record number for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'e': // Circulation Status
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.itemStatusSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for status field for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'f': // owning branch
											dataField.addSubfield(marcFactory.newSubfield('o', subfieldValue));
											// Using Clearview export field
											break;
										case 'g': // barcode
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.barcodeSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for barcode field for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'i': // Polaris Material Type (used as pika item type)
											if (isNumeric(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(indexingProfile.iTypeSubfield, subfieldValue));
											} else {
												logger.error("Bad value {} for Polaris material type field for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'j': // renewal limit
											dataField.addSubfield(marcFactory.newSubfield('y', subfieldValue));
											// Using Clearview export field
											break;
										case 'k': // is holdable
											if (isBoolean(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(isHoldableSubfieldChar, subfieldValue));
											} else {
												logger.error("Bad boolean value {} for isHoldable field for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'l': // display in pac (opposite of item suppression)
											if (isBoolean(subfieldValue)) {
												dataField.addSubfield(marcFactory.newSubfield(isDisplayInPACSubfieldChar, subfieldValue));
												// Using Clearview export field
											} else {
												logger.error("Bad boolean value {} for Display in PAC field for an item on {}", subfieldValue, bibId);
											}
											break;
										case 'm': // combined call number
											dataField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, subfieldValue));
											break;
									}

								}
								curField = dataField;
							} // End of 852 tag
								newRecord.addVariableField(curField);
						} // End of record tags

						//TODO: Set record tag with bib suppression field, creation date field, update date field
						//DataField extractedByAPIField = marcFactory.newDataField("999", ' ', ' ');
						//boolean bibLevelDisplayInPAC =

						// Write marc to File and Do the Record Grouping
						String identifier  = groupAndWriteTheMarcRecord(newRecord, bibId);
						long   processedId = Long.parseLong(identifier);

						logger.debug("Processed {}", processedId);
						return processedId;
					}
				} catch (MarcException e) {
					if (logger.isInfoEnabled()) {
						logger.info("Error loading marc record from file, will load manually. While processing id: {}", bibId, e);
						bibsWithErrors.add(bibId);
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error processing Marc XML records", e);
			bibsWithErrors.add(bibId);
		}
		return null;
	}

	private static void updateMarcAndRegroupRecordIds(List<Long> idArray) {
		//TreeSet<Long> bibIdsUpdated = new TreeSet<>();
		StringBuilder idsToProcess  = new StringBuilder();
		for (long id: idArray){
			if (idsToProcess.length() > 0) {
				idsToProcess.append(",");
			}
			idsToProcess.append(id);
		}
		if (idArray.size() > 50){
			logger.error("More than 50 bibs to extract; call currently isn't limited to 50");
		}

		try {
			JSONObject bibsCallResult = null;
				// TODO: limit of 50 records per call
			String polarisUrl = "/synch/bibs/MARCXML?includeitems=1&bibids=" + idsToProcess;
			if (logger.isDebugEnabled()) {
				logger.debug("Loading marc records with bulk method : {}", polarisUrl);
			}
			int bibsUpdated = 0;
			bibsCallResult = callPolarisApiURL(polarisUrl, debug);
			if (bibsCallResult != null && bibsCallResult.has("GetBibsByIDRows")) {
				//ArrayList<Long> processedIds = new ArrayList<>();
				JSONArray       entries      = bibsCallResult.getJSONArray("GetBibsByIDRows");
				for (int i = 0; i < entries.length(); i++) {
					Long bibId = null;
					try {
						JSONObject entry = entries.getJSONObject(i);
						bibId = entry.getLong("BibliographicRecordID");
						String marcXML     = entry.getString("BibliographicRecordXML");
						Long   processedId = convertMarcXmlAndWriteMarcRecord(marcXML, bibId);
						if (processedId != null && processedId > 0){
							//bibIdsUpdated.add(bibId);
							bibsUpdated++;
						}
					} catch (JSONException e) {
						if (bibId != null) {
							logger.error("Error processing JSON for bib {}", bibId ,e);
							bibsWithErrors.add(bibId);
						} else {
							logger.error("Error processing JSON for a bib", e);
						}
					}
				} // end of for loop
			} else {
				logger.info("API call for specified bibs returned response with no entries");
			}

			updatePolarisExtractLogNumMarkedToProcessProcessed(bibsUpdated);
		} catch (Exception e) {
			logger.error("Error processing newly created bibs", e);
		}
	}

	private static void writeMarcRecord(Record marcRecord, String identifier) {
		logger.debug("Writing marc record for {}", identifier);
		File marcFile = indexingProfile.getFileForIlsRecord(identifier);
		if (!marcFile.getParentFile().exists()) {
			if (!marcFile.getParentFile().mkdirs()) {
				logger.error("Could not create directories for " + marcFile.getAbsolutePath());
			}
		}

		try (FileOutputStream outputStream = new FileOutputStream(marcFile)) {
			MarcWriter marcWriter = new MarcStreamWriter(outputStream, "UTF8", true);
			marcWriter.write(marcRecord);
			marcWriter.close();

			updateLastExtractTimeForRecord(identifier);
		} catch (FileNotFoundException e) {
			logger.warn("File not found exception ", e);
		} catch (IOException e) {
			logger.warn("IO exception ", e);
		}
		logger.debug("Wrote marc record for {}", identifier);
	}


	private static void markBibsToProcess() {
		//Write any bibs that had errors
		for (Long bibToUpdate : bibsWithErrors) {
				markRecordForReExtraction(bibToUpdate);
		}
	}
	private static PreparedStatement updateHoldsCountForBibStatement;
	private static int holdsCountsUpdated = 0;

	private static void fetchHoldsCountForBibs(List<Long> bibIds) {
		// Limit 100 bibs per call
		StringBuilder idsToProcess = new StringBuilder();
		int count = 0;
		for (Long bibId : bibIds) {
			if (idsToProcess.length() > 0) {
				idsToProcess.append(",");
			}
			idsToProcess.append(bibId);
			if (++count == 100){
				fetchHoldsCountForBibs(idsToProcess.toString());
				idsToProcess = new StringBuilder();
				count = 0;
			}
		}
		if (count > 0){
			fetchHoldsCountForBibs(idsToProcess.toString());
		}
	}

	private static void fetchHoldsCountForBibs(String idsStr){
		String url = "/synch/bibs/resourcecounts?bibids=" + idsStr;
		JSONObject resourceCounts = callPolarisApiURL(url);
		if (resourceCounts != null && resourceCounts.has("GetBibResourceCountsByIDRows")){
			try {
				JSONArray entries = resourceCounts.getJSONArray("GetBibResourceCountsByIDRows");
				for (int i = 0; i < entries.length(); i++) {
					JSONObject entry         = entries.getJSONObject(i);
					String     reportedBibId = entry.getString("BibliographicRecordID");
					int        holdsCount    = entry.getInt("HoldRequestsCount");
					try {
						updateHoldsCountForBibStatement.setString(1, reportedBibId);
						updateHoldsCountForBibStatement.setInt(2, holdsCount);
						updateHoldsCountForBibStatement.executeUpdate();
						holdsCountsUpdated++;
						} catch (SQLException e) {
						logger.error("Error updating holds count for {}", reportedBibId, e);
					}
				}
			} catch (JSONException e) {
				logger.error("JSON on error fetching holds count", e);
			}
		}
	}

	private static void processDeletedBibs(String lastExtractDateFormatted, long updateTime) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to fetch BibIds for deleted records since " + lastExtractDateFormatted);

		boolean       hasMoreRecords;
		int           bufferSize          = 500;
		long          recordIdToStartWith = 0;
		int           numDeletions        = 0;
		int           numAlreadyDeleted   = 0;
		int           numFailedDeleted    = 0;
		TreeSet<Long> allDeletedIds       = new TreeSet<>();

		do {
			hasMoreRecords = false;

			String url = "/synch/bibs/deleted/paged?deletedate=" + lastExtractDateFormatted + "&nrecs=" + bufferSize + "&lastid=" + recordIdToStartWith;

			JSONObject deletedRecords = callPolarisApiURL(url);
			if (deletedRecords != null && deletedRecords.has("BibIDListRows")) {
				try {
					JSONArray entries = deletedRecords.getJSONArray("BibIDListRows");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						Long       bibId  = curBib.getLong("BibliographicRecordID");
						allDeletedIds.add(bibId);
					}
					if (entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						recordIdToStartWith = allDeletedIds.last(); // Get the largest current value to use as starting point in next round
						// NOTE: This call does not return a "Last Id" field so we have to determine this ourselves
					}
				} catch (Exception e) {
					logger.error("Error processing deleted bibs", e);
				}
			} else {
				logger.error("Deleted Bibs response did not include BibIDListRows array : {}", deletedRecords);
			}
		} while (hasMoreRecords);


		if (!allDeletedIds.isEmpty()) {
			for (Long id : allDeletedIds) {
				if (!isAlreadyMarkedDeleted(id)) {
					if (deleteRecordFromGrouping(updateTime, id) && markRecordDeletedInExtractInfo(id)) {
						numDeletions++;
					} else {
						numFailedDeleted++;
						if (logger.isInfoEnabled()) {
							logger.info("Failed to delete from index bib Id : {}", id);
						}
					}
				} else {
					numAlreadyDeleted++;
				}
			}
			updatePolarisExtractLogNumDeleted(numDeletions);
			addNoteToExportLog("Finished processing deleted records, of " + allDeletedIds.size() + " records reported by the API, " + numDeletions + " were deleted, " + numAlreadyDeleted + " were already deleted, " + numFailedDeleted + " failed to delete.");
		} else {
			addNoteToExportLog("No deleted records found");
		}
	}


	private static boolean deleteRecordFromGrouping(long updateTime, Long idFromAPI) {
		String bibId = idFromAPI.toString();
		try {
			//Check to see if the identifier is in the grouped work primary identifiers table
			getWorkForPrimaryIdentifierStmt.setString(1, indexingProfile.sourceName);
			getWorkForPrimaryIdentifierStmt.setString(2, bibId);
			try (ResultSet getWorkForPrimaryIdentifierRS = getWorkForPrimaryIdentifierStmt.executeQuery()) {
				if (getWorkForPrimaryIdentifierRS.next()) { // If not true, already deleted skip this
					Long groupedWorkId       = getWorkForPrimaryIdentifierRS.getLong("grouped_work_id");
					Long primaryIdentifierId = getWorkForPrimaryIdentifierRS.getLong("id");

					//Delete the primary identifier
					deleteGroupedWorkPrimaryIdentifier(primaryIdentifierId);

					//Check to see if there are other identifiers for this work
					getAdditionalPrimaryIdentifierForWorkStmt.setLong(1, groupedWorkId);
					try (ResultSet getAdditionalPrimaryIdentifierForWorkRS = getAdditionalPrimaryIdentifierForWorkStmt.executeQuery()) {
						if (getAdditionalPrimaryIdentifierForWorkRS.next()) {
							//There are additional records for this work, just need to mark that it needs indexing again
							// So that the work is still in the index, but without this particular bib
							markGroupedWorkForReindexing(updateTime, groupedWorkId);
							return true;
						} else {
							//The grouped work no longer exists
							String permanentId = getPermanentIdForGroupedWork(groupedWorkId);
							if (permanentId != null && !permanentId.isEmpty()) {
								//Delete the work from solr index
								deleteGroupedWorkFromSolr(permanentId);

								logger.info("Polaris API extract deleted Group Work {} from index on deleting bib {}.", permanentId, bibId);

								return true;
							}
						}
					}
				} else {
					if (logger.isInfoEnabled()) {
						logger.info("Found no grouped work primary identifiers for bib id : {}", bibId);
					}
					return true; // Calling this true because there is no grouping for the bib
				}
			}
		} catch (Exception e) {
			logger.error("Error processing deleted bibs", e);
		}
		return false;
	}

	private static void deleteGroupedWorkFromSolr(String id) {
		if (updateServer == null){
			// Only initialize updateServer if it turns out we are going to use it.

			String solrPort = PikaConfigIni.getIniValue("Reindex", "solrPort");
			updateServer = new ConcurrentUpdateSolrClient.Builder("http://localhost:" + solrPort + "/solr/grouped").withQueueSize(500).withThreadCount(8).build();
			// Including the reindexer.jar in the IntelliJ module build and configuration appears to be necessary for the updateServer to initiate correctly.
			// (Otherwise an java.lang.NoClassDefFoundError error is triggered.)
		}

		logger.info("Clearing existing work from index {}", id);
		try {
			updateServer.deleteById(id);

			//With this commit, we get errors in the log "Previous SolrRequestInfo was not closed!"
			//Allow auto commit functionality to handle this
			//updateServer.commit(true, false, false);
		} catch (Exception e) {
			logger.error("Error deleting work from index", e);
		}
	}

	private static void deleteGroupedWorkPrimaryIdentifier(Long primaryIdentifierId) {
		try {
			deletePrimaryIdentifierStmt.setLong(1, primaryIdentifierId);
			deletePrimaryIdentifierStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error deleting grouped work primary identifier " + primaryIdentifierId + " from database ", e);
		}
	}

	private static String getPermanentIdForGroupedWork(Long groupedWorkId) {
		String permanentId = null;
		try {
			getPermanentIdByWorkIdStmt.setLong(1, groupedWorkId);
			try (ResultSet getPermanentIdByWorkIdRS = getPermanentIdByWorkIdStmt.executeQuery()) {
				if (getPermanentIdByWorkIdRS.next()) {
					permanentId = getPermanentIdByWorkIdRS.getString("permanent_id");
				}
			}
		} catch (SQLException e) {
			logger.error("Error looking up grouped work permanent Id", e);
		}
		return permanentId;
	}

	private static void markGroupedWorkForReindexing(long updateTime, Long groupedWorkId) {
		try {
			markGroupedWorkAsChangedStmt.setLong(1, updateTime);
			markGroupedWorkAsChangedStmt.setLong(2, groupedWorkId);
			markGroupedWorkAsChangedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error while marking a grouped work for reindexing", e);
		}
	}

	private static void getChangedRecordsFromAPI(String lastExtractDateFormatted, String nowFormatted, long updateTime) {
		addNoteToExportLog("Starting to fetch bib records changed since " + lastExtractDateFormatted);
		boolean hasMoreRecords;
		int     bufferSize             = 100; // 100 is the API max per paged call
		long    recordIdToStartWith    = 0;
		int     numSuppressedRecords   = 0;
		int     totalNumChangedRecords = 0;
		ArrayList<Long> changedBibs    = new ArrayList<Long>();

		do {
			int numChangedRecordsThisRound = 0;
			hasMoreRecords = false;
			String url = "/synch/bibs/MARCXML/paged?includeitems=1&startdatemodified=" + lastExtractDateFormatted + "&enddatemodified=" + nowFormatted + "&nrecs=" + bufferSize + "&lastid=" + recordIdToStartWith;
			JSONObject updatedRecords = callPolarisApiURL(url);
			if (updatedRecords != null && updatedRecords.has("GetBibsPagedRows")) {
				try {
					JSONArray entries = updatedRecords.getJSONArray("GetBibsPagedRows");
					for (int i = 0; i < entries.length(); i++) {
						boolean    isSuppressed = false;
						JSONObject curBib       = entries.getJSONObject(i);
						Long       bibId        = curBib.getLong("BibliographicRecordID");
						if (curBib.has("IsDisplayInPAC")) {
							isSuppressed = !curBib.getBoolean("IsDisplayInPAC");
						}
						if (isSuppressed) {
							suppressedIds.add(bibId);
							if (deleteRecordFromGrouping(updateTime, bibId) && markRecordSuppressedInExtractInfo(bibId)) {
								numSuppressedRecords++;
							} else if (logger.isInfoEnabled()) {
								logger.info("Failed to delete from index 'updated but suppressed' bib Id  {}", bibId);
							}
						} else {
							//String creationDate = curBib.getString("CreationDate"); // TODO: extract from microsoft format
							//String updateDate   = curBib.getString("ModificationDate");  // TODO: extract from microsoft format
							String marcXML      = curBib.getString("BibliographicRecordXML");
							if (convertMarcXmlAndWriteMarcRecord(marcXML, bibId) != null) {
								numChangedRecordsThisRound++;
								changedBibs.add(bibId);
							} else {
								logger.error("Error processing updated bib {}, will be marked for reprocessing", bibId);
								bibsWithErrors.add(bibId);
							}
						}
					}
					updatePolarisExtractLogNumUpdatedCumulative(numChangedRecordsThisRound);
					totalNumChangedRecords += numChangedRecordsThisRound;

					if (entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						if (updatedRecords.has("LastID")){
							recordIdToStartWith = updatedRecords.getLong("LastID");
						} else {
							logger.error("Did not find LastID key for next round of updated records fetching : {}", url);
						}
					}
				} catch (Exception e) {
					logger.error("Error processing changed bibs", e);
				}
			} else {
				addNoteToExportLog("No changed records found");
			}
		} while (hasMoreRecords);
		addNoteToExportLog("Finished processing changed records, there were " + totalNumChangedRecords + " changed records and " + numSuppressedRecords + " suppressed records");
		fetchHoldsCountForBibs(changedBibs);
	}

	private static void getNewRecordsFromAPI(String lastExtractDateFormatted, String nowFormatted, long updateTime) {
		addNoteToExportLog("Starting to fetch newly created records since " + lastExtractDateFormatted);
		boolean       hasMoreRecords;
		int           bufferSize           = 100; // 100 is the API max per paged call
		long          recordIdToStartWith  = 0;
		int           numNewRecords        = 0;
		int           numSuppressedRecords = 0;
		TreeSet<Long> createdBibs          = new TreeSet<>();
		ArrayList<Long> addedBibs          = new ArrayList<>();

		do {
			hasMoreRecords = false;
			String     url            = "/synch/bibs/MARCXML/paged?includeitems=1&startdatecreated=" + lastExtractDateFormatted + "&enddatecreated=" + nowFormatted + "&nrecs=" + bufferSize + "&lastid=" + recordIdToStartWith;
			JSONObject createdRecords = callPolarisApiURL(url);
			if (createdRecords != null && createdRecords.has("GetBibsPagedRows")) {
				try {
					JSONArray entries = createdRecords.getJSONArray("GetBibsPagedRows");
					for (int i = 0; i < entries.length(); i++) {
						boolean    isSuppressed = false;
						JSONObject curBib       = entries.getJSONObject(i);
						Long       bibId        = curBib.getLong("BibliographicRecordID");
						if (curBib.has("IsDisplayInPAC")) {
							isSuppressed = !curBib.getBoolean("IsDisplayInPAC");
						}
						if (isSuppressed) {
							suppressedIds.add(bibId);
							if (markRecordSuppressedInExtractInfo(bibId)) {
								numSuppressedRecords++;
								deleteRecordFromGrouping(updateTime, bibId); // Remove from grouping in case its there already
							} else if (logger.isInfoEnabled()) {
								logger.info("Failed to delete from index newly created but suppressed bib Id  {}", bibId);
							}
						} else {
							//String creationDate = curBib.getString("CreationDate"); // TODO: extract from microsoft format
							//String updateDate   = curBib.getString("ModificationDate");  // TODO: extract from microsoft format
							String marcXML      = curBib.getString("BibliographicRecordXML");
							createdBibs.add(bibId); // Need to maintain a clean sort of ids fetched by this method only

							if (convertMarcXmlAndWriteMarcRecord(marcXML, bibId) != null) {
								numNewRecords++;
								addedBibs.add(bibId);
							}
						}

					}
					updatePolarisExtractLogNumAdded(numNewRecords);
					if (entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						recordIdToStartWith = createdBibs.last(); // Use as starting point in next round
					}
				} catch (Exception e) {
					logger.error("Error processing newly created bibs", e);
				}
			} else {
				addNoteToExportLog("No newly created records found");
			}
		} while (hasMoreRecords);
		addNoteToExportLog("Finished processing newly created records, " + numNewRecords + " were new and " + numSuppressedRecords + " were suppressed");
		fetchHoldsCountForBibs(addedBibs);
	}


	private static void getUpdatedItemsFromAPI(String lastExtractDateFormatted) {
		addNoteToExportLog("Starting to fetch items that have updated since " + lastExtractDateFormatted);
		boolean         hasMoreItems;
		int             bufferSize      = 1000;
		long            lastItemId      = 0;
		int             numChangedItems = 0;
		TreeSet<Long>   changedItems    = new TreeSet<>();
		TreeSet<Long>   parentBibIds    = new TreeSet<>();

		do {
			hasMoreItems = false;
			String     url              = "/synch/items/updated/paged?updatedate=" + lastExtractDateFormatted + "&nrecs=" + bufferSize + "&lastid=" + lastItemId;
			JSONObject changedItemsJSON = callPolarisApiURL(url);
			if (changedItemsJSON != null) {
				try {
					JSONArray entries = changedItemsJSON.getJSONArray("ItemIDListRows");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curItem = entries.getJSONObject(i);
						Long       itemId  = curItem.getLong("ItemRecordID");
						changedItems.add(itemId);
						Long parentBibId = fetchUpdatedItem(itemId.toString());
						if (parentBibId != null) {
							numChangedItems++;
							parentBibIds.add(parentBibId);
						}
						if (i % 50 == 0){
							updatePolarisExtractLogNumItemsUpdated(numChangedItems);
						}
					}
					if (entries.length() >= bufferSize) {
						hasMoreItems      = true;
						lastItemId = changedItems.last(); // Get the largest current value to use as starting point in next round
					}
				} catch (Exception e) {
					logger.error("Error processing updated items", e);
				}
			} else {
				addNoteToExportLog("No updated items found");
			}
			updatePolarisExtractLogNumItemsUpdated(numChangedItems);
		} while (hasMoreItems);
		addNoteToExportLog("Finished fetching new/updated items, items updated: " + numChangedItems);
		fetchHoldsCountForBibs(new ArrayList<>(parentBibIds));
	}

	private static Long fetchUpdatedItem(String itemId) {
		String     url      = "/synch/item/" + itemId;
		JSONObject itemJSON = callPolarisApiURL(url);
		if (itemJSON != null && itemJSON.has("ItemGetRows")) {
			try {
				JSONArray entries = itemJSON.getJSONArray("ItemGetRows");
				if (entries.length() == 1) {
					JSONObject itemInfo       = entries.getJSONObject(0);
					boolean    isDisplayInPAC = itemInfo.getBoolean("IsDisplayInPAC");
					if (isDisplayInPAC) {
						Long             parentBibId            = itemInfo.getLong("BibliographicRecordID");
						RecordIdentifier identifier             = new RecordIdentifier(indexingProfile.sourceName, parentBibId.toString());
						boolean          suppressLoggingWarning = itemInfo.has("Barcode") && itemInfo.getString("Barcode").startsWith("econtent");
						Record           record                 = loadMarcRecordFromDisk(parentBibId, suppressLoggingWarning);
						if (record != null) {
							// Create new item record
							DataField itemRecord = marcFactory.newDataField(indexingProfile.itemTag, ' ', ' ');
							if (itemInfo.has("LocationID")) {
								itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.locationSubfield, itemInfo.getString("LocationID")));
							}
							if (itemInfo.has("CollectionID")) {
								itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.collectionSubfield, itemInfo.getString("CollectionID")));
							}
							if (itemInfo.has("ShelfLocation")) {
								String shelfLocation = itemInfo.getString("ShelfLocation");
								if (shelfLocation != null) {
									String shelfLocationCode = polarisExtractTranslationMaps.get("shelfLocationToCode").translateValue(shelfLocation, identifier);
									itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.shelvingLocationSubfield, shelfLocation));
								}
							}
							if (itemInfo.has("ItemRecordID")) {
								String itemRecordID = itemInfo.getString("ItemRecordID");
								if (!itemRecordID.equals(itemId)) {
									logger.error("Fetched item id {} doesn't match requested item id {}", itemRecordID, itemId);
								}
								itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.itemRecordNumberSubfield, itemRecordID));
							}
							String circStatus = null;
							if (itemInfo.has("CircStatus")) {
								circStatus = itemInfo.getString("CircStatus");
								String statusCode = polarisExtractTranslationMaps.get("circulationStatusToCode").translateValue(circStatus, identifier);
								itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.itemStatusSubfield, statusCode));
							}
							if (itemInfo.has("Barcode")) {
								String barcode = itemInfo.getString("Barcode");
								if (barcode != null) {
									itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.barcodeSubfield, barcode));
								} else {
									if (circStatus == null || !circStatus.equals("In-Process")){
										logger.error("Item {} had no barcode: {}", itemId, itemInfo);
									} else {
										// Items with status "In-Process" regularly don't have a barcode yet.
										logger.debug("In-Processing item {} had no barcode: {}", itemId, itemInfo);
									}
								}
							}
							if (itemInfo.has("CallNumber")) {
								String callNumber = itemInfo.getString("CallNumber");
								if (callNumber != null) {
									itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, callNumber));
								}
							}
							if (itemInfo.has("VolumeNumber")) {
								String volumeNumber = itemInfo.getString("VolumeNumber");
								if (volumeNumber != null) {
									itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.volume, volumeNumber));
								}
							}
							if (itemInfo.has("Holdable")) {
								String holdable = itemInfo.getBoolean("Holdable") ? "1" : "0";
								itemRecord.addSubfield(marcFactory.newSubfield(isHoldableSubfieldChar, holdable));
							}
							if (itemInfo.has("MaterialType")) {
								String matType     = itemInfo.getString("MaterialType");
								String matTypeCode = polarisExtractTranslationMaps.get("materialTypeToCode").translateValue(matType, identifier);
								itemRecord.addSubfield(marcFactory.newSubfield(indexingProfile.iTypeSubfield, matTypeCode));
							}
							// Call does have entries for LastCircDate & DueDate

							// Remove the current entry for the item record
							for (DataField dataField : record.getDataFields(indexingProfile.itemTag)) {
								Subfield itemIdSubfield = dataField.getSubfield(indexingProfile.itemRecordNumberSubfield);
								if (itemIdSubfield != null) {
									if (itemIdSubfield.getData().equals(itemId)) {
										record.removeVariableField(dataField);
										break;
									}
								}
							}
							// Add updated item record
							record.addVariableField(itemRecord);
							if (groupAndWriteTheMarcRecord(record, parentBibId) != null) {
								return parentBibId;
							} else {
								logger.error("Error grouping and writing marc record for item {}, record Id {}", itemId, parentBibId);
							}
						} else {
							if (suppressLoggingWarning){
								// Clearview ILS eContent items have barcode prefixes of "econtent"; ignore for logging
								logger.debug("Failed to load probable suppressed eContent record {} with item id {}", parentBibId, itemId);
							} else {
								logger.error("Failed to load record for bibID {} with item id {}, Item data : {}", parentBibId, itemId, itemInfo);
							}
						}
					} else if (logger.isDebugEnabled()){
						logger.debug("Did not add/update suppressed item {}", itemId);
					}
				} else {
					logger.error("Failed to get item entries from fetch item call : {}", itemJSON);
				}
			} catch (JSONException e) {
				logger.error("JSON error fetching item {}", itemId, e);
			}

		} else {
			logger.error("Error fetching item data from API for {}", itemId);
		}
		return null;
	}

	private static void getDeletedItemsFromAPI(String lastExtractDateFormatted) {
		//Get a list of bibs with deleted items
		addNoteToExportLog("Starting to fetch item Ids deleted since " + lastExtractDateFormatted);
		boolean       hasMoreItems;
		int           bufferSize       = 1000;
		long          lastItemId       = 0;
		int           numItemsReported = 0;
		int           numDeletedItems  = 0;
		int           numBibsGrouped   = 0;
		TreeSet<Long> deletedItemIds   = new TreeSet<>();

		do {
			hasMoreItems = false;
			String     url          = "/synch/items/deleted/paged?deletedate=" + lastExtractDateFormatted + "&nrecs=" + bufferSize + "&lastid=" + lastItemId;
			JSONObject deletedItems = callPolarisApiURL(url);
			if (deletedItems != null) {
				try {
					JSONArray entries    = deletedItems.getJSONArray("ItemIDListRows");
					int       numEntries = entries.length();
					numItemsReported += numEntries;
					for (int i = 0; i < numEntries; i++) {
						boolean itemRemoved = false;
						JSONObject curItem = entries.getJSONObject(i);
						Long       itemId  = curItem.getLong("ItemRecordID");
						deletedItemIds.add(itemId); // Need to maintain a clean sort of ids fetched by this method only

						Long parentBibId = getBibIdForItemId(itemId);
						if (parentBibId != null) {
							Record record = loadMarcRecordFromDisk(parentBibId);
							if (record != null) {
								String itemIdStr = itemId.toString();
								for (DataField dataField : record.getDataFields(indexingProfile.itemTag)) {
									Subfield itemIdSubfield = dataField.getSubfield(indexingProfile.itemRecordNumberSubfield);
									if (itemIdSubfield != null) {
										if (itemIdSubfield.getData().equals(itemIdStr)) {
											record.removeVariableField(dataField);
											itemRemoved = true;
											break;
										}
									}
								}
								if (itemRemoved) {
									numDeletedItems++;
									String identifier = groupAndWriteTheMarcRecord(record, parentBibId);
									// Run grouping to cause itemId to be removed from `ils_itemid_to_ilsid`
									// and in case this is the last item on the bib.
									if (identifier != null) {
										numBibsGrouped++;
									}
								}
							}
						}
						if (i % 50 == 0){
							updatePolarisExtractLogNumItemsDeleted(numDeletedItems);
						}
					}
					if (numEntries >= bufferSize) {
						hasMoreItems = true;
						lastItemId   = deletedItemIds.last(); // Get the largest current value to use as starting point in next round
					}
					//Get the grouped work id for the new bib
				} catch (Exception e) {
					logger.error("Error processing deleted items", e);
				}
			} else {
				addNoteToExportLog("No deleted items found");
			}
			updatePolarisExtractLogNumItemsDeleted(numDeletedItems);
		} while (hasMoreItems);
		addNoteToExportLog("API reported " + numItemsReported + " deleted items, " + numDeletedItems + " items deleted, " + numBibsGrouped + " bibs were grouped");
	}

	private static Long getBibIdForItemId(Long itemId) {
		Long bibId = null;
		try {
			getBibIdFromItemIdStatement.setLong(1, itemId);
			try (ResultSet itemIdResult = getBibIdFromItemIdStatement.executeQuery()){
				if (itemIdResult.next()){
					bibId = itemIdResult.getLong(1);
				} else {
					logger.debug("Parent bibId not found in database for itemId {}", itemId);
				}
			}
		} catch (SQLException e) {
			logger.error("Error fetching parent bibId for item id {}", itemId, e);
		}
		return bibId;
	}

	private static boolean lastCallTimedOut = false;

	private static JSONObject callPolarisApiURL(String polarisUrl) {
		return callPolarisApiURL(polarisUrl, debug);
	}

	private static JSONObject callPolarisApiURL(String polarisUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToPolarisAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			// URL & Header Data
			String urlToCall = apiBaseUrl + "/" + polarisAPIToken;
			if (!polarisUrl.startsWith("/")) {
				urlToCall += "/";
			}
			urlToCall += polarisUrl;
			String requestMethod       = "GET";
			String gmtDateString       = gmtDateString();
			String authorizationString = authorizationString(requestMethod, urlToCall, gmtDateString, true);

			try {
				URL polarisApiURL = new URL(urlToCall);
				conn = (HttpURLConnection) polarisApiURL.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod(requestMethod);
				conn.setRequestProperty("Content-Type", "application/json;charset=UTF-8");
				conn.setRequestProperty("Authorization", authorizationString);
				conn.setRequestProperty("PolarisDate", gmtDateString);
				conn.setRequestProperty("Accept", "application/json");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response;
				int           responseCode = conn.getResponseCode();
				if (responseCode == 200) {
					// Get the response
					response = getTheResponse(conn.getInputStream());
					try {
						return new JSONObject(response.toString());
					} catch (JSONException jse) {
						logger.error("Error parsing response \n{}", response, jse);
						return null;
					}

				} else if (responseCode == 500 || responseCode == 404) {
					// 404 is record not found
					if (logErrors) {
						// Get any errors
						if (logger.isInfoEnabled()) {
							logger.info("Received response code {} calling Polaris API {}", responseCode, polarisUrl);
							response = getTheResponse(conn.getErrorStream());
							logger.info("Finished reading response : {}", response);
						}
					}
				} else {
					if (logErrors) {
						logger.error("Received error {} calling Polaris API {}", responseCode, polarisUrl);
						// Get any errors
						response = getTheResponse(conn.getErrorStream());
						logger.error("Finished reading response : {}", response);
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.error("Socket timeout talking to Polaris API (callPolarisApiURL) {} - {}", polarisUrl, e);
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.error("Timeout connecting to Polaris API (callPolarisApiURL) {} - {}", polarisUrl, e);
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.error("Error loading data from Polaris API (callPolarisApiURL) {} - ", polarisUrl, e);
			}
		}
		return null;
	}

	private static boolean connectToPolarisAPI() {
		//Check to see if we already have a valid token
		if (polarisAPIToken != null) {
			if (polarisAPIExpiration - new Date().getTime() > 0) {
				//logger.debug("token is still valid");
				return true;
			} else {
				logger.debug("Token has expired");
			}
		}
//		if (apiBaseUrl == null || apiBaseUrl.isEmpty()) {
//			logger.error("Polaris API URL is not set");
//			return false;
//		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			String urlString              = apiBaseUrl + "/authenticator/staff";
			URL    staffAuthenticationUrl = new URL(urlString);
			String requestMethod          = "POST";
			String domainAndUsername      = PikaConfigIni.getIniValue("Polaris", "staffUserName");
			String staffPassword          = PikaConfigIni.getIniValue("Polaris", "staffUserPw");
			String domain                 = "";
			String staffUsername          = "";

			if (staffPassword == null || staffPassword.isEmpty()){
				logger.error("Polaris Staff password is not found.");
				return false;
			}
			if (domainAndUsername.contains("@") || domainAndUsername.contains("\\")){
				// Split string by slash of at character.
				String[] strings = domainAndUsername.split("[@\\\\]");
				if (strings.length == 2){
					domain        = strings[0];
					staffUsername = strings[1];
				}
			}
			if (domain.isEmpty() || staffUsername.isEmpty()){
				logger.error("Invalid staff user name. Missing domain or staff username separated by slash (\\) or at (@) : {}", domainAndUsername);
				return false;
			}

			// Header Data
			String gmtDateString       = gmtDateString();
			String authorizationString = authorizationString(requestMethod, urlString, gmtDateString, false);

			// Post body
			JSONObject jsonObject = new JSONObject()
							.put("Domain", domain)
							.put("Username", staffUsername)
							.put("Password", staffPassword);

			conn = (HttpURLConnection) staffAuthenticationUrl.openConnection();
			checkForSSLConnection(conn);
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setRequestMethod(requestMethod);
			conn.setRequestProperty("Content-Type", "application/json;charset=UTF-8");
			conn.setRequestProperty("Accept", "application/json");
			conn.setRequestProperty("Authorization", authorizationString);
			conn.setRequestProperty("PolarisDate", gmtDateString);
			conn.setDoOutput(true);
			try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
				wr.write(jsonObject.toString());
				wr.flush();
			}

			StringBuilder response;
			if (conn.getResponseCode() == 200) {
				// Get the response
				response = getTheResponse(conn.getInputStream());
				try {
					JSONObject parser = new JSONObject(response.toString());
					String  polarisUserId            = parser.getString("PolarisUserID");
					String  polarisAuthExpirationStr = parser.getString("AuthExpDate"); // eg /Date(1723247367963-0600)/
					Long timeStamp = getTimeStampMillisecondFromMicrosoftDateString(polarisAuthExpirationStr);
					if (timeStamp != null) {
						polarisAPIExpiration = timeStamp - 10000;
						logger.debug("Polaris token is {} : {}", polarisAPIExpiration, new Date(polarisAPIExpiration));

						polarisAPIToken  = parser.getString("AccessToken");
						polarisAPISecret = parser.getString("AccessSecret");

						// Store our authorization tokens so that we can re-use throughout the day
						systemVariables.setVariable("polarisAPIExpiration", polarisAPIExpiration);
						systemVariables.setVariable("polarisAPIToken", polarisAPIToken);
						systemVariables.setVariable("polarisAPISecret", polarisAPISecret);
					} else {
						//TODO: bad time stamp? what to do
						logger.fatal("Failed to parse Polaris API expiration date-time : {}", polarisAuthExpirationStr);
						return false;
					}
				} catch (JSONException e) {
					logger.error("Error parsing response to json {}", response, e);
					return false;
				}

			} else {
				logger.error("Received error {} connecting to Polaris authentication service", conn.getResponseCode());
				// Get any errors
				response = getTheResponse(conn.getErrorStream());
				logger.error(response);
				return false;
			}

		} catch (Exception e) {
			logger.error("Error connecting to Polaris API", e);
			return false;
		}
		return true;
	}

	private static String gmtDateString(){
		DateTimeFormatter formatter = DateTimeFormatter.ofPattern("EEE, dd MMM yyyy HH:mm:ss O");
		return formatter.format(ZonedDateTime.now(ZoneOffset.UTC));
	}

	private static String pwsString(String urlMethod, String url, String gmtDateString, boolean useSecret){
		urlMethod = urlMethod.toUpperCase();
		String authorizationString = urlMethod + url + gmtDateString;
		if (useSecret){
			authorizationString += polarisAPISecret;
		}
//		if (logger.isDebugEnabled()){
//			logger.debug("Authorization string : \"{}\"", authorizationString);
//		}
		return HMAC(authorizationString, apiSecret);
	}

	private static String authorizationString(String urlMethod, String url, String gmtDateString, boolean useSecret){
		String hmacString = pwsString(urlMethod, url, gmtDateString, useSecret);
		return "PWS " + apiUser + ":" + hmacString;
	}

	/** Polaris Hashed Message Authentication Code
	 * @param string          authentication string
	 * @param clientSecret    API
	 * @return
	 */
	private static String HMAC(String string, String clientSecret){
		String        algorithm     = "HmacSHA1";
		SecretKeySpec secretKeySpec = new SecretKeySpec(clientSecret.getBytes(), algorithm);
		try {
			Mac mac = Mac.getInstance(algorithm);
			mac.init(secretKeySpec);
			return Base64.getEncoder().encodeToString((mac.doFinal(string.getBytes())));
		} catch (NoSuchAlgorithmException e) {
			logger.error("Algorithm error: " + e);
			throw new RuntimeException("HMAC exception");
		} catch (InvalidKeyException e) {
			logger.error("Invalid Key error : " + e);
			throw new RuntimeException("HMAC exception");
		}
	}

	private static void checkForSSLConnection(HttpURLConnection conn) {
		if (conn instanceof HttpsURLConnection) {
			HttpsURLConnection sslConn = (HttpsURLConnection) conn;
			sslConn.setHostnameVerifier((hostname, session) -> {
				return true; //Do not verify host names
			});
		}
	}

	private static StringBuilder getTheResponse(InputStream inputStream) {
		StringBuilder response = new StringBuilder();
		try (BufferedReader rd = new BufferedReader(new InputStreamReader(inputStream, StandardCharsets.UTF_8))) {
			// Setting the charset is apparently important sometimes. See D-4555
			String line;
			while ((line = rd.readLine()) != null) {
				response.append(line);
			}
		} catch (Exception e) {
			logger.warn("Error reading response :", e);
		}
		return response;
	}

	private static Record loadMarcRecordFromDisk(Long bibId) {
		return loadMarcRecordFromDisk(bibId, false);
	}
	private static Record loadMarcRecordFromDisk(Long bibId, boolean suppressWarnings) {
		Record record = null;
		String individualFilename = getFileForIlsRecord(bibId.toString());
		try {
			byte[] fileContents = readFileBytes(individualFilename);
			//FileInputStream inputStream = new FileInputStream(individualFile);
			try (InputStream inputStream = new ByteArrayInputStream(fileContents)) {
				//Don't need to use a permissive reader here since we've written good individual MARCs as part of record grouping
				//Actually we do need to since we can still get MARC records over the max length.
				// Assuming we have correctly saved the individual MARC file in utf-8 encoding; and should handle in utf-8 as well
				MarcReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF8");
				if (marcReader.hasNext()) {
					record = marcReader.next();
				}
			}
		}catch (FileNotFoundException fe){
            if (!suppressWarnings) {
                logger.warn("Could not find MARC record at {} for {}", individualFilename, bibId);
            }
        } catch (Exception e) {
			logger.error("Error reading data from ils file {}", individualFilename, e);
		}
		return record;
	}

	private static String getFileForIlsRecord(String recordNumber) {
		StringBuilder shortId = new StringBuilder(recordNumber.replace(".", ""));
		while (shortId.length() < 9){
			shortId.insert(0, "0");
		}

		String subFolderName;
		if (indexingProfile.createFolderFromLeadingCharacters) {
			subFolderName = shortId.substring(0, indexingProfile.numCharsToCreateFolderFrom);
		} else {
			subFolderName = shortId.substring(0, shortId.length() - indexingProfile.numCharsToCreateFolderFrom);
		}

		String basePath           = indexingProfile.individualMarcPath + "/" + subFolderName;
		return basePath + "/" + shortId + ".mrc";
	}

	static byte[] readFileBytes(String filename) throws IOException {
		FileInputStream f           = new FileInputStream(filename);
		FileChannel     fileChannel = f.getChannel();
		long            fileSize    = fileChannel.size();
		byte[]          fileBytes   = new byte[(int) fileSize];
		ByteBuffer      buffer      = ByteBuffer.wrap(fileBytes);
		fileChannel.read(buffer);
		fileChannel.close();
		f.close();
		return fileBytes;
	}
	private static String groupAndWriteTheMarcRecord(Record marcRecord) {
		return groupAndWriteTheMarcRecord(marcRecord, null);
	}

	private static String groupAndWriteTheMarcRecord(Record marcRecord, Long id) {
		String           identifier       = null;
		RecordIdentifier recordIdentifier = null;
		if (id != null) {
			identifier = id.toString();
			try {
				recordIdentifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(marcRecord, indexingProfile.sourceName, indexingProfile.doAutomaticEcontentSuppression);
				if (recordIdentifier != null) {
					identifier = recordIdentifier.getIdentifier();
				} else {
					logger.warn("Failed to set record identifier in record grouper getPrimaryIdentifierFromMarcRecord(); possible error or automatic econtent suppression trigger.");
				}
			} catch (Exception e) {
				logger.error("catch for id " + id + " or record Identifier " + recordIdentifier, e);
				throw e;
			}
		}
		if (identifier != null && !identifier.isEmpty()) {
			writeMarcRecord(marcRecord, identifier);
		} else {
			logger.warn("Failed to set record identifier in record grouper getPrimaryIdentifierFromMarcRecord(); possible error or automatic econtent suppression trigger.");
		}

		//Set up the grouped work for the record.  This will take care of either adding it to the proper grouped work
		//or creating a new grouped work
		final boolean grouped = (recordIdentifier != null) ? recordGroupingProcessor.processMarcRecord(marcRecord, true, recordIdentifier) : recordGroupingProcessor.processMarcRecord(marcRecord, true);
		if (!grouped) {
			logger.warn(identifier + " was not grouped");
		} else if (logger.isDebugEnabled()) {
			logger.debug("Finished record grouping for " + identifier);
		}
		return identifier;
	}

	private static boolean isNumeric(String str) {
		return str.matches("^\\d+$");
	}

	private static boolean isBoolean(String str) {
		return str.equals("0") || str.equals("1");
	}

	private static boolean isSuppressedEcontent(Record marcRecord){
		List<DataField> linkFields = marcRecord.getDataFields("856");
		for (DataField linkField : linkFields) {
			if (linkField.getSubfield('u') != null) {
				//Check the url to see if it is from OverDrive
				String linkData = linkField.getSubfield('u').getData().trim();
				if (econtentURLsPattern.matcher(linkData).matches()) {
					return true;
				}
			}
		}
		return false;
	}

	private static TranslationMap loadTranslationMap(File translationMapFile, String mapName) {
		Properties props = new Properties();
		try {
			props.load(new FileReader(translationMapFile));
		} catch (IOException e) {
			logger.error("Could not read file translation map, " + translationMapFile.getAbsolutePath(), e);
		}
		TranslationMap translationMap = new TranslationMap("polaris", mapName, true, false, logger);
		for (Object keyObj : props.keySet()) {
			String key   = (String) keyObj;
			String value = props.getProperty(key);
			translationMap.addValue(key.toLowerCase(), value);
			if (!isNumeric(value)){
				// Polaris Extract maps translate a phrase to a number code; so value should be a number.
				logger.error("Polaris Extract translation map {} value '{}' is not numeric; key is {}", mapName, value, key);
			}
		}
		return translationMap;
	}
}


