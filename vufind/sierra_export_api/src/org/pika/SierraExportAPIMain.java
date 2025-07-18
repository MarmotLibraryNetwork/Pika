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

import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
// Import log4j classes.
import org.apache.logging.log4j.Level;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrClient;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.marc4j.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.MarcFactory;
import org.marc4j.marc.Record;
import org.marc4j.marc.impl.SortedMarcFactoryImpl;

import javax.net.ssl.HttpsURLConnection;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.time.Duration;
import java.time.Instant;
import java.time.temporal.ChronoUnit;
import java.util.Date;
import java.util.*;



/**
 * Export data from the Sierra ILS to Pika
 *
 * User: Mark Noble
 * Date: 1/15/18
 */
public class SierraExportAPIMain {
	private static Logger              logger;
	private static String              serverName;
	private static PikaSystemVariables systemVariables;
	private static Long                lastSierraExtractTime;
	private static Date                lastExtractDate; // Will include buffered value
	private static boolean             timeAPI = false;
	//	public static  boolean fetchSingleBibFromCommandLine = false;

	private static IndexingProfile   indexingProfile;
	private static MarcRecordGrouper recordGroupingProcessor;
	private static boolean           isMLN2 = false;
	private static boolean           isMLN1 = false;
//	private static GroupedWorkIndexer groupedWorkIndexer;

	private static String  apiBaseUrl            = null;
	private static boolean allowFastExportMethod = true;

	private static final TreeSet<Long> allBibsToUpdate = new TreeSet<>();
	private static final TreeSet<Long> bibsToProcess   = new TreeSet<>();
	private static final TreeSet<Long> allDeletedIds   = new TreeSet<>();
	private static final TreeSet<Long> bibsWithErrors  = new TreeSet<>();

	//Reporting information
	private static long              exportLogId;
	private static PreparedStatement addNoteToExportLogStmt;
//	private static String            exportPath;

	private static boolean debug     = false;
	private static int     minutesToProcessExport;
	private static Date    startTime = new Date();

	// Connector to Solr for deleting index entries
	private static ConcurrentUpdateSolrClient updateServer;

	private static char bibLevelLocationsSubfield = 'a'; //TODO: may need to make bib-level locations field an indexing setting


	public static void main(String[] args) {
		serverName = args[0];

		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j2.sierra_extract.xml");
		if (log4jFile.exists()) {
			System.setProperty("log4j.pikaSiteName", serverName);
			System.setProperty("log4j.configurationFile", log4jFile.getAbsolutePath());
			logger = LogManager.getLogger();
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile);
			System.exit(1);
		}

		logger.info("{} : Starting Sierra Extract", startTime);

		// Read the base INI file to get information about the server (current directory/cron/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		debug = PikaConfigIni.getBooleanIniValue("System", "debug");

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
			logger.error("Error connecting to Pika database {}", e.toString());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		//Only needed for reindexer
//		//Connect to the pika  econtent database
//		Connection econtentConn = null;
//		try {
//			String databaseConnectionInfo = cleanIniValue(ini.get("Database", "database_econtent_jdbc"));
//			if (databaseConnectionInfo != null) {
//				econtentConn = DriverManager.getConnection(databaseConnectionInfo);
//			} else {
//				logger.error("No eContent database connection info");
//				System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
//			}
//		} catch (Exception e) {
//			System.out.println("Error connecting to econtent database " + e.toString());
//			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
//		}

		//Check to see if the system is offline
		Boolean offline_mode_when_offline_login_allowed = systemVariables.getBooleanValuedVariable("offline_mode_when_offline_login_allowed");
		if (offline_mode_when_offline_login_allowed == null){
			offline_mode_when_offline_login_allowed = false;
		}
		if (offline_mode_when_offline_login_allowed || PikaConfigIni.getBooleanIniValue("Catalog", "offline")) {
			final String message = "Pika Offline Mode is currently on. Pausing for 1 min.";
			logger.info(message);
			initializeExportLogEntry(pikaConn);
			addNoteToExportLog(message);
			try {
				Thread.sleep(60000);
			} catch (Exception e) {
				logger.error("Sleep was interrupted while pausing in Sierra Extract.");
			}
			finalizeExportLogEntry(pikaConn, new Date().getTime());
			closeDBConnections(pikaConn);
			System.exit(0);
		}


