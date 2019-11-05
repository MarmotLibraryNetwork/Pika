package org.pika;

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;

import java.io.*;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;


/**
 * Reindex Grouped Records for display within Pika
 * 
 * @author Mark Noble <mark@marmot.org>
 * 
 */
public class GroupedReindexMain {

	private static Logger logger = Logger.getLogger(GroupedReindexMain.class);

	//General configuration
	private static String serverName;
	private static boolean fullReindex = false;
	private static String individualWorkToProcess;
	private static String baseLogPath;
	private static String solrPort;
	private static String solrDir;
	
	//Reporting information
	private static long reindexLogId;
	private static long startTime;
	private static long endTime;
	private static PreparedStatement addNoteToReindexLogStmt;

	//Database connections and prepared statements
	private static Connection pikaConn     = null;
	private static Connection econtentConn = null;


	public static ArrayList<String> hooplaRecordWithOutExtractInfo = new ArrayList<>();


	/**
	 * Starts the re-indexing process
	 * 
	 * @param args String[] The server name to index with optional parameter for properties of indexing
	 */
	public static void main(String[] args) {
		startTime = new Date().getTime();
		// Get the configuration filename
		if (args.length == 0) {
			System.out.println("Please enter the server to index as the first parameter");
			System.exit(1);
		}
		serverName = args[0];
		System.setProperty("reindex.process.serverName", serverName);
		
		if (args.length >= 2 && args[1].equalsIgnoreCase("fullReindex")){
			fullReindex = true;
		}else if (args.length >= 2 && args[1].equalsIgnoreCase("singleWork")){
			//Process a specific work
			//Prompt for the work to process
			System.out.print("Enter the grouped work id of the work to process: ");

			//  open up standard input
			BufferedReader br = new BufferedReader(new InputStreamReader(System.in));

			//  read the work from the command-line; need to use try/catch with the
			//  readLine() method
			try {
				individualWorkToProcess = br.readLine().trim();
			} catch (IOException ioe) {
				System.out.println("IO error trying to read the work to process!");
				System.exit(1);
			}
		}

		initializeReindex();
		
		addNoteToReindexLog("Initialized Reindex ");
		if (fullReindex){
			addNoteToReindexLog("Performing full reindex");
		}
		
		//Reload schemas as needed
//		reloadDefaultSchemas();

		//Process grouped works
		long numWorksProcessed = 0;
		long numListsProcessed = 0;
		try {
			GroupedWorkIndexer groupedWorkIndexer = new GroupedWorkIndexer(serverName, pikaConn, econtentConn, fullReindex, individualWorkToProcess != null, logger);
			HashMap<Scope, ArrayList<SiteMapEntry>> siteMapsByScope = new HashMap<>();
			HashSet<Long> uniqueGroupedWorks = new HashSet<>();
			if (groupedWorkIndexer.isOkToIndex()) {
				if (individualWorkToProcess != null) {
					//Get more information about the work
					try (PreparedStatement getInfoAboutWorkStmt = pikaConn.prepareStatement("SELECT * FROM grouped_work WHERE permanent_id = ?") ){
						getInfoAboutWorkStmt.setString(1, individualWorkToProcess);
						try (ResultSet infoAboutWork = getInfoAboutWorkStmt.executeQuery()) {
							if (infoAboutWork.next()) {
								groupedWorkIndexer.deleteRecord(individualWorkToProcess);
								groupedWorkIndexer.processGroupedWork(infoAboutWork.getLong("id"), individualWorkToProcess, infoAboutWork.getString("grouping_category"), null, null);
							} else {
								logger.error("Could not find a work with id " + individualWorkToProcess);
							}
						}
					} catch (Exception e) {
						logger.error("Unable to process individual work " + individualWorkToProcess, e);
					}
				} else {
					logger.info("Running Reindex");
					numWorksProcessed = groupedWorkIndexer.processGroupedWorks(siteMapsByScope, uniqueGroupedWorks);
					numListsProcessed = groupedWorkIndexer.processPublicUserLists();
				}
				if (fullReindex) {
					logger.info("Creating Site Maps");
					groupedWorkIndexer.createSiteMaps(siteMapsByScope, uniqueGroupedWorks);

					// Log info about missing hoopla data
					if (hooplaRecordWithOutExtractInfo.size() > 0) {
						logger.warn("Hoopla Records without extract info : " + String.join(", ", hooplaRecordWithOutExtractInfo));
					}
				}

				groupedWorkIndexer.finishIndexing(individualWorkToProcess != null);
				long lexileDataMatches = groupedWorkIndexer.getLexileDataMatches();
				if (lexileDataMatches > 0){
					addNoteToReindexLog("Lexile matches for " + lexileDataMatches + " grouped Works");
				}
				long ARDataMatches = groupedWorkIndexer.getARDataMatches();
				if (ARDataMatches > 0) {
					addNoteToReindexLog("Accelerated Reader matches for " + ARDataMatches + " grouped Works");
				}
			}
		} catch (Error e) {
			logger.error("Error processing reindex ", e);
			addNoteToReindexLog("Error processing reindex " + e.toString());
		} catch (Exception e) {
			logger.error("Exception processing reindex ", e);
			addNoteToReindexLog("Exception processing reindex " + e.toString());
		}

		// Send completion information
		endTime = new Date().getTime();
		cleanupOldStatisticReports();
		sendCompletionMessage(numWorksProcessed, numListsProcessed);
		
		addNoteToReindexLog("Finished Reindex for " + serverName);
		logger.info("Finished Reindex for " + serverName);
		long endTime = new Date().getTime();
		long elapsedTime = endTime - startTime;
		logger.info("Elapsed Minutes " + (elapsedTime / 60000));
	}

