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

package org.marmot;

import au.com.bytecode.opencsv.CSVReader;
import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.MarcStreamWriter;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.*;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * Merge a main marc export file with records from deletes and updates file
 * Pika
 * User: Mark Noble
 * Date: 12/31/2014
 * Time: 11:45 AM
 * <p>
 * Updated by Ayub Jabedo
 * Date: 8/27/2016
 */
class MergeMarcUpdatesAndDeletes {
	private String recordNumberTag      = "";
	private String recordNumberSubfield = "";
	private Logger logger;

	boolean startProcess(Ini configIni, Logger logger) {
		this.logger = logger;

		String mainFilePath   = configIni.get("MergeUpdate", "marcPath");
		String backupPath     = configIni.get("MergeUpdate", "backupPath");
		String marcEncoding   = configIni.get("MergeUpdate", "marcEncoding");
		recordNumberTag       = configIni.get("MergeUpdate", "recordNumberTag");
		recordNumberSubfield  = configIni.get("MergeUpdate", "recordNumberSubfield");
		String changesPath    = configIni.get("MergeUpdate", "changesPath");
		String deleteFilePath = configIni.get("MergeUpdate", "deleteFilePath");

		int     numUpdates;
		int     numDeletions;
		int     numAdditions;
		boolean errorOccurred = false;

		try {

			//Try to get the main file
			File mainFile = new File(mainFilePath);

			HashSet<File> deleteFiles = new HashSet<>();
			HashSet<File> updateFiles = new HashSet<>();

			if (mainFile.isDirectory()) {
				File[] files = new File(mainFilePath).listFiles();
				mainFile = null;
				if (files != null) {
					for (File file : files) {
						if (isValidMarcFile(file)) {
							if (mainFile == null) {
								mainFile = file;
							} else {
								logger.error("More than one update file was found");
								System.exit(1);
							}
						}
					}
				}
			} else {
				if (!isValidMarcFile(mainFile)) {
					mainFile = null;
				}
			}

			if (mainFile != null) {
				//Load changes from changes path if necessary
				if (changesPath != null && changesPath.length() > 0) {
					File changesFile = new File(changesPath);
					if (!changesFile.exists()) {
						logger.error("The changes path " + changesPath + " does not exist");
						return false;
					}
					File[] files;
					if (changesFile.isDirectory()) {
						files = changesFile.listFiles();
					} else {
						files = new File[1];
						files[0] = changesFile;
					}
					if (files != null && files.length > 0) {
						for (File file : files) {
							if (!file.equals(mainFile)) {
								if (file.isDirectory()) {
									File[] filesInDir = file.listFiles(); // single folder, non recursive
									if (filesInDir != null) {
										validateAddsUpdatesFiles(updateFiles, filesInDir);
									}
								} else {
									validateAddsUpdatesFile(updateFiles, file);
								}
							}
						}
					}
				}

				//Load files to delete if necessary
				if (deleteFilePath != null && deleteFilePath.length() > 0){
					File deleteFile = new File(deleteFilePath);
					if (deleteFile.exists()) {
						if (deleteFile.isDirectory()) {
							File[] filesInDir = deleteFile.listFiles(); // single folder, non recursive
							if (filesInDir != null) {
								for (File file : filesInDir) {
									if (isDeleteFile(file)) {
										deleteFiles.add(file);
									}
								}
							}
						} else {
							if (isValidMarcFile(deleteFile) || deleteFile.getName().endsWith("csv")) {
								deleteFiles.add(deleteFile);
							}
						}
					}
				}

				//Display what we are going to update and delete from
				if (logger.isInfoEnabled()) {
					if (!updateFiles.isEmpty()) {
						logger.info("Files to update from:");
						for (File file : updateFiles)
							logger.info(file.getAbsolutePath());
					}
					if (!deleteFiles.isEmpty()) {
						logger.info("Files to delete from:");
						for (File file : deleteFiles) {
							logger.info(file.getAbsolutePath());
						}
					}
				}

				//Process the files
				if ((deleteFiles.size() + updateFiles.size()) > 0) {
					HashMap<String, Record> recordsToUpdate = new HashMap<>();
					if (logger.isInfoEnabled()) {
						if (updateFiles.isEmpty()) {
							logger.info("No records to update.....");
						} else {
							logger.info("Processing records to update. Please wait.....");
						}
					}

					for (File updateFile : updateFiles) {
						try {
							processMarcFile(recordsToUpdate, updateFile);
							if (logger.isInfoEnabled()) {
								for (Map.Entry<String, Record> entry : recordsToUpdate.entrySet()) {
									logger.info(entry.getKey());
								}
							}
						} catch (Exception e) {
							logger.error("Error loading records from update file: " + updateFile.getAbsolutePath(), e);
						}
					}

					HashSet<String> recordsToDelete = new HashSet<>();
					if (deleteFiles.isEmpty()) {
						logger.info("No records to delete.....");
					} else {
						logger.info("Processing records to delete Please wait....");
					}

					for (File deleteFile : deleteFiles) {
						try {
							if (isValidMarcFile(deleteFile)) {
								processMarcFile(recordsToDelete, deleteFile);
							} else if (deleteFile.getName().endsWith("csv")) {
								processCsvFile(recordsToDelete, deleteFile);
							}
							if (logger.isInfoEnabled()) {
								for (String entry : recordsToDelete) {
									logger.info(entry);
								}
							}

						} catch (Exception e) {
							logger.error("Error processing delete file: " + deleteFile.getAbsolutePath(), e);
						}
					}

					String today      = new SimpleDateFormat("yyyyMMdd").format(new Date());
					File   mergedFile = new File(mainFile.getPath() + "." + today + ".merged");


					logger.info("Merge started... pls wait");
					numDeletions = 0;
					numAdditions = 0;
					numUpdates   = 0;
					int    numRecords   = 0;
					String lastRecordId = "";
					try {
						try (FileInputStream marcFileStream = new FileInputStream(mainFile)) {
							MarcReader       mainReader = new MarcPermissiveStreamReader(marcFileStream, true, true);
							Record           curBib;
							MarcStreamWriter mainWriter;
							try (FileOutputStream marcOutputStream = new FileOutputStream(mergedFile)) {
								mainWriter = new MarcStreamWriter(marcOutputStream, marcEncoding);

								while (mainReader.hasNext()) {

									curBib = mainReader.next();
									numRecords++;
									String recordId = getRecordIdFromMarcRecord(curBib);
									if (recordId == null)
										continue;

									if (recordsToUpdate.containsKey(recordId)) {
										//Write the updated record
										mainWriter.write(recordsToUpdate.get(recordId));
										recordsToUpdate.remove(recordId);
										numUpdates++;
										if (logger.isInfoEnabled()) {
											logger.info("Updating... " + recordId);
										}
									} else if (recordsToDelete.contains(recordId)) {
										numDeletions++;
										if (logger.isInfoEnabled()) {
											logger.info("Deleting..." + recordId);
										}
									} else if (!recordsToDelete.contains(recordId)) {
										//Unless the record is marked for deletion, write it
										mainWriter.write(curBib);
									}
									lastRecordId = recordId;
								}

								//Anything left in the updates file is new and should be added
								for (Record newMarc : recordsToUpdate.values()) {
									mainWriter.write(newMarc);
									logger.info("Adding...." + getRecordIdFromMarcRecord(newMarc));
									numAdditions++;
								}
								mainWriter.close();
							}
						}

						logger.info("Additions: " + numAdditions);
						logger.info("Deletions: " + numDeletions);
						logger.info("Updates: " + numUpdates);

						logger.info("Update SUCCESSFUL");
					} catch (Exception e) {
						logger.error("Error processing main file " + mainFile + " Last record processed was the " + numRecords + "-th record in the file. The last recordId processed was " + lastRecordId, e);
						errorOccurred = true;
					}

					//if no processing error occurred go ahead and backup original files
					if (!errorOccurred) {

						for (File updateFile : updateFiles) {
							//Move to the backup directory
							if (!backUpFile(updateFile, backupPath)) {
								logger.error("Unable to move update file " + updateFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + updateFile.getName());
							}
						}

						for (File deleteFile : deleteFiles) {
							//Move to the backup directory
							if (!backUpFile(deleteFile, backupPath)) {
								logger.error("Unable to move update file " + deleteFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + deleteFile.getName());
							}
						}

						//Move the original maim file into back up folder
						if (backUpFile(mainFile, backupPath)) {
							//rename the merged file to the main file
							if (!mergedFile.renameTo(new File(mainFile.getPath()))) {
								logger.error("Unable to rename merged (updated) file to main file. Manual renaming may be necessary!");
							}
						} else {
							logger.error("Unable to move main file " + mainFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + mainFile.getName());
						}
					}

				} else {
					logger.info("No update or delete files were found");
					errorOccurred = true;
				}
			} else {
				logger.info("Did not find file to merge into");
				logger.info("No master file was found in " + mainFilePath);
				errorOccurred = true;
			}
		} catch (Exception e) {
			logger.error("Unknown error merging records", e);
			errorOccurred = true;
		}

		if (errorOccurred) {
			//cleanup temp files
			//if failure occurs
			String today = new SimpleDateFormat("yyyyMMdd").format(new Date());
			File tmpMergedFile = new File(mainFilePath + "." + today + ".merged");
			if (tmpMergedFile.exists()) {
				if (!tmpMergedFile.delete()) {
					logger.error("Could not delete merged file");
				}
			}
		}

		return !errorOccurred;
	}