		String profileToLoad         = "ils";
		String singleRecordToProcess = null;
		if (args.length > 1) {
			/*if (args[1].startsWith(".b")) {
				fetchSingleBibFromCommandLine = true;
			} else*/
			if (args[1].equalsIgnoreCase("timeAPI")) {
				timeAPI = true;
			} else if (args[1].equalsIgnoreCase("singleRecord")) {
				if (args.length == 3) {
					singleRecordToProcess = args[2].replaceAll(".b", "").trim();
					singleRecordToProcess = singleRecordToProcess.substring(0, singleRecordToProcess.length() - 1);
				} else {
					//get input from user
					//  open up standard input
					try (BufferedReader br = new BufferedReader(new InputStreamReader(System.in))) {
						System.out.print("Enter the full Sierra record Id to process (with trailing check digit [use x if you don't know it]) : ");
						singleRecordToProcess = br.readLine().replaceAll(".b", "").replaceAll("b", "").trim();
						singleRecordToProcess = singleRecordToProcess.substring(0, singleRecordToProcess.length() - 1);
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
		if (indexingProfile.marcPath.contains("flatirons") || indexingProfile.marcPath.contains("mln2")){
			isMLN2                    = true;
			bibLevelLocationsSubfield = 'h';
		} else if (indexingProfile.marcPath.contains("marmot") || indexingProfile.marcPath.contains("mln1")) {
			isMLN1 = true;
		}

		String apiVersion = PikaConfigIni.getIniValue("Catalog", "api_version");
		if (apiVersion == null || apiVersion.isEmpty()) {
			logger.error("Sierra API version must be set.");
			closeDBConnections(pikaConn);
			System.exit(1);
		}

		String sierraUrl;
		try (
				PreparedStatement accountProfileStatement = pikaConn.prepareStatement("SELECT * FROM account_profiles WHERE name = '" + indexingProfile.sourceName + "'");
				ResultSet accountProfileResult = accountProfileStatement.executeQuery()
		) {
			if (accountProfileResult.next()) {
				sierraUrl = PikaConfigIni.trimTrailingPunctuation(accountProfileResult.getString("vendorOpacUrl"));
				apiBaseUrl = sierraUrl + "/iii/sierra-api/v" + apiVersion;
			}
		} catch (SQLException e) {
			logger.error("Error retrieving account profile for {}", indexingProfile.sourceName, e);
		}
		if (apiBaseUrl == null || apiBaseUrl.isEmpty()) {
			logger.error("Sierra API url must be set in account profile column vendorOpacUrl.");
			closeDBConnections(pikaConn);
			System.exit(1);
		}

		//Diagnostic routine to probe how near-real time the API info is
		if (timeAPI) {
			measureDelayInItemsAPIupdates();
			closeDBConnections(pikaConn);
			System.exit(0);
		}

		//API timing doesn't require Sierra Field Mapping
		if (indexingProfile.APIItemCallNumberFieldTag == null || indexingProfile.APIItemCallNumberFieldTag.isEmpty()) {
			logger.error("Sierra Field Mappings need to be set.");
			System.exit(0);
		}
		if (indexingProfile.sierraRecordFixedFieldsTag == null || indexingProfile.sierraRecordFixedFieldsTag.isEmpty()) {
			logger.error("Sierra Bib level/fixed field tag needs to be set in the indexing profile.");
			System.exit(0);
		}


		//Extract a Single Bib/Record
		if (singleRecordToProcess != null && !singleRecordToProcess.isEmpty()) {
			try {
				long id = Long.parseLong(singleRecordToProcess);
				logger.info("Extracting single record : {}", id);
				initializeRecordGrouper(pikaConn);
				//allowFastExportMethod = systemVariables.getBooleanValuedVariable("allow_sierra_fast_export");
				allowFastExportMethod = false;  // Set to false for single record to ensure deleted records get marked as deleted;
					// We will need better handling for dealing with deleted records with the fast export method.
				setUpSqlStatements(pikaConn);
				updateMarcAndRegroupRecordIds(singleRecordToProcess, Collections.singletonList(id));
				//TODO: shouldn't this just go straight to updateMarcAndRegroupRecordId(). If not, explain why here. Maybe just deleted records above?
				String message = "Extract process for record " + singleRecordToProcess + " finished.";
				logger.info(message);
				System.out.println(message);
				System.exit(0);
//			} catch (SQLException e) {
//				logger.error("Error setting up prepared statements for Record extraction processing", e);
//				System.exit(1);
			} catch (NumberFormatException e) {
				logger.error("Record {} failed to get extracted.", singleRecordToProcess, e);
				System.exit(1);
			}
		}

//		exportPath = indexingProfile.marcPath;
//		File changedBibsFile = new File(exportPath + "/changed_bibs_to_process.csv");


		Boolean running = systemVariables.getBooleanValuedVariable("sierra_extract_running");
		if (running != null && running) {
			logger.warn("System variable 'sierra_extract_running' is already set to true. This may indicator another Sierra Extract process is running.");
		} else {
			updatePartialExtractRunning(true);
		}
		Integer minutesToProcessFor = PikaConfigIni.getIntIniValue("Catalog", "minutesToProcessExport");
		minutesToProcessExport = (minutesToProcessFor == null) ? 5 : minutesToProcessFor;

		// Initialize Reindexer (used in deleteRecord() )
//		groupedWorkIndexer = new GroupedWorkIndexer(serverName, pikaConn, econtentConn, ini, false, false, logger);

		// Since we only need the reindexer at this time to delete entries, let's just skip over the reindexer to the Solr handler
		String solrPort = PikaConfigIni.getIniValue("Reindex", "solrPort");
		updateServer = new ConcurrentUpdateSolrClient.Builder("http://localhost:" + solrPort + "/solr/grouped").withQueueSize(500).withThreadCount(8).build();
		// Including the reindexer.jar in the IntelliJ module build and configuration appears to be necessary for the updateServer to initiate correctly.
		// (Otherwise an java.lang.NoClassDefFoundError error is triggered.)
//		updateServer.setRequestWriter(new BinaryRequestWriter());


		initializeExportLogEntry(pikaConn);

		//Process MARC record changes
		getBibsAndItemUpdatesFromSierra(pikaConn);
//		getBibsAndItemUpdatesFromSierra(pikaConn, changedBibsFile);

		//Write the number of updates to the log
		updateSierraExtractLogNumToProcess(pikaConn);

		//Setup other systems we will use
		initializeRecordGrouper(pikaConn);

		// Process the Bibs that need updating
		int numRecordsProcessed = updateBibs(pikaConn);

		//Write stats to the log
		updateSierraExtractLogNumToProcess(pikaConn, numRecordsProcessed);

		//Write any records that still haven't been processed
		markBibsToProcess();
//		markBibsToProcess(changedBibsFile);

		updateSierraExtractLogNumToProcess(pikaConn, numRecordsProcessed);

		if (!lastCallTimedOut) {
			retrieveDataFromSierraDNA(pikaConn);
		} else {
			logger.warn("Skipping Sierra DNA connection because we had time out errors. Trying to prevent getting stuck.");
		}

		// Wrap up
		long nextStartTime = startTime.getTime() / 1000;
		updateLastExportTime(nextStartTime, allBibsToUpdate.size());
		addNoteToExportLog("Setting last export time to " + nextStartTime + " (" + startTime.toString() + ")");

		addNoteToExportLog("Finished exporting sierra data " + new Date().toString());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - startTime.getTime();
		addNoteToExportLog("Elapsed Minutes " + (elapsedTime / 60000));

		finalizeExportLogEntry(pikaConn, endTime);

		updatePartialExtractRunning(false);

		closeDBConnections(pikaConn);
		logger.info("{} : Finished Sierra Extract", new Date());
	}

	private static void initializeExportLogEntry(Connection pikaConn) {
		//Start an export log entry
		logger.info("Creating log entry for Sierra API Extract");

		try (PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO sierra_api_export_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS)) {
			createLogEntryStatement.setLong(1, startTime.getTime() / 1000);
			createLogEntryStatement.setLong(2, startTime.getTime() / 1000);
			createLogEntryStatement.setString(3, "Initialization of Sierra API Extract complete");
			createLogEntryStatement.executeUpdate();
			ResultSet generatedKeys = createLogEntryStatement.getGeneratedKeys();
			if (generatedKeys.next()) {
				exportLogId = generatedKeys.getLong(1);
			}

			addNoteToExportLogStmt = pikaConn.prepareStatement("UPDATE sierra_api_export_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for record grouping process", e);
			System.exit(0);
		}
	}

	private static void finalizeExportLogEntry(Connection pikaConn, long endTime) {
		try (PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE sierra_api_export_log SET endTime = ?, numRemainingRecords = ? WHERE id = ?")) {
			finishedStatement.setLong(1, endTime / 1000);
			finishedStatement.setLong(2, allBibsToUpdate.size());
			finishedStatement.setLong(3, exportLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update sierra api export log with completion time.", e);
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

	private static void initializeRecordGrouper(Connection pikaConn) {
		recordGroupingProcessor = new MarcRecordGrouper(pikaConn, indexingProfile, logger);
	}

	private static void retrieveDataFromSierraDNA(Connection pikaConn) {
		logger.info("Starting SierraDNA connection");
		//Connect to the sierra database
		String url              = PikaConfigIni.getIniValue("Catalog", "sierra_db");
		String sierraDBUser     = PikaConfigIni.getIniValue("Catalog", "sierra_db_user");
		String sierraDBPassword = PikaConfigIni.getIniValue("Catalog", "sierra_db_password");
		Properties connectionProperties = new Properties();
		if (sierraDBUser != null && sierraDBPassword != null && !sierraDBPassword.isEmpty() && !sierraDBUser.isEmpty()) {
			// Use specific username and password when the are issues with special characters
			connectionProperties.setProperty("user", sierraDBUser);
			connectionProperties.setProperty("password", sierraDBPassword);
		}
		connectionProperties.setProperty("socketTimeout ", "300");
		// Set a socket timeout so that the Extractor doesn't hang on missed responses from SierraDNA

			if (url != null) {
			Connection sierraDBConn = null;
			try {
				//Open the connection to the database
				sierraDBConn = DriverManager.getConnection(url, connectionProperties);

				// Data extracted from the Sierra Database
				exportActiveOrders(sierraDBConn);
//				exportDueDates(exportPath, sierraDBConn); // Shouldn't be needed any longer. Pascal 6/13/2019
				exportHolds(sierraDBConn, pikaConn);

			} catch (Exception e) {
				logger.error("Sierra DNA Error: ", e);
			}
			if (sierraDBConn != null) {
				try {
					//Close the connection
					sierraDBConn.close();
				} catch (Exception e) {
					logger.error("Sierra DNA Error: ", e);
				}
			}
		}
	}

	private static void markBibsToProcess() {
//	private static void markBibsToProcess(File changedBibsFile) {
//		try (BufferedWriter bibIdsToProcessWriter = new BufferedWriter(new FileWriter(changedBibsFile, false))) {
//			for (Long bibToUpdate : allBibsToUpdate) {
//				bibIdsToProcessWriter.write(bibToUpdate + "\r\n");
//			}
//			//Write any bibs that had errors
//			for (Long bibToUpdate : bibsWithErrors) {
//				bibIdsToProcessWriter.write(bibToUpdate + "\r\n");
//			}
//			bibIdsToProcessWriter.flush();
//
//		} catch (Exception e) {
//			logger.error("Error saving remaining bibs to process", e);
//		}

		for (Long bibToUpdate : allBibsToUpdate) {
			if (!bibsToProcess.contains(bibToUpdate)) { // If it was in the bibsToProcess list already, then it was already marked for re-extraction
				markRecordForReExtraction(bibToUpdate);
			}
		}
		//Write any bibs that had errors
		for (Long bibToUpdate : bibsWithErrors) {
			if (!bibsToProcess.contains(bibToUpdate)) { // If it was in the bibsToProcess list already, then it was already marked for re-extraction
				markRecordForReExtraction(bibToUpdate);
			}
		}
	}

	private static void updateSierraExtractLogNumToProcess(Connection pikaConn) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE sierra_api_export_log SET numRecordsToProcess = ?, numErrors = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, allBibsToUpdate.size());
			setNumProcessedStmt.setLong(2, bibsWithErrors.size());
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updateSierraExtractLogNumToProcess(Connection pikaConn, int numRecordsProcessed) {
		// Log how many bibs are left to process
		try (PreparedStatement setNumProcessedStmt = pikaConn.prepareStatement("UPDATE sierra_api_export_log SET numErrors = ?, numRecordsProcessed = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS)) {
			setNumProcessedStmt.setLong(1, bibsWithErrors.size());
			setNumProcessedStmt.setLong(2, numRecordsProcessed);
			setNumProcessedStmt.setLong(3, exportLogId);
			setNumProcessedStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update log entry with number of records that have changed", e);
		}
	}

	private static void updateLastExportTime(long exportStartTime, long numBibsRemainingToProcess) {
		try {
			Long lastSierraExtractTimeConfirm = systemVariables.getLongValuedVariable("last_sierra_extract_time");
			if (lastSierraExtractTimeConfirm.equals(lastSierraExtractTime)) {
				systemVariables.setVariable("last_sierra_extract_time", Long.toString(exportStartTime));
				systemVariables.setVariable("remaining_sierra_records", Long.toString(numBibsRemainingToProcess));
				//Update the last extract time
			} else {
				logger.warn("Last Sierra Extract time was changed in database during extraction. Not updating last extract time");

			}
		} catch (Exception e) {
			logger.error("There was an error updating the database, not setting last extract time.", e);
		}
	}


	private static void loadUnprocessedBibs(Connection pikaConn) {
//	private static void loadUnprocessedBibs(Connection pikaConn, File changedBibsFile) {
//		try {
//			if (changedBibsFile.exists()) {
//				try (BufferedReader changedBibsReader = new BufferedReader(new FileReader(changedBibsFile))) {
//					String curLine = changedBibsReader.readLine();
//					while (curLine != null) {
//						Long bibId = Long.parseLong(curLine);
//						allBibsToUpdate.add(bibId);
//						curLine = changedBibsReader.readLine();
//					}
//				}
//			}
//		} catch (Exception e) {
//			logger.error("Error loading changed bibs to process", e);
//		}
		try (
				PreparedStatement bibsToProcessStatement = pikaConn.prepareStatement("SELECT ilsId FROM ils_extract_info WHERE lastExtracted IS NULL AND indexingProfileId = " + indexingProfile.id, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet bibsToProcessResults = bibsToProcessStatement.executeQuery()
		) {
			while (bibsToProcessResults.next()) {
				String fullSierraBibId = bibsToProcessResults.getString("ilsId");
				Long   bibId           = Long.parseLong(fullSierraBibId.substring(2, fullSierraBibId.length() - 1));
				// Strip off .b at start and ending check digit to get a number to use with API calls
				bibsToProcess.add(bibId);
				allBibsToUpdate.add(bibId);
			}

		} catch (SQLException e) {
			logger.error("Error loading changed bibs to process", e);
		}
	}

	/**
	 * When extract need is low, do a cleanup process of re-fetching bibs that haven't been extracted for the longest
	 * time, (up-to two weeks ago) to ensure that the extracted data is current.  The point is to attempt to capture
	 * any changes that may have been missed previously.
	 *
	 * @param pikaConn MariaDB connection
	 */
	private static void loadOldestLastExtractedBibs(Connection pikaConn) {
		final long    fourteenDaysAgo          = (startTime.getTime() / 1000) - 1209600;
		int           oldBibsToProcess         = 0;
		final boolean loadingNightlyFullExport = indexingProfile.lastGroupedTime != null && indexingProfile.lastGroupedTime > fourteenDaysAgo;
		// Using the last grouping time as an indicator for whether this site is delivering a full marc export.
		// If it does, the file should get regrouped every night. Which makes it unnecessary for us to fetch
		// long-ago extracted bibs.

		if (!loadingNightlyFullExport) {
			try (
							PreparedStatement bibsToProcessStatement = pikaConn.prepareStatement("SELECT ilsId FROM ils_extract_info WHERE deleted IS NULL AND lastExtracted <  " + fourteenDaysAgo + " AND indexingProfileId = " + indexingProfile.id + " ORDER BY lastExtracted ASC LIMIT 200", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
							ResultSet bibsToProcessResults = bibsToProcessStatement.executeQuery()
			) {
				while (bibsToProcessResults.next()) {
					String fullSierraBibId = bibsToProcessResults.getString("ilsId");
					Long   bibId           = Long.parseLong(fullSierraBibId.substring(2, fullSierraBibId.length() - 1));
					// Strip off .b at start and ending check digit to get a number to use with API calls
					allBibsToUpdate.add(bibId);
					oldBibsToProcess++;
				}
				addNoteToExportLog("Since the number of bibs to process is below 50, another " + oldBibsToProcess + " bibs with the oldest last extracted time will be processed.");
			} catch (SQLException e) {
				logger.error("Error loading bibs with oldest last extract time to process", e);
			}
		}
	}

	private static void getBibsAndItemUpdatesFromSierra(Connection pikaConn) {
//	private static void getBibsAndItemUpdatesFromSierra(Connection pikaConn, File changedBibsFile) {
		//Load unprocessed transactions
//		loadUnprocessedBibs(pikaConn, changedBibsFile);
		loadUnprocessedBibs(pikaConn);

		Long id = systemVariables.getVariableId("allow_sierra_fast_export");
		if (id != null) {
			allowFastExportMethod = systemVariables.getBooleanValuedVariable("allow_sierra_fast_export");
		} else {
			systemVariables.setVariable("allow_sierra_fast_export", "1");
		}

		lastSierraExtractTime = systemVariables.getLongValuedVariable("last_sierra_extract_time");

		//Last Update time in UTC
		// Use a buffer value to cover gaps in extraction rounds
		Integer bufferInterval = PikaConfigIni.getIntIniValue("Catalog", "ilsAPIExtractBuffer");
		if (bufferInterval == null || bufferInterval < 0) {
			bufferInterval = 300; // 5 mins
		}
		lastExtractDate = Date.from(Instant.ofEpochSecond(lastSierraExtractTime).minus(bufferInterval, ChronoUnit.SECONDS));

		Date now       = new Date();
		Date yesterday = Date.from(now.toInstant().minus(1, ChronoUnit.DAYS));
		if (lastExtractDate.before(yesterday)) {
			logger.warn("Last Extract date was more than 24 hours ago.");
			// We used to only extract the last 24 hours because there would have been a full export marc file delivered,
			// but with this process that isn't a good assumption any longer.  Now we will just issue a warning
		}

		String lastExtractDateTimeFormatted = getSierraAPIDateTimeString(lastExtractDate);
//		String deletionDateFormatted       = getSierraAPIDateString(new Date(lastExtractDate.getTime() - 24 * 60 * 60 * 1000)); // date component only, is needed for fetching deleted things
		String deletionDateFormatted        = getSierraAPIDateString(yesterday); // date component only, is needed for fetching deleted things
																						// Use yesterday to ensure we don't have any timezone issues
		long   updateTime                   = new Date().getTime() / 1000;
		logger.info("Loading records changed since {}", lastExtractDate.toString());


		setUpSqlStatements(pikaConn);
		processDeletedBibs(deletionDateFormatted, updateTime);
		getNewRecordsFromAPI(lastExtractDateTimeFormatted, updateTime);
		getChangedRecordsFromAPI(lastExtractDateTimeFormatted, updateTime);
		getNewItemsFromAPI(lastExtractDateTimeFormatted);
		getChangedItemsFromAPI(lastExtractDateTimeFormatted);
		getDeletedItemsFromAPI(deletionDateFormatted);
	}

	private static void setUpSqlStatements(Connection pikaConn) {
		try {
			getWorkForPrimaryIdentifierStmt           = pikaConn.prepareStatement("SELECT id, grouped_work_id FROM grouped_work_primary_identifiers WHERE type = ? AND identifier = ?");
			deletePrimaryIdentifierStmt               = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ? LIMIT 1");
			getAdditionalPrimaryIdentifierForWorkStmt = pikaConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers WHERE grouped_work_id = ?");
			markGroupedWorkAsChangedStmt              = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?");
			getPermanentIdByWorkIdStmt                = pikaConn.prepareStatement("SELECT permanent_id FROM grouped_work WHERE id = ?");
			isAlreadyDeletedExtractInfoStatement      = pikaConn.prepareStatement("SELECT 1 FROM ils_extract_info WHERE deleted IS NOT NULL AND indexingProfileId = ? AND ilsId = ?");
			updateExtractInfoStatement                = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined
			markDeletedExtractInfoStatement           = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted, deleted) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted), deleted=VALUES(deleted)"); // unique key is indexingProfileId and ilsId combined
			//TODO: note Starting in MariaDB 10.3.3 function VALUES() becomes VALUE(). But documentation notes "The VALUES() function can still be used even from MariaDB 10.3.3, but only in INSERT ... ON DUPLICATE KEY UPDATE statements; it's a syntax error otherwise."
//			deleteGroupedWorkStmt                     = pikaConn.prepareStatement("DELETE from grouped_work where id = ?");
		} catch (Exception e) {
			logger.error("Error setting up prepared statements for Record extraction processing", e);
		}
	}

	/**
	 * Build a string that is in the format for a dateTime expected by the Sierra API from a Date
	 * (in ISO 8601 format (yyyy-MM-dd'T'HH:mm:ssZZ))
	 *
	 * @param dateTime the dateTime to format
	 * @return a string representing the dateTime in the expected format
	 */
	private static String getSierraAPIDateTimeString(Date dateTime) {
		SimpleDateFormat dateTimeFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
		dateTimeFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		return dateTimeFormatter.format(dateTime);
	}

	private static String getSierraAPIDateTimeString(Instant dateTime) {
		return getSierraAPIDateTimeString(Date.from(dateTime));
	}

	/**
	 * Build a string that is in the format for a date (date only, no time component) expected by the Sierra API from a Date
	 * (in ISO 8601 format (yyyy-MM-dd'T'HH:mm:ssZZ))
	 *
	 * @param dateTime the dateTime to format
	 * @return a string representing the date (date only, no time component) in the expected format
	 */
	private static String getSierraAPIDateString(Date dateTime) {
		SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd");
		dateFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		return dateFormatter.format(dateTime);
	}

	private static int updateBibs(Connection pikaConn) {
		//This section uses the batch method which doesn't work in Sierra because we are limited to 100 exports per hour

		addNoteToExportLog("Found " + allBibsToUpdate.size() + " bib records that need to be updated with data from Sierra.");

		if (allBibsToUpdate.size() < 50) {
			loadOldestLastExtractedBibs(pikaConn);
		}

		int numProcessed = 0;
		if (!allBibsToUpdate.isEmpty()) {
			boolean hasMoreIdsToProcess;
			int  batchSize       = 25;
			long exportStartTime = new Date().getTime() / 1000;
			do {
				hasMoreIdsToProcess = false;
				StringBuilder   idsToProcess = new StringBuilder();
				int             maxIndex     = Math.min(allBibsToUpdate.size(), batchSize);
				ArrayList<Long> ids          = new ArrayList<>();
				for (int i = 0; i < maxIndex; i++) {
					if (idsToProcess.length() > 0) {
						idsToProcess.append(",");
					}
					Long lastId = allBibsToUpdate.last();
					idsToProcess.append(lastId);
					ids.add(lastId);
					allBibsToUpdate.remove(lastId);
				}
				if (!ids.isEmpty()) {
					updateMarcAndRegroupRecordIds(idsToProcess, ids);
				}
				numProcessed += maxIndex;
				if (numProcessed % 250 == 0 || allBibsToUpdate.isEmpty()) {
					addNoteToExportLog("Processed " + numProcessed);
					if (minutesToProcessExport > 0 && (new Date().getTime() / 1000) - exportStartTime >= minutesToProcessExport * 60L) {
						addNoteToExportLog("Stopping export due to time constraints, there are " + allBibsToUpdate.size() + " bibs remaining to be processed.");
						break;
					}
				}
				if (!allBibsToUpdate.isEmpty()) {
					hasMoreIdsToProcess = true;
					updateSierraExtractLogNumToProcess(pikaConn, numProcessed);
				}
			} while (hasMoreIdsToProcess);
		}

		return numProcessed;
	}

	private static void exportHolds(Connection sierraConn, Connection pikaConn) {
		Savepoint startOfHolds = null;
		try {
			logger.info("Starting export of holds");

			//Start a transaction so we can rebuild an entire table
			startOfHolds = pikaConn.setSavepoint();
			pikaConn.setAutoCommit(false);

			HashMap<String, Long> numHoldsByBib    = new HashMap<>();
			HashMap<String, Long> numHoldsByVolume = new HashMap<>();
			//Export bib level holds
			try (
					PreparedStatement bibHoldsStmt = sierraConn.prepareStatement("SELECT COUNT(hold.id) AS numHolds, record_type_code, record_num FROM sierra_view.hold LEFT JOIN sierra_view.record_metadata ON hold.record_id = record_metadata.id WHERE record_type_code = 'b' AND (status = '0' OR status = 't') GROUP BY record_type_code, record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet bibHoldsRS = bibHoldsStmt.executeQuery()
			) {
				while (bibHoldsRS.next()) {
					String bibId    = bibHoldsRS.getString("record_num");
					Long   numHolds = bibHoldsRS.getLong("numHolds");
					bibId = getfullSierraBibId(bibId);
					numHoldsByBib.put(bibId, numHolds);
				}
			}

			boolean exportItemHolds = PikaConfigIni.getBooleanIniValue("Catalog", "exportItemHolds");
			if (exportItemHolds) {
				//Export item level holds
				try (
						PreparedStatement itemHoldsStmt = sierraConn.prepareStatement("SELECT COUNT(hold.id) AS numHolds, record_num\n" +
								"FROM sierra_view.hold \n" +
								"INNER JOIN sierra_view.bib_record_item_record_link ON hold.record_id = item_record_id \n" +
								"INNER JOIN sierra_view.record_metadata ON bib_record_item_record_link.bib_record_id = record_metadata.id \n" +
								"WHERE status = '0' OR status = 't' " +
								"GROUP BY record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
						ResultSet itemHoldsRS = itemHoldsStmt.executeQuery()
				) {
					while (itemHoldsRS.next()) {
						String bibId = itemHoldsRS.getString("record_num");
						bibId = getfullSierraBibId(bibId);
						Long numHolds = itemHoldsRS.getLong("numHolds");
						if (numHoldsByBib.containsKey(bibId)) {
							numHoldsByBib.put(bibId, numHolds + numHoldsByBib.get(bibId));
						} else {
							numHoldsByBib.put(bibId, numHolds);
						}
					}
				}
			}

			//Export volume level holds
			try (
					PreparedStatement volumeHoldsStmt = sierraConn.prepareStatement(
							"SELECT COUNT(hold.id) AS numHolds, bib_metadata.record_num AS bib_num, volume_metadata.record_num AS volume_num \n" +
									"FROM sierra_view.hold \n" +
									"INNER JOIN sierra_view.bib_record_volume_record_link ON hold.record_id = volume_record_id \n" +
									"INNER JOIN sierra_view.record_metadata AS volume_metadata ON bib_record_volume_record_link.volume_record_id = volume_metadata.id \n" +
									"INNER JOIN sierra_view.record_metadata AS bib_metadata ON bib_record_volume_record_link.bib_record_id = bib_metadata.id \n" +
									"WHERE status = '0' OR status = 't'\n" +
									"GROUP BY bib_metadata.record_num, volume_metadata.record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet volumeHoldsRS = volumeHoldsStmt.executeQuery()
			) {
				while (volumeHoldsRS.next()) {
					String bibId    = volumeHoldsRS.getString("bib_num");
					String volumeId = volumeHoldsRS.getString("volume_num");
					bibId    = getfullSierraBibId(bibId);
					volumeId = getfullSierraVolumeId(volumeId);
					Long numHolds = volumeHoldsRS.getLong("numHolds");
					//Do not count these in
					if (numHoldsByBib.containsKey(bibId)) {
						numHoldsByBib.put(bibId, numHolds + numHoldsByBib.get(bibId));
					} else {
						numHoldsByBib.put(bibId, numHolds);
					}
					if (numHoldsByVolume.containsKey(volumeId)) {
						numHoldsByVolume.put(volumeId, numHolds + numHoldsByVolume.get(bibId));
					} else {
						numHoldsByVolume.put(volumeId, numHolds);
					}
				}
			}

			pikaConn.prepareCall("TRUNCATE TABLE ils_hold_summary").executeQuery();

			try (PreparedStatement addIlsHoldSummary = pikaConn.prepareStatement("INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)")) {

				for (String bibId : numHoldsByBib.keySet()) {
					addIlsHoldSummary.setString(1, bibId);
					addIlsHoldSummary.setLong(2, numHoldsByBib.get(bibId));
					addIlsHoldSummary.executeUpdate();
				}

				for (String volumeId : numHoldsByVolume.keySet()) {
					addIlsHoldSummary.setString(1, volumeId);
					addIlsHoldSummary.setLong(2, numHoldsByVolume.get(volumeId));
					addIlsHoldSummary.executeUpdate();
				}
			}

			try {
				pikaConn.commit();
				pikaConn.setAutoCommit(true);
			} catch (Exception e) {
				logger.warn("error committing hold updates rolling back", e);
				pikaConn.rollback(startOfHolds);
			}

		} catch (Exception e) {
			logger.error("Unable to export holds from Sierra. Rolling back ils holds table", e);
			if (startOfHolds != null) {
				try {
					pikaConn.rollback(startOfHolds);
				} catch (Exception e1) {
					logger.error("Unable to rollback due to exception", e1);
				}
			}
		}
		logger.info("Finished exporting holds");
	}

	private static String getfullSierraBibId(String bibId) {
		return ".b" + bibId + getCheckDigit(bibId);
	}

	private static String getfullSierraBibId(Long bibId) {
		return getfullSierraBibId(bibId.toString());
	}

	private static String getfullSierraItemId(String itemId) {
		return ".i" + itemId + getCheckDigit(itemId);
	}

	private static String getfullSierraVolumeId(String volumeId) {
		return ".j" + volumeId + getCheckDigit(volumeId);
	}


	private static PreparedStatement getWorkForPrimaryIdentifierStmt;
	private static PreparedStatement getAdditionalPrimaryIdentifierForWorkStmt;
	private static PreparedStatement deletePrimaryIdentifierStmt;
	private static PreparedStatement markGroupedWorkAsChangedStmt;
	//	private static PreparedStatement deleteGroupedWorkStmt;
	private static PreparedStatement getPermanentIdByWorkIdStmt;

	private static void processDeletedBibs(String lastExtractDateFormatted, long updateTime) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to fetch BibIds for deleted records since " + lastExtractDateFormatted);

		boolean hasMoreRecords;
		int     bufferSize          = 1000;
		long    recordIdToStartWith = 1;
		int     numDeletions        = 0;
		int     numAlreadyDeleted   = 0;

		do {
			hasMoreRecords = false;

			String url = apiBaseUrl + "/bibs/?deletedDate=[" + lastExtractDateFormatted + ",]&fields=id&deleted=true&limit=" + bufferSize + "&id=[" + recordIdToStartWith + ",]";
			//Adding &id=[x,] should make the query "sort by" record id value.

			JSONObject deletedRecords = callSierraApiURL(url, debug);

			if (deletedRecords != null) {
				try {
					JSONArray entries = deletedRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						Long       bibId  = curBib.getLong("id");
						allDeletedIds.add(bibId);
					}
					//If nothing has been deleted, iii provides entries, but not a total
					if ((deletedRecords.has("total") && deletedRecords.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						recordIdToStartWith = allDeletedIds.last() + 1; // Get the largest current value to use as starting point in next round
					}
				} catch (Exception e) {
					logger.error("Error processing deleted bibs", e);
				}
			}
		} while (hasMoreRecords);


		if (!allDeletedIds.isEmpty()) {
			for (Long id : allDeletedIds) {
				if (!isAlreadyMarkedDeleted(id)) {
					if (deleteRecord(updateTime, id) && markRecordDeletedInExtractInfo(id)) {
						numDeletions++;
					} else {
						logger.info("Failed to delete from index bib Id : {}", id);
					}
				} else {
					numAlreadyDeleted++;
				}
			}
			addNoteToExportLog("Finished processing deleted records, of " + allDeletedIds.size() + " records reported by the API, " + numDeletions + " were deleted, " + numAlreadyDeleted + " were already deleted.");
		} else {
			addNoteToExportLog("No deleted records found");
		}
	}

	/**
	 * Checks the API if the Bib is deleted or suppressed
	 *
	 * @param id Bib Id without the .b prefix or the trailing check digit
	 * @return
	 */
	private static boolean isDeletedInAPI(long id) {
		String     url               = apiBaseUrl + "/bibs/" + id + "?fields=id,deleted,suppressed";
		JSONObject isDeletedResponse = callSierraApiURL(url, debug);
		if (isDeletedResponse != null) {
			try {
				if (isDeletedResponse.has("deleted") && isDeletedResponse.getBoolean("deleted")) {
					return true;
				} else {
					return isDeletedResponse.has("suppressed") && isDeletedResponse.getBoolean("suppressed");
				}
			} catch (JSONException e) {
				logger.error("Error checking if a bib was deleted", e);
			}
		}
		return false;
	}

	private static boolean deleteRecord(long updateTime, Long idFromAPI) {
		String bibId = getfullSierraBibId(idFromAPI);
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

								logger.info("Sierra API extract deleted Group Work {} from index. Investigate if it is an anomalous deletion by the Sierra API extract", permanentId);

								// See https://marmot.myjetbrains.com/youtrack/issue/D-2364
								return true;
							}
						}
					}
				} else {
					logger.info("Found no grouped work primary identifiers for bib id : {}", bibId);
					if (isDeletedInAPI(idFromAPI)) {
						return true;
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error processing deleted bibs", e);
		}
		return false;
	}

	private static void deleteGroupedWorkFromSolr(String id) {
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
			logger.error("Error deleting grouped work primary identifier {} from database ", primaryIdentifierId, e);
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

	private static void getChangedRecordsFromAPI(String lastExtractDateFormatted, long updateTime) {
		addNoteToExportLog("Starting to fetch bib Ids changed since " + lastExtractDateFormatted);
		boolean       hasMoreRecords;
		int           bufferSize           = 1000;
		long          recordIdToStartWith  = 1;
		int           numChangedRecords    = 0;
		int           numSuppressedRecords = 0;
		TreeSet<Long> changedBibs          = new TreeSet<>();

		do {
			hasMoreRecords = false;
			String     url            = apiBaseUrl + "/bibs/?updatedDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,suppressed&limit=" + bufferSize + "&id=[" + recordIdToStartWith + ",]";
			JSONObject createdRecords = callSierraApiURL(url, debug);
			if (createdRecords != null) {
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						Long       bibId  = curBib.getLong("id");
						changedBibs.add(bibId); // Need to maintain a clean sort of ids fetched by this method only
						boolean isSuppressed = false;
						if (curBib.has("suppressed")) {
							isSuppressed = curBib.getBoolean("suppressed");
						}
						if (isSuppressed) {
							allDeletedIds.add(bibId);
							if (deleteRecord(updateTime, bibId) && markRecordDeletedInExtractInfo(bibId)) {
								numSuppressedRecords++;
							} else {
								logger.info("Failed to delete from index bib Id : {}", bibId);
							}

						} else {
							allBibsToUpdate.add(bibId);
							numChangedRecords++;
						}
					}
					if ((createdRecords.has("total") && createdRecords.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						recordIdToStartWith = changedBibs.last() + 1; // Get the largest current value to use as starting point in next round
					}

				} catch (Exception e) {
					logger.error("Error processing changed bibs", e);
				}
			} else {
				addNoteToExportLog("No changed records found");
			}
		} while (hasMoreRecords);
		addNoteToExportLog("Finished processing changed records, there were " + numChangedRecords + " changed records and " + numSuppressedRecords + " suppressed records");
	}

	private static void getNewRecordsFromAPI(String lastExtractDateFormatted, long updateTime) {
		addNoteToExportLog("Starting to fetch Ids for records created since " + lastExtractDateFormatted);
		boolean       hasMoreRecords;
		int           bufferSize           = 1000;
		long          recordIdToStartWith  = 1;
		int           numNewRecords        = 0;
		int           numSuppressedRecords = 0;
		TreeSet<Long> createdBibs          = new TreeSet<>();

		do {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/bibs/?createdDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,suppressed&limit=" + bufferSize + "&id=[" + recordIdToStartWith + ",]";
			//Adding &id=[x,] should make the query "sort by" record id value.
			JSONObject createdRecords = callSierraApiURL(url, debug);
			if (createdRecords != null) {
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib       = entries.getJSONObject(i);
						boolean    isSuppressed = false;
						Long       bibId        = curBib.getLong("id");
						createdBibs.add(bibId); // Need to maintain a clean sort of ids fetched by this method only
						if (curBib.has("suppressed")) {
							isSuppressed = curBib.getBoolean("suppressed");
						}
						if (isSuppressed) {
							allDeletedIds.add(bibId);
							if (deleteRecord(updateTime, bibId) && markRecordDeletedInExtractInfo(bibId)) {
								numSuppressedRecords++;
							} else {
								logger.info("Failed to delete from index newly created but suppressed bib Id : {}", bibId);
							}
						} else {
							allBibsToUpdate.add(bibId);
							numNewRecords++;
						}
					}
					if ((createdRecords.has("total") && createdRecords.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreRecords      = true;
						recordIdToStartWith = createdBibs.last() + 1; // Get the largest current value to use as starting point in next round
					}
				} catch (Exception e) {
					logger.error("Error processing newly created bibs", e);
				}
			} else {
				addNoteToExportLog("No newly created records found");
			}
		} while (hasMoreRecords);
		addNoteToExportLog("Finished processing newly created records, " + numNewRecords + " were new and " + numSuppressedRecords + " were suppressed");
	}

	private static void getNewItemsFromAPI(String lastExtractDateFormatted) {
		addNoteToExportLog("Starting to fetch bibIds with items created since " + lastExtractDateFormatted);
		boolean       hasMoreRecords;
		int           bufferSize        = 1000;
		long          itemIdToStartWith = 1;
		int           numNewRecords     = 0;
		TreeSet<Long> newItems          = new TreeSet<>();

		do {
			hasMoreRecords = false;
			String     url;
			if(itemIdToStartWith == 1){
				url = apiBaseUrl + "/items/?createdDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize;
			}else {
				url = apiBaseUrl + "/items/?createdDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize + "&id=[" + itemIdToStartWith + ",]";
			}
			JSONObject createdItems = callSierraApiURL(url, debug);
			if (createdItems != null) {
				try {
					JSONArray entries = createdItems.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curItem = entries.getJSONObject(i);
						Long       itemId  = curItem.getLong("id");
						newItems.add(itemId);
						JSONArray bibIds = curItem.getJSONArray("bibIds");
						for (int j = 0; j < bibIds.length(); j++) {
							Long bibId = bibIds.getLong(j);
							if (!allDeletedIds.contains(bibId) && !allBibsToUpdate.contains(bibId)) {
								allBibsToUpdate.add(bibId);
								numNewRecords++;
							}
						}
					}
					if ((createdItems.has("total") && createdItems.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreRecords    = true;
						itemIdToStartWith = newItems.last() + 1; // Get largest current value to use as starting point in next round
					}
					//Get the grouped work id for the new bib
				} catch (Exception e) {
					logger.error("Error processing newly created items", e);
				}
			} else {
				addNoteToExportLog("No newly created items found");
			}
		} while (hasMoreRecords);
		addNoteToExportLog("Finished fetching bibIds for newly created items, " + numNewRecords + " additional bibs to update");
	}

	private static void measureDelayInItemsAPIupdates() {
		Instant timeLimitUsedInRequest    = Instant.now();
		String  startofTestDateTimeString = getSierraAPIDateTimeString(timeLimitUsedInRequest);
		String url                        = apiBaseUrl + "/items/?updatedDate=[" + startofTestDateTimeString + ",]&deleted=false&fields=id,updatedDate,bibIds&limit=1"; //&id=[1,]";
		int     items                     = 0;
		do {
			Instant    timeOfRequest = Instant.now();
			JSONObject delayTest     = callSierraApiURL(url, debug);
			if (delayTest != null && delayTest.has("total")) {
				try {
					items = delayTest.getInt("total");
					if (items > 0 && delayTest.has("entries")) {
						JSONArray entries     = delayTest.getJSONArray("entries");
						String    updateDate  = entries.getJSONObject(0).getString("updatedDate");
						Instant   updateTime  = Instant.parse(updateDate); // parse uses ISO 8601 formats (expecting "yyyy-MM-dd'T'HH:mm:ss'Z'")
						long      difference  = Duration.between(updateTime, timeOfRequest).getSeconds();
						long      difference2 = Duration.between(timeLimitUsedInRequest, updateTime).getSeconds();
						long      difference3 = Duration.between(timeLimitUsedInRequest, timeOfRequest).getSeconds();
//						long timeToGetMeasurement = Duration.between(timeLimitUsedInRequest, Instant.now()).getSeconds();
						long delay = difference - difference2;
						logger.info("Difference between item update time and the time of the request call is : {} seconds", difference);
						logger.info("Difference between item update time and the time limit in the request is  : {} seconds", difference2);
						logger.info("Difference between the time of this request call and the time limit in the request is  : {} seconds", difference3);
						logger.info("Delay is currently : {} seconds", delay);

						System.out.println("Difference between item update time and the time of the request call is : " + difference + " seconds");
						System.out.println("Difference between item update time and the time limit in the request is  : " + difference2 + " seconds");
						System.out.println("Difference between the time of this request call and the time limit in the request is  : " + difference3 + " seconds");
						System.out.println("Delay is currently : " + delay + " seconds");
					}
				} catch (/*ParseException | */JSONException e) {
					logger.error("Error processing while measuring delay", e);
				}
			}
			if (items == 0) {
				try {
					Thread.sleep(1000); // Wait a second before next round
				} catch (InterruptedException e) {
					logger.error("Error processing while measuring delay", e);
				}
			}
		} while (items == 0);
	}

	private static void getChangedItemsFromAPI(String lastExtractDateFormatted) {
		addNoteToExportLog("Starting to fetch bibIds with items that have updated since " + lastExtractDateFormatted);
		boolean       hasMoreItems;
		int           bufferSize        = 1000;
		long          itemIdToStartWith = 1;
		int           numChangedItems   = 0;
		int           numNewBibs        = 0;
		TreeSet<Long> changedItems      = new TreeSet<>();

		do {
			hasMoreItems = false;
			String     url;
			if(itemIdToStartWith == 1) {
				url = apiBaseUrl + "/items/?updatedDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize;
			}else
			{
				url = apiBaseUrl + "/items/?updatedDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize + "&id=[" + itemIdToStartWith + ",]";
			}
			JSONObject changedItemsJSON = callSierraApiURL(url, debug);
			if (changedItemsJSON != null) {
				try {
					JSONArray entries = changedItemsJSON.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curItem = entries.getJSONObject(i);
						Long       itemId  = curItem.getLong("id");
						changedItems.add(itemId);
						numChangedItems++;
						if (curItem.has("bibIds")) {
							JSONArray bibIds = curItem.getJSONArray("bibIds");
							for (int j = 0; j < bibIds.length(); j++) {
								Long bibId = bibIds.getLong(j);
								if (!allDeletedIds.contains(bibId) && !allBibsToUpdate.contains(bibId)) {
									allBibsToUpdate.add(bibId);
									numNewBibs++;
								}
							}
						} else {
							logger.error("Entry for items update did not contain BibIds : {}", curItem);
						}
					}
					if ((changedItemsJSON.has("total") && changedItemsJSON.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreItems      = true;
						itemIdToStartWith = changedItems.last() + 1; // Get largest current value to use as starting point in next round
					}
				} catch (Exception e) {
					logger.error("Error processing updated items", e);
				}
			} else {
				addNoteToExportLog("No updated items found");
			}
		} while (hasMoreItems);
		addNoteToExportLog("Finished fetching Ids for updated items " + numChangedItems + ", this added " + numNewBibs + " bibs to process");
	}

	private static void getDeletedItemsFromAPI(String lastExtractDateFormatted) {
		//Get a list of bibs with deleted items
		addNoteToExportLog("Starting to fetch bib Ids with items deleted since " + lastExtractDateFormatted);
		boolean       hasMoreItems;
		int           bufferSize        = 1000;
		long          itemIdToStartWith = 1;
		int           numDeletedItems   = 0;
		int           numBibsToUpdate   = 0;
		TreeSet<Long> deletedItemIds    = new TreeSet<>();

		do {
			hasMoreItems = false;
			String url;
			if(itemIdToStartWith == 1) {
				url = apiBaseUrl + "/items/?deletedDate=[" + lastExtractDateFormatted + ",]&deleted=true&fields=id,bibIds&limit=" + bufferSize;
			}else{
				url = apiBaseUrl + "/items/?deletedDate=[" + lastExtractDateFormatted + ",]&deleted=true&fields=id,bibIds&limit=" + bufferSize + "&id=[" + itemIdToStartWith + ",]";
			}
			//TODO: BibIds aren't being returned
			//From documentation (https://techdocs.iii.com/sierraapi/Content/zReference/queryParameters.htm)
			// Deleted records return only their id, deletedDate, and deleted properties.
			JSONObject deletedItems = callSierraApiURL(url, debug);
			if (deletedItems != null) {
				try {
					JSONArray entries = deletedItems.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curItem = entries.getJSONObject(i);
						Long       itemId  = curItem.getLong("id");
						deletedItemIds.add(itemId); // Need to maintain a clean sort of ids fetched by this method only
						JSONArray bibIds = curItem.getJSONArray("bibIds");
						numDeletedItems++;
						for (int j = 0; j < bibIds.length(); j++) {
							Long bibId = bibIds.getLong(j);
							if (!allDeletedIds.contains(bibId) && !allBibsToUpdate.contains(bibId)) {
								allBibsToUpdate.add(bibId);
								numBibsToUpdate++;
							}
						}
					}
					if ((deletedItems.has("total") && deletedItems.getLong("total") >= bufferSize) || entries.length() >= bufferSize) {
						hasMoreItems      = true;
						itemIdToStartWith = deletedItemIds.last() + 1; // Get the largest current value to use as starting point in next round
					}
					//Get the grouped work id for the new bib
				} catch (Exception e) {
					logger.error("Error processing deleted items", e);
				}
			} else {
				addNoteToExportLog("No deleted items found");
			}
		} while (hasMoreItems);
		addNoteToExportLog("Finished fetching " + numDeletedItems + " deleted items found, " + numBibsToUpdate + " additional bibs to update");
	}

	private static final MarcFactory marcFactory = MarcFactory.newInstance();

	private static boolean updateMarcAndRegroupRecordId(Long id) {
		try {
			String     sierraUrl   = apiBaseUrl + "/bibs/" + id + "/marc";
			JSONObject marcResults = getMarcJSONFromSierraApiURL(sierraUrl, id);
			if (marcResults != null) {
				if (marcResults.has("httpStatus")) {
					final int code = marcResults.getInt("code");
					if (code == 107) {
						//TODO: test if the API Confirms is deleted/suppressed, then remove ( This can happen when the deletion was originally missed)
						//This record was deleted
						if (isDeletedInAPI(id)) {
							if (deleteRecord(new Date().getTime() / 1000, id)) {
								markRecordDeletedInExtractInfo(id);
								logger.debug("id {} was deleted", id);
								return true;
							}
						}
						logger.error("Received error code 107 but record is not deleted or suppressed {}", getfullSierraBibId(id));
						return false;
					} else if (code == 100){
						if (marcResults.has("name") && marcResults.getString("name").startsWith("InvalidMARCException")){
							logger.warn("Sierra API reporting '{}', please investigate & correct bib {}", marcResults.getString("name"), getfullSierraBibId(id));
							return false;
						}

					} else {
						logger.error("Error response calling {}", sierraUrl);
						logger.error("Unknown error, code : {}, {}", code, marcResults);
						return false;
					}
				}
				String    leader     = marcResults.has("leader") ? marcResults.getString("leader") : "";
				Record    marcRecord = new SortedMarcFactoryImpl().newRecord(leader); // Use the SortedMarcFactoryImpl (which puts the tags in numerical order
				JSONArray fields     = marcResults.getJSONArray("fields");
				if (leader.isEmpty()){
					logger.warn("Sierra MARC JSON missing leader information for {}", id);
				}
				for (int i = 0; i < fields.length(); i++) {
					JSONObject                   fieldData = fields.getJSONObject(i);
					@SuppressWarnings("unchecked") Iterator<String> tags      = (Iterator<String>) fieldData.keys();
					while (tags.hasNext()) {
						String tag = tags.next();
						if (tag.equals(indexingProfile.recordNumberTag) || tag.equals(indexingProfile.sierraRecordFixedFieldsTag)) {
							// Skip duplicating tags that appear here
							// These are strange special purpose tags used by other vendors for flatirons. (some kind of authority control for the record Number tag)
							// Skip for all sites since this can cause problems for suppression and record number identification.
							// For now, warn for any site but mln1 or mln2
							logger.log((isMLN2 || isMLN1) ? Level.DEBUG : Level.WARN, "API Reported regular tag with tag number ({}) matching the recordNumber ({}) or Fixed Fields ({}) : {}", tag, indexingProfile.recordNumberTag, indexingProfile.sierraRecordFixedFieldsTag, id);
							continue;
						}
							if (fieldData.get(tag) instanceof JSONObject) {
							JSONObject fieldDataDetails = fieldData.getJSONObject(tag);
							char       ind1             = fieldDataDetails.getString("ind1").charAt(0);
							char       ind2             = fieldDataDetails.getString("ind2").charAt(0);
							DataField  dataField        = marcFactory.newDataField(tag, ind1, ind2);
							JSONArray  subfields        = fieldDataDetails.getJSONArray("subfields");
							for (int j = 0; j < subfields.length(); j++) {
								JSONObject subfieldData         = subfields.getJSONObject(j);
								String     subfieldIndicatorStr = (String) subfieldData.keys().next();
								char       subfieldIndicator    = subfieldIndicatorStr.charAt(0);
								String     subfieldValue        = subfieldData.getString(subfieldIndicatorStr); //TODO: this doesn't interpret some slash-u notations
								dataField.addSubfield(marcFactory.newSubfield(subfieldIndicator, subfieldValue));
							}
								marcRecord.addVariableField(dataField);

						} else {
							String fieldValue = fieldData.getString(tag);
							marcRecord.addVariableField(marcFactory.newControlField(tag, fieldValue));
						}
					}
				}
				logger.debug("Converted JSON to MARC for Bib");

				// Prepare Record Number tag
				DataField recordNumberField = marcFactory.newDataField(indexingProfile.recordNumberTag, ' ', ' ', "" + indexingProfile.recordNumberField /*convert to string*/, getfullSierraBibId(id));

				//Load Sierra Fixed Field / Bib Level Tag
				JSONObject fixedFieldResults = getMarcJSONFromSierraApiURL(apiBaseUrl + "/bibs/" + id + "?fields=fixedFields,locations", id);
				if (fixedFieldResults != null && !fixedFieldResults.has("code")) {
					DataField        sierraFixedField = marcFactory.newDataField(indexingProfile.sierraRecordFixedFieldsTag, ' ', ' ');
					final JSONObject fixedFields      = fixedFieldResults.getJSONObject("fixedFields");
					if (indexingProfile.bcode3Subfield != ' ') {
						String bCode3 = fixedFields.getJSONObject("31").getString("value");
						sierraFixedField.addSubfield(marcFactory.newSubfield(indexingProfile.bcode3Subfield, bCode3));
					}
					if (indexingProfile.materialTypeSubField != ' ') {
						String matType = fixedFields.getJSONObject("30").getString("value");
						sierraFixedField.addSubfield(marcFactory.newSubfield(indexingProfile.materialTypeSubField, matType));
					}
					if (indexingProfile.sierraLanguageFixedField != ' ') {
						String languageCode = fixedFields.getJSONObject("24").getString("value");
						sierraFixedField.addSubfield(marcFactory.newSubfield(indexingProfile.sierraLanguageFixedField, languageCode));
					}
					if (bibLevelLocationsSubfield != ' ') { // Probably should be added to the indexing profile at some point
						if (fixedFields.has("26")) {
							String location = fixedFields.getJSONObject("26").getString("value");
							if (location.equalsIgnoreCase("multi")) {
								JSONArray locationsJSON = fixedFieldResults.getJSONArray("locations");
								for (int k = 0; k < locationsJSON.length(); k++) {
									location = locationsJSON.getJSONObject(k).getString("code");
									sierraFixedField.addSubfield(marcFactory.newSubfield(bibLevelLocationsSubfield, location));
								}
							} else {
								sierraFixedField.addSubfield(marcFactory.newSubfield(bibLevelLocationsSubfield, location));
								if (isMLN2){
									recordNumberField.addSubfield(marcFactory.newSubfield('b', location));
								}
							}
						}
					}
					marcRecord.addVariableField(sierraFixedField);

					//Add the identifier
					marcRecord.addVariableField(recordNumberField);

					if (fixedFields.has("27")) { // Copies fixed field
						//Get Items for the bib record
						getItemsForBib(id, marcRecord);
						logger.debug("Processed items for Bib");
					} else {
						logger.warn("Bib : {} does not have a copies fixed field", id);
					}

					// Write marc to File and Do the Record Grouping
					groupAndWriteTheMarcRecord(marcRecord, id);

				} else {
					logger.error("Error exporting marc record for {} call for fixed fields returned an error code or null", id);
					return false;
				}

			} else {
				logger.error("Error exporting marc record for {} call returned null", id);
				return false;
			}
		} catch (Exception e) {
			logger.error("Error in updateMarcAndRegroupRecordId processing bib {} from Sierra API", id, e);
			return false;
		}
		return true;
	}

	private static String groupAndWriteTheMarcRecord(Record marcRecord) {
		return groupAndWriteTheMarcRecord(marcRecord, null);
	}

	private static String groupAndWriteTheMarcRecord(Record marcRecord, Long id) {
		String           identifier       = null;
		RecordIdentifier recordIdentifier = null;
		if (id != null) {
			identifier = getfullSierraBibId(id);
		} else {
			try {
				recordIdentifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(marcRecord, indexingProfile.sourceName, indexingProfile.doAutomaticEcontentSuppression);
				if (recordIdentifier != null) {
					identifier = recordIdentifier.getIdentifier();
				} else {
					logger.warn("Failed to set record identifier in record grouper getPrimaryIdentifierFromMarcRecord(); possible error or automatic eontent suppression trigger.");
				}
			} catch (Exception e) {
				logger.error("catch for id {} or record Identifier {}", id, recordIdentifier, e);
				throw e;
			}
		}
		if (identifier != null && !identifier.isEmpty()) {
			logger.debug("Writing marc record for {}", identifier);
			writeMarcRecord(marcRecord, identifier);
			logger.debug("Wrote marc record for {}", identifier);
		} else {
			logger.warn("Failed to set record identifier in record grouper getPrimaryIdentifierFromMarcRecord(); possible error or automatic econtent suppression trigger.");
		}

		//Setup the grouped work for the record.  This will take care of either adding it to the proper grouped work
		//or creating a new grouped work
		final boolean grouped = (recordIdentifier != null) ? recordGroupingProcessor.processMarcRecord(marcRecord, true, recordIdentifier) : recordGroupingProcessor.processMarcRecord(marcRecord, true);
		if (!grouped) {
			logger.warn("{} was not grouped", identifier);
		} else {
			logger.debug("Finished record grouping for {}", identifier);
		}
		return identifier;
	}


	private static final SimpleDateFormat sierraAPIDateFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");

	private static void getItemsForBib(Long id, Record marcRecord) {
		//Get a list of all items
		boolean hasMoreItems;
		long    startTime         = new Date().getTime();
		int     bufferSize        = 1000;
		long    itemIdToStartWith = 1;

		do {
			hasMoreItems = false;
			//This will return a 404 error if all items are suppressed or if the record has no items
			JSONObject itemIds;
			if(itemIdToStartWith == 1){
				itemIds = callSierraApiURL(apiBaseUrl + "/items/?limit=" + bufferSize + "&deleted=false&suppressed=false&fields=updatedDate,createdDate,location,status,barcode,callNumber,itemType,fixedFields,varFields&bibIds=" + id, debug);
			}else{
				itemIds = callSierraApiURL(apiBaseUrl + "/items/?limit=" + bufferSize + "&id=[" + itemIdToStartWith + ",]&deleted=false&suppressed=false&fields=updatedDate,createdDate,location,status,barcode,callNumber,itemType,fixedFields,varFields&bibIds=" + id, debug);
			}
			if (itemIds != null) {
				try {
					if (itemIds.has("code")) {
						if (itemIds.getInt("code") != 404) {
							logger.error("Error getting information about items {}", itemIds);
						}
					} else {
						boolean   lookingForEContent = !indexingProfile.APIItemEContentExportFieldTag.isEmpty();
						String    itemId             = "0"; // Initialize just to avoid having to check later
						JSONArray entries            = itemIds.getJSONArray("entries");
						if (logger.isDebugEnabled()) {
							logger.debug("fetching items for " + id + " elapsed time " + (new Date().getTime() - startTime) + "ms found " + entries.length());
						}
						for (int i = 0; i < entries.length(); i++) {
							JSONObject curItem     = entries.getJSONObject(i);
							JSONObject fixedFields = curItem.getJSONObject("fixedFields");
							JSONArray  varFields   = curItem.getJSONArray("varFields");
							DataField  itemField   = marcFactory.newDataField(indexingProfile.itemTag, ' ', ' ');
							itemId = curItem.getString("id");
							//Record Number
							if (indexingProfile.itemRecordNumberSubfield != ' ') {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.itemRecordNumberSubfield, getfullSierraItemId(itemId)));
							}
							//barcode
							if (curItem.has("barcode")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.barcodeSubfield, curItem.getString("barcode")));
							}
							//location
							if (indexingProfile.locationSubfield != ' ' && curItem.has("location")) {
								String locationCode = curItem.getJSONObject("location").getString("code");
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.locationSubfield, locationCode));
							}
							//status
							if (curItem.has("status")) {
								String statusCode = curItem.getJSONObject("status").getString("code");
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.itemStatusSubfield, statusCode));
								if (curItem.getJSONObject("status").has("duedate")) {
									Date dueDate = sierraAPIDateFormatter.parse(curItem.getJSONObject("status").getString("duedate"));
									itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, indexingProfile.dueDateFormatter.format(dueDate)));
								} else {
									itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, ""));
								}
							} else {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, ""));
							}
							//total checkouts
							if (indexingProfile.totalCheckoutsSubfield != ' ' && fixedFields.has("76")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.totalCheckoutsSubfield, fixedFields.getJSONObject("76").getString("value")));
							}
							//last year checkouts
							if (indexingProfile.lastYearCheckoutsSubfield != ' ' && fixedFields.has("110")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.lastYearCheckoutsSubfield, fixedFields.getJSONObject("110").getString("value")));
							}
							//year to date checkouts
							if (indexingProfile.yearToDateCheckoutsSubfield != ' ' && fixedFields.has("109")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.yearToDateCheckoutsSubfield, fixedFields.getJSONObject("109").getString("value")));
							}
							//total renewals
							if (indexingProfile.totalRenewalsSubfield != ' ' && fixedFields.has("77")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.totalRenewalsSubfield, fixedFields.getJSONObject("77").getString("value")));
							}
							//iType
							if (indexingProfile.iTypeSubfield != ' ' && fixedFields.has("61")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.iTypeSubfield, fixedFields.getJSONObject("61").getString("value")));
							}
							//date created
							if (indexingProfile.dateCreatedSubfield != ' ' && curItem.has("createdDate")) {
								Date createdDate = sierraAPIDateFormatter.parse(curItem.getString("createdDate"));
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dateCreatedSubfield, indexingProfile.dateCreatedFormatter.format(createdDate)));
							}
							//last check in date
							if (indexingProfile.lastCheckinDateSubfield != ' ' && fixedFields.has("68")) {
								Date lastCheckin = sierraAPIDateFormatter.parse(fixedFields.getJSONObject("68").getString("value"));
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.lastCheckinDateSubfield, indexingProfile.lastCheckinFormatter.format(lastCheckin)));
							}
							//opac message field
							final boolean usesOpacMessageField = indexingProfile.opacMessageSubfield != ' ';
							if (usesOpacMessageField && fixedFields.has("108")) {
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.opacMessageSubfield, fixedFields.getJSONObject("108").getString("value")));
							}
							//icode2
							if (indexingProfile.iCode2Subfield != ' ' && (!usesOpacMessageField || indexingProfile.opacMessageSubfield != indexingProfile.iCode2Subfield) && fixedFields.has("60")) {
								// Using opacMessage field in place of the icode2 field for Northern Waters for suppression.
								// (do not fetch icode2 when it is the same value as the opac message field
								itemField.addSubfield(marcFactory.newSubfield(indexingProfile.iCode2Subfield, fixedFields.getJSONObject("60").getString("value")));
							}

							//						boolean hadCallNumberVarField = false;
							//Process variable fields
							for (int j = 0; j < varFields.length(); j++) {
								JSONObject    curVarField             = varFields.getJSONObject(j);
								String        fieldTag                = curVarField.getString("fieldTag");
								StringBuilder allFieldContent         = new StringBuilder();
								JSONArray     subfields               = null;
								boolean       isThisAnEContentItemURL = lookingForEContent && curVarField.has("marcTag") && curVarField.getString("marcTag").equals("856");
								if (curVarField.has("subfields")) {
									subfields = curVarField.getJSONArray("subfields");
									for (int k = 0; k < subfields.length(); k++) {
										JSONObject subfield = subfields.getJSONObject(k);
										if (isThisAnEContentItemURL){
											if (subfield.has("tag") && subfield.getString("tag").equalsIgnoreCase("u")){
												allFieldContent.append(subfield.getString("content"));
												break;  //Only need the url
											}
										} else {
											// Don't concatenate subfields if this is an econtent Item tag
											allFieldContent.append(subfield.getString("content"));
										}
									}
								} else {
									allFieldContent.append(curVarField.getString("content"));
								}

								if (fieldTag.equals(indexingProfile.APIItemCallNumberFieldTag)) {
									if (subfields != null) {
										//hadCallNumberVarField = true;
										for (int k = 0; k < subfields.length(); k++) {
											JSONObject subfield = subfields.getJSONObject(k);
											String     tag      = subfield.getString("tag");
											String     content  = subfield.getString("content");
											if (!indexingProfile.APIItemCallNumberPrestampSubfield.isEmpty() && tag.equalsIgnoreCase(indexingProfile.APIItemCallNumberPrestampSubfield)) {
												itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberPrestampSubfield, content));
											} else if (!indexingProfile.APIItemCallNumberSubfield.isEmpty() && tag.equalsIgnoreCase(indexingProfile.APIItemCallNumberSubfield)) {
												itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, content));
											} else if (!indexingProfile.APIItemCallNumberCutterSubfield.isEmpty() && tag.equalsIgnoreCase(indexingProfile.APIItemCallNumberCutterSubfield)) {
												itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberCutterSubfield, content));
											} else if (!indexingProfile.APICallNumberPoststampSubfield.isEmpty() && tag.equalsIgnoreCase(indexingProfile.APICallNumberPoststampSubfield)) {
												itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberPoststampSubfield, content));
											}
											//else {
											//	logger.warn("For item " + getfullSierraItemId(itemId) + " (" + getfullSierraBibId(id) + "), unhandled call number subfield " + tag + " with content : "+ content + "; " + curVarField.toString());
											//This is to catch any settings not handled in the field mappings.
											//}
										}
									} else {
										String content = curVarField.getString("content");
										itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, content));
									}
								} else if (!indexingProfile.APIItemVolumeFieldTag.isEmpty() && fieldTag.equals(indexingProfile.APIItemVolumeFieldTag)) {
									itemField.addSubfield(marcFactory.newSubfield(indexingProfile.volume, allFieldContent.toString()));
								} else if (!indexingProfile.APIItemURLFieldTag.isEmpty() && fieldTag.equals(indexingProfile.APIItemURLFieldTag)) {
									itemField.addSubfield(marcFactory.newSubfield(indexingProfile.itemUrl, allFieldContent.toString()));
								} else if (!indexingProfile.APIItemEContentExportFieldTag.isEmpty() && fieldTag.equals(indexingProfile.APIItemEContentExportFieldTag)) {
									itemField.addSubfield(marcFactory.newSubfield(indexingProfile.eContentDescriptor, allFieldContent.toString()));
								}
								//							else if (
								//									!fieldTag.equalsIgnoreCase("b") // fieldTag b is for barcode (Do not need to handle)
								//											&& !fieldTag.equalsIgnoreCase("x") // fieldTag x is for Internal Note (Do not need to handle)
								//											&& !fieldTag.equalsIgnoreCase("m") // fieldTag m is for free text message field (Do not need to handle)
								//											&& !fieldTag.equalsIgnoreCase("r") // fieldTag r is for Course Reserves note (Do not need to handle)
								//							) {
								//								logger.warn("For item " + getfullSierraItemId(itemId) + " (" + getfullSierraBibId(id) + "), unhandled item variable field " + fieldTag + " ; " + curVarField.toString());
								//							}
							}

							//The item level call number info seems to always be in the var field (at least for Marmot) TODO: this may not be needed
							//						//if there wasn't call number data is the varfields
							//						if (!hadCallNumberVarField && curItem.has("callNumber") && indexingProfile.callNumberSubfield != ' '){
							//							itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, curItem.getString("callNumber")));
							//						}

							// Now Add the item record to the MARC
							marcRecord.addVariableField(itemField);

						}
						if ((itemIds.has("total") && itemIds.getInt("total") >= bufferSize) || entries.length() == bufferSize) {
							hasMoreItems      = true;
							itemIdToStartWith = Long.parseLong(itemId) + 1;
						}
					}
				} catch (Exception e) {
					logger.error("Error getting information about items", e);
				}
			} else if (itemIdToStartWith == 1L && logger.isDebugEnabled()) {
				logger.debug("finished getting items for " + id + " elapsed time " + (new Date().getTime() - startTime) + "ms found none");
			}
		} while (hasMoreItems);
	}

	private static void updateMarcAndRegroupRecordIds(CharSequence ids, List<Long> idArray) {
		try {
			JSONObject marcResults = null;
			if (allowFastExportMethod) {
				//Don't log errors since we get regular errors if we exceed the export rate.
				String sierraUrl = apiBaseUrl + "/bibs/marc?id=" + ids;
				logger.debug("Loading marc records with fast method {}", sierraUrl);
				marcResults = callSierraApiURL(sierraUrl, debug);
			}
			if (marcResults != null && marcResults.has("file")) {
				logger.debug("Got results with fast method");
				ArrayList<Long> processedIds = new ArrayList<>();
				String          dataFileUrl  = marcResults.getString("file");
				String          marcData     = getMarcFromSierraApiURL(dataFileUrl, debug);
				if (marcData != null) {
					logger.debug("Got marc record file");
					byte[]     bytes = marcData.getBytes(StandardCharsets.UTF_8);
					MarcReader marcReader;
					try (ByteArrayInputStream input = new ByteArrayInputStream(bytes)) {
						marcReader = new MarcPermissiveStreamReader(input, true, true, "UTF8");

						while (marcReader.hasNext()) {
							try {
								logger.debug("Starting to process the next marc Record");

								Record marcRecord = marcReader.next();
								logger.debug("Got the next marc Record data");

								// Write marc to File and Do the Record Grouping
								String identifier = groupAndWriteTheMarcRecord(marcRecord);

								Long shortId = Long.parseLong(identifier.substring(2, identifier.length() - 1));
								processedIds.add(shortId);
								logger.debug("Processed {}", identifier);
							} catch (MarcException e) {
								if (logger.isInfoEnabled()) {
									logger.info("Error loading marc record from file, will load manually. While processing ids: {}", ids, e);
									//This might be where the flatirons warnings come from.
								}
							}
						}
						// For any records that failed in the fast method,
						for (Long id : idArray) {
							if (!processedIds.contains(id)) {
								logger.debug("Starting to process {} with the not-fast method", id);

								if (!updateMarcAndRegroupRecordId(id)) {
									//Don't fail the entire process.  We will just reprocess next time the export runs
									logger.debug("Processing {} failed", id);

//									addNoteToExportLog("Processing " + id + " failed"); //Fails in singleRecord mode
									bibsWithErrors.add(id);
									//allPass = false;
								} else {
									logger.debug("Processed {}", id);
								}
							}
						}
					} catch (Exception e) {
						logger.error("Error occurring while processing binary MARC files from the API.", e);
					}
				}
			} else {
				logger.debug("No results with fast method available, loading with slow method");
				//Don't need this message since it will happen regularly.
				//logger.info("Error exporting marc records for " + ids + " marc results did not have a file");
				for (Long id : idArray) {
					logger.debug("starting to process {}", id);

					if (!updateMarcAndRegroupRecordId(id)) {
						//Don't fail the entire process.  We will just reprocess next time the export runs
						logger.debug("Processing {} failed", id);

//						addNoteToExportLog("Processing " + id + " failed"); //Fails in singleRecord mode
						bibsWithErrors.add(id);
						//allPass = false;
					}
				}
				if (logger.isDebugEnabled()) {
					logger.debug("finished processing {} records with the slow method", idArray.size());
				}
			}
		} catch (Exception e) {
			logger.error("Error processing newly created bibs", e);
		}
	}

	private static void writeMarcRecord(Record marcRecord, String identifier) {
		File marcFile = indexingProfile.getFileForIlsRecord(identifier);
		if (!marcFile.getParentFile().exists()) {
			if (!marcFile.getParentFile().mkdirs()) {
				logger.error("Could not create directories for {}", marcFile.getAbsolutePath());
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
	}


	private static PreparedStatement updateExtractInfoStatement;

	private static void markRecordForReExtraction(Long bibToUpdate) {
		updateLastExtractTimeForRecord(getfullSierraBibId(bibToUpdate), null);
	}

	private static boolean updateLastExtractTimeForRecord(String identifier) {
		return updateLastExtractTimeForRecord(identifier, startTime.getTime() / 1000);
	}

	private static boolean updateLastExtractTimeForRecord(String identifier, Long lastExtractTime) {
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
				return result == 1 || result == 2;
			} catch (SQLException e) {
				logger.error("Unable to update ils_extract_info table for {}", identifier, e);
			}
		}
		return false;
	}

	private static PreparedStatement markDeletedExtractInfoStatement;

	private static boolean markRecordDeletedInExtractInfo(Long bibId) {
		String identifier = getfullSierraBibId(bibId);
		try {
			markDeletedExtractInfoStatement.setLong(1, indexingProfile.id);
			markDeletedExtractInfoStatement.setString(2, identifier);
			markDeletedExtractInfoStatement.setLong(3, startTime.getTime() / 1000);
			markDeletedExtractInfoStatement.setDate(4, new java.sql.Date(startTime.getTime()));
			int result = markDeletedExtractInfoStatement.executeUpdate();
			return result == 1 || result == 2;  // TODO: what causes (result == 2) ?
		} catch (Exception e) {
			logger.error("Failed to mark record {} as deleted in extract info table", bibId, e);
		}
		return false;
	}

	private static PreparedStatement isAlreadyDeletedExtractInfoStatement;

	private static boolean isAlreadyMarkedDeleted(Long idFromAPI) {
		String bibId = getfullSierraBibId(idFromAPI);
		try {
			isAlreadyDeletedExtractInfoStatement.setLong(1, indexingProfile.id);
			isAlreadyDeletedExtractInfoStatement.setString(2, bibId);
			try (ResultSet isAlreadyDeletedRS = isAlreadyDeletedExtractInfoStatement.executeQuery()) {
				if (isAlreadyDeletedRS.next()) {
					return true;
				}
			} catch (SQLException e) {
				logger.error("Failed to get result for deleted record in ils_extract_info table for {}", bibId, e);
			}
		} catch (SQLException e) {
			logger.error("Failed to look up deleted record in ils_extract_info table for {}", bibId, e);
		}
		return false;
	}


