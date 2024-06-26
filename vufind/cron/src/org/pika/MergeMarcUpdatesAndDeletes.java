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

import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.marc4j.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.sql.Connection;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * TODO: Use of this process is likely obsolete; use other MergeMarcUpdatesAndDeletes instead
 *
 * Merge a main marc export file with records from a delete and updates file
 * Pika
 * User: Mark Noble
 * Date: 12/31/2014
 * Time: 11:45 AM
 */
//public class MergeMarcUpdatesAndDeletes implements IProcessHandler {
//	private String recordNumberTag    = "";
//	private String recordNumberPrefix = "";
//
//	@Override
//	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
//		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Merge Marc Updates and Deletes");
//		processLog.saveToDatabase(pikaConn, logger);
//
//		//TODO: Load a list of indexing profiles that require merging
//		//TODO: SQL to load the indexing profiles
//
//		//TODO: Loop through the indexing profiles
//
//		//Get a list of marc records that need to be processed
//		//TODO: Read these from the indexing profile
//		String exportPath   = PikaConfigIni.getIniValue("Reindex", "marcPath");
//		String backupPath   = PikaConfigIni.getIniValue("Reindex", "marcBackupPath");
//		String marcEncoding = PikaConfigIni.getIniValue("Reindex", "marcEncoding");
////		recordNumberTag = PikaConfigIni.getIniValue("Reindex", "recordNumberTag");
//		recordNumberPrefix = PikaConfigIni.getIniValue("Reindex", "recordNumberPrefix");
//		File mainFile = null;
//
//		//TODO: Handle more than one set of updates and deletes (in order of creation date)
//		File deletesFile = null;
//		File updatesFile = null;
//
//		int numUpdates   = 0;
//		int numDeletions = 0;
//		int numAdditions = 0;
//
//		try {
//			File[] filesInExport = new File(exportPath).listFiles();
//			if (filesInExport != null) {
//				for (File exportFile : filesInExport) {
//					//TODO: Read the pattern for updates and deletes from the indexing profil
//					if (exportFile.getName().matches(".*updated.*")) {
//						updatesFile = exportFile;
//					} else if (exportFile.getName().matches(".*deleted.*")) {
//						deletesFile = exportFile;
//					} else if (exportFile.getName().endsWith("mrc") || exportFile.getName().endsWith("marc")) {
//						mainFile = exportFile;
//					}
//				}
//
//				if (mainFile == null) {
//					logger.error("Did not find file to merge into");
//					processLog.addNote("Did not find file to merge into");
//					processLog.saveToDatabase(pikaConn, logger);
//				} else {
//					boolean                 errorOccurred   = false;
//					HashMap<String, Record> recordsToUpdate = new HashMap<>();
//					//TODO: Handle multiple update files
//					if (updatesFile != null) {
//						try {
//							FileInputStream marcFileStream = new FileInputStream(updatesFile);
//							MarcReader      updatesReader  = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
//
//							//Read a list of records in the updates file
//							while (updatesReader.hasNext()) {
//								Record curBib   = updatesReader.next();
//								String recordId = getRecordIdFromMarcRecord(curBib);
//								recordsToUpdate.put(recordId, curBib);
//							}
//							marcFileStream.close();
//						} catch (Exception e) {
//							processLog.addNote("Error processing updates file. " + e.toString());
//							logger.error("Error loading records from updates fail", e);
//							processLog.saveToDatabase(pikaConn, logger);
//							errorOccurred = true;
//						}
//					}
//
//					HashSet<String> recordsToDelete = new HashSet<>();
//					//TODO: Handle multiple delete files
//					if (deletesFile != null) {
//						try {
//							FileInputStream marcFileStream = new FileInputStream(deletesFile);
//							MarcReader      deletesReader  = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
//
//							while (deletesReader.hasNext()) {
//								Record curBib   = deletesReader.next();
//								String recordId = getRecordIdFromMarcRecord(curBib);
//								recordsToDelete.add(recordId);
//							}
//
//							marcFileStream.close();
//						} catch (Exception e) {
//							processLog.incErrors();
//							processLog.addNote("Error processing deletes file. " + e.toString());
//							logger.error("Error processing deletes file", e);
//							errorOccurred = true;
//							processLog.saveToDatabase(pikaConn, logger);
//						}
//					}
//
//					String today          = new SimpleDateFormat("yyyyMMdd").format(new Date());
//					File   mergedFile     = new File(mainFile.getPath() + "." + today + ".merged");
//					int    numRecordsRead = 0;
//					String lastRecordId   = "";
//					Record curBib;
//					try {
//						FileInputStream marcFileStream = new FileInputStream(mainFile);
//						MarcReader      mainReader     = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
//
//						FileOutputStream marcOutputStream = new FileOutputStream(mergedFile);
//						MarcStreamWriter mainWriter       = new MarcStreamWriter(marcOutputStream);
//						while (mainReader.hasNext()) {
//							curBib = mainReader.next();
//							String recordId = getRecordIdFromMarcRecord(curBib);
//							numRecordsRead++;
//
//							if (recordsToUpdate.containsKey(recordId)) {
//								//Write the updated record
//								mainWriter.write(recordsToUpdate.get(recordId));
//								recordsToUpdate.remove(recordId);
//								numUpdates++;
//							} else if (!recordsToDelete.contains(recordId)) {
//								//Unless the record is marked for deletion, write it
//								mainWriter.write(curBib);
//								numDeletions++;
//							}
//
//							lastRecordId = recordId;
//						}
//
//						//Anything left in the updates file is new and should be added
//						for (Record newMarc : recordsToUpdate.values()) {
//							mainWriter.write(newMarc);
//							numAdditions++;
//						}
//						mainWriter.close();
//						marcFileStream.close();
//					} catch (Exception e) {
//						processLog.incErrors();
//						processLog.addNote("Error processing main file. " + e.toString());
//						processLog.addNote("Read " + numRecordsRead + " last record read was " + lastRecordId + e.toString());
//						logger.error("Error processing main file", e);
//						errorOccurred = true;
//						processLog.saveToDatabase(pikaConn, logger);
//					}
//
//					if (!new File(backupPath).exists()) {
//						if (!new File(backupPath).mkdirs()) {
//							processLog.incErrors();
//							processLog.addNote("Could not create backup path");
//							logger.error("Could not create backup path");
//							errorOccurred = true;
//							processLog.saveToDatabase(pikaConn, logger);
//						}
//					}
//					if (updatesFile != null && !errorOccurred) {
//						//Move to the backup directory
//						if (!updatesFile.renameTo(new File(backupPath + "/" + updatesFile.getName()))) {
//							processLog.incErrors();
//							processLog.addNote("Unable to move updates file to backup directory.");
//							logger.error("Unable to move updates file " + updatesFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + updatesFile.getName());
//							processLog.saveToDatabase(pikaConn, logger);
//							errorOccurred = true;
//						}
//					}
//
//					if (deletesFile != null && !errorOccurred) {
//						//Move to the backup directory
//						if (!deletesFile.renameTo(new File(backupPath + "/" + deletesFile.getName()))) {
//							processLog.incErrors();
//							processLog.addNote("Unable to move deletion file to backup directory.");
//							logger.error("Unable to move deletion file to backup directory");
//							processLog.saveToDatabase(pikaConn, logger);
//							errorOccurred = true;
//						}
//					}
//
//					if (!errorOccurred) {
//						String mainFilePath = mainFile.getPath();
//						if (!mainFile.renameTo(new File(backupPath + "/" + mainFile.getName()))) {
//							processLog.incErrors();
//							processLog.addNote("Unable to move main file " + mainFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + mainFile.getName());
//							logger.error("Unable to move main file " + mainFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + mainFile.getName());
//							processLog.saveToDatabase(pikaConn, logger);
//						} else {
//							//Move the merged file to the main file
//							if (!mergedFile.renameTo(new File(mainFilePath))) {
//								processLog.incErrors();
//								processLog.addNote("Unable to move merged file to main file.");
//								logger.error("Unable to move merged file to main file");
//								processLog.saveToDatabase(pikaConn, logger);
//							} else {
//								logger.debug("Added " + numAdditions);
//								logger.debug("Updated " + numUpdates);
//								logger.debug("Deleted " + numDeletions);
//
//								processLog.addNote("Added " + numAdditions);
//								processLog.addNote("Updated " + numUpdates);
//								processLog.addNote("Deleted " + numDeletions);
//								processLog.saveToDatabase(pikaConn, logger);
//							}
//						}
//					}
//				}
//			} else {
//				logger.error("No files were found in " + exportPath);
//			}
//		} catch (Exception e) {
//			processLog.incErrors();
//			processLog.addNote("Unknown error merging records. " + e.toString());
//			logger.error("Unknown error merging records", e);
//			processLog.saveToDatabase(pikaConn, logger);
//		}
//		processLog.setFinished();
//		processLog.saveToDatabase(pikaConn, logger);
//	}
//
//	private String getRecordIdFromMarcRecord(Record marcRecord) {
//		List<DataField> recordIdField = getDataFields(marcRecord, recordNumberTag);
//		//Make sure we only get one ils identifier
//		for (DataField curRecordField : recordIdField) {
//			Subfield subfieldA = curRecordField.getSubfield('a');
//			if (subfieldA != null && (recordNumberPrefix.length() == 0 || subfieldA.getData().length() > recordNumberPrefix.length())) {
//				if (curRecordField.getSubfield('a').getData().substring(0, recordNumberPrefix.length()).equals(recordNumberPrefix)) {
//					return curRecordField.getSubfield('a').getData();
//				}
//			}
//		}
//		return null;
//	}
//
//	private List<DataField> getDataFields(Record marcRecord, String tag) {
//		List            variableFields       = marcRecord.getVariableFields(tag);
//		List<DataField> variableFieldsReturn = new ArrayList<>();
//		for (Object variableField : variableFields) {
//			if (variableField instanceof DataField) {
//				variableFieldsReturn.add((DataField) variableField);
//			}
//		}
//		return variableFieldsReturn;
//	}
//}
