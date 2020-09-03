/*
 * Copyright (C) 2020  Marmot Library Network
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

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.marc4j.MarcException;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.MarcStreamWriter;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.*;
import java.sql.*;
import java.util.*;
import java.util.Date;

/**
 * Export data from Horizon and update Pika copy MARC records and the database
 * Pika
 * User: Mark Noble
 * Date: 10/18/2015
 * Time: 10:18 PM
 */
public class HorizonExportMain {
	private static Logger            logger = Logger.getLogger(HorizonExportMain.class);
	private static String            serverName; //Pika instance name
	private static IndexingProfile   indexingProfile;
	private static Connection        pikaConn;
	private static MarcRecordGrouper recordGroupingProcessor;

	private static PreparedStatement updateExtractInfoStatement;

	public static void main(String[] args) {
		serverName = args[0];

		Date startTime = new Date();
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.horizon_export.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.toString());
		}
		logger.info(startTime.toString() + ": Starting Horizon Export");

		// Read the base INI file to get information about the server (current directory/conf/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		String profileToLoad = "ils";
		if (args.length > 1) {
			profileToLoad = args[1];
		}

		//Connect to the pika database
		pikaConn = null;
		try {
			String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			pikaConn                           = DriverManager.getConnection(databaseConnectionInfo);
			updateExtractInfoStatement         = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined
		} catch (Exception e) {
			System.out.println("Error connecting to pika database " + e.toString());
			System.exit(1);
		}

		indexingProfile = IndexingProfile.loadIndexingProfile(pikaConn, profileToLoad, logger);

		//Setup other systems we will use
		initializeRecordGrouper(pikaConn);

		//Look for any exports from Horizon that have not been processed
		processChangesFromHorizon();

		//TODO: Get a list of records with holds on them?

		//Cleanup
		if (pikaConn != null) {
			try {
				//Close the connection
				pikaConn.close();
			} catch (Exception e) {
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}

		Date currentTime = new Date();
		logger.info(currentTime.toString() + ": Finished Horizon Export");
	}

	/**
	 * Processes the exports from Horizon.  If a record appears in multiple extracts,
	 * we just process the last extract.
	 *
	 * Expects extracts to already be copied to the server and to be in the
	 * /data/pika-plus/{sitename}/marc_updates directory
	 */
	private static void processChangesFromHorizon() {
		File         fullExportFile      = new File(indexingProfile.marcPath + "/fullexport.mrc");
		File         fullExportDirectory = fullExportFile.getParentFile();
		File         sitesDirectory      = fullExportDirectory.getParentFile();
		final String exportPath          = sitesDirectory.getAbsolutePath() + "/marc_updates";
		File         exportFile          = new File(exportPath);
		if (!exportFile.exists()) {
			logger.error("Export path " + exportPath + " does not exist");
			return;
		}
		File[] files = exportFile.listFiles((dir, name) -> name.matches(".*\\.mrc"));
		if (files == null) {
			//Nothing to process
			return;
		}
		TreeMap<String, File> filesToProcess = new TreeMap<>();
		//Make sure files are sorted in order.  We can do a simple sort since they have the timestamp in the filename
		for (File file : files) {
			filesToProcess.put(file.getName(), file);
		}
		//A list of records to be updated.
		HashMap<String, Record> recordsToUpdate = new HashMap<>();
		Set<String>             filenames       = filesToProcess.keySet();
		String[]                filenamesArray  = filenames.toArray(new String[filenames.size()]);
		for (String fileName : filenamesArray) {
			File file = filesToProcess.get(fileName);
			logger.debug("Processing " + file.getName());
			try (
							FileInputStream marcFileStream = new FileInputStream(file)
			) {
				//Record Grouping always writes individual MARC records as UTF8
				MarcReader updatesReader = new MarcPermissiveStreamReader(marcFileStream, true, true, "UTF8");
				while (updatesReader.hasNext()) {
					try {
						Record curBib   = updatesReader.next();
						String recordId = getRecordIdFromMarcRecord(curBib);
						recordsToUpdate.put(recordId, curBib);
					} catch (MarcException me) {
						logger.info("File " + file + " has not been fully written", me);
						filesToProcess.remove(fileName);
						break;
					}
				}
			} catch (EOFException e) {
				logger.error("File " + file + " has not been fully written", e);
				filesToProcess.remove(fileName);
			} catch (Exception e) {
				logger.error("Unable to read file " + file + " not processing", e);
				filesToProcess.remove(fileName);
			}
		}
		//Now that we have all the records, merge them and update the database.
		boolean errorUpdatingDatabase = false;
		int     numUpdates            = 0;
		try {
			pikaConn.setAutoCommit(false);
			long updateTime = new Date().getTime() / 1000;
			for (String recordId : recordsToUpdate.keySet()) {
				Record recordToUpdate = recordsToUpdate.get(recordId);
				if (!updateMarc(recordId, recordToUpdate, updateTime)) {
					logger.error("Error updating marc record " + recordId);
					errorUpdatingDatabase = true;
				}
				numUpdates++;
				if (numUpdates % 50 == 0) {
					pikaConn.commit();
				}
			}
		} catch (Exception e) {
			logger.error("Error updating marc records");
			errorUpdatingDatabase = true;
		} finally {
			try {
				//Turn auto commit back on
				pikaConn.commit();
				pikaConn.setAutoCommit(true);
			} catch (Exception e) {
				logger.error("Error committing changes");
			}
		}

		logger.info("Updated a total of " + numUpdates + " from " + filesToProcess.size() + " files");

		if (!errorUpdatingDatabase) {
			//Finally, move all files we have processed to another folder (or delete) so we don't process them again
			for (File file : filesToProcess.values()) {
				logger.debug("Deleting " + file.getName() + " since it has been processed");
				if (!file.delete()) {
					logger.warn("Could not delete " + file.getName());
				}
			}
			logger.info("Deleted " + filesToProcess.size() + " files that were processed successfully.");
		} else {
			logger.error("There were errors updating the database, not clearing the files so they will be processed next time");
		}
	}

	private static boolean updateMarc(String recordId, Record recordToUpdate, long updateTime) {
		//Replace the MARC record in the individual marc records
		try {
			File marcFile = indexingProfile.getFileForIlsRecord(recordId);
			if (!marcFile.exists()) {
				//This is a new record, we can just skip it for now.
				return true;
			}

			try (FileOutputStream marcOutputStream = new FileOutputStream(marcFile)) {
				MarcStreamWriter updateWriter = new MarcStreamWriter(marcOutputStream);
				updateWriter.setAllowOversizeEntry(true);
				updateWriter.write(recordToUpdate);
				updateWriter.close();
				//Update the database to indicate it has changed

				//Setup the grouped work for the record.  This will take care of either adding it to the proper grouped work
				//or creating a new grouped work
				if (!recordGroupingProcessor.processMarcRecord(recordToUpdate, true)) {
					logger.warn(recordId + " was suppressed");
				} else {
					logger.debug("Finished record grouping for " + recordId);
				}

				try {
					//Update last extract info
					updateExtractInfoStatement.setLong(1, indexingProfile.id);
					updateExtractInfoStatement.setString(2, recordId);
					updateExtractInfoStatement.setLong(3, updateTime);
					updateExtractInfoStatement.executeUpdate();
				} catch (SQLException e) {
					logger.error("Could not mark that " + recordId + " was extracted due to error ", e);
					return false;
				}
				return true;
			}
		} catch (Exception e) {
			logger.error("Error saving changed MARC record");
			return false;
		}

	}

	private static String getRecordIdFromMarcRecord(Record marcRecord) {
		List<DataField> recordIdField = getDataFields(marcRecord, indexingProfile.recordNumberTag);
		//Make sure we only get one ils identifier
		for (DataField curRecordField : recordIdField) {
			Subfield subfieldA = curRecordField.getSubfield('a');
			if (subfieldA != null) {
				return curRecordField.getSubfield('a').getData();
			}
		}
		return null;
	}

	private static List<DataField> getDataFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}

	private static void initializeRecordGrouper(Connection pikaConn) {
		recordGroupingProcessor = new MarcRecordGrouper(pikaConn, indexingProfile, logger);
	}

}