	private void validateAddsUpdatesFiles(HashSet<File> updateFiles, File[] files) {
		for (File file : files) {
			if (file.isFile()) {
				validateAddsUpdatesFile(updateFiles, file);
			}

			//TODO: Optionally recurse directories
			//if (file.isDirectory()) {

			//}
		}
	}

	private void validateAddsUpdatesFile(HashSet<File> updateFiles, File file) {
		if (isValidMarcFile(file)) {
			updateFiles.add(file);
		}
	}

	private boolean backUpFile(File fileToMove, String backupPath) {
		boolean fileBackedUp;
		try {
			fileBackedUp = archiveFile(fileToMove, backupPath);
		} catch (IOException e) {
			return false;
		}

		return fileBackedUp;
	}

	//returns true if file is safely moved to backup location
	private boolean archiveFile(File fileToMove, String backUpPath) throws IOException {

		boolean fileMoved = false;

		if (new File(backUpPath).exists() || new File(backUpPath).mkdirs()) {

			try {
				fileMoved = fileToMove.renameTo(new File(backUpPath));
			} catch (Exception e) {
				fileMoved = false;
			} finally {
				if (!fileMoved) {
					//try copying over then deleting the original
					Util.copyFileNoOverwrite(fileToMove, new File(backUpPath));
					if (fileToMove.delete()) {
						fileMoved = true;
					}else{
						logger.warn("Could not remove file " + fileToMove.getAbsolutePath() + " after copying");
					}
				}
			}
		} else {
			throw new IOException("BackUp folder is non existent or cannot be created!");
		}


		return fileMoved;
	}