	private static void cleanupOldStatisticReports() {

	}

//	private static void reloadDefaultSchemas() {
		/*logger.info("Reloading schemas from default");
		try {
			//Copy schema to grouped2

			logger.debug("Copying " + "../../data_dir_setup/solr/grouped/conf/schema.xml" + " to " + "../../data_dir_setup/solr/grouped2/conf/schema.xml");
			if (!Util.copyFile(new File("../../data_dir_setup/solr/grouped/conf/schema.xml"), new File("../../data_dir_setup/solr/grouped2/conf/schema.xml"))){
				logger.warn("Unable to copy default schema.xml to grouped2 in data_dir_setup");
				addNoteToReindexLog("Unable to copy default schema.xml to grouped2 in data_dir_setup");
			}
			//Synonyms
			logger.debug("Copying " + "../../data_dir_setup/solr/grouped/conf/synonyms.txt" + " to " + "../../data_dir_setup/solr/grouped2/conf/synonyms.txt");
			if (!Util.copyFile(new File("../../data_dir_setup/solr/grouped/conf/synonyms.txt"), new File("../../data_dir_setup/solr/grouped2/conf/synonyms.txt"))){
				logger.warn("Unable to copy default synonyms.txt to grouped2 in data_dir_setup");
				addNoteToReindexLog("Unable to copy default synonyms.txt to grouped2 in data_dir_setup");
			}
		} catch (IOException e) {
			logger.error("error reloading copying default schemas", e);
			addNoteToReindexLog("error reloading copying default schemas " + e.toString());
		}*/

		//MDN 10-21-2015 temporarily do not reload schemas as we test replication
		/*//grouped
		reloadSchema("grouped");
		reloadSchema("grouped2");
		//genealogy
		reloadSchema("genealogy");*/
//	}

