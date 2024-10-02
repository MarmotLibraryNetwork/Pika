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
import org.marc4j.MarcException;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.MarcStreamWriter;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

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
	private static Logger              logger;
	private static String              serverName; //Pika instance name
	private static IndexingProfile     indexingProfile;
	private static Connection          pikaConn;
	private static MarcRecordGrouper   recordGroupingProcessor;
	private static PikaSystemVariables systemVariables;
	private static String              sysVarName = "holdsCountFileLastModTime";
	private static boolean             hadErrors  = false;

	private static PreparedStatement updateExtractInfoStatement;
	private static long holdFileLastModified;

	public static void main(String[] args) {
		serverName = args[0];

		Date startTime = new Date();
		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j2.horizon_export.xml");
		if (log4jFile.exists()) {
			System.setProperty("log4j.pikaSiteName", serverName);
			System.setProperty("log4j.configurationFile", log4jFile.getAbsolutePath());
			logger = LogManager.getLogger();
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile);
			System.exit(1);
		}
		logger.info("{}: Starting Horizon Export", startTime);

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
			pikaConn                   = DriverManager.getConnection(databaseConnectionInfo);
			updateExtractInfoStatement = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined
		} catch (Exception e) {
			System.out.println("Error connecting to pika database " + e);
			System.exit(1);
		}

		indexingProfile = IndexingProfile.loadIndexingProfile(pikaConn, profileToLoad, logger);

		//Setup other systems we will use
		initializeRecordGrouper(pikaConn);

		// Start up systemVariables
		systemVariables = new PikaSystemVariables(logger, pikaConn);

		//Check for a new holds file
		processNewHoldsFile(pikaConn);

		//Look for any exports from Horizon that have not been processed
		processChangesFromHorizon();


		//Cleanup
		if (pikaConn != null) {
			try {
				//Close the connection
				pikaConn.close();
			} catch (Exception e) {
				System.out.println("Error closing connection: " + e);
				logger.error("Error closing connection", e);
			}
		}

		Date currentTime = new Date();
		logger.info("{}: Finished Horizon Export", currentTime);
	}

	/**
	 * Check the marc folder to see if the holds files have been updated since the last export time.
	 * If so, load a count of holds per bib and then update the database.
	 *
	 * @param pikaConn       the connection to the database
	 */
	private static void processNewHoldsFile(Connection pikaConn) {
		HashMap<Long, Integer> holdsByBib = new HashMap<>();
		boolean                completed  = false;
		boolean                writeHolds = false;
		File                   holdFile   = new File(indexingProfile.marcPath + "/holdsCount.csv");
		if (holdFile.exists()) {
			Long lastProcessedFileModTime = systemVariables.getLongValuedVariable(sysVarName);
			holdFileLastModified = holdFile.lastModified();
			if (lastProcessedFileModTime == null || holdFileLastModified > lastProcessedFileModTime) {
				long now = new Date().getTime();
				if (now - holdFileLastModified > 2 * 24 * 60 * 60 * 1000) {
					logger.warn("Holds File was last written more than 2 days ago");
				} else {
					writeHolds = true;
					long lastCatalogIdRead = 0L;
					try (
									BufferedReader reader = new BufferedReader(new FileReader(holdFile));
					){
						// Ignore first three lines
						reader.readLine();
						reader.readLine();
						reader.readLine();
						String line = reader.readLine();
						while (line != null) {
							int firstComma = line.indexOf(',');
							if (firstComma > 0) {
								try {
									String catalogIdStr = line.substring(0, firstComma).trim();
									String countStr     = line.substring(firstComma + 1).trim();
									long   catalogId    = Long.parseLong(catalogIdStr);
									int    count        = Integer.parseInt(countStr);
									holdsByBib.put(catalogId, count);
									lastCatalogIdRead = catalogId;
								} catch (NumberFormatException e) {
									logger.error("Error parsing holds count file line : {}", line, e);
								}
							}
							line = reader.readLine();
						}
					} catch (Exception e) {
						logger.error("Error reading holds file ", e);
						hadErrors = true;
					}
					logger.info("Read {} bibs with holds, lastCatalogIdRead = {}", holdsByBib.size(), lastCatalogIdRead);
				}
			}
		} else {
			logger.warn("No holds file found at " + indexingProfile.marcPath + "/holdsCount.csv");
			hadErrors = true;
		}

		//Now that we've counted all the holds, update the database
		if (!hadErrors && writeHolds) {
			try {
				pikaConn.setAutoCommit(false);
				pikaConn.prepareCall("TRUNCATE ils_hold_summary").executeUpdate();  // Truncate so that id value doesn't grow beyond column size
				logger.info("Removed existing hold counts");
				PreparedStatement updateHoldsStmt = pikaConn.prepareStatement("INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)");
				for (long ilsId : holdsByBib.keySet()) {
					Integer holdsCount = holdsByBib.get(ilsId);
					updateHoldsStmt.setLong(1, ilsId);
					updateHoldsStmt.setInt(2, holdsCount);
					int numUpdates = updateHoldsStmt.executeUpdate();
					if (numUpdates != 1) {
						logger.warn("Hold was not inserted {}, {}", ilsId, holdsCount);
					}
				}
				pikaConn.commit();
				pikaConn.setAutoCommit(true);
				logger.info("Finished adding new holds to the database");
				completed = true;
				} catch (Exception e) {
				logger.error("Error updating holds database", e);
				hadErrors = true;
			}
		}
		if (completed){
			systemVariables.setVariable(sysVarName, holdFileLastModified);
		}
	}


	/**
	 * Processes the exports from Horizon.  If a record appears in multiple extracts,
	 * we just process the last extract.
	 *
	 * Expects extracts to already be copied to the server and to be in the
	 * /data/pika/{sitename}/marc_updates directory
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
//				MarcReader updatesReader = new MarcPermissiveStreamReader(marcFileStream, true, true, "UTF8");
				MarcReader updatesReader = new MarcPermissiveStreamReader(marcFileStream, true, true);
				// Input file from partial export files is likely MARC8 rather than UTF8
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
				logger.info("Deleting " + file.getName() + " since it has been processed");
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
//				logger.info("New record " + recordId + " found in partial export wasn't written and not processed.");
//				return true;
				logger.info("New record " + recordId + " found in partial export and will be grouped.");
				// The file exist check above should be redundant now with the record grouping below.
			}

			try (FileOutputStream marcOutputStream = new FileOutputStream(marcFile)) {
				MarcStreamWriter updateWriter = new MarcStreamWriter(marcOutputStream, "UTF-8", true);
				// Save as UTF8 encoded record
				updateWriter.write(recordToUpdate);
				updateWriter.close();
				//Update the database to indicate it has changed

				// Set up the grouped work for the record.  This will take care of either adding it to the proper grouped work
				// or creating a new grouped work
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
		List<VariableField> variableFields = marcRecord.getVariableFields(tag);
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
