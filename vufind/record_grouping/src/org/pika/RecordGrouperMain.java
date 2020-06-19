package org.pika;

//import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
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
	private static Logger              logger = Logger.getLogger(RecordGrouperMain.class);
	private static String              serverName;
	private static PikaSystemVariables systemVariables;

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

	public static void main(String[] args) {
		// Get the configuration filename
		if (args.length == 0) {
			System.out.println("Welcome to the Record Grouping Application developed by Marmot Library Network.  \n" +
					"This application will group works by title, author, language, and format to create a \n" +
					"unique work id.  \n" +
					"\n" +
					"Additional information about the grouping process can be found at: \n" +
					"TBD\n" + //TODO: marmot page
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
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.grouping.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
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
					title  = args[2];
					author = args[3];
					format = args[4];
					languageCode = args[5];
					if (args.length >= 7) {
						subtitle = args[6];
					}
				} else {
					title    = getInputFromCommandLine("Enter the title");
					subtitle = getInputFromCommandLine("Enter the subtitle");
					author   = getInputFromCommandLine("Enter the author");
					format   = getInputFromCommandLine("Enter the format");
					languageCode = getInputFromCommandLine("Enter the language code");
				}
				// Load the configuration file
				PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

				//Connect to the database
				Connection pikaConn           = null;
				try {
					String databaseConnectionInfo   = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
					pikaConn           = DriverManager.getConnection(databaseConnectionInfo);
				} catch (Exception e) {
					System.out.println("Error connecting to database " + e.toString());
					System.exit(1);
				}

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
				System.out.print(result.toString());
				break;
			default:
				doStandardRecordGrouping(args);
				break;
		}
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
					logger.warn("Error determining whether or not an indexing profile uses the Siera API Extract");
					// log the error but otherwise continue as normal
				}

				indexingProfiles.add(profile);
			}

		} catch (Exception e) {
			logger.error("Error loading indexing profiles", e);
			System.exit(1);
		}
		return indexingProfiles;
	}

	private static void doStandardRecordGrouping(String[] args) {
		long processStartTime = new Date().getTime();

		if (logger.isInfoEnabled()) {
			logger.info("Starting grouping of records " + new Date().toString());
		}

		// Load the configuration file
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Connect to the database
		Connection pikaConn           = null;
		Connection econtentConnection = null;
		try {
			String databaseConnectionInfo   = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			String econtentDBConnectionInfo = PikaConfigIni.getIniValue("Database", "database_econtent_jdbc");
			pikaConn           = DriverManager.getConnection(databaseConnectionInfo);
			econtentConnection = DriverManager.getConnection(econtentDBConnectionInfo);

		} catch (Exception e) {
			System.out.println("Error connecting to database " + e.toString());
			System.exit(1);
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		//Start a reindex log entry
		try {
			if (logger.isInfoEnabled()) {
				logger.info("Creating log entry for index");
			}
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
		lastGroupingTime           = systemVariables.getLongValuedVariable("last_grouping_time");

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
				case "fullregroupingnoclear":
					fullRegroupingNoClear = true;
					break;
				case "fullregrouping":
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

		RecordGroupingProcessor recordGroupingProcessor = null;
		if (!onlyDoCleanup) {

			if (!explodeMarcsOnly) {
				markRecordGroupingRunning(true);

				clearDatabase(pikaConn, clearDatabasePriorToGrouping);
			}

			//Determine if we want to validateChecksumsFromDisk
			validateChecksumsFromDisk = systemVariables.getBooleanValuedVariable("validateChecksumsFromDisk");

			ArrayList<IndexingProfile> indexingProfiles = loadIndexingProfiles(pikaConn, indexingProfileToRun);

			// Main Record Grouping Processing
			if (indexingProfileToRun == null || indexingProfileToRun.equalsIgnoreCase("overdrive")) {
				groupOverDriveRecords(pikaConn, econtentConnection, explodeMarcsOnly);
			}
			if (indexingProfiles.size() > 0) {
				groupIlsRecords(pikaConn, indexingProfiles, explodeMarcsOnly);
			}

		}

		if (!explodeMarcsOnly) {
			try {
				if (logger.isInfoEnabled()) {
					logger.info("Doing post processing of record grouping");
				}
				pikaConn.setAutoCommit(false);

				//Cleanup the data
				removeGroupedWorksWithoutPrimaryIdentifiers(pikaConn);
				pikaConn.commit();
				updateLastGroupingTime();
				pikaConn.commit();

				pikaConn.setAutoCommit(true);
				if (logger.isInfoEnabled()) {
					logger.info("Finished doing post processing of record grouping");
				}
			} catch (SQLException e) {
				logger.error("Error in grouped work post processing", e);
			}

			markRecordGroupingRunning(false);
		}

		if (recordGroupingProcessor != null) {
			recordGroupingProcessor.dumpStats();
			//TODO: each profile should run this??
		}

		long endTime     = new Date().getTime();
		long elapsedTime = endTime - processStartTime;
		if (logger.isInfoEnabled()) {
			logger.info("Finished grouping records " + new Date().toString());
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

	private static void removeDeletedRecords(String source, String dataDirPath) {
		if (marcRecordIdsInDatabase.size() > 0) {
			addNoteToGroupingLog("Deleting " + marcRecordIdsInDatabase.size() + " record ids for profile " + source + " from the ils_marc_checksums table since they are no longer in the export.");
			for (String recordNumber : marcRecordIdsInDatabase.keySet()) {
				//Remove the record from the ils_marc_checksums table
				try {
					removeMarcRecordChecksum.setString(1, source);
					removeMarcRecordChecksum.setLong(2, marcRecordIdsInDatabase.get(recordNumber));
					int numRemoved = removeMarcRecordChecksum.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete " + source + ":" + recordNumber + " from ils_marc_checksums table");
					} else if (source.equals("ils")) {
						//This is to diagnose Sierra API Extract issues
						if (logger.isDebugEnabled()) {
							logger.debug("Deleted ils record " +  source + ":" + recordNumber + " from the ils checksum table.");
						}

					}
				} catch (SQLException e) {
					logger.error("Error removing id " +  source + ":" + recordNumber + " from ils_marc_checksums table", e);
				}
			}
			marcRecordIdsInDatabase.clear();
		}

		if (primaryIdentifiersInDatabase.size() > 0) {
			if (logger.isInfoEnabled()) {
				logger.info("Deleting " + primaryIdentifiersInDatabase.size() + " primary identifiers for profile " + source + " from the database since they are no longer in the export.");
			}
			for (String recordNumber : primaryIdentifiersInDatabase.keySet()) {
				//Remove the record from the grouped_work_primary_identifiers table
				try {
					removePrimaryIdentifier.setLong(1, primaryIdentifiersInDatabase.get(recordNumber));
					int numRemoved = removePrimaryIdentifier.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete " + recordNumber + " from grouped_work_primary_identifiers table");
					} else if (source.equals("ils")) {
						//TODO: this is temporary. this is to diagnose Sierra API Extract issues
						if (logger.isDebugEnabled()) {
							logger.debug("Deleting grouped work primary identifier entry for record " + recordNumber);
						}
					}
				} catch (SQLException e) {
					logger.error("Error removing " + recordNumber + " from grouped_work_primary_identifiers table", e);
				}
			}
			TreeSet<String> theList = new TreeSet<String>(primaryIdentifiersInDatabase.keySet());
			writeExistingRecordsFile(theList, "remaining_primary_identifiers_to_be_deleted", dataDirPath);
			primaryIdentifiersInDatabase.clear();
		}
	}

	private static void markRecordGroupingRunning(boolean isRunning) {
		systemVariables.setVariable("record_grouping_running", isRunning);
	}

	private static SimpleDateFormat dayFormatter = new SimpleDateFormat("yyyy-MM-dd");

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
			logger.error("Unable to write existing records to " + filePrefix, e);
		}
	}

	private static void updateLastGroupingTime() {
		//Update the last grouping time in the variables table
		try {
			Long finishTime = new Date().getTime() / 1000;
			systemVariables.setVariable("last_grouping_time", finishTime);
		} catch (Exception e) {
			logger.error("Error setting last grouping time", e);
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
				if (logger.isInfoEnabled()) {
					logger.info("Removed " + numWorksNotLinkedToPrimaryIdentifier + " grouped works that were not linked to primary identifiers");
				}
			}
			pikaConn.commit();
			pikaConn.setAutoCommit(autoCommit);
		} catch (Exception e) {
			logger.error("Unable to remove grouped works that no longer have a primary identifier, grouped table id : " + groupedWorkId, e);
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
							logger.warn(fullIdentifierLowerCase + " was already loaded in marcRecordIdsInDatabase");
						} else {
							marcRecordIdsInDatabase.put(fullIdentifierLowerCase, ilsMarcChecksumRS.getLong("id"));
						}
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error loading marc checksums for marc records : " + indexingProfileToRun, e);
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

	private static void clearDatabase(Connection pikaConn, boolean clearDatabasePriorToGrouping) {
		if (clearDatabasePriorToGrouping) {
			try {
				addNoteToGroupingLog("Clearing out grouping related tables.");
				pikaConn.prepareStatement("TRUNCATE ils_marc_checksums").executeUpdate();
				pikaConn.prepareStatement("TRUNCATE grouped_work").executeUpdate();
				pikaConn.prepareStatement("TRUNCATE grouped_work_primary_identifiers").executeUpdate();
			} catch (Exception e) {
				System.out.println("Error clearing database " + e.toString());
				System.exit(1);
			}
		}
	}

	private static void groupIlsRecords(Connection pikaConn, ArrayList<IndexingProfile> indexingProfiles, boolean explodeMarcsOnly) {
		//Get indexing profiles
		for (IndexingProfile curProfile : indexingProfiles) {
			addNoteToGroupingLog("Processing profile " + curProfile.sourceName);

			String marcPath = curProfile.marcPath;

			//Check to see if we should process the profile
			boolean         processProfile = curProfile.groupUnchangedFiles;
			ArrayList<File> filesToProcess = new ArrayList<>();
			//Check to see if we have any new files, if so we will process all of them to be sure deletes and overlays process properly
			Pattern filesToMatchPattern = Pattern.compile(curProfile.filenamesToInclude, Pattern.CASE_INSENSITIVE);
			File[]  catalogBibFiles     = new File(marcPath).listFiles();
			if (catalogBibFiles != null) {
				for (File curBibFile : catalogBibFiles) {
					if (filesToMatchPattern.matcher(curBibFile.getName()).matches()) {
						filesToProcess.add(curBibFile);
						if (!processProfile && curBibFile.lastModified() > lastGroupingTime * 1000) {
							//If the file has changed since the last grouping time we should process it again
							// (Normally we want to skip grouping if the records haven't changed, since the last time we have grouped them)
							processProfile = true;
						}
					}
				}
			}
			if (!processProfile) {
				if (logger.isDebugEnabled()){
					logger.debug("Checking if " + curProfile.sourceName + " has had any records marked for regrouping.");
				}
				if (!curProfile.usingSierraAPIExtract && checkForForcedRegrouping(pikaConn, curProfile.sourceName)) {
					processProfile = true;
					addNoteToGroupingLog(curProfile.sourceName + " has no file changes but will be processed because records have been marked for forced regrouping.");
				}
			}

			if (!processProfile) {
				addNoteToGroupingLog("Skipping processing profile " + curProfile.sourceName + " because nothing has changed");
			} else {
				loadIlsChecksums(pikaConn, curProfile.sourceName);
				loadExistingPrimaryIdentifiers(pikaConn, curProfile.sourceName);

				MarcRecordGrouper recordGroupingProcessor;
				switch (curProfile.groupingClass) {
					case "MarcRecordGrouper":
						recordGroupingProcessor = new MarcRecordGrouper(pikaConn, curProfile, logger, fullRegroupingClearGroupingTables);
						break;
					case "SideLoadedRecordGrouper":
						recordGroupingProcessor = new SideLoadedRecordGrouper(pikaConn, curProfile, logger, fullRegroupingClearGroupingTables);
						break;
					case "HooplaRecordGrouper":
						recordGroupingProcessor = new HooplaRecordGrouper(pikaConn, curProfile, logger, fullRegroupingClearGroupingTables);
						break;
					default:
						logger.error("Unknown class for record grouping " + curProfile.groupingClass);
						continue;
				}

				String          marcEncoding                     = curProfile.marcEncoding;
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
										logger.warn("Bib did not have control number or identifier, the " + numRecordsRead + "-th record of the file " + curBibFile.getAbsolutePath());
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

									boolean marcUpToDate = writeIndividualMarc(curProfile, curBib, recordId, marcRecordsWritten, marcRecordsOverwritten);
									recordNumbersInExport.add(recordIdentifier.toString());
									if (!explodeMarcsOnly) {
										if (true || /*TODO: temp only*/ !marcUpToDate || fullRegroupingNoClear) {
//										if ( !marcUpToDate || fullRegroupingNoClear) {
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
									logger.warn("Error processing individual record for " + recordId + " on the " + numRecordsRead + "-th record of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed + " trying to continue", e);
								} else {
									logger.warn("Error processing individual record on the " + numRecordsRead + "-th record of " + curBibFile.getAbsolutePath() + "  The last record processed was " + lastRecordProcessed + ", trying to continue", e);
								}
							}
							numRecordsRead++;
							if (numRecordsRead % 100000 == 0) {
								recordGroupingProcessor.dumpStats();
							}
							//TODO: temp?
//							if (numRecordsRead % 5000 == 0) {
//								updateLastUpdateTimeInLog();
//								//Let the hard drives rest a bit so other things can happen.
//								Thread.sleep(100);
//							}
						}
					} catch (Exception e) {
						if (!recordId.isEmpty()) {
							logger.error("Error loading  bibs on record " + numRecordsRead + " in profile " + curProfile.sourceName + " on the record  " + recordId, e);
						} else {
							logger.error("Error loading  bibs on record " + numRecordsRead + " in profile " + curProfile.sourceName + " the last record processed was " + lastRecordProcessed, e);
						}
					}
					addNoteToGroupingLog("&nbsp;&nbsp; - Finished grouping " + numRecordsRead + " records with " + numRecordsProcessed + " actual changes from the marc file " + curBibFile.getName() + " in profile " + curProfile.sourceName);
				}

				addNoteToGroupingLog("&nbsp;&nbsp; - Records Processed: " + recordNumbersInExport.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Suppressed: " + suppressedRecordNumbersInExport.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Written: " + marcRecordsWritten.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Overwritten: " + marcRecordsOverwritten.size());

				removeDeletedRecords(curProfile.sourceName, marcPath);

				String profileName = curProfile.sourceName.replaceAll(" ", "_");
				writeExistingRecordsFile(recordNumbersInExport, "record_grouping_" + profileName + "_bibs_in_export", marcPath);
				if (suppressedRecordNumbersInExport.size() > 0) {
					writeExistingRecordsFile(suppressedRecordNumbersInExport, "record_grouping_" + profileName + "_bibs_to_ignore", marcPath);
				}
				if (suppressedControlNumbersInExport.size() > 0) {
					writeExistingRecordsFile(suppressedControlNumbersInExport, "record_grouping_" + profileName + "_control_numbers_to_ignore", marcPath);
				}
				if (recordNumbersToIndex.size() > 0) {
					writeExistingRecordsFile(recordNumbersToIndex, "record_grouping_" + profileName + "_bibs_to_index", marcPath);
				}
				if (marcRecordsWritten.size() > 0) {
					writeExistingRecordsFile(marcRecordsWritten, "record_grouping_" + profileName + "_new_bibs_written", marcPath);
				}
				if (marcRecordsOverwritten.size() > 0) {
					writeExistingRecordsFile(marcRecordsOverwritten, "record_grouping_" + profileName + "_changed_bibs_written", marcPath);
				}
			}

		}
	}

	private static void groupOverDriveRecords(Connection pikaConn, Connection econtentConnection, boolean explodeMarcsOnly) {
		if (explodeMarcsOnly) {
			//Nothing to do since we don't have marc records to process
			return;
		}
		OverDriveRecordGrouper recordGroupingProcessor = new OverDriveRecordGrouper(pikaConn, econtentConnection, logger, fullRegroupingClearGroupingTables);
		addNoteToGroupingLog("Starting to group overdrive records");
//		loadIlsChecksums(pikaConn, "overdrive"); // There are no checksums for overdrive metadata
		loadExistingPrimaryIdentifiers(pikaConn, "overdrive");

		int numRecordsProcessed = 0;
		try {
			String OverdriveRecordSQL = "SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName, code, publisher FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id INNER JOIN overdrive_api_product_languages_ref ON overdrive_api_product_languages_ref.productId = overdrive_api_products.id INNER JOIN overdrive_api_product_languages ON overdrive_api_product_languages_ref.languageId = overdrive_api_product_languages.id WHERE deleted = 0 AND isOwnedByCollections = 1";
			PreparedStatement overDriveRecordsStmt;
			if (lastGroupingTime != null && !fullRegroupingClearGroupingTables && !fullRegroupingNoClear) {
				overDriveRecordsStmt = econtentConnection.prepareStatement(OverdriveRecordSQL + " AND (dateUpdated >= ? OR lastMetadataChange >= ? OR lastAvailabilityChange >= ?)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
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
		} catch (Exception e) {
			logger.error("Error processing OverDrive data", e);
		}
	}

	private static SimpleDateFormat oo8DateFormat = new SimpleDateFormat("yyMMdd");
	private static SimpleDateFormat oo5DateFormat = new SimpleDateFormat("yyyyMMdd");

	private static boolean writeIndividualMarc(IndexingProfile indexingProfile, Record marcRecord, String recordNumber, TreeSet<String> marcRecordsWritten, TreeSet<String> marcRecordsOverwritten) {
		boolean marcRecordUpToDate = false;
		//Copy the record to the individual marc path
		if (recordNumber != null) {
			String recordNumberWithSource = indexingProfile.sourceName + ":" + recordNumber;
			Long   checksum               = getChecksum(marcRecord);
			Long   existingChecksum       = getExistingChecksum(recordNumberWithSource);
			File   individualFile         = indexingProfile.getFileForIlsRecord(recordNumber);

			//If we are doing partial regrouping or full regrouping without clearing the previous results,
			//Check to see if the record needs to be written before writing it.
			if (!fullRegroupingClearGroupingTables) {
				boolean checksumUpToDate = existingChecksum != null && existingChecksum.equals(checksum);
				boolean fileExists       = individualFile.exists();
				marcRecordUpToDate = fileExists && checksumUpToDate;
				if (!fileExists) {
					marcRecordsWritten.add(recordNumber);
				} else if (!checksumUpToDate) {
					marcRecordsOverwritten.add(recordNumber);
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
							marcRecordsOverwritten.add(recordNumber);
						}
					} catch (FileNotFoundException e) {
						if (logger.isDebugEnabled()) {
							logger.debug("Individual marc record not found " + recordNumberWithSource);
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
					getDateAddedForRecord(marcRecord, recordNumberWithSource, individualFile);
					updateMarcRecordChecksum(recordNumber, indexingProfile.sourceName, checksum);
					//logger.debug("checksum changed for " + recordNumber + " was " + existingChecksum + " now its " + checksum);
				} catch (IOException e) {
					logger.error("Error writing marc for record " + recordNumberWithSource, e);
				}
			} else {
				//Update date first detected if needed
				if (marcRecordFirstDetectionDates.containsKey(recordNumberWithSource) && marcRecordFirstDetectionDates.get(recordNumberWithSource) == null) {
					getDateAddedForRecord(marcRecord, recordNumberWithSource, individualFile);
					updateMarcRecordChecksum(recordNumber, indexingProfile.sourceName, checksum);
				}
			}
		} else {
			logger.error("Record number for MARC record was not supplied");
			marcRecordUpToDate = true;
		}
		return marcRecordUpToDate;
	}

	private static void getDateAddedForRecord(Record marcRecord, String recordNumberWithSource, File individualFile) {
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
				marcRecordFirstDetectionDates.put(recordNumberWithSource, timeAdded);
			} catch (Exception e) {
				if (logger.isDebugEnabled()) {
					logger.debug("Error loading creation time for " + filePath, e);
				}
			}
		}
	}

	private static Long getExistingChecksum(String recordNumber) {
		return marcRecordChecksums.get(recordNumber);
	}

	private static void updateMarcRecordChecksum(String recordNumber, String source, long checksum) {
		long   dateFirstDetected;
		String recordNumberWithSource = source + ":" + recordNumber;
		if (marcRecordFirstDetectionDates.containsKey(recordNumberWithSource) && marcRecordFirstDetectionDates.get(recordNumberWithSource) != null) {
			dateFirstDetected = marcRecordFirstDetectionDates.get(recordNumberWithSource);
		} else {
			dateFirstDetected = new Date().getTime() / 1000;
		}
		try {
			insertMarcRecordChecksum.setString(1, recordNumber);
			insertMarcRecordChecksum.setString(2, source);
			insertMarcRecordChecksum.setLong(3, checksum);
			insertMarcRecordChecksum.setLong(4, dateFirstDetected);
			insertMarcRecordChecksum.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update checksum for marc record : " + recordNumberWithSource, e);
		}
	}

	private static void outputMarcRecord(Record marcRecord, File individualFile) throws IOException {
		try (FileOutputStream outputStream = new FileOutputStream(individualFile, false)) {
			MarcStreamWriter writer2 = new MarcStreamWriter(outputStream, "UTF-8", true);
			writer2.write(marcRecord);
			writer2.close();
		}
	}

	private static Pattern specialCharPattern = Pattern.compile("\\p{C}");

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

	private static StringBuffer     notes      = new StringBuffer();
	private static SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

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