	/*private static void reloadSchema(String schemaName) {
		boolean errorCopyingFiles = false;
		boolean fileChanged = false;
		try {
			File defaultSchema = new File("../../data_dir_setup/solr/" + schemaName + "/conf/schema.xml");
			File activeSchema = new File(solrDir + "/" + schemaName + "/conf/schema.xml");
			if (!Util.compareFiles(defaultSchema, activeSchema, logger)) {
				logger.debug("Copying " + "../../data_dir_setup/solr/" + schemaName + "/conf/schema.xml" + " to " + solrDir + "/" + schemaName + "/conf/schema.xml");
				if (!Util.copyFile(defaultSchema, activeSchema)) {
					logger.warn("Unable to copy schema for " + schemaName);
					addNoteToReindexLog("Unable to copy schema for " + schemaName);
					errorCopyingFiles = true;
				}else{
					fileChanged = true;
				}
			}

			File defaultASCIIMapping = new File("../../data_dir_setup/solr/" + schemaName + "/conf/mapping-FoldToASCII.txt");
			File activeASCIIMapping = new File(solrDir + "/" + schemaName + "/conf/mapping-FoldToASCII.txt");
			if (!Util.compareFiles(defaultASCIIMapping, activeASCIIMapping, logger)) {
				logger.debug("Copying " + "../../data_dir_setup/solr/" + schemaName + "/conf/mapping-FoldToASCII.txt" + " to " + solrDir + "/" + schemaName + "/conf/mapping-FoldToASCII.txt");
				if (!Util.copyFile(defaultASCIIMapping, activeASCIIMapping)) {
					logger.warn("Unable to copy mapping-FoldToASCII.txt for " + schemaName);
					addNoteToReindexLog("Unable to copy mapping-FoldToASCII.txt for " + schemaName);
					errorCopyingFiles = true;
				}else{
					fileChanged = true;
				}
			}

			File defaultLatinMapping = new File("../../data_dir_setup/solr/" + schemaName + "/conf/mapping-ISOLatin1Accent.txt");
			File activeLatinMapping = new File(solrDir + "/" + schemaName + "/conf/mapping-ISOLatin1Accent.txt");
			if (!Util.compareFiles(defaultLatinMapping, activeLatinMapping, logger)) {
				logger.debug("Copying " + "../../data_dir_setup/solr/" + schemaName + "/conf/mapping-ISOLatin1Accent.txt" + " to " + solrDir + "/" + schemaName + "/conf/mapping-ISOLatin1Accent.txt");
				if (!Util.copyFile(defaultLatinMapping, activeLatinMapping)) {
					logger.warn("Unable to copy mapping-ISOLatin1Accent.txt for " + schemaName);
					addNoteToReindexLog("Unable to copy mapping-ISOLatin1Accent.txt for " + schemaName);
					errorCopyingFiles = true;
				} else {
					fileChanged = true;
				}
			}

			File defaultSynonyms = new File("../../data_dir_setup/solr/" + schemaName + "/conf/synonyms.txt");
			File activeSynonyms = new File(solrDir + "/" + schemaName + "/conf/synonyms.txt");
			if (!Util.compareFiles(defaultSynonyms, activeSynonyms, logger)) {
				logger.debug("Copying " + "../../data_dir_setup/solr/" + schemaName + "/conf/synonyms.txt" + " to " + solrDir + "/" + schemaName + "/conf/synonyms.txt");
				if (!Util.copyFile(defaultSynonyms, activeSynonyms)) {
					logger.warn("Unable to copy mapping-ISOLatin1Accent.txt for " + schemaName);
					addNoteToReindexLog("Unable to copy mapping-ISOLatin1Accent.txt for " + schemaName);
					errorCopyingFiles = true;
				} else {
					fileChanged = true;
				}
			}

			File defaultSolrConfig = new File("../../data_dir_setup/solr/" + schemaName + "/conf/solrconfig.xml");
			File activeSolrConfig = new File(solrDir + "/" + schemaName + "/conf/solrconfig.xml");
			if (!Util.compareFiles(defaultSolrConfig, activeSolrConfig, logger)) {
				logger.debug("Copying " + "../../data_dir_setup/solr/" + schemaName + "/conf/solrconfig.xml" + " to " + solrDir + "/" + schemaName + "/conf/solrconfig.xml");
				if (!Util.copyFile(defaultSolrConfig, activeSolrConfig)) {
					logger.warn("Unable to copy solrconfig.xml for " + schemaName);
					addNoteToReindexLog("Unable to copy solrconfig.xml for " + schemaName);
					errorCopyingFiles = true;
				} else {
					fileChanged = true;
				}
			}
		} catch (IOException e) {
			logger.error("error reloading default schema for " + schemaName, e);
			addNoteToReindexLog("error reloading default schema for " + schemaName + " " + e.toString());
			errorCopyingFiles = false;
		}
		if (!errorCopyingFiles && fileChanged){
			addNoteToReindexLog("Reloading Schema " + schemaName);
			URLPostResponse response = Util.getURL("http://localhost:" + solrPort + "/solr/admin/cores?action=RELOAD&core=" + schemaName, logger);
			if (!response.isSuccess()){
				logger.error("Error reloading default schema for " + schemaName + " " + response.getMessage());
				addNoteToReindexLog("Error reloading default schema for " + schemaName + " " + response.getMessage());
			}
		}else{
			logger.debug("Not reloading core because nothing changed.");
		}
	}*/

	private static StringBuffer reindexNotes = new StringBuffer();
	private static SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
	static void addNoteToReindexLog(String note) {
		if (addNoteToReindexLogStmt == null){
			//This happens when called from another system (i.e. from Sierra Export)
			return;
		}
		try {
			Date date = new Date();
			reindexNotes.append("<br>").append(dateFormat.format(date)).append(note);
			addNoteToReindexLogStmt.setString(1, Util.trimTo(65535, reindexNotes.toString()));
			addNoteToReindexLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToReindexLogStmt.setLong(3, reindexLogId);
			addNoteToReindexLogStmt.executeUpdate();
			if (logger.isInfoEnabled()) {
				logger.info(note);
			}
		} catch (SQLException e) {
			logger.error("Error adding note to Reindex Log", e);
		}
	}