//	private static void exportDueDates(String exportPath, Connection conn) throws SQLException, IOException {
//		addNoteToExportLog("Starting export of due dates");
//		String            dueDatesSQL     = "select record_num, due_gmt from sierra_view.checkout inner join sierra_view.item_view on item_record_id = item_view.id where due_gmt is not null";
//		PreparedStatement getDueDatesStmt = conn.prepareStatement(dueDatesSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
//		ResultSet         dueDatesRS      = null;
//		boolean           loadError       = false;
//		try {
//			dueDatesRS = getDueDatesStmt.executeQuery();
//		} catch (SQLException e1) {
//			logger.error("Error loading active orders", e1);
//			loadError = true;
//		}
//		if (!loadError) {
//			File      dueDateFile   = new File(exportPath + "/due_dates.csv");
//			CSVWriter dueDateWriter = new CSVWriter(new FileWriter(dueDateFile));
//			while (dueDatesRS.next()) {
//				try {
//					String recordNum = dueDatesRS.getString("record_num");
//					if (recordNum != null) {
//						String dueDateRaw = dueDatesRS.getString("due_gmt");
//						String itemId     = getfullSierraItemId(recordNum);
//						Date   dueDate    = dueDatesRS.getDate("due_gmt");
//						dueDateWriter.writeNext(new String[]{itemId, Long.toString(dueDate.getTime()), dueDateRaw});
//					} else {
//						logger.warn("No record number found while exporting due dates");
//					}
//				} catch (Exception e) {
//					logger.error("Error writing due dates", e);
//				}
//			}
//			dueDateWriter.close();
//			dueDatesRS.close();
//		}
//		addNoteToExportLog("Finished exporting due dates");
//	}

	private static void exportActiveOrders(Connection sierraConn) {
		addNoteToExportLog("Starting export of active orders");

		//Load the orders we had last time
		String                 exportPath             = indexingProfile.marcPath;
		String                 orderFilePath          = exportPath + "/active_orders.csv";
		File                   orderRecordFile        = new File(orderFilePath);
		HashMap<Long, Integer> existingBibsWithOrders = new HashMap<>();
		readOrdersFile(orderRecordFile, existingBibsWithOrders);

		boolean suppressOrderRecordsThatAreReceivedAndCataloged = PikaConfigIni.getBooleanIniValue("Catalog", "suppressOrderRecordsThatAreReceivedAndCatalogged");
		boolean suppressOrderRecordsThatAreCataloged            = PikaConfigIni.getBooleanIniValue("Catalog", "suppressOrderRecordsThatAreCatalogged");
		boolean suppressOrderRecordsThatAreReceived             = PikaConfigIni.getBooleanIniValue("Catalog", "suppressOrderRecordsThatAreReceived");

		String orderStatusesToExport = PikaConfigIni.getIniValue("Reindex", "orderStatusesToExport");
		if (orderStatusesToExport == null) {
			orderStatusesToExport = "o|1";
		}
		String[]      orderStatusesToExportVals = orderStatusesToExport.split("\\|");
		StringBuilder orderStatusCodesSQL       = new StringBuilder();
		for (String orderStatusesToExportVal : orderStatusesToExportVals) {
			if (orderStatusCodesSQL.length() > 0) {
				orderStatusCodesSQL.append(" OR ");
			}
			orderStatusCodesSQL.append(" order_status_code = '").append(orderStatusesToExportVal).append("'");
		}
		String activeOrderSQL = "SELECT bib_view.record_num AS bib_record_num, order_view.record_num AS order_record_num, accounting_unit_code_num, order_status_code, copies, location_code, catalog_date_gmt, received_date_gmt " +
				"FROM sierra_view.order_view " +
				"INNER JOIN sierra_view.bib_record_order_record_link ON bib_record_order_record_link.order_record_id = order_view.record_id " +
				"INNER JOIN sierra_view.bib_view ON sierra_view.bib_view.id = bib_record_order_record_link.bib_record_id " +
				"INNER JOIN sierra_view.order_record_cmf ON order_record_cmf.order_record_id = order_view.id " +
				"WHERE (" + orderStatusCodesSQL + ") AND order_view.is_suppressed = 'f' AND location_code != 'multi' AND ocode4 != 'n'";

			if (suppressOrderRecordsThatAreCataloged) { // Ignore entries with a set catalog date more than a day old ( a day to allow for the transition from order item to regular item)
//				activeOrderSQL += " AND (catalog_date_gmt IS NULL OR NOW() - catalog_date_gmt < '1 DAY'::INTERVAL) ";
				activeOrderSQL += " AND catalog_date_gmt IS NULL";
			} else if (suppressOrderRecordsThatAreReceived) { // Ignore entries with a set received date more than a day old ( a day to allow for the transition from order item to regular item)
//				activeOrderSQL += " AND (received_date_gmt IS NULL OR NOW() - received_date_gmt < '1 DAY'::INTERVAL) ";
				activeOrderSQL += " AND received_date_gmt IS NULL";
			} else if (suppressOrderRecordsThatAreReceivedAndCataloged) { // Only ignore entries that have both a received and catalog date, and a catalog date more than a day old
//				activeOrderSQL += " AND (catalog_date_gmt IS NULL or received_date_gmt IS NULL OR NOW() - catalog_date_gmt < '1 DAY'::INTERVAL) ";
				activeOrderSQL += " AND (catalog_date_gmt IS NULL OR received_date_gmt IS NULL)";
			}

		int numBibsToProcess     = 0;
		int numBibsOrdersAdded   = 0;
		int numBibsOrdersChanged = 0;
		int numBibsOrdersRemoved = 0;
		try (
				PreparedStatement getActiveOrdersStmt = sierraConn.prepareStatement(activeOrderSQL, ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
				ResultSet activeOrdersRS = getActiveOrdersStmt.executeQuery()
		) {
			if (logger.isInfoEnabled() && !activeOrdersRS.first()){
				// Note: the first command can only work when the ResultSet is a scrollable type. (Default type is forward-only)
				logger.info("No applicable order records were found");
			}
			File tempWriteFile = new File(orderRecordFile + ".tmp");
			writeToFileFromSQLResultFile(tempWriteFile, activeOrdersRS);
			activeOrdersRS.close();
			if (!tempWriteFile.renameTo(orderRecordFile)) {
				if (orderRecordFile.exists() && orderRecordFile.delete()) {
					if (!tempWriteFile.renameTo(orderRecordFile)) {
						logger.error("failed to delete existing order record file and replace with temp file");
					}
				} else {
					logger.warn("Failed to rename temp order record file");
				}
			}

			//Check to see which bibs either have new or deleted orders
			HashMap<Long, Integer> updatedBibsWithOrders = new HashMap<>();
			readOrdersFile(orderRecordFile, updatedBibsWithOrders);
			for (Long bibId : updatedBibsWithOrders.keySet()) {
				if (!existingBibsWithOrders.containsKey(bibId)) {
					//We didn't have a bib with an order before, update it
					allBibsToUpdate.add(bibId);
					numBibsToProcess++;
					numBibsOrdersAdded++;
				} else {
					if (!updatedBibsWithOrders.get(bibId).equals(existingBibsWithOrders.get(bibId))) {
						//Number of orders has changed, we should reindex.
						allBibsToUpdate.add(bibId);
						numBibsToProcess++;
						numBibsOrdersChanged++;
					}
					existingBibsWithOrders.remove(bibId);
				}
			}
			if (logger.isDebugEnabled()){
				logger.debug("{} order records were extracted for {} bibs", updatedBibsWithOrders.size(), allBibsToUpdate.size());
			}

			//Now that all updated bibs are processed, look for any that we used to have that no longer exist
			Set<Long> bibsWithOrdersRemoved = existingBibsWithOrders.keySet();
			allBibsToUpdate.addAll(bibsWithOrdersRemoved);
			numBibsOrdersRemoved = bibsWithOrdersRemoved.size();
			numBibsToProcess += numBibsOrdersRemoved;
		} catch (Exception e) {
			logger.error("Error loading active orders", e);
		}
		addNoteToExportLog("Finished exporting active orders");
		addNoteToExportLog(numBibsToProcess + " total records to update.<br> " + numBibsOrdersAdded + " records have new order records.<br>" + numBibsOrdersChanged + " records have order record updates.<br>" + numBibsOrdersRemoved + " records have no order records now.");
	}

	private static void readOrdersFile(File orderRecordFile, HashMap<Long, Integer> bibsWithOrders) {
		try {
			if (orderRecordFile.exists()) {
				try (CSVReader orderReader = new CSVReader(new FileReader(orderRecordFile))) {
					//Skip the header
					orderReader.readNext();
					String[] recordData = orderReader.readNext();
					while (recordData != null) {
						Long bibId = Long.parseLong(recordData[0]);
						if (bibsWithOrders.containsKey(bibId)) {
							bibsWithOrders.put(bibId, bibsWithOrders.get(bibId) + 1);
						} else {
							bibsWithOrders.put(bibId, 1);
						}

						recordData = orderReader.readNext();
					}
				}
			}
		} catch (IOException e) {
			logger.error("Error reading order records file", e);
		} catch (NumberFormatException e) {
			logger.error("Error while reading order records file", e);
		}
	}


	private static void writeToFileFromSQLResultFile(File dataFile, ResultSet dataRS) {
		try (CSVWriter dataFileWriter = new CSVWriter(new FileWriter(dataFile))) {
			dataFileWriter.writeAll(dataRS, true);
		} catch (IOException e) {
			logger.error("Error Writing File", e);
		} catch (SQLException e) {
			logger.error("SQL Error", e);
		}
	}

	private static String sierraAPIToken;
	private static String sierraAPITokenType;
	private static long   sierraAPIExpiration;

	private static boolean connectToSierraAPI() {
		//Check to see if we already have a valid token
		if (sierraAPIToken != null) {
			if (sierraAPIExpiration - new Date().getTime() > 0) {
				//logger.debug("token is still valid");
				return true;
			} else {
				logger.debug("Token has expired");
			}
		}
		if (apiBaseUrl == null || apiBaseUrl.isEmpty()) {
			logger.error("Sierra API URL is not set");
			return false;
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL    emptyIndexURL = new URL(apiBaseUrl + "/token");
			String clientKey     = PikaConfigIni.getIniValue("Catalog", "clientKey");
			String clientSecret  = PikaConfigIni.getIniValue("Catalog", "clientSecret");
			String encoded       = Base64.getEncoder().encodeToString((clientKey + ":" + clientSecret).getBytes());

			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			checkForSSLConnection(conn);
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setRequestMethod("POST");
			conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			conn.setRequestProperty("Authorization", "Basic " + encoded);
			conn.setDoOutput(true);
			try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
				wr.write("grant_type=client_credentials");
				wr.flush();
			}

			StringBuilder response;
			if (conn.getResponseCode() == 200) {
				// Get the response
				response = getTheResponse(conn.getInputStream());
				try {
					JSONObject parser = new JSONObject(response.toString());
					sierraAPIToken     = parser.getString("access_token");
					sierraAPITokenType = parser.getString("token_type");
					//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
					sierraAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
					//logger.debug("Sierra token is " + sierraAPIToken);
				} catch (JSONException jse) {
					logger.error("Error parsing response to json {}", response, jse);
					return false;
				}

			} else {
				logger.error("Received error " + conn.getResponseCode() + " connecting to sierra authentication service");
				// Get any errors
				response = getTheResponse(conn.getErrorStream());
				logger.error(response);
				return false;
			}

		} catch (Exception e) {
			logger.error("Error connecting to sierra API", e);
			return false;
		}
		return true;
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

	private static boolean lastCallTimedOut = false;

	private static JSONObject callSierraApiURL(String sierraUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToSierraAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/marc-json");
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
						logger.error("Error parsing response \n{}", response.toString(), jse);
						return null;
					}

				} else if (responseCode == 500 || responseCode == 404) {
					// 404 is record not found
					if (logErrors) {
						// Get any errors
						if (logger.isInfoEnabled()) {
							logger.info("Received response code {} calling sierra API {}", responseCode, sierraUrl);
							response = getTheResponse(conn.getErrorStream());
							logger.info("Finished reading response : {}", response);
						}
					}
				} else {
					if (logErrors) {
						logger.error("Received error {} calling sierra API {}", responseCode, sierraUrl);
						// Get any errors
						response = getTheResponse(conn.getErrorStream());
						logger.error("Finished reading response : {}", response);
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.error("Socket timeout talking to sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.error("Timeout connecting to sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.error("Error loading data from sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
			}
		}
		return null;
	}

	private static String getMarcFromSierraApiURL(String sierraUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToSierraAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/marc-json");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response;
				int           responseCode = conn.getResponseCode();
				if (responseCode == 200) {
					// Get the response
					response = getTheResponse(conn.getInputStream());
					return response.toString();
				} else if (responseCode == 404) {
					// 404 is record not found
					if (logErrors) {
						// Get any errors
						if (logger.isInfoEnabled()) {
							logger.info("Received response code {} calling sierra API {}", responseCode, sierraUrl);
							response = getTheResponse(conn.getErrorStream());
							logger.info("Finished reading response : {}", response);
						}
					}
				} else {
					if (logErrors) {
						logger.error("Received error {} calling sierra API {}", responseCode, sierraUrl);
						// Get any errors
						response = getTheResponse(conn.getErrorStream());
						logger.error("Finished reading response : {}", response);
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.error("Socket timeout talking to sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.error("Timeout connecting to sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.error("Error loading data from sierra API (callSierraApiURL) {} - {}", sierraUrl, e.toString());
			}
		}
		return null;
	}

	private static void updatePartialExtractRunning(boolean running) {
		systemVariables.setVariable("sierra_extract_running", running);
	}

	private static JSONObject getMarcJSONFromSierraApiURL(String sierraUrl, Long id) {
		lastCallTimedOut = false;
		if (connectToSierraAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/marc-in-json");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response;
				if (conn.getResponseCode() == 200) {
					// Get the response
					response = getTheResponse(conn.getInputStream());
					try {
						return new JSONObject(response.toString());
					} catch (JSONException e) {
						logger.error("JSON error parsing response from MARC JSON call for {} : {}", getfullSierraBibId(id), response, e);
					}
				} else {
					// Get any errors
					response = getTheResponse(conn.getErrorStream());

					try {
						return new JSONObject(response.toString());
					} catch (JSONException e) {
						logger.error("Received error {} calling sierra API {}", conn.getResponseCode(), sierraUrl);
						logger.error(response.toString(), e);
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.error("Socket timeout talking to sierra API (getMarcJSONFromSierraApiURL) {}", e.toString());
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.error("Timeout connecting to sierra API (getMarcJSONFromSierraApiURL) {}", e.toString());
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.error("Error loading data from sierra API (getMarcJSONFromSierraApiURL) for {}", getfullSierraBibId(id), e);
			}
		}
		return null;
	}

	/**
	 * Calculates a check digit for a III identifier
	 *
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	private static String getCheckDigit(String basedId) {
		int sumOfDigits = 0;
		for (int i = 0; i < basedId.length(); i++) {
			int multiplier = ((basedId.length() + 1) - i);
			sumOfDigits += multiplier * Integer.parseInt(basedId.substring(i, i + 1));
		}
		int modValue = sumOfDigits % 11;
		if (modValue == 10) {
			return "x";
		} else {
			return Integer.toString(modValue);
		}
	}

}