	/**
	 *
	 * @param file            The file to check
	 * @return                Whether or not the file contains deletes
	 */
	private static boolean isDeleteFile(File file) {
		return (isValidMarcFile(file) || file.getName().endsWith("csv"));
	}

	private static boolean isValidMarcFile(File file) {
		return file.getName().endsWith("mrc") || file.getName().endsWith("marc");
	}


	private static void processCsvFile(HashSet<String> recordsToDelete, File deleteFile) throws IOException {
		try (CSVReader reader = new CSVReader(new FileReader(deleteFile.getPath()))) {

			String[] nextLine;
			while ((nextLine = reader.readNext()) != null) {
				recordsToDelete.add(nextLine[0]);
			}

		}
	}

	private void processMarcFile(HashSet<String> recordsToDelete, File deleteFile) throws Exception {
		FileInputStream marcFileStream = new FileInputStream(deleteFile);

		MarcReader deletesReader = new MarcPermissiveStreamReader(marcFileStream, true, true);
		while (deletesReader.hasNext()) {
			Record curBib = deletesReader.next();
			String recordId = getRecordIdFromMarcRecord(curBib);
			if (recordId != null)
				recordsToDelete.add(recordId);
		}

		marcFileStream.close();


	}

	private void processMarcFile(HashMap<String, Record> recordsToUpdate, File updateFile) throws Exception {
		FileInputStream marcFileStream = new FileInputStream(updateFile);
		MarcReader updatesReader = new MarcPermissiveStreamReader(marcFileStream, true, true);

		//Read a list of records in the updates file
		while (updatesReader.hasNext()) {
			Record curBib = updatesReader.next();
			String recordId = getRecordIdFromMarcRecord(curBib);

			if (recordsToUpdate != null && recordId != null) {
				recordsToUpdate.put(recordId, curBib);
			}
		}

			marcFileStream.close();
	}


	private String getRecordIdFromMarcRecord(Record marcRecord) {
		//if a subfield is found in ini file, then use it
		//no subfield check for record tag ids between 001 to 010
		int tagID = Integer.parseInt(recordNumberTag);
		if (tagID < 10) {
			List<ControlField> recordIdField = getControlFields(marcRecord, recordNumberTag);
			if (recordIdField != null && recordIdField.size() > 0) {
				return recordIdField.get(0).getData().trim();
			}
		} else {
			List<DataField> recordIdField1 = getDataFields(marcRecord, recordNumberTag);
			//Make sure we only get one ils identifier
			if (recordNumberSubfield != null && !recordNumberSubfield.isEmpty()) {
				char c = recordNumberSubfield.toCharArray()[0];
				for (DataField curRecordField : recordIdField1) {
					Subfield subfield = curRecordField.getSubfield(c);
					if (subfield != null) {
						return subfield.getData().trim();
					}
				}
			} else {
				logger.error("The recordNumberSubfield was not specified. This is required for the merging of this record set.");
				System.exit(1);
			}
		}

		return null;
	}

	private List<DataField> getDataFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields) {
			if (variableField instanceof DataField) {
				variableFieldsReturn.add((DataField) variableField);
			}
		}
		return variableFieldsReturn;
	}

	private List<ControlField> getControlFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<ControlField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields) {
			if (variableField instanceof ControlField) {
				variableFieldsReturn.add((ControlField) variableField);
			}
		}
		return variableFieldsReturn;
	}
}

