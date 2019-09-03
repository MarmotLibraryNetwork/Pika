package org.pika;

import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.ini4j.Ini;
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
import java.util.stream.Collectors;
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

	static String groupedWorkTableName = "grouped_work";

	private static HashMap<String, Long> marcRecordChecksums           = new HashMap<>();
	private static HashMap<String, Long> marcRecordFirstDetectionDates = new HashMap<>();
	private static HashMap<String, Long> marcRecordIdsInDatabase       = new HashMap<>();
	private static HashMap<String, Long> primaryIdentifiersInDatabase  = new HashMap<>();
	private static PreparedStatement     insertMarcRecordChecksum;
	private static PreparedStatement     removeMarcRecordChecksum;
	private static PreparedStatement     removePrimaryIdentifier;

	private static Long    lastGroupingTime;
	private static Long    lastGroupingTimeVariableId;
	private static boolean fullRegrouping            = false; //todo: refactor name so that it is clear that this option clears out the database tables for grouped works
	private static boolean fullRegroupingNoClear     = false;
	private static boolean validateChecksumsFromDisk = false;

	//Reporting information
	private static long              groupingLogId;
	private static PreparedStatement addNoteToGroupingLogStmt;

	public static void main(String[] args) {
		// Get the configuration filename
		if (args.length == 0) {
			System.out.println("Welcome to the Record Grouping Application developed by Marmot Library Network.  \n" +
					"This application will group works by title, author, and format to create a \n" +
					"unique work id.  \n" +
					"\n" +
					"Additional information about the grouping process can be found at: \n" +
					"TBD\n" +
					"\n" +
					"This application can be used in several distinct ways based on the command line parameters\n" +
					"1) Generate a work id for an individual title/author/format\n" +
					"   record_grouping.jar generateWorkId <title> <author> <format> <subtitle (optional)>\n" +
					"   \n" +
					"   format should be one of: \n" +
					"   - book\n" +
					"   - music\n" +
					"   - movie\n" +
					"   \n" +
					"2) Generate work ids for a Pika site based on the exports for the site\n" +
					"   record_grouping.jar <pika_site_name>\n" +
					"   \n" +
					"3) benchmark the record generation and test the functionality\n" +
					"   record_grouping.jar benchmark\n" +
					"4) Generate author authorities based on data in the exports\n" +
					"   record_grouping.jar generateAuthorAuthorities <pika_site_name>\n" +
					"5) Only run record grouping cleanup\n" +
					"   record_grouping.jar <pika_site_name> runPostGroupingCleanup\n" +
					"6) Only explode records into individual records (no grouping)\n" +
					"   record_grouping.jar <pika_site_name> explodeMarcs\n" +
					"7) Record Group a specific indexing profile\n" +
					"   record_grouping.jar <pika_site_name> \"<profile name>\"");
			System.exit(1);
		}

		serverName = args[0];

		switch (serverName) {
			case "benchmark":
				boolean validateNYPL = false;
				if (args.length > 1) {
					if (args[1].equals("nypl")) {
						validateNYPL = true;
					}
				}
				doBenchmarking(validateNYPL);
				break;
			case "loadAuthoritiesFromVIAF":
				File log4jFile = new File("./log4j.grouping.properties");
				if (log4jFile.exists()) {
					PropertyConfigurator.configure(log4jFile.getAbsolutePath());
				} else {
					System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
					System.exit(1);
				}
				VIAF.loadAuthoritiesFromVIAF();
				break;
			case "generateWorkId":
				String title;
				String author;
				String format;
				String subtitle = null;
				if (args.length >= 4) {
					title  = args[1];
					author = args[2];
					format = args[3];
					if (args.length >= 5) {
						subtitle = args[4];
					}
				} else {
					title    = getInputFromCommandLine("Enter the title");
					subtitle = getInputFromCommandLine("Enter the subtitle");
					author   = getInputFromCommandLine("Enter the author");
					format   = getInputFromCommandLine("Enter the format");
				}
				GroupedWorkBase work = GroupedWorkFactory.getInstance(-1);
				work.setTitle(title, 0, subtitle);
				work.setAuthor(author);
				work.setGroupingCategory(format);
				JSONObject result = new JSONObject();
				try {
					result.put("normalizedAuthor", work.getAuthoritativeAuthor());
					result.put("normalizedTitle", work.getAuthoritativeTitle());
					result.put("workId", work.getPermanentId());
				} catch (Exception e) {
					logger.error("Error generating response", e);
				}
				System.out.print(result.toString());
				break;
			case "generateAuthorAuthorities":
				generateAuthorAuthorities(args);
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

		//  read the work from the command-line; need to use try/catch with the
		//  readLine() method
		String value = null;
		try {
			value = br.readLine().trim();
		} catch (IOException ioe) {
			System.out.println("IO error trying to read " + prompt);
			System.exit(1);
		}
		return value;
	}

	private static void generateAuthorAuthorities(String[] args) {
		serverName = args[1];
		long processStartTime = new Date().getTime();

		CSVWriter authoritiesWriter;
		try {
			authoritiesWriter = new CSVWriter(new FileWriter(new File("./author_authorities.properties.temp")));
		} catch (Exception e) {
			logger.error("Error creating temp file to store authorities");
			return;
		}

		//Load existing authorities
		HashMap<String, String> currentAuthorities = loadAuthorAuthorities(authoritiesWriter);
		HashMap<String, String> manualAuthorities  = loadManualAuthorities();

		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.grouping.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
			System.exit(1);
		}
		logger.info("Starting grouping of records " + new Date().toString());

		// Parse the configuration file
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

		RecordGroupingProcessor recordGroupingProcessor = new RecordGroupingProcessor(pikaConn, serverName, logger, true);
		generateAuthorAuthoritiesForIlsRecords(currentAuthorities, manualAuthorities, authoritiesWriter, recordGroupingProcessor);
		generateAuthorAuthoritiesForOverDriveRecords(econtentConnection, currentAuthorities, manualAuthorities, authoritiesWriter);

		try {
			authoritiesWriter.flush();
			authoritiesWriter.close();

			//TODO: Swap temp authority file with full authority file?

		} catch (Exception e) {
			logger.error("Error closing authorities writer");
		}
		try {
			pikaConn.close();
			econtentConnection.close();
		} catch (Exception e) {
			logger.error("Error closing database ", e);
			System.exit(1);
		}

		for (String curManualTitle : authoritiesWithSpecialHandling.keySet()) {
			logger.debug("Manually handle \"" + curManualTitle + "\",\"" + currentAuthorities.get(curManualTitle) + "\", (" + authoritiesWithSpecialHandling.get(curManualTitle) + ")");
		}

		logger.info("Finished generating author authorities " + new Date().toString());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - processStartTime;
		logger.info("Elapsed Minutes " + (elapsedTime / 60000));
	}

	private static HashMap<String, String> loadManualAuthorities() {
		HashMap<String, String> manualAuthorAuthorities = new HashMap<>();
		try {
			CSVReader csvReader = new CSVReader(new FileReader(new File("./manual_author_authorities.properties")));
			String[]  curLine   = csvReader.readNext();
			while (curLine != null) {
				if (curLine.length >= 2) {
					manualAuthorAuthorities.put(curLine[0], curLine[1]);
				}
				curLine = csvReader.readNext();
			}
		} catch (IOException e) {
			logger.error("Unable to load author authorities", e);
		}
		return manualAuthorAuthorities;

	}

	private static void generateAuthorAuthoritiesForOverDriveRecords(Connection econtentConnection, HashMap<String, String> currentAuthorities, HashMap<String, String> manualAuthorities, CSVWriter authoritiesWriter) {
		int numRecordsProcessed = 0;
		try {
			PreparedStatement overDriveRecordsStmt;
			overDriveRecordsStmt = econtentConnection.prepareStatement("SELECT id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName FROM overdrive_api_products WHERE deleted = 0", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement overDriveCreatorStmt = econtentConnection.prepareStatement("SELECT fileAs FROM overdrive_api_product_creators WHERE productId = ? AND role like ? ORDER BY id", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet         overDriveRecordRS    = overDriveRecordsStmt.executeQuery();
			while (overDriveRecordRS.next()) {
				Long id = overDriveRecordRS.getLong("id");

				String mediaType          = overDriveRecordRS.getString("mediaType");
				String title              = overDriveRecordRS.getString("title");
				String subtitle           = overDriveRecordRS.getString("subtitle");
				String primaryCreatorRole = overDriveRecordRS.getString("primaryCreatorRole");
				String author             = overDriveRecordRS.getString("primaryCreatorName");
				//primary creator in overdrive is always first name, last name.  Therefore, we need to look in the creators table
				if (author != null) {
					overDriveCreatorStmt.setLong(1, id);
					overDriveCreatorStmt.setString(2, primaryCreatorRole);
					ResultSet creatorInfoRS         = overDriveCreatorStmt.executeQuery();
					boolean   swapFirstNameLastName = false;
					if (creatorInfoRS.next()) {
						String tmpAuthor = creatorInfoRS.getString("fileAs");
						if (tmpAuthor.equals(author) && (mediaType.equals("ebook") || mediaType.equals("audiobook"))) {
							swapFirstNameLastName = true;
						} else {
							author = tmpAuthor;
						}
					} else {
						swapFirstNameLastName = true;
					}
					if (swapFirstNameLastName) {
						if (author.contains(" ")) {
							String[]      authorParts = author.split("\\s+");
							StringBuilder tmpAuthor   = new StringBuilder();
							for (int i = 1; i < authorParts.length; i++) {
								tmpAuthor.append(authorParts[i]).append(" ");
							}
							tmpAuthor.append(authorParts[0]);
							author = tmpAuthor.toString();
						}
					}
					creatorInfoRS.close();
				}

				if (author == null) continue;

				GroupedWorkBase work = GroupedWorkFactory.getInstance(-1);
				work.setTitle(title, 0, subtitle);
				work.setAuthor(author);
				if (mediaType.equalsIgnoreCase("audiobook")) {
					work.setGroupingCategory("book");
				} else if (mediaType.equalsIgnoreCase("ebook")) {
					work.setGroupingCategory("book");
				} else if (mediaType.equalsIgnoreCase("music")) {
					work.setGroupingCategory("music");
				} else if (mediaType.equalsIgnoreCase("video")) {
					work.setGroupingCategory("movie");
				}
				addAlternateAuthoritiesForWorkToAuthoritiesFile(currentAuthorities, manualAuthorities, authoritiesWriter, work);
				numRecordsProcessed++;
			}
			overDriveRecordRS.close();

			logger.info("Finished loading authorities, read " + numRecordsProcessed + " records from overdrive ");
		} catch (Exception e) {
			System.out.println("Error loading OverDrive records: " + e.toString());
			e.printStackTrace();
		}
	}

	private static void generateAuthorAuthoritiesForIlsRecords(HashMap<String, String> currentAuthorities, HashMap<String, String> manualAuthorities, CSVWriter authoritiesWriter, RecordGroupingProcessor recordGroupingProcessor) {
		logger.debug("Generating authorities for ILS Records");
		int numRecordsRead = 0;
		//TODO: these config.ini settings are obsolete and should be refactored if this code gets used
		String marcPath       = PikaConfigIni.getIniValue("Reindex", "marcPath");
		String marcEncoding   = PikaConfigIni.getIniValue("Reindex", "marcEncoding");
		String loadFormatFrom = PikaConfigIni.getIniValue("Reindex", "loadFormatFrom");
		char   formatSubfield = ' ';
		if (loadFormatFrom.equals("item")) {
			formatSubfield = PikaConfigIni.getCharIniValue("Reindex", "formatSubfield");
		}
		//TODO: Use Format determination in indexing profile?

		File[] catalogBibFiles = new File(marcPath).listFiles();
		if (catalogBibFiles != null) {
			for (File curBibFile : catalogBibFiles) {
				if (curBibFile.getName().toLowerCase().endsWith(".mrc") || curBibFile.getName().toLowerCase().endsWith(".marc")) {
					try {
						FileInputStream marcFileStream = new FileInputStream(curBibFile);
						MarcReader      catalogReader  = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
						while (catalogReader.hasNext()) {
							Record          curBib = catalogReader.next();
							GroupedWorkBase work   = recordGroupingProcessor.setupBasicWorkForIlsRecord(curBib, loadFormatFrom, formatSubfield, "");
							addAlternateAuthoritiesForWorkToAuthoritiesFile(currentAuthorities, manualAuthorities, authoritiesWriter, work);
							numRecordsRead++;
						}
						marcFileStream.close();
					} catch (Exception e) {
						logger.error("Error loading catalog bibs on record " + numRecordsRead, e);
					}
					logger.info("Finished grouping " + numRecordsRead + " records from the ils file " + curBibFile.getName());
				}
			}
		}
	}

	//private static HashMap<String, String> altNameToOriginalName = new HashMap<>();
	private static TreeMap<String, String> authoritiesWithSpecialHandling = new TreeMap<>();

	private static void addAlternateAuthoritiesForWorkToAuthoritiesFile(HashMap<String, String> currentAuthorities, HashMap<String, String> manualAuthorities, CSVWriter authoritiesWriter, GroupedWorkBase work) {
		String normalizedAuthor = work.getAuthor();
		if (normalizedAuthor.length() > 0) {
			HashSet<String> alternateAuthorNames = work.getAlternateAuthorNames();
			if (alternateAuthorNames.size() > 0) {
				for (String curAltName : alternateAuthorNames) {
					if (curAltName != null && curAltName.length() > 0) {
						//Make sure the authority doesn't link to multiple normalized names
						if (!currentAuthorities.containsKey(curAltName)) {
							//altNameToOriginalName.put(curAltName, work.getOriginalAuthor());
							currentAuthorities.put(curAltName, normalizedAuthor);
							authoritiesWriter.writeNext(new String[]{curAltName, normalizedAuthor});
						} else {
							if (!currentAuthorities.get(curAltName).equals(normalizedAuthor)) {
								if (!manualAuthorities.containsKey(curAltName)) {
									//Add to the list of authorities that need special handling.  So we can add them manually.
									authoritiesWithSpecialHandling.put(curAltName, work.getOriginalAuthor());
									//logger.warn("Look out, alternate name (" + curAltName + ") links to multiple normalized authors '" + currentAuthorities.get(curAltName) + "' and '" + normalizedAuthor + "' original names \n'" + altNameToOriginalName.get(curAltName) + "' and '" + work.getOriginalAuthor() + "'");
								}
							}
						}
					}
				}
			}
		}/*else if (work.getOriginalAuthor().length() > 0 && !work.getOriginalAuthor().equals(".") && ! work.getOriginalAuthor().matches("^[\\d./-]+$")){
			logger.warn("Got a 0 length normalized author, review it. Original Author " + work.getOriginalAuthor());
		}*/

	}

	private static HashMap<String, String> loadAuthorAuthorities(CSVWriter authoritiesWriter) {
		HashMap<String, String> authorAuthorities = new HashMap<>();
		try {
			CSVReader csvReader = new CSVReader(new FileReader(new File("./author_authorities.properties")));
			String[]  curLine   = csvReader.readNext();
			while (curLine != null) {
				if (curLine.length >= 2) {
					authorAuthorities.put(curLine[0], curLine[1]);
					if (authoritiesWriter != null) {
						//Copy to the temp file
						authoritiesWriter.writeNext(curLine);
					}
				}
				curLine = csvReader.readNext();
			}
		} catch (IOException e) {
			logger.error("Unable to load author authorities", e);
		}
		return authorAuthorities;
	}

	private static void doBenchmarking(boolean validateNYPL) {
		long processStartTime = new Date().getTime();
		File log4jFile        = new File("./log4j.grouping.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
			System.exit(1);
		}
		logger.info("Starting record grouping benchmark " + new Date().toString());

		try {
			//Load the input file to test
			File      benchmarkFile        = new File("./benchmark_input.csv");
			CSVReader benchmarkInputReader = new CSVReader(new FileReader(benchmarkFile));

			//Create a file to store the results within
			SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd_HH-mm-ss");
			File             resultsFile;
			if (validateNYPL) {
				resultsFile = new File("./benchmark_results/" + dateFormatter.format(new Date()) + "_nypl.csv");
			} else {
				resultsFile = new File("./benchmark_results/" + dateFormatter.format(new Date()) + "_marmot.csv");
			}
			CSVWriter resultsWriter = new CSVWriter(new FileWriter(resultsFile));
			resultsWriter.writeNext(new String[]{"Original Title", "Original Author", "Format", "Normalized Title", "Normalized Author", "Permanent Id", "Validation Results"});

			//Load the desired results
			File validationFile;
			if (validateNYPL) {
				validationFile = new File("./benchmark_output_nypl.csv");
			} else {
				validationFile = new File("./benchmark_validation_file.csv");
			}
			CSVReader validationReader = new CSVReader(new FileReader(validationFile));

			//Read the header from input
			String[] csvData;
			benchmarkInputReader.readNext();

			int numErrors   = 0;
			int numTestsRun = 0;
			//Read validation file
			String[] validationData;
			validationReader.readNext();
			while ((csvData = benchmarkInputReader.readNext()) != null) {
				if (csvData.length >= 3) {
					numTestsRun++;
					String originalTitle  = csvData[0];
					String originalAuthor = csvData[1];
					String groupingFormat = csvData[2];

					//Get normalized the information and get the permanent id
					GroupedWorkBase work = GroupedWorkFactory.getInstance(4);
					work.setTitle(originalTitle, 0, "");
					work.setAuthor(originalAuthor);
					work.setGroupingCategory(groupingFormat);

					//Read from validation file
					validationData = validationReader.readNext();
					//Check to make sure the results we got are correct
					String validationResults = "";
					if (validationData != null && validationData.length >= 6) {
						String expectedTitle;
						String expectedAuthor;
						String expectedWorkId;
						if (validateNYPL) {
							expectedTitle  = validationData[2];
							expectedAuthor = validationData[3];
							expectedWorkId = validationData[5];
						} else {
							expectedTitle  = validationData[3];
							expectedAuthor = validationData[4];
							expectedWorkId = validationData[5];
						}

						if (!expectedTitle.equals(work.getAuthoritativeTitle())) {
							validationResults += "Normalized title incorrect expected " + expectedTitle + "; ";
						}
						if (!expectedAuthor.equals(work.getAuthoritativeAuthor())) {
							validationResults += "Normalized author incorrect expected " + expectedAuthor + "; ";
						}
						if (!expectedWorkId.equals(work.getPermanentId())) {
							validationResults += "Grouped Work Id incorrect expected " + expectedWorkId + "; ";
						}
						if (validationResults.length() != 0) {
							numErrors++;
						}
					} else {
						validationResults += "Did not find validation information ";
					}

					//Save results
					String[] results;
					if (validationResults.length() == 0) {
						results = new String[]{originalTitle, originalAuthor, groupingFormat, work.getAuthoritativeTitle(), work.getAuthoritativeAuthor(), work.getPermanentId()};
					} else {
						results = new String[]{originalTitle, originalAuthor, groupingFormat, work.getAuthoritativeTitle(), work.getAuthoritativeAuthor(), work.getPermanentId(), validationResults};
					}
					resultsWriter.writeNext(results);
					/*if (numTestsRun >= 100){
						break;
					}*/
				}
			}
			resultsWriter.flush();
			logger.debug("Ran " + numTestsRun + " tests.");
			logger.debug("Found " + numErrors + " errors.");
			benchmarkInputReader.close();
			validationReader.close();

			long endTime     = new Date().getTime();
			long elapsedTime = endTime - processStartTime;
			logger.info("Total Run Time " + (elapsedTime / 1000) + " seconds, " + (elapsedTime / 60000) + " minutes.");
			logger.info("Processed " + Double.toString((double) numTestsRun / (double) (elapsedTime / 1000)) + " records per second.");

			//Write results to the test file for comparison
			resultsWriter.writeNext(new String[0]);
			resultsWriter.writeNext(new String[]{"Tests Run", Integer.toString(numTestsRun)});
			resultsWriter.writeNext(new String[]{"Errors", Integer.toString(numErrors)});
			resultsWriter.writeNext(new String[]{"Total Run Time (seconds)", Long.toString((elapsedTime / 1000))});
			resultsWriter.writeNext(new String[]{"Records Per Second", Double.toString((double) numTestsRun / (double) (elapsedTime / 1000))});


			resultsWriter.flush();
			resultsWriter.close();
		} catch (Exception e) {
			logger.error("Error running benchmark", e);
		}
	}

	private static void doStandardRecordGrouping(String[] args) {
		long processStartTime = new Date().getTime();

		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.grouping.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
			System.exit(1);
		}
		logger.info("Starting grouping of records " + new Date().toString());

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
			logger.info("Creating log entry for index");
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
		lastGroupingTimeVariableId = systemVariables.getVariableId("last_grouping_time");

		//Check to see if we need to clear the database
		boolean clearDatabasePriorToGrouping = false;
		boolean onlyDoCleanup                = false;
		boolean explodeMarcsOnly             = false;
		String  indexingProfileToRun         = null;
		if (args.length >= 2 && args[1].equalsIgnoreCase("explodeMarcs")) {
			explodeMarcsOnly             = true;
			clearDatabasePriorToGrouping = false;
		} else if (args.length >= 2 && args[1].equalsIgnoreCase("fullRegroupingNoClear")) {
			fullRegroupingNoClear = true;
		} else if (args.length >= 2 && args[1].equalsIgnoreCase("fullRegrouping")) {
			clearDatabasePriorToGrouping = true;
			fullRegrouping               = true;
		} else if (args.length >= 2 && args[1].equalsIgnoreCase("runPostGroupingCleanup")) {
			clearDatabasePriorToGrouping = false;
			fullRegrouping               = false;
			onlyDoCleanup                = true;
		} else if (args.length >= 2) {
			//The last argument is the indexing profile to run
			indexingProfileToRun = args[1];
			fullRegrouping       = false;
		} else {
			fullRegrouping = false;
		}

		RecordGroupingProcessor recordGroupingProcessor = null;
		if (!onlyDoCleanup) {
			recordGroupingProcessor = new RecordGroupingProcessor(pikaConn, serverName, logger, fullRegrouping);

			if (!explodeMarcsOnly) {
				markRecordGroupingRunning(true);

				clearDatabase(pikaConn, clearDatabasePriorToGrouping);
			}

			//Determine if we want to validateChecksumsFromDisk
			validateChecksumsFromDisk = systemVariables.getBooleanValuedVariable("validateChecksumsFromDisk");

			ArrayList<IndexingProfile> indexingProfiles = new ArrayList<>();

			String fetchIndexingProfileSQL = "SELECT * FROM indexing_profiles";
			if (indexingProfileToRun != null && !indexingProfileToRun.isEmpty()) {
				fetchIndexingProfileSQL += " where name like '" + indexingProfileToRun + "'";
			}
			try (
					PreparedStatement getIndexingProfilesStmt = pikaConn.prepareStatement(fetchIndexingProfileSQL);
					ResultSet indexingProfilesRS = getIndexingProfilesStmt.executeQuery()
			) {
				while (indexingProfilesRS.next()) {
					IndexingProfile profile = new IndexingProfile();
					profile.id                                = indexingProfilesRS.getLong(1);
					profile.name                              = indexingProfilesRS.getString("name");
					profile.marcPath                          = indexingProfilesRS.getString("marcPath");
					profile.filenamesToInclude                = indexingProfilesRS.getString("filenamesToInclude");
					profile.individualMarcPath                = indexingProfilesRS.getString("individualMarcPath");
					profile.numCharsToCreateFolderFrom        = indexingProfilesRS.getInt("numCharsToCreateFolderFrom");
					profile.createFolderFromLeadingCharacters = indexingProfilesRS.getBoolean("createFolderFromLeadingCharacters");
					profile.groupingClass                     = indexingProfilesRS.getString("groupingClass");
					profile.recordNumberTag                   = indexingProfilesRS.getString("recordNumberTag");
					profile.recordNumberField                 = getCharFromRecordSet(indexingProfilesRS, "recordNumberField");
					profile.recordNumberPrefix                = indexingProfilesRS.getString("recordNumberPrefix");
					profile.marcEncoding                      = indexingProfilesRS.getString("marcEncoding");
					profile.formatSource                      = indexingProfilesRS.getString("formatSource");
					profile.specifiedFormatCategory           = indexingProfilesRS.getString("specifiedFormatCategory");
					profile.format                            = getCharFromRecordSet(indexingProfilesRS, "format");
					profile.itemTag                           = indexingProfilesRS.getString("itemTag");
					profile.eContentDescriptor                = getCharFromRecordSet(indexingProfilesRS, "eContentDescriptor");
					profile.doAutomaticEcontentSuppression    = indexingProfilesRS.getBoolean("doAutomaticEcontentSuppression");
					profile.groupUnchangedFiles               = indexingProfilesRS.getBoolean("groupUnchangedFiles");

					// Does this profile use the Sierra API Extract
					try (
							PreparedStatement getSierraFieldMappingsStmt = pikaConn.prepareStatement("SELECT * FROM sierra_export_field_mapping where indexingProfileId =" + profile.id);
							ResultSet getSierraFieldMappingsRS = getSierraFieldMappingsStmt.executeQuery()
					){
						// If there is a sierra field mapping entry for the profile, then it does use the Sierra API Extract
						if (getSierraFieldMappingsRS.next()){
							profile.usingSierraAPIExtract = true;
						}
					} catch (Exception e){
						logger.warn("Error determining whether or not an indexing profile uses the Siera API Extract");
						// logger the error but otherwise continue as normal
					}

					indexingProfiles.add(profile);
				}

			} catch (Exception e) {
				logger.error("Error loading indexing profiles", e);
				System.exit(1);
			}

			// Main Record Grouping Processing
			if (indexingProfileToRun == null || indexingProfileToRun.equalsIgnoreCase("overdrive")) {
				groupOverDriveRecords(pikaConn, econtentConnection, recordGroupingProcessor, explodeMarcsOnly);
			}
			if (indexingProfiles.size() > 0) {
				groupIlsRecords(pikaConn, indexingProfiles, explodeMarcsOnly);
			}

		}

		if (!explodeMarcsOnly) {
			try {
				logger.info("Doing post processing of record grouping");
				pikaConn.setAutoCommit(false);

				//Cleanup the data
				removeGroupedWorksWithoutPrimaryIdentifiers(pikaConn);
				pikaConn.commit();
				//removeUnlinkedIdentifiers(pikaConn);
				//pikaConn.commit();
				//makeIdentifiersLinkingToMultipleWorksInvalidForEnrichment(pikaConn);
				//pikaConn.commit();
				updateLastGroupingTime();
				pikaConn.commit();

				pikaConn.setAutoCommit(true);
				logger.info("Finished doing post processing of record grouping");
			} catch (SQLException e) {
				logger.error("Error in grouped work post processing", e);
			}

			markRecordGroupingRunning(false);
		}

		if (recordGroupingProcessor != null) {
			recordGroupingProcessor.dumpStats();
		}

		logger.info("Finished grouping records " + new Date().toString());
		long endTime     = new Date().getTime();
		long elapsedTime = endTime - processStartTime;
		logger.info("Elapsed Minutes " + (elapsedTime / 60000));

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

	private static void removeDeletedRecords(String curProfile) {
		if (marcRecordIdsInDatabase.size() > 0) {
			logger.info("Deleting " + marcRecordIdsInDatabase.size() + " record ids for profile " + curProfile + " from the database since they are no longer in the export.");
			addNoteToGroupingLog("Deleting " + marcRecordIdsInDatabase.size() + " record ids for profile " + curProfile + " from the database since they are no longer in the export.");
			for (String recordNumber : marcRecordIdsInDatabase.keySet()) {
				//Remove the record from the ils_marc_checksums table
				try {
					removeMarcRecordChecksum.setLong(1, marcRecordIdsInDatabase.get(recordNumber));
					int numRemoved = removeMarcRecordChecksum.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete " + recordNumber + " from ils_marc_checksums table");
					} else if (curProfile.equals("ils")) {
						//TODO: this is temporary. this is to diagnose Sierra API Extract issues
						logger.debug("Deleted ils record " + recordNumber + " from the ils checksum table.");

					}
				} catch (SQLException e) {
					logger.error("Error removing ILS id " + recordNumber + " from ils_marc_checksums table", e);
				}
			}
			marcRecordIdsInDatabase.clear();
		}

		if (primaryIdentifiersInDatabase.size() > 0) {
			logger.info("Deleting " + primaryIdentifiersInDatabase.size() + " primary identifiers for profile " + curProfile + " from the database since they are no longer in the export.");
			for (String recordNumber : primaryIdentifiersInDatabase.keySet()) {
				//Remove the record from the grouped_work_primary_identifiers table
				try {
					removePrimaryIdentifier.setLong(1, primaryIdentifiersInDatabase.get(recordNumber));
					int numRemoved = removePrimaryIdentifier.executeUpdate();
					if (numRemoved != 1) {
						logger.warn("Could not delete " + recordNumber + " from grouped_work_primary_identifiers table");
					} else if (curProfile.equals("ils")) {
						//TODO: this is temporary. this is to diagnose Sierra API Extract issues
						logger.debug("Deleting grouped work primary identifier entry for record " + recordNumber);
					}
				} catch (SQLException e) {
					logger.error("Error removing " + recordNumber + " from grouped_work_primary_identifiers table", e);
				}
			}
			TreeSet<String> theList = new TreeSet<String>(primaryIdentifiersInDatabase.keySet());
			writeExistingRecordsFile(theList, "remaining_primary_identifiers_to_be_deleted");
			primaryIdentifiersInDatabase.clear();
		}
	}

	private static void markRecordGroupingRunning(boolean isRunning) {
		systemVariables.setVariable("record_grouping_running", isRunning);
	}

	private static char getCharFromRecordSet(ResultSet indexingProfilesRS, String fieldName) throws SQLException {
		char   result        = ' ';
		String databaseValue = indexingProfilesRS.getString(fieldName);
		if (!indexingProfilesRS.wasNull() && databaseValue.length() > 0) {
			result = databaseValue.charAt(0);
		}
		return result;
	}

	private static SimpleDateFormat dayFormatter = new SimpleDateFormat("yyyy-MM-dd");

	private static void writeExistingRecordsFile(TreeSet<String> recordNumbersInExport, String filePrefix) {
		try {
			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
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

	/*private static void makeIdentifiersLinkingToMultipleWorksInvalidForEnrichment(Connection vufindConn) {
		//Mark any secondaryIdentifiers that link to more than one grouped record and therefore should not be used for enrichment
		try{
			boolean autoCommit = vufindConn.getAutoCommit();
			//First mark that all are ok to use
			PreparedStatement markAllIdentifiersAsValidStmt = vufindConn.prepareStatement("UPDATE grouped_work_identifiers SET valid_for_enrichment = 1");
			markAllIdentifiersAsValidStmt.executeUpdate();

			//Get a list of any secondaryIdentifiers that are used to load enrichment (isbn, issn, upc) that are attached to more than one grouped work
			vufindConn.setAutoCommit(false);
			PreparedStatement invalidIdentifiersStmt = vufindConn.prepareStatement(
					"SELECT grouped_work_identifiers.id as secondary_identifier_id, type, identifier, COUNT(grouped_work_id) as num_related_works\n" +
							"FROM grouped_work_identifiers\n" +
							"INNER JOIN grouped_work_identifiers_ref ON grouped_work_identifiers.id = identifier_id\n" +
							"WHERE type IN ('isbn', 'issn', 'upc')\n" +
							"GROUP BY grouped_work_identifiers.id\n" +
							"HAVING num_related_works > 1", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

			ResultSet invalidIdentifiersRS = invalidIdentifiersStmt.executeQuery();
			PreparedStatement getRelatedWorksForIdentifierStmt = vufindConn.prepareStatement("SELECT full_title, author, grouping_category\n" +
					"FROM grouped_work_identifiers_ref\n" +
					"INNER JOIN grouped_work ON grouped_work_id = grouped_work.id\n" +
					"WHERE identifier_id = ?");
			PreparedStatement updateInvalidIdentifierStmt = vufindConn.prepareStatement("UPDATE grouped_work_identifiers SET valid_for_enrichment = 0 where id = ?");
			int numIdentifiersUpdated = 0;
			while (invalidIdentifiersRS.next()){
				String type = invalidIdentifiersRS.getString("type");
				String identifier = invalidIdentifiersRS.getString("identifier");
				Long secondaryIdentifierId = invalidIdentifiersRS.getLong("secondary_identifier_id");
				//Get the related works for the identifier
				getRelatedWorksForIdentifierStmt.setLong(1, secondaryIdentifierId);
				ResultSet relatedWorksForIdentifier = getRelatedWorksForIdentifierStmt.executeQuery();
				StringBuilder titles = new StringBuilder();
				ArrayList<String> titlesBroken = new ArrayList<>();
				StringBuilder authors = new StringBuilder();
				ArrayList<String> authorsBroken = new ArrayList<>();
				StringBuilder categories = new StringBuilder();
				ArrayList<String> categoriesBroken = new ArrayList<>();

				while (relatedWorksForIdentifier.next()){
					titlesBroken.add(relatedWorksForIdentifier.getString("full_title"));
					if (titles.length() > 0){
						titles.append(", ");
					}
					titles.append(relatedWorksForIdentifier.getString("full_title"));

					authorsBroken.add(relatedWorksForIdentifier.getString("author"));
					if (authors.length() > 0){
						authors.append(", ");
					}
					authors.append(relatedWorksForIdentifier.getString("author"));

					categoriesBroken.add(relatedWorksForIdentifier.getString("grouping_category"));
					if (categories.length() > 0){
						categories.append(", ");
					}
					categories.append(relatedWorksForIdentifier.getString("grouping_category"));
				}

				boolean allTitlesSimilar = true;
				if (titlesBroken.size() >= 2){
					String firstTitle = titlesBroken.get(0);

					for (int i = 1; i < titlesBroken.size(); i++){
						String curTitle = titlesBroken.get(i);
						if (!curTitle.equals(firstTitle)){
							if (curTitle.startsWith(firstTitle) || firstTitle.startsWith(curTitle)){
								logger.info(type + " " + identifier + " did not match on titles '" + titles + "', but the titles are similar");
							}else{
								allTitlesSimilar = false;
							}
						}
					}
				}

				boolean allAuthorsSimilar = true;
				if (authorsBroken.size() >= 2){
					String firstAuthor = authorsBroken.get(0);
					for (int i = 1; i < authorsBroken.size(); i++){
						String curAuthor = authorsBroken.get(i);
						if (!curAuthor.equals(firstAuthor)){
							if (curAuthor.startsWith(firstAuthor) || firstAuthor.startsWith(curAuthor)){
								logger.info(type + " " + identifier + " did not match on authors '" + authors + "', but the authors are similar");
							}else{
								allAuthorsSimilar = false;
							}
						}
					}
				}

				boolean allCategoriesSimilar = true;
				if (categoriesBroken.size() >= 2){
					String firstCategory = categoriesBroken.get(0);
					for (int i = 1; i < categoriesBroken.size(); i++){
						String curCategory = categoriesBroken.get(i);
						if (!curCategory.equals(firstCategory)){
							allCategoriesSimilar = false;
						}
					}
				}

				if (!(allTitlesSimilar && allAuthorsSimilar && allCategoriesSimilar)) {
					updateInvalidIdentifierStmt.setLong(1, invalidIdentifiersRS.getLong("secondary_identifier_id"));
					updateInvalidIdentifierStmt.executeUpdate();
					numIdentifiersUpdated++;
				}else{
					logger.info("Leaving secondary identifier as valid because the titles are similar enough");
				}
			}
			logger.info("Marked " + numIdentifiersUpdated + " secondaryIdentifiers as invalid for enrichment because they link to multiple grouped records");
			invalidIdentifiersRS.close();
			invalidIdentifiersStmt.close();
			vufindConn.commit();
			vufindConn.setAutoCommit(autoCommit);
		}catch (Exception e){
			logger.error("Unable to mark secondary identifiers as invalid for enrichment", e);
		}
	}*/

	/*private static void removeUnlinkedIdentifiers(Connection vufindConn) {
		//Remove any secondary identifiers that are no longer linked to a primary identifier
		try{
			boolean autoCommit = vufindConn.getAutoCommit();
			vufindConn.setAutoCommit(false);
			PreparedStatement unlinkedIdentifiersStmt = vufindConn.prepareStatement("SELECT id FROM grouped_work_identifiers where id NOT IN " +
					"(SELECT DISTINCT secondary_identifier_id from grouped_work_primary_to_secondary_id_ref)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet unlinkedIdentifiersRS = unlinkedIdentifiersStmt.executeQuery();
			PreparedStatement removeIdentifierStmt = vufindConn.prepareStatement("DELETE FROM grouped_work_identifiers where id = ?");
			int numUnlinkedIdentifiersRemoved = 0;
			while (unlinkedIdentifiersRS.next()){
				removeIdentifierStmt.setLong(1, unlinkedIdentifiersRS.getLong(1));
				removeIdentifierStmt.executeUpdate();
				numUnlinkedIdentifiersRemoved++;
				if (numUnlinkedIdentifiersRemoved % 500 == 0){
					vufindConn.commit();
				}
			}
			logger.info("Removed " + numUnlinkedIdentifiersRemoved + " identifiers that were not linked to primary identifiers");
			unlinkedIdentifiersRS.close();
			unlinkedIdentifiersStmt.close();
			vufindConn.commit();
			vufindConn.setAutoCommit(autoCommit);
		}catch(Exception e){
			logger.error("Error removing identifiers that are no longer linked to a primary identifier", e);
		}
	}*/

	private static void removeGroupedWorksWithoutPrimaryIdentifiers(Connection pikaConn) {
		//Remove any grouped works that no longer link to a primary identifier
		Long groupedWorkId = null;
		try {
			boolean autoCommit = pikaConn.getAutoCommit();
			pikaConn.setAutoCommit(false);
			try (
					PreparedStatement deleteWorkStmt = pikaConn.prepareStatement("DELETE from grouped_work WHERE id = ?");
					PreparedStatement deleteRelatedIdentifiersStmt = pikaConn.prepareStatement("DELETE from grouped_work_identifiers_ref WHERE grouped_work_id = ?");
					PreparedStatement groupedWorksWithoutIdentifiersStmt = pikaConn.prepareStatement("SELECT grouped_work.id from grouped_work where id NOT IN (SELECT DISTINCT grouped_work_id from grouped_work_primary_identifiers)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet groupedWorksWithoutIdentifiersRS = groupedWorksWithoutIdentifiersStmt.executeQuery()
			) {
				int numWorksNotLinkedToPrimaryIdentifier = 0;
				while (groupedWorksWithoutIdentifiersRS.next()) {
					groupedWorkId = groupedWorksWithoutIdentifiersRS.getLong(1);
					deleteWorkStmt.setLong(1, groupedWorkId);
					deleteWorkStmt.executeUpdate();

					deleteRelatedIdentifiersStmt.setLong(1, groupedWorkId);
					deleteRelatedIdentifiersStmt.executeUpdate();
					numWorksNotLinkedToPrimaryIdentifier++;
					if (numWorksNotLinkedToPrimaryIdentifier % 500 == 0) {
						pikaConn.commit();
					}
				}
				logger.info("Removed " + numWorksNotLinkedToPrimaryIdentifier + " grouped works that were not linked to primary identifiers");
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
				removeMarcRecordChecksum = pikaConn.prepareStatement("DELETE FROM ils_marc_checksums WHERE id = ?");
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

	private static void loadExistingPrimaryIdentifiers(Connection pikaConn, String indexingProfileToRun) {
		//Load Existing Primary Identifiers so we can clean up
		try {
			if (removePrimaryIdentifier == null) {
				removePrimaryIdentifier = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ?");
			}

			String primaryIdentifiersSQL = "SELECT * from grouped_work_primary_identifiers";
			if (indexingProfileToRun != null) {
				primaryIdentifiersSQL += " where type like ?";
			}
			try (PreparedStatement loadPrimaryIdentifiers = pikaConn.prepareStatement(primaryIdentifiersSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
				if (indexingProfileToRun != null) {
					loadPrimaryIdentifiers.setString(1, indexingProfileToRun);
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
				pikaConn.prepareStatement("TRUNCATE ils_marc_checksums").executeUpdate();
				pikaConn.prepareStatement("TRUNCATE " + groupedWorkTableName).executeUpdate();
				String groupedWorkIdentifiersTableName = "grouped_work_identifiers";
				pikaConn.prepareStatement("TRUNCATE " + groupedWorkIdentifiersTableName).executeUpdate();
				String groupedWorkIdentifiersRefTableName = "grouped_work_identifiers_ref";
				pikaConn.prepareStatement("TRUNCATE " + groupedWorkIdentifiersRefTableName).executeUpdate();
				String groupedWorkPrimaryIdentifiersTableName = "grouped_work_primary_identifiers";
				pikaConn.prepareStatement("TRUNCATE " + groupedWorkPrimaryIdentifiersTableName).executeUpdate();
				pikaConn.prepareStatement("TRUNCATE grouped_work_primary_to_secondary_id_ref").executeUpdate();
			} catch (Exception e) {
				System.out.println("Error clearing database " + e.toString());
				System.exit(1);
			}
		}
	}

	private static void groupIlsRecords(Connection pikaConn, ArrayList<IndexingProfile> indexingProfiles, boolean explodeMarcsOnly) {
		//Get indexing profiles
		for (IndexingProfile curProfile : indexingProfiles) {
			addNoteToGroupingLog("Processing profile " + curProfile.name);

			String marcPath = curProfile.marcPath;

			//Check to see if we should process the profile
			boolean         processProfile = false;
			ArrayList<File> filesToProcess = new ArrayList<>();
			//Check to see if we have any new files, if so we will process all of them to be sure deletes and overlays process properly
			Pattern filesToMatchPattern = Pattern.compile(curProfile.filenamesToInclude, Pattern.CASE_INSENSITIVE);
			File[]  catalogBibFiles     = new File(marcPath).listFiles();
			if (catalogBibFiles != null) {
				for (File curBibFile : catalogBibFiles) {
					if (filesToMatchPattern.matcher(curBibFile.getName()).matches()) {
						filesToProcess.add(curBibFile);
						//If the file has changed since the last grouping time we should process it again
						if (curBibFile.lastModified() > lastGroupingTime * 1000) {
							processProfile = true;
						}
					}
				}
			}
			if (curProfile.groupUnchangedFiles) {
				processProfile = true;
			}
			if (!processProfile) {
				logger.debug("Checking if " + curProfile.name + " has had any records marked for regrouping.");
				if (!curProfile.usingSierraAPIExtract && checkForForcedRegrouping(pikaConn, curProfile.name)) {
					processProfile = true;
					addNoteToGroupingLog(curProfile.name + " has no file changes but will be processed because records have been marked for forced regrouping.");
				}
			}

			if (!processProfile) {
				addNoteToGroupingLog("Skipping processing profile " + curProfile.name + " because nothing has changed");
			} else {
				loadIlsChecksums(pikaConn, curProfile.name);
				loadExistingPrimaryIdentifiers(pikaConn, curProfile.name);

				MarcRecordGrouper recordGroupingProcessor;
				switch (curProfile.groupingClass) {
					case "MarcRecordGrouper":
						recordGroupingProcessor = new MarcRecordGrouper(pikaConn, curProfile, logger, fullRegrouping);
						break;
					case "SideLoadedRecordGrouper":
						recordGroupingProcessor = new SideLoadedRecordGrouper(pikaConn, curProfile, logger, fullRegrouping);
						break;
					case "HooplaRecordGrouper":
						recordGroupingProcessor = new HooplaRecordGrouper(pikaConn, curProfile, logger, fullRegrouping);
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
								RecordIdentifier recordIdentifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(curBib, curProfile.name, curProfile.doAutomaticEcontentSuppression);
								if (recordIdentifier == null) {
									//logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
									String controlNumber = curBib.getControlNumber();
									if (controlNumber != null) {
										suppressedControlNumbersInExport.add(controlNumber);
									} else {
										logger.warn("Bib did not have control number or identifier, the " + numRecordsRead + "-th record of the file " + curBibFile.getAbsolutePath());
										logger.info(curBib.toString());
									}
								} else if (recordIdentifier.isSuppressed()) {
									//logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
									suppressedRecordNumbersInExport.add(recordIdentifier.getIdentifier());
								} else {
									recordId = recordIdentifier.getIdentifier();

									boolean marcUpToDate = writeIndividualMarc(curProfile, curBib, recordId, marcRecordsWritten, marcRecordsOverwritten);
									recordNumbersInExport.add(recordIdentifier.toString());
									if (!explodeMarcsOnly) {
										if (!marcUpToDate || fullRegroupingNoClear) {
											if (recordGroupingProcessor.processMarcRecord(curBib, !marcUpToDate)) {
												recordNumbersToIndex.add(recordIdentifier.toString());
											} else {
												suppressedRecordNumbersInExport.add(recordIdentifier.toString());
											}
											numRecordsProcessed++;
										}
										//Mark that the record was processed
										String fullId = curProfile.name + ":" + recordId;
										fullId = fullId.toLowerCase().trim();
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
							if (numRecordsRead % 5000 == 0) {
								updateLastUpdateTimeInLog();
								//Let the hard drives rest a bit so other things can happen.
								Thread.sleep(100);
							}
						}
					} catch (Exception e) {
						if (!recordId.isEmpty()) {
							logger.error("Error loading  bibs on record " + numRecordsRead + " in profile " + curProfile.name + " on the record  " + recordId, e);
						} else {
							logger.error("Error loading  bibs on record " + numRecordsRead + " in profile " + curProfile.name + " the last record processed was " + lastRecordProcessed, e);
						}
					}
					logger.info("Finished grouping " + numRecordsRead + " records with " + numRecordsProcessed + " actual changes from the marc file " + curBibFile.getName() + " in profile " + curProfile.name);
					addNoteToGroupingLog("&nbsp;&nbsp; - Finished grouping " + numRecordsRead + " records from the marc file " + curBibFile.getName());
				}

				addNoteToGroupingLog("&nbsp;&nbsp; - Records Processed: " + recordNumbersInExport.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Suppressed: " + suppressedRecordNumbersInExport.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Written: " + marcRecordsWritten.size());
				addNoteToGroupingLog("&nbsp;&nbsp; - Records Overwritten: " + marcRecordsOverwritten.size());

				removeDeletedRecords(curProfile.name);

				String profileName = curProfile.name.replaceAll(" ", "_");
				writeExistingRecordsFile(recordNumbersInExport, "record_grouping_" + profileName + "_bibs_in_export");
				if (suppressedRecordNumbersInExport.size() > 0) {
					writeExistingRecordsFile(suppressedRecordNumbersInExport, "record_grouping_" + profileName + "_bibs_to_ignore");
				}
				if (suppressedControlNumbersInExport.size() > 0) {
					writeExistingRecordsFile(suppressedControlNumbersInExport, "record_grouping_" + profileName + "_control_numbers_to_ignore");
				}
				if (recordNumbersToIndex.size() > 0) {
					writeExistingRecordsFile(recordNumbersToIndex, "record_grouping_" + profileName + "_bibs_to_index");
				}
				if (marcRecordsWritten.size() > 0) {
					writeExistingRecordsFile(marcRecordsWritten, "record_grouping_" + profileName + "_new_bibs_written");
				}
				if (marcRecordsOverwritten.size() > 0) {
					writeExistingRecordsFile(marcRecordsOverwritten, "record_grouping_" + profileName + "_changed_bibs_written");
				}
			}

		}
	}

	/*private static void loadExistingMarcFiles(File individualMarcPath, HashSet<String> existingFiles) {
		File[] subFiles = individualMarcPath.listFiles();
		if (subFiles != null){
			for (File curFile : subFiles){
				String fileName = curFile.getName();
				if (!fileName.equals(".") && !fileName.equals("..")){
					if (curFile.isDirectory()){
						loadExistingMarcFiles(curFile, existingFiles);
					}else{
						existingFiles.add(fileName);
					}
				}
			}
		}
	}*/

	private static void groupOverDriveRecords(Connection pikaConn, Connection econtentConnection, RecordGroupingProcessor recordGroupingProcessor, boolean explodeMarcsOnly) {
		if (explodeMarcsOnly) {
			//Nothing to do since we don't have marc records to process
			return;
		}
		addNoteToGroupingLog("Starting to group overdrive records");
		loadIlsChecksums(pikaConn, "overdrive");
		loadExistingPrimaryIdentifiers(pikaConn, "overdrive");

		int numRecordsProcessed = 0;
		try {
			PreparedStatement overDriveRecordsStmt;
			if (lastGroupingTime != null && !fullRegrouping && !fullRegroupingNoClear) {
				overDriveRecordsStmt = econtentConnection.prepareStatement("SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id WHERE deleted = 0 and isOwnedByCollections = 1 and (dateUpdated >= ? OR lastMetadataChange >= ? OR lastAvailabilityChange >= ?)", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				overDriveRecordsStmt.setLong(1, lastGroupingTime);
				overDriveRecordsStmt.setLong(2, lastGroupingTime);
				overDriveRecordsStmt.setLong(3, lastGroupingTime);
			} else {
				overDriveRecordsStmt = econtentConnection.prepareStatement("SELECT overdrive_api_products.id, overdriveId, mediaType, title, subtitle, primaryCreatorRole, primaryCreatorName FROM overdrive_api_products INNER JOIN overdrive_api_product_metadata ON overdrive_api_product_metadata.productId = overdrive_api_products.id WHERE deleted = 0 and isOwnedByCollections = 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			}
			TreeSet<String> recordNumbersInExport = new TreeSet<>();
			try (
					PreparedStatement overDriveIdentifiersStmt = econtentConnection.prepareStatement("SELECT * FROM overdrive_api_product_identifiers WHERE id = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					PreparedStatement overDriveCreatorStmt = econtentConnection.prepareStatement("SELECT fileAs FROM overdrive_api_product_creators WHERE productId = ? AND role like ? ORDER BY id", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet overDriveRecordRS = overDriveRecordsStmt.executeQuery()
			) {

				while (overDriveRecordRS.next()) {
					Long   id                 = overDriveRecordRS.getLong("id");
					String overdriveId        = overDriveRecordRS.getString("overdriveId");
					String mediaType          = overDriveRecordRS.getString("mediaType");
					String title              = overDriveRecordRS.getString("title");
					String subtitle           = overDriveRecordRS.getString("subtitle");
					String primaryCreatorRole = overDriveRecordRS.getString("primaryCreatorRole");
					String author             = overDriveRecordRS.getString("primaryCreatorName");
					recordNumbersInExport.add(overdriveId);
					//primary creator in overdrive is always first name, last name.  Therefore, we need to look in the creators table
					if (author != null) {
						overDriveCreatorStmt.setLong(1, id);
						overDriveCreatorStmt.setString(2, primaryCreatorRole);
						ResultSet creatorInfoRS         = overDriveCreatorStmt.executeQuery();
						boolean   swapFirstNameLastName = false;
						if (creatorInfoRS.next()) {
							String tmpAuthor = creatorInfoRS.getString("fileAs");
							if (tmpAuthor.equals(author) && (mediaType.equalsIgnoreCase("ebook") || mediaType.equalsIgnoreCase("audiobook"))) {
								swapFirstNameLastName = true;
							} else {
								author = tmpAuthor;
							}
						} else {
							swapFirstNameLastName = true;
						}
						if (swapFirstNameLastName) {
							logger.debug("Swap First Last Name for author '" + author + "' for an Overdrive title.");
							// I suspect this never gets called anymore. pascal 7/26/2018
							if (author.contains(" ")) {
								String[]      authorParts = author.split("\\s+");
								StringBuilder tmpAuthor   = new StringBuilder();
								for (int i = 1; i < authorParts.length; i++) {
									tmpAuthor.append(authorParts[i]).append(" ");
								}
								tmpAuthor.append(authorParts[0]);
								author = tmpAuthor.toString();
							}
						}
						creatorInfoRS.close();
					}

					overDriveIdentifiersStmt.setLong(1, id);
					RecordIdentifier primaryIdentifier = new RecordIdentifier();
					primaryIdentifier.setValue("overdrive", overdriveId);

					recordGroupingProcessor.processRecord(primaryIdentifier, title, subtitle, author, mediaType, true);
					primaryIdentifiersInDatabase.remove(primaryIdentifier.toString().toLowerCase());
					numRecordsProcessed++;
				}
				overDriveRecordRS.close();
			}

			//This is no longer needed because we do cleanup differently now (get a list of everything in the database and then cleanup anything that isn't in the API anymore
			if (fullRegrouping) {
				writeExistingRecordsFile(recordNumbersInExport, "record_grouping_overdrive_records_in_export");
			}
			removeDeletedRecords("overdrive");
			addNoteToGroupingLog("Finished grouping " + numRecordsProcessed + " records from overdrive ");
		} catch (Exception e) {
			System.out.println("Error loading OverDrive records: " + e.toString());
			e.printStackTrace();
		}
	}

	private static SimpleDateFormat oo8DateFormat = new SimpleDateFormat("yyMMdd");
	private static SimpleDateFormat oo5DateFormat = new SimpleDateFormat("yyyyMMdd");

	private static boolean writeIndividualMarc(IndexingProfile indexingProfile, Record marcRecord, String recordNumber, TreeSet<String> marcRecordsWritten, TreeSet<String> marcRecordsOverwritten) {
		boolean marcRecordUpToDate = false;
		//Copy the record to the individual marc path
		if (recordNumber != null) {
			String recordNumberWithSource = indexingProfile.name + ":" + recordNumber;
			Long   checksum               = getChecksum(marcRecord);
			Long   existingChecksum       = getExistingChecksum(recordNumberWithSource);
			File   individualFile         = indexingProfile.getFileForIlsRecord(recordNumber);

			//If we are doing partial regrouping or full regrouping without clearing the previous results,
			//Check to see if the record needs to be written before writing it.
			if (!fullRegrouping) {
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
						logger.debug("Individual marc record not found " + recordNumberWithSource);
					} catch (Exception e) {
						logger.error("Error getting checksum for file", e);
					}
				}
			}

			if (!marcRecordUpToDate) {
				try {
					outputMarcRecord(marcRecord, individualFile);
					getDateAddedForRecord(marcRecord, recordNumberWithSource, individualFile);
					updateMarcRecordChecksum(recordNumber, indexingProfile.name, checksum);
					//logger.debug("checksum changed for " + recordNumber + " was " + existingChecksum + " now its " + checksum);
				} catch (IOException e) {
					logger.error("Error writing marc", e);
				}
			} else {
				//Update date first detected if needed
				if (marcRecordFirstDetectionDates.containsKey(recordNumberWithSource) && marcRecordFirstDetectionDates.get(recordNumberWithSource) == null) {
					getDateAddedForRecord(marcRecord, recordNumberWithSource, individualFile);
					updateMarcRecordChecksum(recordNumber, indexingProfile.name, checksum);
				}
			}
		} else {
			logger.error("Error did not find record number for MARC record");
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
				logger.debug("Error loading creation time for " + filePath, e);
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
			logger.error("Unable to update checksum for ils marc record", e);
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
			logger.info(note);
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

	private static boolean checkForForcedRegrouping(Connection pikaConn, String indexingProfile) {
		try (PreparedStatement checkForRecordsMarkedForRegrouping = pikaConn.prepareStatement("SELECT COUNT(*) from ils_marc_checksums where source like ? AND checksum = 0", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			checkForRecordsMarkedForRegrouping.setString(1, indexingProfile);
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