	private static void initializeReindex() {
		// Delete the existing reindex.log file
		File solrMarcLog = new File(baseLogPath + "/" + serverName + "/logs/grouped_reindex.log");
		if (solrMarcLog.exists()){
			if (!solrMarcLog.delete()){
				logger.warn("Could not remove " + solrMarcLog.toString());
			}
		}
		for (int i = 1; i <= 10; i++){
			solrMarcLog = new File(baseLogPath + "/" + serverName + "/logs/grouped_reindex.log." + i);
			if (solrMarcLog.exists()){
				if (!solrMarcLog.delete()){
					logger.warn("Could not remove " + solrMarcLog.toString());
				}
			}
		}
		solrMarcLog = new File("org.solrmarc.log");
		if (solrMarcLog.exists()){
			if (!solrMarcLog.delete()){
				logger.warn("Could not remove " + solrMarcLog.toString());
			}
		}
		for (int i = 1; i <= 4; i++){
			solrMarcLog = new File("org.solrmarc.log." + i);
			if (solrMarcLog.exists()){
				if (!solrMarcLog.delete()){
					logger.warn("Could not remove " + solrMarcLog.toString());
				}
			}
		}
		
		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.grouped_reindex.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.getAbsolutePath());
			System.exit(1);
		}
		
		logger.info("Starting Reindex for " + serverName);

		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		baseLogPath = PikaConfigIni.getIniValue("Site", "baseLogPath");
		solrPort = PikaConfigIni.getIniValue("Reindex", "solrPort");
		if (solrPort == null || solrPort.length() == 0) {
			logger.error("You must provide the port where the solr index is loaded in the import configuration file");
			System.exit(1);
		}

		solrDir = PikaConfigIni.getIniValue("Index", "local");
		if (solrDir == null){
			solrDir = "/data/pika/" + serverName + "/solr";
		}
		
		logger.info("Setting up database connections");
		//Setup connections to pika and econtent databases
		String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
		if (databaseConnectionInfo == null || databaseConnectionInfo.length() == 0) {
			logger.error("Pika Database connection information not found in Database Section.  Please specify connection information in database_vufind_jdbc.");
			System.exit(1);
		}
		try {
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (SQLException e) {
			logger.error("Could not connect to pika database : " + e.getMessage());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}

		String econtentDBConnectionInfo = PikaConfigIni.getIniValue("Database", "database_econtent_jdbc");
		if (econtentDBConnectionInfo == null || econtentDBConnectionInfo.length() == 0) {
			logger.error("Database connection information for eContent database not found in Database Section.  Please specify connection information as database_econtent_jdbc key.");
			System.exit(1);
		}
		try {
			econtentConn = DriverManager.getConnection(econtentDBConnectionInfo);
		} catch (SQLException e) {
			logger.error("Could not connect to econtent database : " + e.getMessage());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}
		
		//Start a reindex log entry 
		try {
			logger.info("Creating log entry for index");
			PreparedStatement createLogEntryStatement = pikaConn.prepareStatement("INSERT INTO reindex_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS);
			createLogEntryStatement.setLong(1, new Date().getTime() / 1000);
			createLogEntryStatement.setLong(2, new Date().getTime() / 1000);
			createLogEntryStatement.setString(3, "Initialization complete");
			createLogEntryStatement.executeUpdate();
			ResultSet generatedKeys = createLogEntryStatement.getGeneratedKeys();
			if (generatedKeys.next()){
				reindexLogId = generatedKeys.getLong(1);
			}
			addNoteToReindexLogStmt = pikaConn.prepareStatement("UPDATE reindex_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for reindex process", e);
			System.exit(0);
		}
		
	}
	
	private static void sendCompletionMessage(Long numWorksProcessed, Long numListsProcessed){
		long elapsedTime = endTime - startTime;
		float elapsedMinutes = (float)elapsedTime / (float)(60000); 
		logger.info("Time elapsed: " + elapsedMinutes + " minutes");

		final long finishedTimestamp = new Date().getTime() / 1000;
		try {
			PreparedStatement finishedStatement = pikaConn.prepareStatement("UPDATE reindex_log SET endTime = ?, numWorksProcessed = ?, numListsProcessed = ? WHERE id = ?");
			finishedStatement.setLong(1, finishedTimestamp);
			finishedStatement.setLong(2, numWorksProcessed);
			finishedStatement.setLong(3, numListsProcessed);
			finishedStatement.setLong(4, reindexLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update reindex log with completion time.", e);
		}

		//Update variables table to mark the index as complete
		if (individualWorkToProcess == null){
			PikaSystemVariables systemVariables = new PikaSystemVariables(logger, pikaConn);
			if (!systemVariables.setVariable(fullReindex ? "lastFullReindexFinish" : "lastPartialReindexFinish", finishedTimestamp)){
				logger.error("Unable to update variables with completion time.");
			}
		}
	}

	private static PreparedStatement updateNumWorksStatement;
	public static void updateNumWorksProcessed(long numWorksProcessed){
		try {
			if (updateNumWorksStatement == null){
				updateNumWorksStatement = pikaConn.prepareStatement("UPDATE reindex_log SET lastUpdate = ?, numWorksProcessed = ? WHERE id = ?");
			}
			updateNumWorksStatement.setLong(1, new Date().getTime() / 1000);
			updateNumWorksStatement.setLong(2, numWorksProcessed);
			updateNumWorksStatement.setLong(3, reindexLogId);
			updateNumWorksStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update reindex log with number of works processed.", e);
		}
	}

}
