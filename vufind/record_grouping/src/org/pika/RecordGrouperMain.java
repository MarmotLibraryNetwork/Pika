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

// Import log4j classes.
import org.apache.logging.log4j.Logger;
import org.apache.logging.log4j.LogManager;

//import au.com.bytecode.opencsv.CSVReader;

import au.com.bytecode.opencsv.CSVWriter;
import org.json.JSONObject;
import org.marc4j.MarcException;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.MarcStreamWriter;
import org.marc4j.marc.*;

import java.io.*;
import java.nio.file.*;
import java.nio.file.attribute.BasicFileAttributes;
import java.sql.*;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;
import java.util.regex.Pattern;
import java.util.zip.CRC32;

/**
 * Groups records so that we can show single multiple titles as one rather than as multiple lines.
 *
 * Grouping happens at 3 different levels:
 */
public class RecordGrouperMain {
	private static Logger              logger;
	private static String              serverName;
	private static PikaSystemVariables systemVariables;
	private static Connection          pikaConn           = null;
	private static Connection          econtentConnection = null;

	private static HashMap<String, Long> marcRecordChecksums           = new HashMap<>();
	private static HashMap<String, Long> marcRecordFirstDetectionDates = new HashMap<>();
	private static HashMap<String, Long> marcRecordIdsInDatabase       = new HashMap<>();
	private static HashMap<String, Long> primaryIdentifiersInDatabase  = new HashMap<>();
	private static PreparedStatement     insertMarcRecordChecksum;
	private static PreparedStatement     removeMarcRecordChecksum;
	private static PreparedStatement     removePrimaryIdentifier;

	private static Long    lastGroupingTime;
	private static boolean fullRegroupingClearGroupingTables = false;
	private static boolean fullRegroupingNoClear             = false;
	private static boolean validateChecksumsFromDisk         = false;

	//Reporting information
	private static long              groupingLogId;
	private static PreparedStatement addNoteToGroupingLogStmt;
	private static PreparedStatement updateProfileLastGroupedTimeStmt;

	public static void main(String[] args) {
		// Get the configuration filename
		if (args.length == 0) {
			System.out.println("Welcome to the Record Grouping Application developed by Marmot Library Network.  \n" +
					"This application will group works by title, author, language, and format to create a \n" +
					"unique work id.  \n" +
					"\n" +
					"Additional information about the grouping process can be found at: \n" +
					"https://marmot.org/content/pika-grouping-overview\n" +
					"\n" +
					"This application can be used in several distinct ways based on the command line parameters\n" +
					"1) Generate a work id for an individual title/author/format\n" +
					"   record_grouping.jar <pika_site_name> generateWorkId <title> <author> <format> <languageCode> <subtitle (optional)>\n" +
					"   \n" +
					"2) Generate work ids for a Pika site based on the exports for the site\n" +
					"   record_grouping.jar <pika_site_name>\n" +
					"   \n" +
//					"3) benchmark the record generation and test the functionality\n" +
//					"   record_grouping.jar benchmark\n" +
					"4) Only run record grouping cleanup\n" +
					"   record_grouping.jar <pika_site_name> runPostGroupingCleanup\n" +
					"5) Only explode records into individual records (no grouping)\n" +
					"   record_grouping.jar <pika_site_name> explodeMarcs\n" +
					"6) Record Group a specific indexing profile\n" +
					"   record_grouping.jar <pika_site_name> \"<profile name>\"");
			System.exit(1);
		}

		serverName = args[0];

		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j2.grouping.xml");
		if (log4jFile.exists()) {
			System.setProperty("log4j.pikaSiteName", serverName);
			System.setProperty("log4j.configurationFile", log4jFile.getAbsolutePath());
			logger = LogManager.getLogger(RecordGrouperMain.class);
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
			System.exit(1);
		}

		// Load the configuration file
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Connect to the database
		try {
			String databaseConnectionInfo   = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			String econtentDBConnectionInfo = PikaConfigIni.getIniValue("Database", "database_econtent_jdbc");
			pikaConn           = DriverManager.getConnection(databaseConnectionInfo);
			econtentConnection = DriverManager.getConnection(econtentDBConnectionInfo);

		} catch (Exception e) {
			System.out.println("Error connecting to database " + e);
			System.exit(1);
		}


		String action = args[1];

		switch (action) {
//			case "benchmark":
//				boolean validateNYPL = false;
//				if (args.length > 1) {
//					if (args[1].equals("nypl")) {
//						validateNYPL = true;
//					}
//				}
//				doBenchmarking(validateNYPL);
//				break;
			case "generateWorkId":
				String title;
				String author;
				String format;
				String languageCode;
				String subtitle = null;
				if (args.length >= 5) {
					title        = args[2];
					author       = args[3];
					format       = args[4];
					languageCode = args[5];
					if (args.length >= 7) {
						subtitle = args[6];
					}
				} else {
					title        = getInputFromCommandLine("Enter the title");
					subtitle     = getInputFromCommandLine("Enter the subtitle");
					author       = getInputFromCommandLine("Enter the author");
					format       = getInputFromCommandLine("Enter the format");
					languageCode = getInputFromCommandLine("Enter the language code");
				}

				//Connect to the database
				GroupedWork5 work = (GroupedWork5) GroupedWorkFactory.getInstance(5, pikaConn);
				work.setTitle(title, subtitle);
				work.setAuthor(author);
				work.setGroupingCategory(format, new RecordIdentifier("generate Work ID", "n/a"));
				work.setGroupingLanguage(languageCode);
				JSONObject result = new JSONObject();
				try {
					result.put("groupingAuthor", work.getAuthoritativeAuthor());
					result.put("groupingTitle", work.getAuthoritativeTitle());
					result.put("groupingCategory", work.getGroupingCategory());
					result.put("groupingLanguage", work.getGroupingLanguage());
					result.put("workId", work.getPermanentId());
				} catch (Exception e) {
					logger.error("Error generating response", e);
				}
				System.out.print(result);
				break;
			case "singleRecord":
				String fullRecordId;
				if (args.length == 3) {
					fullRecordId = args[2];
				} else {
					fullRecordId = getInputFromCommandLine("Enter the full record Id (source:sourceId)");
				}
				String source = null;
				String sourceId = null;
				try {
					source   = fullRecordId.substring(0, fullRecordId.indexOf(':'));
					sourceId = fullRecordId.substring(fullRecordId.indexOf(':') + 1);
				} catch (Exception e) {
					System.out.print("Please include source and source Id separated by a colon. (:)");
					System.exit(1);
				}
				RecordIdentifier recordIdentifier = new RecordIdentifier(source, sourceId);
				processSingleRecord(recordIdentifier);

				break;
			case "singleWork":
				String groupedWorkId;
				if (args.length == 3) {
					groupedWorkId = args[2];
				} else {
					groupedWorkId = getInputFromCommandLine("Enter the grouped work permanent id");
				}
				boolean success = processSingleWork(groupedWorkId);
				String response = "{\"success\":" + (success ? "1" : "0") + "}";
				System.out.print(response);
				break;
			case "processMerges":
				processFreshMerges();
				break;
			default:
				doStandardRecordGrouping(args);
				break;
		}
	}

