package org.pika;

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcStreamWriter;
import org.marc4j.MarcWriter;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.impl.SubfieldImpl;

import java.io.*;
import java.sql.*;
import java.util.*;
import java.util.Date;

import au.com.bytecode.opencsv.CSVWriter;

/**
 * Export data from Koha
 *
 * Pika
 * User: Mark Noble
 * Date: 3/22/2015
 * Time: 9:23 PM
 */
public class KohaExportMain {
	private static Logger logger = Logger.getLogger(KohaExportMain.class);
	private static String serverName; //Pika instance name

	private static IndexingProfile indexingProfile;
	private static String          exportPath;

	private static boolean getDeletedBibs = false;

	// Item subfields
	private static char locationSubfield      = 'a';
	private static char subLocationSubfield   = '8';
	private static char shelflocationSubfield = 'c';
	private static char withdrawnSubfield     = '0';
	private static char damagedSubfield       = '4';
	private static char lostSubfield          = '1';
	private static char notforloanSubfield    = '7'; //Primary status subfield
	private static char restrictedSubfield    = '5';
	private static char dueDateSubfield       = 'q';

	private static long updateTime;
	private static long lastKohaExtractTime;
	private static Long lastKohaExtractTimeVariableId = null;


	public static void main(String[] args) {
		serverName = args[0];

		Date startTime = new Date();
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.koha_export.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.toString());
		}

		logger.info(startTime.toString() + ": Starting Koha Extract");

		// Read the base INI file to get information about the server (current directory/conf/config.ini)
		Ini ini = loadConfigFile("config.ini");

		// Connect to the Pika database
		Connection pikaConn = null;
		try {
			String databaseConnectionInfo = cleanIniValue(ini.get("Database", "database_vufind_jdbc"));
			if (databaseConnectionInfo == null){
				logger.error("Please provide database_vufind_jdbc within config.ini (or better config.pwd.ini) ");
				System.exit(1);
			}
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (Exception e) {
			logger.error("Error connecting to pika database ", e);
			System.exit(1);
		}

		updateTime = new Date().getTime() / 1000;
		String profileToLoad               = "ils";
		int    numHoursToFetchBibDeletions = 24;
		if (args.length > 1){
			if (args[1].equalsIgnoreCase("getDeletedBibs")) {
				getDeletedBibs = true;
				if (args.length > 2) {
					try {
						numHoursToFetchBibDeletions = Integer.parseInt(args[2]);
					} catch (NumberFormatException e) {
						logger.warn("Could not parse number of hours to fetch parameter: " + args[2] + "; Using default 24 instead.");
					}
				}
				exportPath = "/data/pika-plus/bywaterkoha/deletes/marc"; // default path
				if (args.length > 3) {
					exportPath = args[3];
				}
			} else if (args[1].equalsIgnoreCase("updateLastExtractTime")){
				long timestamp;
				if (args.length > 2) {
					// Set to a specific timestamp if that is passed on the command line
					String newExtractTime = args[2];
					timestamp = Long.parseLong(newExtractTime);
				} else {
					// Otherwise, set to 24 hours ago
					timestamp = updateTime - 24*3600;
				}
				logger.info("Setting the Last Koha Extract Time to timestamp: " + timestamp);

				// Update the extract time and exit
				setLastKohaExtractTime(pikaConn, timestamp);
				System.exit(0);
			} else {
				profileToLoad = args[1];
			}
		}

		// Connect to the Koha database
		Connection kohaConn = null;
		try {
			String kohaConnectionJDBC = "jdbc:mysql://" +
					cleanIniValue(ini.get("Catalog", "db_host")) +
					":" + cleanIniValue(ini.get("Catalog", "db_port")) +
					"/" + cleanIniValue(ini.get("Catalog", "db_name") +
					"?user=" + cleanIniValue(ini.get("Catalog", "db_user")) +
					"&password=" + cleanIniValue(ini.get("Catalog", "db_pwd")) +
					"&useUnicode=yes&characterEncoding=UTF-8");
			kohaConn = DriverManager.getConnection(kohaConnectionJDBC);
		} catch (Exception e) {
			logger.error("Error connecting to koha database ", e);
			System.exit(1);
		}

		if (getDeletedBibs){
			// Fetch Deleted Bibs from today
			try {
				String            deletedBibsFileName        = "deletedBibs.csv";
				long              yesterday                  = updateTime - numHoursToFetchBibDeletions * 3600; // seconds
				PreparedStatement getDeletedBibsFromKohaStmt = kohaConn.prepareStatement("select biblionumber from deletedbiblio where timestamp >= ?");
				getDeletedBibsFromKohaStmt.setTimestamp(1, new Timestamp(yesterday * 1000));
				ResultSet getDeletedBibsFromKohaRS = getDeletedBibsFromKohaStmt.executeQuery();
				writeToFileFromSQLResult(deletedBibsFileName, getDeletedBibsFromKohaRS);

			} catch (Exception e) {
				logger.error("Error loading deleted records from Koha database", e);
				System.exit(1);
			}


		} else {
			// Regular Koha extraction processes

			indexingProfile = IndexingProfile.loadIndexingProfile(pikaConn, profileToLoad, logger);
			exportPath      = indexingProfile.marcPath;
			if (exportPath.startsWith("\"")){
				exportPath = exportPath.substring(1, exportPath.length() - 1);
			}

			// Override any relevant subfield settings if they are set
			if (indexingProfile.locationSubfield != ' '){
				locationSubfield = indexingProfile.locationSubfield;
			}
			if (indexingProfile.subLocationSubfield != ' '){
				subLocationSubfield = indexingProfile.subLocationSubfield;
			}
			if (indexingProfile.shelvingLocationSubfield != ' '){
				shelflocationSubfield = indexingProfile.shelvingLocationSubfield;
			}
			if (indexingProfile.dueDateSubfield != ' '){
				dueDateSubfield = indexingProfile.dueDateSubfield;
			}

			getLastKohaExtractTime(pikaConn);

			if(
				// Get a list of works that have changed or deleted since the last index
					getChangedRecordsFromDatabase(ini, pikaConn, kohaConn) &&
							getDeletedItemsFromDatabase(/*ini,*/ pikaConn, kohaConn)
			){
				setLastKohaExtractTime(pikaConn);
			} else {
				logger.error("There was an error updating item info or the database, not setting last extract time.");
			}

			exportHolds(pikaConn, kohaConn);
			exportHoldShelfItems(kohaConn);
			exportInTransitItems(kohaConn);
		}

		if (pikaConn != null){
			try{
				//Close the connection
				pikaConn.close();
			}catch(Exception e){
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}
		if (kohaConn != null){
			try{
				//Close the connection
				kohaConn.close();
			}catch(Exception e){
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}
		Date currentTime = new Date();
		logger.info(currentTime.toString() + ": Finished Koha Extract");
	}

	private static void getLastKohaExtractTime(Connection pikaConn) {
		try {
			PreparedStatement loadLastKohaExtractTimeStmt = pikaConn.prepareStatement("SELECT * from variables WHERE name = 'last_koha_extract_time'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet         lastKohaExtractTimeRS       = loadLastKohaExtractTimeStmt.executeQuery();
			if (lastKohaExtractTimeRS.next()){
				lastKohaExtractTime = lastKohaExtractTimeRS.getLong("value");
				lastKohaExtractTimeVariableId = lastKohaExtractTimeRS.getLong("id");
			}else{
				//Get the last 5 minutes for the initial setup
				lastKohaExtractTime = updateTime - 5 * 60;
			}

			// go back 1 minutes
			lastKohaExtractTime -= 60;
//			lastKohaExtractTime -= 1 * 60; // minutes version, if we decide to greater than 1
		} catch (SQLException e) {
			logger.error("Error fetching the last Koha extract time from the Pika database", e);
		}
	}

	private static void setLastKohaExtractTime(Connection pikaConn) {
		long finishTime = new Date().getTime() / 1000;
		setLastKohaExtractTime(pikaConn, finishTime);
	}

	private static void setLastKohaExtractTime(Connection pikaConn, long finishTime) {
		// Update the last extract time
		try {
			if (lastKohaExtractTimeVariableId == null){
				// If we haven't fetch the Koha Extract time yet, try now.
				getLastKohaExtractTime(pikaConn);
			}
			if (lastKohaExtractTimeVariableId != null) {
				PreparedStatement updateVariableStmt = pikaConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setLong(1, finishTime);
				updateVariableStmt.setLong(2, lastKohaExtractTimeVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else {
				PreparedStatement insertVariableStmt = pikaConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('last_koha_extract_time', ?)");
				insertVariableStmt.setString(1, Long.toString(finishTime));
				insertVariableStmt.executeUpdate();
				insertVariableStmt.close();
			}
		} catch (SQLException e) {
			logger.error("Error setting the last Koha extract time", e);
		}
	}

	private static void exportInTransitItems(Connection kohaConn) {
		logger.info("Starting export of in-transit items");
		try {
			PreparedStatement getInTransitItemsStmt = kohaConn.prepareStatement("SELECT itemnumber from branchtransfers WHERE datearrived IS NULL");
			ResultSet         inTransitItemsRS      = getInTransitItemsStmt.executeQuery();

			writeToFileFromSQLResult("inTransitItems.csv", inTransitItemsRS);

			inTransitItemsRS.close();
			getInTransitItemsStmt.close();
		} catch (SQLException e) {
			logger.error("Error retrieving in-transit items from Koha", e);
		} catch (IOException e) {
			logger.error("Error writing in-transit items to file", e);
		}
		logger.info("Finished export of in-transit items");
	}

	private static void exportHoldShelfItems(Connection kohaConn) {
		logger.info("Starting export of hold shelf items");
		try {
			PreparedStatement onHoldShelfItemsStmt = kohaConn.prepareStatement("SELECT itemnumber from reserves WHERE found = 'W'"); // W is Waiting at Library (As FYI a found value of 'T' is In Transit
			ResultSet         onHoldShelfItemsRS   = onHoldShelfItemsStmt.executeQuery();

			writeToFileFromSQLResult("holdShelfItems.csv", onHoldShelfItemsRS);

			onHoldShelfItemsRS.close();
			onHoldShelfItemsStmt.close();
		} catch (SQLException e) {
			logger.error("Error retrieving hold shelf items from Koha", e);
		} catch (IOException e) {
			logger.error("Error writing hold shelf items to file", e);
		}
		logger.info("Finished export of hold shelf items");
	}

	private static void writeToFileFromSQLResult(String fileName, ResultSet dataRS) throws IOException, SQLException {
		File      dataFile       = new File(exportPath + "/" + fileName);
		CSVWriter dataFileWriter = new CSVWriter(new FileWriter(dataFile));
		dataFileWriter.writeAll(dataRS, true);
		dataFileWriter.close();
	}

	private static void exportHolds(Connection vufindConn, Connection kohaConn) {
		Savepoint startOfHolds = null;
		try {
			logger.info("Starting export of holds");

			//Start a transaction so we can rebuild an entire table
			startOfHolds = vufindConn.setSavepoint();
			vufindConn.setAutoCommit(false);
			vufindConn.prepareCall("TRUNCATE TABLE ils_hold_summary").executeQuery();

			PreparedStatement addIlsHoldSummary = vufindConn.prepareStatement("INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)");

			//Export bib level holds
			HashMap<String, Long> numHoldsByBib = new HashMap<>();
			PreparedStatement     bibHoldsStmt  = kohaConn.prepareStatement("select count(*) as numHolds, biblionumber from reserves group by biblionumber", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet             bibHoldsRS    = bibHoldsStmt.executeQuery();
			while (bibHoldsRS.next()){
				String bibId    = bibHoldsRS.getString("biblionumber");
				Long   numHolds = bibHoldsRS.getLong("numHolds");
				numHoldsByBib.put(bibId, numHolds);
			}
			bibHoldsRS.close();

			for (String bibId : numHoldsByBib.keySet()){
				addIlsHoldSummary.setString(1, bibId);
				addIlsHoldSummary.setLong(2, numHoldsByBib.get(bibId));
				addIlsHoldSummary.executeUpdate();
			}

			try {
				vufindConn.commit();
				vufindConn.setAutoCommit(true);
			}catch (Exception e){
				logger.warn("error committing hold updates rolling back", e);
				vufindConn.rollback(startOfHolds);
			}

		} catch (Exception e) {
			logger.error("Unable to export holds from Koha", e);
			if (startOfHolds != null) {
				try {
					vufindConn.rollback(startOfHolds);
				}catch (Exception e1){
					logger.error("Unable to rollback due to exception", e1);
				}
			}
		}
		logger.info("Finished exporting holds");
	}

	private static boolean getChangedRecordsFromDatabase(Ini ini, Connection pikaConn, Connection kohaConn) {
		boolean success = true;

		// Get the time the last extract was done
		try{
			logger.info("Starting to load changed records from Koha using the Database connection");

			String maxRecordsToUpdateDuringExtractStr = ini.get("Catalog", "maxRecordsToUpdateDuringExtract");
			int    maxRecordsToUpdateDuringExtract    = 100000;
			if (maxRecordsToUpdateDuringExtractStr != null && maxRecordsToUpdateDuringExtractStr.length() > 0){
				maxRecordsToUpdateDuringExtract = Integer.parseInt(maxRecordsToUpdateDuringExtractStr);
			}

//			PreparedStatement getChangedItemsFromKohaStmt = kohaConn.prepareStatement("select itemnumber, biblionumber, barcode, homebranch, ccode, location, damaged, itemlost, withdrawn, restricted, onloan, notforloan from items where timestamp >= ? LIMIT 0, ?");
			PreparedStatement getChangedItemsFromKohaStmt = kohaConn.prepareStatement("select itemnumber, biblionumber, homebranch, ccode, location, damaged, itemlost, withdrawn, onloan, notforloan from items where timestamp >= ? LIMIT 0, ?");
			//notforloan is the primary status column for aspencat bywater koha (subfield 7)
			//shelf loc is subfield c, column location
			//sub location is subfield 8 (they call it collection code), column ccode
			//location is subfield a, column homebranch

			getChangedItemsFromKohaStmt.setTimestamp(1, new Timestamp(lastKohaExtractTime * 1000));
			getChangedItemsFromKohaStmt.setLong(2, maxRecordsToUpdateDuringExtract);

			ResultSet                                  itemChangeRS = getChangedItemsFromKohaStmt.executeQuery();
			HashMap<String, ArrayList<ItemChangeInfo>> changedBibs  = new HashMap<>();
			while (itemChangeRS.next()){
				String bibNumber     = itemChangeRS.getString("biblionumber");
				String itemNumber    = itemChangeRS.getString("itemnumber");
				String location      = itemChangeRS.getString("homebranch");
				String subLocation   = itemChangeRS.getString("ccode");
				String shelfLocation = itemChangeRS.getString("location");
//				int    restricted    = itemChangeRS.getInt("restricted");
				int    damaged       = itemChangeRS.getInt("damaged");
				int    withdrawn     = itemChangeRS.getInt("withdrawn");
				String itemlost      = itemChangeRS.getString("itemlost");
				String notforloan    = itemChangeRS.getString("notforloan");
				String dueDate        = "";
				try {
					dueDate = itemChangeRS.getString("onloan");
				}catch (SQLException e){
					logger.info("Invalid onloan value for bib " + bibNumber + " item " + itemNumber);
				}

				ItemChangeInfo changeInfo = new ItemChangeInfo();
				changeInfo.setItemId(itemNumber);
				changeInfo.setLocation(location);
				changeInfo.setSubLocation(subLocation);
				changeInfo.setShelfLocation(shelfLocation);
				changeInfo.setDamaged(damaged);
				changeInfo.setItemLost(itemlost);
				changeInfo.setWithdrawn(withdrawn);
//				changeInfo.setRestricted(restricted);
				changeInfo.setNotForLoan(notforloan);
				changeInfo.setDueDate(dueDate);

				ArrayList<ItemChangeInfo> itemChanges;
				if (changedBibs.containsKey(bibNumber)) {
					itemChanges = changedBibs.get(bibNumber);
				}else{
					itemChanges = new ArrayList<>();
					changedBibs.put(bibNumber, itemChanges);
				}
				itemChanges.add(changeInfo);
			}

			pikaConn.setAutoCommit(false);
			logger.info("A total of " + changedBibs.size() + " bibs to update");

			// Update MARC then mark owning grouped work as changed
			PreparedStatement markGroupedWorkForBibAsChangedStmt = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'ils' and identifier = ?)");
			int               numUpdates                         = 0;
			for (String curBibId : changedBibs.keySet()){
				//Update the marc record
				updateMarc(curBibId, changedBibs.get(curBibId));
				//Update the database
				try {
					markGroupedWorkForBibAsChangedStmt.setLong(1, updateTime);
					markGroupedWorkForBibAsChangedStmt.setString(2, curBibId);
					markGroupedWorkForBibAsChangedStmt.executeUpdate();

					numUpdates++;
					if (numUpdates % 50 == 0){
						pikaConn.commit();
					}
				}catch (SQLException e){
					logger.error("Could not mark that " + curBibId + " was changed due to error ", e);
					success = false;
				}
			}
			logger.info("A total of " + numUpdates + " bibs were marked for reindexing");


			//Turn auto commit back on
			pikaConn.commit();
			pikaConn.setAutoCommit(true);

		} catch (Exception e){
			logger.error("Error loading changed records from Koha database", e);
			success = false;
			System.exit(1);
		}
		logger.info("Finished loading changed records from Koha database");
		return success;
	}

	private static boolean getDeletedItemsFromDatabase(/*Ini ini,*/ Connection pikaConn, Connection kohaConn){
		boolean success = true;
		try {
			logger.info("Starting to load deleted items from Koha using the database connection");

//			PreparedStatement getDeletedItemsFromKohaStmt = kohaConn.prepareStatement("select itemnumber, biblionumber from items where timestamp >= ? LIMIT 0, ?");
			PreparedStatement getDeletedItemsFromKohaStmt = kohaConn.prepareStatement("select itemnumber, biblionumber from deleteditems where timestamp >= ?");
			getDeletedItemsFromKohaStmt.setTimestamp(1, new Timestamp(lastKohaExtractTime * 1000));
			ResultSet                          deletedItemsRS      = getDeletedItemsFromKohaStmt.executeQuery();
			HashMap<String, ArrayList<String>> bibsForDeletedItems = new HashMap<>();
			int numItemsToDelete = 0;
			while (deletedItemsRS.next()) {
				String itemNumber = deletedItemsRS.getString("itemnumber");
				String bibNumber  = deletedItemsRS.getString("biblionumber");
				if (itemNumber != null && !itemNumber.isEmpty() && bibNumber != null && !bibNumber.isEmpty()) {
					ArrayList<String> deletedIds;

					if (bibsForDeletedItems.containsKey(bibNumber)) {
						deletedIds = bibsForDeletedItems.get(bibNumber);
					} else {
						deletedIds = new ArrayList<>();
						bibsForDeletedItems.put(bibNumber, deletedIds);
					}
					deletedIds.add(itemNumber);
					numItemsToDelete++;

				} else {
					logger.warn("Received results from koha database with an empty item or bib number");
				}
			}

			pikaConn.setAutoCommit(false);
			logger.info("A total of " + numItemsToDelete + " items from " + bibsForDeletedItems.size() + " records to delete");

			// Update MARC then mark owning grouped work as changed
			PreparedStatement markGroupedWorkForBibAsChangedStmt = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'ils' and identifier = ?)");
			int               numUpdates                         = 0;
			for (String curBibId : bibsForDeletedItems.keySet()){

				// Update the marc record
				deleteItemsFromMarc(curBibId, bibsForDeletedItems.get(curBibId));

				//Update the database
				try {
					markGroupedWorkForBibAsChangedStmt.setLong(1, updateTime);
					markGroupedWorkForBibAsChangedStmt.setString(2, curBibId);
					markGroupedWorkForBibAsChangedStmt.executeUpdate();

					numUpdates++;
					if (numUpdates % 50 == 0){
						pikaConn.commit();
					}
				}catch (SQLException e){
					logger.error("Could not mark that " + curBibId + " was changed due to error ", e);
					success = false;
				}
			}
			logger.info("A total of " + numUpdates + " bibs were marked for reindexing");


		} catch (Exception e){
			logger.error("Error loading deleted items from Koha database", e);
			success = false;
			System.exit(1);
		}
		return success;

	}

	private static void updateMarc(String curBibId, ArrayList<ItemChangeInfo> itemChangeInfo) {
		//Load the existing marc record from file
		try {
			Record marcRecord = loadMarc(curBibId);
			if (marcRecord != null) {

				//Loop through all item fields to see what has changed
				ArrayList<ItemChangeInfo> remainingItemsToUpdate = new ArrayList<>(itemChangeInfo);
				List<VariableField> itemFields = marcRecord.getVariableFields(indexingProfile.itemTag);
				for (VariableField itemFieldVar : itemFields) {
					DataField itemField = (DataField) itemFieldVar;
					if (itemField.getSubfield(indexingProfile.itemRecordNumberSubfield) != null) {
						String itemRecordNumber = itemField.getSubfield(indexingProfile.itemRecordNumberSubfield).getData();
						//Update the items
						for (ItemChangeInfo curItem : itemChangeInfo) {
							//Find the correct item
							if (itemRecordNumber.equals(curItem.getItemId())) {
								setBooleanSubfield(itemField, curItem.getWithdrawn(), withdrawnSubfield);
								setBooleanSubfield(itemField, curItem.getDamaged(), damagedSubfield);
//									setBooleanSubfield(itemField, curItem.getRestricted(), restrictedSubfield);
								setSubfieldValue(itemField, lostSubfield, curItem.getItemLost());
								setSubfieldValue(itemField, locationSubfield, curItem.getLocation());
								setSubfieldValue(itemField, subLocationSubfield, curItem.getSubLocation());
								setSubfieldValue(itemField, shelflocationSubfield, curItem.getShelfLocation());
								setSubfieldValue(itemField, dueDateSubfield, curItem.getDueDate());
								setSubfieldValue(itemField, notforloanSubfield, curItem.getNotForLoan());
								remainingItemsToUpdate.remove(curItem);
							}
						}
						if (remainingItemsToUpdate.size() == 0){
							break;
						}
					}
				}
				if (remainingItemsToUpdate.size() > 0 ){
					StringBuilder ItemIds = new StringBuilder();
					for (ItemChangeInfo curItem : remainingItemsToUpdate){
						ItemIds.append(curItem.getItemId()).append(", ");
					}
					logger.info("Items " + ItemIds.toString() + " were not updated for record " +curBibId);
					// Possibly new items from today
				}

				//Write the new marc record
				saveMarc(marcRecord, curBibId);
			}
		}catch (Exception e){
			logger.error("Error updating marc record for bib " + curBibId, e);
		}
	}

	private static void deleteItemsFromMarc(String curBibId, ArrayList<String> deletedIDs) {
		// Load the existing marc record from file
		try {
			Record marcRecord = loadMarc(curBibId);
			if (marcRecord != null) {

				// Loop through all item fields to find the deleted items
				boolean isRecordChanged = false;
				List<VariableField> itemFields = marcRecord.getVariableFields(indexingProfile.itemTag);
				ArrayList<String> remainingItemsToDelete = new ArrayList<>(deletedIDs);
				for (VariableField itemFieldVar : itemFields) {
					DataField itemField = (DataField) itemFieldVar;
					if (itemField.getSubfield(indexingProfile.itemRecordNumberSubfield) != null) {
						String itemRecordNumber = itemField.getSubfield(indexingProfile.itemRecordNumberSubfield).getData().trim();
						// Update the items
						for (String curItemID : deletedIDs) {
							// Find the correct item
							if (itemRecordNumber.equals(curItemID)) {
								marcRecord.removeVariableField(itemFieldVar);
								remainingItemsToDelete.remove(curItemID);
								isRecordChanged = true;
							}
						}
						if (remainingItemsToDelete.size() == 0){
							break;
						}
					}
				}
				if (remainingItemsToDelete.size() > 0){
					logger.info("Items " + String.join(", ", deletedIDs) +  " were not found for deletion on bib " + curBibId );
					// This may be an unneeded check, as in we have already deleted the items in a round of extraction before this one.
				}

				// Write the new marc record
				if (isRecordChanged) {
					saveMarc(marcRecord, curBibId);
				}
			}
		} catch (Exception e){
			logger.error("Error updating marc record for bib " + curBibId, e);
		}
	}

	private static void setSubfieldValue(DataField itemField, char subfield, String newValue) {
		if (newValue == null){
			if (itemField.getSubfield(subfield) != null) itemField.removeSubfield(itemField.getSubfield(subfield));
		}else{

			if (itemField.getSubfield(subfield) != null) {
				itemField.getSubfield(subfield).setData(newValue);
			}else{
				itemField.addSubfield(new SubfieldImpl(subfield, newValue));
			}
		}
	}

	private static void setBooleanSubfield(DataField itemField, int flagValue, char withdrawnSubfieldChar) {
		if (flagValue == 0){
			Subfield withDrawnSubfield = itemField.getSubfield(withdrawnSubfieldChar);
			if (withDrawnSubfield != null){
				itemField.removeSubfield(withDrawnSubfield);
			}
		}else{
			Subfield withDrawnSubfield = itemField.getSubfield(withdrawnSubfieldChar);
			if (withDrawnSubfield == null){
				itemField.addSubfield(new SubfieldImpl(withdrawnSubfieldChar, "1"));
			}else{
				withDrawnSubfield.setData("1");
			}
		}
	}

	private static Record loadMarc(String curBibId) {
		//Load the existing marc record from file
		try {
			logger.debug("Loading MARC for " + curBibId);
			File marcFile = indexingProfile.getFileForIlsRecord(curBibId);
			if (marcFile.exists()) {
				FileInputStream inputStream = new FileInputStream(marcFile);
				MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
				if (marcReader.hasNext()) {
					Record marcRecord = marcReader.next();
					inputStream.close();
					return marcRecord;
				} else {
					logger.info("Could not read marc record for " + curBibId + ". The bib was empty");
				}
			}else{
				logger.debug("Marc Record does not exist for " + curBibId + " (" + marcFile.getAbsolutePath() + "). It is not part of the main extract yet.");
			}
		}catch (Exception e){
			logger.error("Error updating marc record for bib " + curBibId, e);
		}
		return null;
	}

	private static void saveMarc(Record marcObject, String curBibId) {
		// Write the new marc record
		File marcFile = indexingProfile.getFileForIlsRecord(curBibId);

		MarcWriter writer;
		try {
			writer = new MarcStreamWriter(new FileOutputStream(marcFile, false));
			writer.write(marcObject);
			writer.close();
			logger.debug("  Created or saved updated MARC record to " + marcFile.getAbsolutePath());
		} catch (FileNotFoundException e) {
			logger.error("Error saving marc record for bib " + curBibId, e);
		}

	}

	private static Ini loadConfigFile(String filename){
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
			for (Profile.Section curSection : siteSpecificIni.values()){
				for (String curKey : curSection.keySet()){
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
				for (Profile.Section curSection : siteSpecificPwdIni.values()){
					for (String curKey : curSection.keySet()){
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