	private static void processFreshMerges(){
		String sql = "SELECT permanent_id FROM grouped_work INNER JOIN grouped_work_merges ON (permanent_id = sourceGroupedWorkId)";
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement(sql);
				ResultSet resultSet = preparedStatement.executeQuery();
		) {
			while (resultSet.next()) {
				String groupedWorkId = resultSet.getString(1);
				processSingleWork(groupedWorkId);
			}
			removeGroupedWorksWithoutPrimaryIdentifiers(pikaConn);
		} catch (SQLException throwables) {
			logger.error(throwables);
		}
	}

	private static boolean processSingleWork(String groupedWorkId) {
		logger.info("Grouping single work : {}", groupedWorkId);

		boolean success = true;
		String sql = "SELECT type, identifier FROM grouped_work_primary_identifiers \n" +
				"INNER JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id) \n" +
				"WHERE permanent_id = '" + groupedWorkId + "'";
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement(sql);
				ResultSet resultSet = preparedStatement.executeQuery();
		) {
			while (resultSet.next()) {
				String source   = resultSet.getString(1);
				String sourceId = resultSet.getString(2);
				if (!processSingleRecord(new RecordIdentifier(source, sourceId))){
					success = false;
				}
			}
		} catch (SQLException throwables) {
			logger.error(throwables);
			success = false;
		}
		return success;
	}

	private static boolean processSingleRecord(RecordIdentifier recordIdentifier) {
		String source = recordIdentifier.getSource();
		logger.info("Grouping single record : {}", recordIdentifier);

		if (source.equalsIgnoreCase("overdrive")) {
			OverDriveRecordGrouper recordGroupingProcessor = new OverDriveRecordGrouper(pikaConn, econtentConnection, logger);
			try {
				String OverdriveRecordSQL = "SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName, code, publisher, edition " +
						"FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id " +
						"LEFT JOIN overdrive_api_product_languages_ref ON overdrive_api_product_languages_ref.productId = overdrive_api_products.id " +
						"LEFT JOIN overdrive_api_product_languages ON overdrive_api_product_languages_ref.languageId = overdrive_api_product_languages.id " +
						"WHERE overdriveId = ?";

				PreparedStatement overDriveRecordsStmt = econtentConnection.prepareStatement(OverdriveRecordSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				overDriveRecordsStmt.setString(1, recordIdentifier.getIdentifier());

				try (ResultSet overDriveRecordRS = overDriveRecordsStmt.executeQuery()) {
					if (overDriveRecordRS.next()) {
						recordGroupingProcessor.processOverDriveRecord(recordIdentifier, overDriveRecordRS, true);
						return true;
					}
				}
			} catch (SQLException e) {
				logger.error("Error processing OverDrive data", e);
			}

		} else {
			ArrayList<IndexingProfile> indexingProfiles = loadIndexingProfiles(pikaConn, source);
			for (IndexingProfile curProfile : indexingProfiles) {
				MarcRecordGrouper recordGroupingProcessor;
				switch (curProfile.groupingClass) {
					case "MarcRecordGrouper":
						recordGroupingProcessor = new MarcRecordGrouper(pikaConn, curProfile, logger);
						break;
					case "SideLoadedRecordGrouper":
						recordGroupingProcessor = new SideLoadedRecordGrouper(pikaConn, curProfile, logger);
						break;
					case "HooplaRecordGrouper":
						recordGroupingProcessor = new HooplaRecordGrouper(pikaConn, curProfile, logger);
						break;
					case "PolarisRecordGrouper":
						recordGroupingProcessor = new PolarisRecordGrouper(pikaConn, curProfile, logger);
						break;
					default:
						logger.error("Unknown class for record grouping {}", curProfile.groupingClass);
						continue;
				}
				// Read Record
				File curBibFile = curProfile.getFileForIlsRecord(recordIdentifier.getIdentifier());
				try (FileInputStream marcFileStream = new FileInputStream(curBibFile)) {
					MarcReader catalogReader = new MarcPermissiveStreamReader(marcFileStream, true, true, "UTF8");
					//Individual Marc file should already be in UTF-8 encoding
					if (catalogReader.hasNext()) {
						Record curBib = catalogReader.next();

						recordIdentifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(curBib, source, curProfile.doAutomaticEcontentSuppression);
						// This ensures the overdrive grouping suppression still takes place.

						if (recordGroupingProcessor.processMarcRecord(curBib, true, recordIdentifier)) {
							logger.debug("{} was successfully grouped.", recordIdentifier);
							return true;
						} else {
							logger.error("{} was not grouped.", recordIdentifier);
						}

					}
				} catch (IOException e) {
					logger.error("Error reading MARC file for {}", recordIdentifier, e);
//					System.out.println("Error reading MARC file for " + recordIdentifier);
//					System.exit(1);
				}

			}

		}
		return false;
	}

	private static String getInputFromCommandLine(String prompt) {
		//Prompt for the work to process
		System.out.print(prompt + ": ");

		//  open up standard input
		BufferedReader br = new BufferedReader(new InputStreamReader(System.in));
		// surrounding with try with resource breaks on the second prompt.

		String value = null;
		try {
			value = br.readLine().trim();
		} catch (IOException e) {
			System.out.println("IO error trying to read " + prompt + " - " + e.toString());
			System.exit(1);
		}
		return value;
	}


//	private static void doBenchmarking(boolean validateNYPL) {
//		long processStartTime = new Date().getTime();
//		File log4jFile        = new File("./log4j.grouping.properties");
//		if (log4jFile.exists()) {
//			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
//		} else {
//			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
//			System.exit(1);
//		}
//		if (logger.isInfoEnabled()) {
//			logger.info("Starting record grouping benchmark " + new Date().toString());
//		}
//
//		try {
//			//Load the input file to test
//			File      benchmarkFile        = new File("./benchmark_input.csv");
//			CSVReader benchmarkInputReader = new CSVReader(new FileReader(benchmarkFile));
//
//			//Create a file to store the results within
//			SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd_HH-mm-ss");
//			File             resultsFile;
//			if (validateNYPL) {
//				resultsFile = new File("./benchmark_results/" + dateFormatter.format(new Date()) + "_nypl.csv");
//			} else {
//				resultsFile = new File("./benchmark_results/" + dateFormatter.format(new Date()) + "_marmot.csv");
//			}
//			CSVWriter resultsWriter = new CSVWriter(new FileWriter(resultsFile));
//			resultsWriter.writeNext(new String[]{"Original Title", "Original Author", "Format", "Normalized Title", "Normalized Author", "Permanent Id", "Validation Results"});
//
//			//Load the desired results
//			File validationFile;
//			if (validateNYPL) {
//				validationFile = new File("./benchmark_output_nypl.csv");
//			} else {
//				validationFile = new File("./benchmark_validation_file.csv");
//			}
//			CSVReader validationReader = new CSVReader(new FileReader(validationFile));
//
//			//Read the header from input
//			String[] csvData;
//			benchmarkInputReader.readNext();
//
//			int numErrors   = 0;
//			int numTestsRun = 0;
//			//Read validation file
//			String[] validationData;
//			validationReader.readNext();
//			while ((csvData = benchmarkInputReader.readNext()) != null) {
//				if (csvData.length >= 3) {
//					numTestsRun++;
//					String originalTitle  = csvData[0];
//					String originalAuthor = csvData[1];
//					String groupingFormat = csvData[2];
//
//					//Get normalized the information and get the permanent id
//					GroupedWorkBase work = GroupedWorkFactory.getInstance(4);
//					work.setTitle(originalTitle, "");
//					work.setAuthor(originalAuthor);
//					work.setGroupingCategory(groupingFormat, new RecordIdentifier("n/a", "n/a"));
//
//					//Read from validation file
//					validationData = validationReader.readNext();
//					//Check to make sure the results we got are correct
//					String validationResults = "";
//					if (validationData != null && validationData.length >= 6) {
//						String expectedTitle;
//						String expectedAuthor;
//						String expectedWorkId;
//						if (validateNYPL) {
//							expectedTitle  = validationData[2];
//							expectedAuthor = validationData[3];
//							expectedWorkId = validationData[5];
//						} else {
//							expectedTitle  = validationData[3];
//							expectedAuthor = validationData[4];
//							expectedWorkId = validationData[5];
//						}
//
//						if (!expectedTitle.equals(work.getAuthoritativeTitle())) {
//							validationResults += "Normalized title incorrect expected " + expectedTitle + "; ";
//						}
//						if (!expectedAuthor.equals(work.getAuthoritativeAuthor())) {
//							validationResults += "Normalized author incorrect expected " + expectedAuthor + "; ";
//						}
//						if (!expectedWorkId.equals(work.getPermanentId())) {
//							validationResults += "Grouped Work Id incorrect expected " + expectedWorkId + "; ";
//						}
//						if (validationResults.length() != 0) {
//							numErrors++;
//						}
//					} else {
//						validationResults += "Did not find validation information ";
//					}
//
//					//Save results
//					String[] results;
//					if (validationResults.length() == 0) {
//						results = new String[]{originalTitle, originalAuthor, groupingFormat, work.getAuthoritativeTitle(), work.getAuthoritativeAuthor(), work.getPermanentId()};
//					} else {
//						results = new String[]{originalTitle, originalAuthor, groupingFormat, work.getAuthoritativeTitle(), work.getAuthoritativeAuthor(), work.getPermanentId(), validationResults};
//					}
//					resultsWriter.writeNext(results);
//					/*if (numTestsRun >= 100){
//						break;
//					}*/
//				}
//			}
//			resultsWriter.flush();
//			if (logger.isDebugEnabled()) {
//				logger.debug("Ran " + numTestsRun + " tests.");
//				logger.debug("Found " + numErrors + " errors.");
//			}
//			benchmarkInputReader.close();
//			validationReader.close();
//
//			long endTime     = new Date().getTime();
//			long elapsedTime = endTime - processStartTime;
//			if (logger.isInfoEnabled()) {
//				logger.info("Total Run Time " + (elapsedTime / 1000) + " seconds, " + (elapsedTime / 60000) + " minutes.");
//				logger.info("Processed " + Double.toString((double) numTestsRun / (double) (elapsedTime / 1000)) + " records per second.");
//			}
//
//			//Write results to the test file for comparison
//			resultsWriter.writeNext(new String[0]);
//			resultsWriter.writeNext(new String[]{"Tests Run", Integer.toString(numTestsRun)});
//			resultsWriter.writeNext(new String[]{"Errors", Integer.toString(numErrors)});
//			resultsWriter.writeNext(new String[]{"Total Run Time (seconds)", Long.toString((elapsedTime / 1000))});
//			resultsWriter.writeNext(new String[]{"Records Per Second", Double.toString((double) numTestsRun / (double) (elapsedTime / 1000))});
//
//
//			resultsWriter.flush();
//			resultsWriter.close();
//		} catch (Exception e) {
//			logger.error("Error running benchmark", e);
//		}
//	}

	private static ArrayList<IndexingProfile> loadIndexingProfiles(Connection pikaConn, String indexingProfileToRun) {
		ArrayList<IndexingProfile> indexingProfiles = new ArrayList<>();

		String fetchIndexingProfileSQL = "SELECT * FROM indexing_profiles";
		if (indexingProfileToRun != null && !indexingProfileToRun.isEmpty()) {
			fetchIndexingProfileSQL += " WHERE sourceName LIKE '" + indexingProfileToRun + "'";
		}
		try (
				PreparedStatement getIndexingProfilesStmt = pikaConn.prepareStatement(fetchIndexingProfileSQL);
				ResultSet indexingProfilesRS = getIndexingProfilesStmt.executeQuery()
		) {
			while (indexingProfilesRS.next()) {
				IndexingProfile profile = new IndexingProfile(indexingProfilesRS);

				// Does this profile use the Sierra API Extract
				try (
						PreparedStatement getSierraFieldMappingsStmt = pikaConn.prepareStatement("SELECT * FROM sierra_export_field_mapping WHERE indexingProfileId =" + profile.id);
						ResultSet getSierraFieldMappingsRS = getSierraFieldMappingsStmt.executeQuery()
				) {
					// If there is a sierra field mapping entry for the profile, then it does use the Sierra API Extract
					if (getSierraFieldMappingsRS.next()) {
						profile.usingSierraAPIExtract = true;
					}
				} catch (Exception e) {
					logger.warn("Error determining whether or not an indexing profile uses the Sierra API Extract");
					// log the error but otherwise continue as normal
				}

				indexingProfiles.add(profile);
			}
			if (indexingProfileToRun != null && !indexingProfileToRun.isEmpty() && indexingProfiles.isEmpty()) {
				logger.error("Did not find {} in database.", indexingProfileToRun);
				System.exit(1);
			}
		} catch (Exception e) {
			logger.error("Error loading indexing profiles", e);
			System.exit(1);
		}
		return indexingProfiles;
	}

	private static void doStandardRecordGrouping(String[] args) {
		long processStartTime = new Date().getTime();
		logger.info("Starting standard record grouping");

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		// Start a reindex log entry
		try {
			logger.info("Creating log entry for grouping");

			ResultSet generatedKeys;
			try (PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO record_grouping_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS)) {
				createLogEntryStatement.setLong(1, processStartTime / 1000);
				createLogEntryStatement.setLong(2, processStartTime / 1000);
				createLogEntryStatement.setString(3, "Initialization complete");
				createLogEntryStatement.executeUpdate();
				generatedKeys = createLogEntryStatement.getGeneratedKeys();
				if (generatedKeys.next()) {
					groupingLogId = generatedKeys.getLong(1);
				}
			}

			addNoteToGroupingLogStmt = pikaConn.prepareStatement("UPDATE record_grouping_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for record grouping process", e);
			System.exit(0);
		}

		//Make sure that our export is valid
		Boolean bypassValidation = false;
		Long    bypassVariableId = systemVariables.getVariableId("bypass_export_validation");
		if (bypassVariableId == null) {
			systemVariables.setVariable("bypass_export_validation", false);
		} else {
			bypassValidation = systemVariables.getBooleanValuedVariable("bypass_export_validation");
		}

		Boolean lastExportValid = systemVariables.getBooleanValuedVariable("last_export_valid");
		if (lastExportValid == null) {
			lastExportValid = false;
		}

		if (!lastExportValid) {
			if (bypassValidation) {
				logger.warn("The last export was not valid.  Still regrouping because bypass validation is on.");
			} else {
				logger.error("The last export was not valid.  Not regrouping to avoid loading incorrect records.");
				System.exit(1);
			}
		}

		//Get the last grouping time
		lastGroupingTime = systemVariables.getLongValuedVariable("last_grouping_time");

		getGroupingTimeLimit(processStartTime);

		//Check to see if we need to clear the database
		boolean clearDatabasePriorToGrouping = false;
		boolean onlyDoCleanup                = false;
		boolean explodeMarcsOnly             = false;
		String  indexingProfileToRun         = null;
		if (args.length >= 2) {
			switch (args[1].toLowerCase()) {
				case "explodemarcs":
					explodeMarcsOnly = true;
					break;
				case "fullregrouping":
				case "fullregroupingnoclear":
					fullRegroupingNoClear = true;
					break;
				case "fullregroupingclear":
					clearDatabasePriorToGrouping = true;
					fullRegroupingClearGroupingTables = true;
					break;
				case "runpostgroupingcleanup":
					onlyDoCleanup = true;
					break;
				default:
					//The last argument is the indexing profile to regroup
					indexingProfileToRun = args[1];
			}
		}

		if (!onlyDoCleanup) {

			if (!explodeMarcsOnly) {
				markRecordGroupingRunning(true);

				if (clearDatabasePriorToGrouping) {
					clearDatabase();
				}
			}

			//Determine if we want to validateChecksumsFromDisk
			final Boolean aBoolean = systemVariables.getBooleanValuedVariable("validateChecksumsFromDisk");
			if (aBoolean == null) {
				systemVariables.setVariable("validateChecksumsFromDisk", validateChecksumsFromDisk);
			} else {
				validateChecksumsFromDisk = aBoolean;
			}

			// Main Record Grouping Processing
			if (indexingProfileToRun == null || indexingProfileToRun.equalsIgnoreCase("overdrive")) {
				groupOverDriveRecords(pikaConn, econtentConnection, explodeMarcsOnly);
			}

			ArrayList<IndexingProfile> indexingProfiles = null;
			if (indexingProfileToRun == null || !indexingProfileToRun.equalsIgnoreCase("overdrive")){
				indexingProfiles = loadIndexingProfiles(pikaConn, indexingProfileToRun);
			}
			if (indexingProfiles != null && !indexingProfiles.isEmpty()) {
				groupIlsRecords(pikaConn, indexingProfiles, explodeMarcsOnly);
			}

		}

		// Cleanup the grouping data
		if (!explodeMarcsOnly) {
			try {
				if (onlyDoCleanup){
					addNoteToGroupingLog("Do grouping data clean up only");
				} else  {
					addNoteToGroupingLog("Doing post processing of record grouping");
				}

				if (fullRegroupingNoClear || onlyDoCleanup){
					removePrimaryIdentifiersForDeletedProfiles(pikaConn);
					// Remove primary identifiers first so that the parent work entry may be removed below
				}

				removeGroupedWorksWithoutPrimaryIdentifiers(pikaConn);
				if (!onlyDoCleanup && indexingProfileToRun == null) {
					// Do not update grouping time when processing a specific indexing profile
					if (!finishingGroupingEarly) {
						// Only update last grouping time when grouping got through all indexing profiles
						updateLastGroupingTime();
					}
				}

				pikaConn.setAutoCommit(true);
				logger.info("Finished doing post processing of record grouping");
			} catch (SQLException e) {
				logger.error("Error in grouped work post processing", e);
			}

			markRecordGroupingRunning(false);
		}

		long endTime     = new Date().getTime();
		long elapsedTime = endTime - processStartTime;
		if (logger.isInfoEnabled()) {
			logger.info("Finished grouping records");
			logger.info("Elapsed Minutes " + (elapsedTime / 60000));
		}

		try {
			PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE record_grouping_log SET endTime = ? WHERE id = ?");
			finishedStatement.setLong(1, endTime / 1000);
			finishedStatement.setLong(2, groupingLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update record grouping log with completion time.", e);
		}

		try {
			pikaConn.close();
			econtentConnection.close();
		} catch (Exception e) {
			logger.error("Error closing database ", e);
			System.exit(1);
		}
	}

	private static long startTime;
	private static long groupingTimeLimitInMilliSeconds = 0;
	private static void getGroupingTimeLimit(long processStartTime) {
		Long maxGroupingTime = systemVariables.getLongValuedVariable("grouping_time_limit");
		if (maxGroupingTime != null && maxGroupingTime > 0){
			startTime                       = processStartTime;
			groupingTimeLimitInMilliSeconds = maxGroupingTime * 60 * 1000;
		}
	}

	private static boolean finishingGroupingEarly = false;
	private static boolean passedTimeLimit(){
		return groupingTimeLimitInMilliSeconds > 0 && new Date().getTime() - startTime > groupingTimeLimitInMilliSeconds;
	}

	private static void removeDeletedRecords(String source, String dataDirPath) {
//		final boolean debugSierraExtract = logger.isDebugEnabled() && source.equals("ils");
		if (!marcRecordIdsInDatabase.isEmpty()) {
			addNoteToGroupingLog("Deleting " + marcRecordIdsInDatabase.size() + " record ids for profile " + source + " from the ils_marc_checksums table since they are no longer in the export.");
			for (String recordNumber : marcRecordIdsInDatabase.keySet()) {
				//Remove the record from the ils_marc_checksums table
				try {
					removeMarcRecordChecksum.setString(1, source);
					removeMarcRecordChecksum.setLong(2, marcRecordIdsInDatabase.get(recordNumber));
					int numRemoved = removeMarcRecordChecksum.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete {}:{} from ils_marc_checksums table", source, recordNumber);
					}
//					else if (debugSierraExtract) {
//						//This is to diagnose Sierra API Extract issues
//						logger.debug("Deleted ils record " + source + ":" + recordNumber + " from the ils checksum table.");
//					}

				} catch (SQLException e) {
					logger.error("Error removing id {}:{} from ils_marc_checksums table", source, recordNumber, e);
				}
			}
			marcRecordIdsInDatabase.clear();
		}

		if (!primaryIdentifiersInDatabase.isEmpty()) {
			addNoteToGroupingLog("Deleting " + primaryIdentifiersInDatabase.size() + " primary identifiers for profile " + source + " from the database since they are no longer in the export.");

			for (String recordNumber : primaryIdentifiersInDatabase.keySet()) {
				//Remove the record from the grouped_work_primary_identifiers table
				try {
					removePrimaryIdentifier.setLong(1, primaryIdentifiersInDatabase.get(recordNumber));
					int numRemoved = removePrimaryIdentifier.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete {} from grouped_work_primary_identifiers table", recordNumber);
					}
//					else if (debugSierraExtract) {
//							logger.debug("Deleting grouped work primary identifier entry for record " + recordNumber);
//					}
				} catch (SQLException e) {
					logger.error("Error removing {} from grouped_work_primary_identifiers table", recordNumber, e);
				}
			}
			TreeSet<String> theList = new TreeSet<>(primaryIdentifiersInDatabase.keySet());
			if (!theList.isEmpty()) {
				writeExistingRecordsFile(theList, "remaining_primary_identifiers_to_be_deleted", dataDirPath);
			}
			primaryIdentifiersInDatabase.clear();
		}
	}

	private static void markRecordGroupingRunning(boolean isRunning) {
		systemVariables.setVariable("record_grouping_running", isRunning);
	}

	private static final SimpleDateFormat dayFormatter = new SimpleDateFormat("yyyy-MM-dd");

	private static void writeExistingRecordsFile(TreeSet<String> recordNumbersInExport, String filePrefix, String dataDirPath) {
		try {
//			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
			File dataDir = new File(dataDirPath);
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date   curDate          = new Date();
			String curDateFormatted = dayFormatter.format(curDate);
			File   recordsFile      = new File(dataDir.getAbsolutePath() + "/" + filePrefix + "_" + curDateFormatted + ".csv");
			try (CSVWriter recordWriter = new CSVWriter(new FileWriter(recordsFile))) {
				for (String curRecord : recordNumbersInExport) {
					recordWriter.writeNext(new String[]{curRecord});
				}
				recordWriter.flush();
			}
		} catch (IOException e) {
			logger.error("Unable to write existing records to {} {}", filePrefix, e.getMessage());
		}
	}

	private static void updateLastGroupingTime() {
		//Update the last grouping time in the variables table
		try {
			Long finishTime = new Date().getTime() / 1000;
			systemVariables.setVariable("last_grouping_time", finishTime);
			addNoteToGroupingLog("Updated last_grouping_time to " + finishTime);
		} catch (Exception e) {
			logger.error("Error setting last grouping time", e);
		}
	}


	private static void removePrimaryIdentifiersForDeletedProfiles(Connection pikaConn) {
		//Remove any primary identifier that links to an indexing profile that no longer exists
		Long primaryIdentifierId = null;
		try {
			boolean autoCommit = pikaConn.getAutoCommit();
			pikaConn.setAutoCommit(false);
			try (
							PreparedStatement deletePrimaryIdentifierStmt = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ?");
							PreparedStatement primaryIdentifiersWithoutIndexingProfileStmt = pikaConn.prepareStatement(
											"SELECT id FROM grouped_work_primary_identifiers WHERE type != 'overdrive' AND type NOT IN (SELECT sourceName FROM indexing_profiles)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
							ResultSet primaryIdentifiersWithoutIndexingProfileRS = primaryIdentifiersWithoutIndexingProfileStmt.executeQuery()
			) {
				int orphanedPrimaryIdentifiers = 0;
				while (primaryIdentifiersWithoutIndexingProfileRS.next()) {
					primaryIdentifierId = primaryIdentifiersWithoutIndexingProfileRS.getLong(1);
					deletePrimaryIdentifierStmt.setLong(1, primaryIdentifierId);
					deletePrimaryIdentifierStmt.executeUpdate();

					orphanedPrimaryIdentifiers++;
					if (orphanedPrimaryIdentifiers % 500 == 0) {
						pikaConn.commit();
					}
				}
				addNoteToGroupingLog("Removed " + orphanedPrimaryIdentifiers + " primary identifiers linked to indexing profile that no longer exists");

			}
			pikaConn.commit();
			pikaConn.setAutoCommit(autoCommit);
		} catch (Exception e) {
			logger.error("Unable to remove grouped works that no longer have a primary identifier, grouped table id : {}", primaryIdentifierId, e);
		}
	}

	private static void removeGroupedWorksWithoutPrimaryIdentifiers(Connection pikaConn) {
		//Remove any grouped works that no longer link to a primary identifier
		Long groupedWorkId = null;
		try {
			boolean autoCommit = pikaConn.getAutoCommit();
			pikaConn.setAutoCommit(false);
			try (
					PreparedStatement deleteWorkStmt = pikaConn.prepareStatement("DELETE FROM grouped_work WHERE id = ?");
					PreparedStatement groupedWorksWithoutIdentifiersStmt = pikaConn.prepareStatement("SELECT grouped_work.id FROM grouped_work WHERE id NOT IN (SELECT DISTINCT grouped_work_id FROM grouped_work_primary_identifiers)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet groupedWorksWithoutIdentifiersRS = groupedWorksWithoutIdentifiersStmt.executeQuery()
			) {
				int numWorksNotLinkedToPrimaryIdentifier = 0;
				while (groupedWorksWithoutIdentifiersRS.next()) {
					groupedWorkId = groupedWorksWithoutIdentifiersRS.getLong(1);
					deleteWorkStmt.setLong(1, groupedWorkId);
					deleteWorkStmt.executeUpdate();

					numWorksNotLinkedToPrimaryIdentifier++;
					if (numWorksNotLinkedToPrimaryIdentifier % 500 == 0) {
						pikaConn.commit();
					}
				}
				addNoteToGroupingLog("Removed " + numWorksNotLinkedToPrimaryIdentifier + " grouped works that were not linked to primary identifiers");

			}
			pikaConn.commit();
			pikaConn.setAutoCommit(autoCommit);
		} catch (Exception e) {
			logger.error("Unable to remove grouped works that no longer have a primary identifier, grouped table id : {}", groupedWorkId, e);
		}
	}

	private static void loadIlsChecksums(Connection pikaConn, String indexingProfileToRun) {
		//Load MARC Existing MARC Record checksums from Pika
		try {
			if (insertMarcRecordChecksum == null) {
				insertMarcRecordChecksum = pikaConn.prepareStatement("INSERT INTO ils_marc_checksums (ilsId, source, checksum, dateFirstDetected) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), dateFirstDetected=VALUES(dateFirstDetected), source=VALUES(source)");
				removeMarcRecordChecksum = pikaConn.prepareStatement("DELETE FROM ils_marc_checksums WHERE source = ? AND id = ?");
			}

			//MDN 2/23/2015 - Always load checksums so we can optimize writing to the database
			String ilsMarcCheckSumSQL = "SELECT * FROM ils_marc_checksums ";
			if (indexingProfileToRun != null) {
				ilsMarcCheckSumSQL += " WHERE source LIKE ?";
			}
			try (PreparedStatement loadIlsMarcChecksums = pikaConn.prepareStatement(ilsMarcCheckSumSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
				loadIlsMarcChecksums.setString(1, indexingProfileToRun);
				try (ResultSet ilsMarcChecksumRS = loadIlsMarcChecksums.executeQuery()) {
					Long zero = 0L;
					while (ilsMarcChecksumRS.next()) {
						Long checksum = ilsMarcChecksumRS.getLong("checksum");
						if (checksum.equals(zero)) {
							checksum = null;
						}
						String fullIdentifier = ilsMarcChecksumRS.getString("source") + ":" + ilsMarcChecksumRS.getString("ilsId").trim();
						marcRecordChecksums.put(fullIdentifier, checksum);
						marcRecordFirstDetectionDates.put(fullIdentifier, ilsMarcChecksumRS.getLong("dateFirstDetected"));
						if (ilsMarcChecksumRS.wasNull()) {
							marcRecordFirstDetectionDates.put(fullIdentifier, null);
						}
						String fullIdentifierLowerCase = fullIdentifier.toLowerCase();
						if (marcRecordIdsInDatabase.containsKey(fullIdentifierLowerCase)) {
							logger.warn("{} was already loaded in marcRecordIdsInDatabase", fullIdentifierLowerCase);
						} else {
							marcRecordIdsInDatabase.put(fullIdentifierLowerCase, ilsMarcChecksumRS.getLong("id"));
						}
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error loading marc checksums for marc records : {}", indexingProfileToRun, e);
			System.exit(1);
		}
	}

	private static void loadExistingPrimaryIdentifiers(Connection pikaConn, String source) {
		//Load Existing Primary Identifiers so we can clean up
		try {
			if (removePrimaryIdentifier == null) {
				removePrimaryIdentifier = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ?");
			}

			String primaryIdentifiersSQL = "SELECT * FROM grouped_work_primary_identifiers";
			if (source != null) {
				primaryIdentifiersSQL += " WHERE type LIKE ?";
			}
			try (PreparedStatement loadPrimaryIdentifiers = pikaConn.prepareStatement(primaryIdentifiersSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
				if (source != null) {
					loadPrimaryIdentifiers.setString(1, source);
				}

				try (ResultSet primaryIdentifiersRS = loadPrimaryIdentifiers.executeQuery()) {
					while (primaryIdentifiersRS.next()) {
						String fullIdentifier      = primaryIdentifiersRS.getString("type") + ":" + primaryIdentifiersRS.getString("identifier").trim();
						String identifierLowerCase = fullIdentifier.toLowerCase();
						if (primaryIdentifiersInDatabase.containsKey(identifierLowerCase)) {
							logger.warn(identifierLowerCase + " was already loaded in primaryIdentifiersInDatabase");
						} else {
							primaryIdentifiersInDatabase.put(identifierLowerCase, primaryIdentifiersRS.getLong("id"));
						}
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error loading primary identifiers ", e);
			System.exit(1);
		}
	}

	/**
	 * Clear out Grouped Work tables
	 */
	private static void clearDatabase() {
		try {
			addNoteToGroupingLog("Clearing out grouping related tables.");
			pikaConn.prepareStatement("TRUNCATE ils_marc_checksums").executeUpdate();
			pikaConn.prepareStatement("TRUNCATE grouped_work").executeUpdate();
			pikaConn.prepareStatement("TRUNCATE grouped_work_primary_identifiers").executeUpdate();
		} catch (Exception e) {
			logger.error("Error clearing database", e);
			System.out.println("Error clearing database " + e);
			System.exit(1);
		}
	}
	private static boolean updateProfileLastGroupedTime(long profileId, long newLastGroupedTime){
		try {
			updateProfileLastGroupedTimeStmt.setLong(1, newLastGroupedTime);
			updateProfileLastGroupedTimeStmt.setLong(2, profileId);
			int result = updateProfileLastGroupedTimeStmt.executeUpdate();
			return result > 0;
		} catch (SQLException e) {
			logger.error("Error updating indexing profile {} last grouped time", profileId, e);
		}
	return false;
	}

	private static void groupIlsRecords(Connection pikaConn, ArrayList<IndexingProfile> indexingProfiles, boolean explodeMarcsOnly) {
		try {
			updateProfileLastGroupedTimeStmt = pikaConn.prepareStatement("UPDATE indexing_profiles SET lastGroupedTime = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Error Setting up updateProfileLastGroupedTimeStmt ", e);
		}
		//Get indexing profiles
		for (IndexingProfile curProfile : indexingProfiles) {
			if (!passedTimeLimit()) {
				addNoteToGroupingLog("Processing profile " + curProfile.sourceName);

				//Check to see if we should process the profile
				long            profileStartGroupingTime = new Date().getTime() / 1000;
				String          marcPath                 = curProfile.marcPath;
				boolean         checkMinFileSize         = curProfile.minMarcFileSize != null && curProfile.minMarcFileSize > 0L;
				boolean         processProfile           = curProfile.groupUnchangedFiles || fullRegroupingClearGroupingTables;
				ArrayList<File> filesToProcess           = new ArrayList<>();
				Pattern         filesToMatchPattern      = Pattern.compile(curProfile.filenamesToInclude, Pattern.CASE_INSENSITIVE);
				FileFilter      marcFileFilter           = (file) -> filesToMatchPattern.matcher(file.getName()).matches();
				File[]          marcFiles                = new File(marcPath).listFiles(marcFileFilter);
				//Check to see if we have any new files, if so we will process all of them to be sure deletes and overlays process properly
				if (marcFiles != null && marcFiles.length > 0) {
					if (checkMinFileSize && marcFiles.length > 1) {
						checkMinFileSize = false;
						logger.error("Profile setting minMarcFileSize will only be used when there is only one marc file for the profile to group");
					}
					for (File marcFile : marcFiles) {
						if (curProfile.groupUnchangedFiles) {
							logger.warn("groupUnchangedFiles is on for profile {}. This setting should be turned off once the profile has been correctly set up.", curProfile.sourceName);
						}
						if (checkMinFileSize && marcFile.length() < curProfile.minMarcFileSize) {
							processProfile = false;
							addNoteToGroupingLog(curProfile.sourceName + " will be skipped because the full marc file is below the minimum file size.");
							logger.error("{} profile's marc file {} file size {} is below min file size level {}", curProfile.sourceName, marcFile.getName(), marcFile.length(), curProfile.minMarcFileSize);
						} else {
							filesToProcess.add(marcFile);
							if (!processProfile) {
								final long fileLastModifiedTime = marcFile.lastModified();
								if (curProfile.lastGroupedTime == 0) {
									if (fileLastModifiedTime > lastGroupingTime * 1000) {
										//If the file has changed since the last grouping time we should process it again
										// (Normally we want to skip grouping if the records haven't changed, since the last time we have grouped them)
										processProfile = true;
									}
								} else if (fileLastModifiedTime > curProfile.lastGroupedTime * 1000) {
									// Now we can track grouping time for specific sideloads so that they can also be processed outside the regular full regrouping
									processProfile = true;
								}
							}
							if (checkMinFileSize) {
								long  fileSize     = marcFile.length();
								float percentAbove = ((float) (fileSize - curProfile.minMarcFileSize)) / fileSize;
								if (logger.isInfoEnabled()){
									logger.info("Minimum full export file size checking is enabled.  The min level is : {}",  curProfile.minMarcFileSize);
									logger.info("The full export file is {} percent above min level.", percentAbove);
								}
								if (percentAbove > 0.05f) {
									long newLevel = Math.round(fileSize * 0.97f);
									logger.warn("Marc file is more than 5% larger the min size level. Please adjust the min size level to {}", newLevel);
								}
							}
						}
					}
					if (!processProfile) {
						logger.debug("Checking if {} has had any records marked for regrouping.", curProfile.sourceName);
						if (!curProfile.usingSierraAPIExtract && checkForForcedRegrouping(pikaConn, curProfile.sourceName)) {
							//TODO: Just regroup the records that have been marked for regrouping
							processProfile = true;
							addNoteToGroupingLog(curProfile.sourceName + " has no file changes but will be processed because records have been marked for forced regrouping.");
						}
					}
				} else {
					processProfile = false;
					addNoteToGroupingLog("No marc files matching profile criteria found to group");
				}
				if (!processProfile) {
					addNoteToGroupingLog("Skipping processing profile " + curProfile.sourceName + " because nothing has changed");
				} else {
					loadIlsChecksums(pikaConn, curProfile.sourceName);
					loadExistingPrimaryIdentifiers(pikaConn, curProfile.sourceName);

					MarcRecordGrouper recordGroupingProcessor;
					switch (curProfile.groupingClass) {
						case "MarcRecordGrouper":
							recordGroupingProcessor = new MarcRecordGrouper(pikaConn, curProfile, logger, fullRegroupingNoClear || fullRegroupingClearGroupingTables);
							break;
						case "SideLoadedRecordGrouper":
							recordGroupingProcessor = new SideLoadedRecordGrouper(pikaConn, curProfile, logger, fullRegroupingNoClear || fullRegroupingClearGroupingTables);
							break;
						case "HooplaRecordGrouper":
							recordGroupingProcessor = new HooplaRecordGrouper(pikaConn, curProfile, logger, fullRegroupingNoClear || fullRegroupingClearGroupingTables);
							break;
						case "PolarisRecordGrouper":
							recordGroupingProcessor = new PolarisRecordGrouper(pikaConn, curProfile, logger);
							break;
						default:
							logger.error("Unknown class for record grouping " + curProfile.groupingClass);
							continue;
					}

					String          marcEncoding                     = curProfile.marcEncoding;
					long            totalRecordsGroupedForProfile    = 0;
					TreeSet<String> recordNumbersInExport            = new TreeSet<>();
 					TreeSet<String> suppressedRecordNumbersInExport  = new TreeSet<>();
					TreeSet<String> suppressedControlNumbersInExport = new TreeSet<>();
					TreeSet<String> marcRecordsOverwritten           = new TreeSet<>();
					TreeSet<String> marcRecordsWritten               = new TreeSet<>();
					TreeSet<String> recordNumbersToIndex             = new TreeSet<>();

					String lastRecordProcessed = "";
					for (File curBibFile : filesToProcess) {
 						String recordId            = "";
						int    numRecordsProcessed = 0;
						int    numRecordsRead      = 0;
						try (FileInputStream marcFileStream = new FileInputStream(curBibFile)) {
							MarcReader catalogReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
							logger.info("Grouping file " + curBibFile.getAbsolutePath());
							while (catalogReader.hasNext()) {
								recordId = ""; // reset the record Id in case of exceptions
								try {
									Record           curBib           = catalogReader.next();
									RecordIdentifier recordIdentifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(curBib, curProfile.sourceName, curProfile.doAutomaticEcontentSuppression);
									if (recordIdentifier == null) {
	//									if (logger.isDebugEnabled()) {
	//										logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
	//									}
										String controlNumber = curBib.getControlNumber();
										if (controlNumber != null) {
											suppressedControlNumbersInExport.add(controlNumber);
										} else {
											logger.warn("Bib did not have control number or identifier, the {}-th record of the file {}", numRecordsRead, curBibFile.getAbsolutePath());
											if (logger.isInfoEnabled()) {
												logger.info(curBib.toString());
											}
										}
									} else if (recordIdentifier.isSuppressed()) {
	//									if (logger.isDebugEnabled()) {
	//										logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
	//									}
										suppressedRecordNumbersInExport.add(recordIdentifier.getIdentifier());
									} else {
										recordId = recordIdentifier.getIdentifier();

										boolean marcUpToDate = writeIndividualMarc(curProfile, curBib, recordIdentifier, marcRecordsWritten, marcRecordsOverwritten);
										// when fullRegroupingClearGroupingTables is true writeIndividualMarc() should return false
										recordNumbersInExport.add(recordIdentifier.toString());
										if (!explodeMarcsOnly) {
											if (!marcUpToDate || fullRegroupingNoClear) {
												if (recordGroupingProcessor.processMarcRecord(curBib, !marcUpToDate, recordIdentifier)) {
													recordNumbersToIndex.add(recordIdentifier.toString());
												} else {
													suppressedRecordNumbersInExport.add(recordIdentifier.toString());
												}
												numRecordsProcessed++;
											}
											//Mark that the record was processed
											String fullId = recordIdentifier.toString().toLowerCase().trim();
											marcRecordIdsInDatabase.remove(fullId);
											primaryIdentifiersInDatabase.remove(fullId);
										}
										lastRecordProcessed = recordId;
									}
								} catch (MarcException e) {
									if (!recordId.isEmpty()) {
										logger.warn("Error processing individual record for {} on the {}-th record of {} the last record processed was {} trying to continue", recordId, numRecordsRead, curBibFile.getAbsolutePath(), lastRecordProcessed, e);
									} else {
										logger.warn("Error processing individual record on the {}-th record of {}  The last record processed was {}, trying to continue", numRecordsRead, curBibFile.getAbsolutePath(), lastRecordProcessed, e);
									}
								}
								numRecordsRead++;
//								if (numRecordsRead % 100000 == 0) {
//									recordGroupingProcessor.dumpStats();
//								}
								//TODO: temp?
	//							if (numRecordsRead % 5000 == 0) {
	//								updateLastUpdateTimeInLog();
	//								//Let the hard drives rest a bit so other things can happen.
	//								Thread.sleep(100);
	//							}
							}
						} catch (Exception e) {
							if (!recordId.isEmpty()) {
								logger.error("Error loading bibs on record {} in profile {} on the record  {}", numRecordsRead, curProfile.sourceName, recordId, e);
							} else {
								logger.error("Error loading bibs on record {} in profile {} the last record processed was {}", numRecordsRead, curProfile.sourceName, lastRecordProcessed, e);
							}
						}
						addNoteToGroupingLog("&nbsp;&nbsp; - Finished checking " + numRecordsRead + " records with " + numRecordsProcessed + " actual changes grouped from the marc file " + curBibFile.getName() + " in profile " + curProfile.sourceName);
						totalRecordsGroupedForProfile += numRecordsProcessed;
					}

					addNoteToGroupingLog("&nbsp;&nbsp; - Records in Export(s)  : " + recordNumbersInExport.size());
					addNoteToGroupingLog("&nbsp;&nbsp; - Records Suppressed    : " + suppressedRecordNumbersInExport.size());
					addNoteToGroupingLog("&nbsp;&nbsp; - New Records Written   : " + marcRecordsWritten.size());
					addNoteToGroupingLog("&nbsp;&nbsp; - Records Overwritten   : " + marcRecordsOverwritten.size());
					addNoteToGroupingLog("&nbsp;&nbsp; - Total Records Grouped : " + totalRecordsGroupedForProfile);
					addNoteToGroupingLog("&nbsp;&nbsp; - Elapsed Time (mins)   : " + ((new Date().getTime()/1000) - profileStartGroupingTime) / 60);

					removeDeletedRecords(curProfile.sourceName, marcPath);

					if (!updateProfileLastGroupedTime(curProfile.id, profileStartGroupingTime)){
						logger.error("Failed to set last grouping time for " + curProfile.sourceName );
					}

					String profileName = curProfile.sourceName.replaceAll(" ", "_");
					writeExistingRecordsFile(recordNumbersInExport, "record_grouping_" + profileName + "_bibs_in_export", marcPath);
					if (!suppressedRecordNumbersInExport.isEmpty()) {
						writeExistingRecordsFile(suppressedRecordNumbersInExport, "record_grouping_" + profileName + "_bibs_to_ignore", marcPath);
					}
					if (!suppressedControlNumbersInExport.isEmpty()) {
						writeExistingRecordsFile(suppressedControlNumbersInExport, "record_grouping_" + profileName + "_control_numbers_to_ignore", marcPath);
					}
					if (!recordNumbersToIndex.isEmpty()) {
						writeExistingRecordsFile(recordNumbersToIndex, "record_grouping_" + profileName + "_bibs_to_index", marcPath);
					}
					if (!marcRecordsWritten.isEmpty()) {
						writeExistingRecordsFile(marcRecordsWritten, "record_grouping_" + profileName + "_new_bibs_written", marcPath);
					}
					if (!marcRecordsOverwritten.isEmpty()) {
						writeExistingRecordsFile(marcRecordsOverwritten, "record_grouping_" + profileName + "_changed_bibs_written", marcPath);
					}
				}
			} else {
				finishingGroupingEarly = true;
				addNoteToGroupingLog("Passed grouping time limit. Skipping profile " + curProfile.sourceName);
			}

		}
	}

	private static void groupOverDriveRecords(Connection pikaConn, Connection econtentConnection, boolean explodeMarcsOnly) {
		if (explodeMarcsOnly) {
			//Nothing to do since we don't have marc records to process
			return;
		}
		long startTime = new Date().getTime();
		OverDriveRecordGrouper recordGroupingProcessor = new OverDriveRecordGrouper(pikaConn, econtentConnection, logger);
		addNoteToGroupingLog("Starting to group overdrive records");
//		loadIlsChecksums(pikaConn, "overdrive"); // There are no checksums for overdrive metadata
		loadExistingPrimaryIdentifiers(pikaConn, "overdrive");

		int numRecordsProcessed = 0;
		try {
//			String            OverdriveRecordSQL = "SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName, code, publisher, edition FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id INNER JOIN overdrive_api_product_languages_ref ON overdrive_api_product_languages_ref.productId = overdrive_api_products.id INNER JOIN overdrive_api_product_languages ON overdrive_api_product_languages_ref.languageId = overdrive_api_product_languages.id WHERE deleted = 0 AND isOwnedByCollections = 1";
			// Because we may be dealing with multiple overdrive accounts now, the flag isOwnedByCollections is not a reliable filter (one collect many own while the other doesn't)
			String            OverdriveRecordSQL = "SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName, code, publisher, edition FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id LEFT JOIN overdrive_api_product_languages_ref ON overdrive_api_product_languages_ref.productId = overdrive_api_products.id LEFT JOIN overdrive_api_product_languages ON overdrive_api_product_languages_ref.languageId = overdrive_api_product_languages.id WHERE deleted = 0";
			//LEFT joins needed to fetch titles without language information
			PreparedStatement overDriveRecordsStmt;
			if (lastGroupingTime != null && !fullRegroupingClearGroupingTables && !fullRegroupingNoClear) {
				//TODO: I suspect we only need to update grouping for things that have been modified since last grouping
				// when in the fullRegroupingNoClear mode. pascal 9/22/21
				overDriveRecordsStmt = econtentConnection.prepareStatement(OverdriveRecordSQL + " AND (dateUpdated >= ? OR lastMetadataChange >= ? OR lastAvailabilityChange >= ?)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				//TODO: which availability changes effect grouping?
				overDriveRecordsStmt.setLong(1, lastGroupingTime);
				overDriveRecordsStmt.setLong(2, lastGroupingTime);
				overDriveRecordsStmt.setLong(3, lastGroupingTime);
			} else {
				overDriveRecordsStmt = econtentConnection.prepareStatement(OverdriveRecordSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			}
			TreeSet<String> recordNumbersInExport = new TreeSet<>();
			try (
					ResultSet overDriveRecordRS = overDriveRecordsStmt.executeQuery()
			) {

				while (overDriveRecordRS.next()) {
					String           overdriveId       = overDriveRecordRS.getString("overdriveId");
					RecordIdentifier primaryIdentifier = new RecordIdentifier("overdrive", overdriveId);
					recordGroupingProcessor.processOverDriveRecord(primaryIdentifier, overDriveRecordRS, true);
					recordNumbersInExport.add(overdriveId);

					primaryIdentifiersInDatabase.remove(primaryIdentifier.toString().toLowerCase());
					numRecordsProcessed++;
				}
			}

			String dataDirPath = PikaConfigIni.getIniValue("Reindex", "marcPath");
			if (fullRegroupingClearGroupingTables) {
				writeExistingRecordsFile(recordNumbersInExport, "record_grouping_overdrive_records_in_export", dataDirPath);
			}
			if (fullRegroupingNoClear) { //TODO: verify this is the only time we need to do this
				removeDeletedRecords("overdrive", dataDirPath);
			}
			addNoteToGroupingLog("Finished grouping " + numRecordsProcessed + " records from overdrive ");
			addNoteToGroupingLog("&nbsp;&nbsp; - Elapsed Time (mins)   : " + (new Date().getTime() - startTime) / 60000);

		} catch (Exception e) {
			logger.error("Error processing OverDrive data", e);
		}
	}

	private static final SimpleDateFormat oo8DateFormat = new SimpleDateFormat("yyMMdd");
	private static final SimpleDateFormat oo5DateFormat = new SimpleDateFormat("yyyyMMdd");

	private static boolean writeIndividualMarc(IndexingProfile indexingProfile, Record marcRecord, RecordIdentifier recordIdentifier, TreeSet<String> marcRecordsWritten, TreeSet<String> marcRecordsOverwritten) {
		boolean marcRecordUpToDate = false;
		//Copy the record to the individual marc path
		if (recordIdentifier != null) {
			long checksum         = getChecksum(marcRecord);
			Long existingChecksum = getExistingChecksum(recordIdentifier);
			File individualFile   = indexingProfile.getFileForIlsRecord(recordIdentifier.getIdentifier());

			//If we are doing partial regrouping or full regrouping without clearing the previous results,
			//Check to see if the record needs to be written before writing it.
			if (!fullRegroupingClearGroupingTables) {
				boolean checksumUpToDate = existingChecksum != null && existingChecksum.equals(checksum);
				boolean fileExists       = individualFile.exists();
				marcRecordUpToDate = fileExists && checksumUpToDate;
				if (!fileExists) {
					marcRecordsWritten.add(recordIdentifier.getIdentifier());
				} else if (!checksumUpToDate) {
					marcRecordsOverwritten.add(recordIdentifier.getIdentifier());
				}
				//Temporary confirmation of CRC
				if (marcRecordUpToDate && validateChecksumsFromDisk) {
					try (FileInputStream inputStream = new FileInputStream(individualFile)) {
						MarcReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true);

						Record recordOnDisk   = marcReader.next();
						Long   actualChecksum = getChecksum(recordOnDisk);
						if (!actualChecksum.equals(checksum)) {
							//checksum in the database is wrong
							marcRecordUpToDate = false;
							marcRecordsOverwritten.add(recordIdentifier.getIdentifier());
						}
					} catch (FileNotFoundException e) {
						if (logger.isDebugEnabled()) {
							logger.debug("Individual marc record not found " + recordIdentifier);
						}
						marcRecordUpToDate = false;
					} catch (Exception e) {
						logger.error("Error getting checksum for file", e);
						marcRecordUpToDate = false;
					}
				}
			}

			if (!marcRecordUpToDate) {
				try {
					outputMarcRecord(marcRecord, individualFile);
					getDateAddedForRecord(marcRecord, recordIdentifier, individualFile);
					updateMarcRecordChecksum(recordIdentifier, checksum);
					//logger.debug("checksum changed for " + recordIdentifier + " was " + existingChecksum + " now its " + checksum);
				} catch (IOException e) {
					logger.error("Error writing marc for record " + recordIdentifier, e);
				}
			} else {
				//Update date first detected if needed
				if (marcRecordFirstDetectionDates.containsKey(recordIdentifier.getIdentifier()) && marcRecordFirstDetectionDates.get(recordIdentifier.getSourceAndId()) == null) {
					getDateAddedForRecord(marcRecord, recordIdentifier, individualFile);
					updateMarcRecordChecksum(recordIdentifier, checksum);
				}
			}
		} else {
			logger.error("Record number for MARC record was not supplied");
			marcRecordUpToDate = true;
		}
		return marcRecordUpToDate;
	}

	private static void getDateAddedForRecord(Record marcRecord, RecordIdentifier recordIdentifier, File individualFile) {
		//Set first detection date based on the creation date of the file
		if (individualFile.exists()) {
			Path filePath = individualFile.toPath();
			try {
				//First get the date we first saw the file
				BasicFileAttributes attributes = Files.readAttributes(filePath, BasicFileAttributes.class);
				long                timeAdded  = attributes.creationTime().toMillis() / 1000;
				//Check within the bib to see if there is an earlier date, first the 008
				//Which should contain the creation date
				ControlField oo8 = (ControlField) marcRecord.getVariableField("008");
				if (oo8 != null) {
					if (oo8.getData().length() >= 6) {
						String dateAddedStr = oo8.getData().substring(0, 6);
						try {
							Date dateAdded = oo8DateFormat.parse(dateAddedStr);
							if (dateAdded.getTime() / 1000 < timeAdded) {
								timeAdded = dateAdded.getTime() / 1000;
							}
						} catch (ParseException e) {
							//Could not parse the date, but that's ok
						}
					}
				}
				//Now the 005 which has last transaction date.   Not ideal, but ok if it's earlier than
				//what we have.
				ControlField oo5 = (ControlField) marcRecord.getVariableField("005");
				if (oo5 != null) {
					if (oo5.getData().length() >= 8) {
						String dateAddedStr = oo5.getData().substring(0, 8);
						try {
							Date dateAdded = oo5DateFormat.parse(dateAddedStr);
							if (dateAdded.getTime() / 1000 < timeAdded) {
								timeAdded = dateAdded.getTime() / 1000;
							}
						} catch (ParseException e) {
							//Could not parse the date, but that's ok
						}
					}
				}
				marcRecordFirstDetectionDates.put(recordIdentifier.getSourceAndId(), timeAdded);
			} catch (Exception e) {
				if (logger.isDebugEnabled()) {
					logger.debug("Error loading creation time for " + filePath, e);
				}
			}
		}
	}

	private static Long getExistingChecksum(RecordIdentifier recordNumber) {
		return marcRecordChecksums.get(recordNumber.getSourceAndId());
	}

	private static void updateMarcRecordChecksum(RecordIdentifier recordIdentifier, long checksum) {
		long   dateFirstDetected;
		String recordNumberWithSource = recordIdentifier.getSourceAndId();
		if (marcRecordFirstDetectionDates.containsKey(recordNumberWithSource) && marcRecordFirstDetectionDates.get(recordNumberWithSource) != null) {
			dateFirstDetected = marcRecordFirstDetectionDates.get(recordNumberWithSource);
		} else {
			dateFirstDetected = new Date().getTime() / 1000;
		}
		try {
			insertMarcRecordChecksum.setString(1, recordIdentifier.getIdentifier());
			insertMarcRecordChecksum.setString(2, recordIdentifier.getSource());
			insertMarcRecordChecksum.setLong(3, checksum);
			insertMarcRecordChecksum.setLong(4, dateFirstDetected);
			insertMarcRecordChecksum.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update checksum for marc record : " + recordIdentifier, e);
		}
	}

	private static void outputMarcRecord(Record marcRecord, File individualFile) throws IOException {
		try (FileOutputStream outputStream = new FileOutputStream(individualFile, false)) {
			MarcStreamWriter writer2 = new MarcStreamWriter(outputStream, "UTF-8", true);
			writer2.write(marcRecord);
			writer2.close();
		}
	}

	private static final Pattern specialCharPattern = Pattern.compile("\\p{C}");

	private static long getChecksum(Record marcRecord) {
		CRC32  crc32              = new CRC32();
		String marcRecordContents = marcRecord.toString();
		//There can be slight differences in how the record length gets calculated between ILS export and what is written
		//by MARC4J since there can be differences in whitespace and encoding.
		// Remove the text LEADER
		// Remove the length of the record
		// Remove characters in position 12-16 (position of data)
		marcRecordContents = marcRecordContents.substring(12, 19) + marcRecordContents.substring(24).trim();
		marcRecordContents = specialCharPattern.matcher(marcRecordContents).replaceAll("?");
		crc32.update(marcRecordContents.getBytes());
		return crc32.getValue();
	}

	private static final StringBuffer     notes      = new StringBuffer();
	private static final SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

	private static void addNoteToGroupingLog(String note) {
		try {
			Date date = new Date();
			notes.append("<br>").append(dateFormat.format(date)).append(": ").append(note);
			addNoteToGroupingLogStmt.setString(1, trimLogEntry(notes.toString()));
			addNoteToGroupingLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToGroupingLogStmt.setLong(3, groupingLogId);
			addNoteToGroupingLogStmt.executeUpdate();
			if (logger.isInfoEnabled()) {
				logger.info(note);
			}
		} catch (SQLException e) {
			logger.error("Error adding note to Record Grouping Log", e);
		}
	}

	private static void updateLastUpdateTimeInLog() {
		try {
			addNoteToGroupingLogStmt.setString(1, trimLogEntry(notes.toString()));
			addNoteToGroupingLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToGroupingLogStmt.setLong(3, groupingLogId);
			addNoteToGroupingLogStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error adding note to Record Grouping Log", e);
		}
	}

	private static String trimLogEntry(String stringToTrim) {
		if (stringToTrim == null) {
			return null;
		}
		if (stringToTrim.length() > 65535) {
			stringToTrim = stringToTrim.substring(0, 65535);
		}
		return stringToTrim.trim();
	}

	private static boolean checkForForcedRegrouping(Connection pikaConn, String sourceName) {
		try (PreparedStatement checkForRecordsMarkedForRegrouping = pikaConn.prepareStatement("SELECT COUNT(*) FROM ils_marc_checksums WHERE source = ? AND checksum = 0", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			checkForRecordsMarkedForRegrouping.setString(1, sourceName);
			try (ResultSet checkForRecordsMarkedForRegroupingRS = checkForRecordsMarkedForRegrouping.executeQuery()) {
				checkForRecordsMarkedForRegroupingRS.next();
				long numMarkedCheckSums = checkForRecordsMarkedForRegroupingRS.getLong(1);
				if (numMarkedCheckSums > 0) {
					return true;
				}
			}
		} catch (SQLException e) {
			logger.error("Error checking for grouped works marked for forced regrouping", e);
		}
		return false;
	}
}
