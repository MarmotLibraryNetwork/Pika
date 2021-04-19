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
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.marc4j.MarcException;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.marc.*;

import java.io.File;
import java.io.FileInputStream;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;

/**
 * Loads a MARC file and validates that all records within it are good.
 * If good, sets lastExportValid variable to true
 * If not good, sets lastExportValid variable to false
 * Pika
 * User: Mark Noble
 * Date: 10/30/2015
 * Time: 5:01 PM
 */
public class ValidateMarcExport implements IProcessHandler {
	private Logger logger;

	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		boolean                    allExportsValid  = true;
		ArrayList<IndexingProfile> indexingProfiles = loadIndexingProfiles(pikaConn);
		CronProcessLogEntry        processLog       = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Validate Marc Records");
		processLog.saveToDatabase(pikaConn, logger);

		for (IndexingProfile curProfile : indexingProfiles) {
			String marcPath     = curProfile.marcPath;
			String marcEncoding = curProfile.marcEncoding;
			processLog.addNote("Processing profile " + curProfile.name + " using marc encoding " + marcEncoding);

			File[] catalogBibFiles = new File(marcPath).listFiles();
			if (catalogBibFiles != null) {
				for (File curBibFile : catalogBibFiles) {
					try {
						int    numRecordsRead            = 0;
						int    numSuppressedRecords      = 0;
						int    numRecordsToIndex         = 0;
						String lastRecordProcessed       = "";
						String lastProcessedRecordLogged = "";
						if (curBibFile.getName().toLowerCase().endsWith(".mrc") || curBibFile.getName().toLowerCase().endsWith(".marc")) {
							//TODO: need an option to only process new files, ie files with a mod date of less than 24 hours ago
							processLog.addNote("&nbsp;&nbsp;Processing file " + curBibFile.getName());
							try (FileInputStream marcFileStream = new FileInputStream(curBibFile)) {
								MarcReader catalogReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
								while (catalogReader.hasNext()) {
									Record curBib;
									try {
										curBib = catalogReader.next();
										numRecordsRead++;
										RecordIdentifier recordIdentifier = getPrimaryIdentifierFromMarcRecord(curBib, curProfile);
										if (recordIdentifier == null) {
											//logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
											lastRecordProcessed = curBib.getControlNumber();
											numSuppressedRecords++;
										} else if (recordIdentifier.isSuppressed()) {
											//logger.debug("Record with control number " + curBib.getControlNumber() + " was suppressed or is eContent");
											numSuppressedRecords++;
											lastRecordProcessed = recordIdentifier.getIdentifier();
										} else {
											numRecordsToIndex++;
											lastRecordProcessed = recordIdentifier.getIdentifier();
										}
									} catch (MarcException me) {
										if (!lastProcessedRecordLogged.equals(lastRecordProcessed)) {
											logger.warn("Error processing individual record on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed + " trying to continue", me);
											processLog.addNote("Error processing individual record on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed + " trying to continue.  " + me.toString());
											processLog.incErrors();
											processLog.saveToDatabase(pikaConn, logger);
											lastProcessedRecordLogged = lastRecordProcessed;
										}
									}
								}
								marcFileStream.close();
								processLog.addNote("&nbsp;&nbsp;&nbsp;&nbsp;File is valid.  Found " + numRecordsToIndex + " records that will be indexed and " + numSuppressedRecords + " records that will be suppressed.");
							} catch (Exception e) {
								logger.error("&nbsp;&nbsp;&nbsp;&nbsp;Error loading catalog bibs on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed, e);
								processLog.addNote("Error loading catalog bibs on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed + ". " + e.toString());
								allExportsValid = false;
								processLog.saveToDatabase(pikaConn, logger);
							}
						}
					} catch (Exception e) {
						logger.error("Error validating marc records in file " + curBibFile.getAbsolutePath(), e);
						processLog.addNote("Error validating marc records " + curBibFile.getAbsolutePath() + "  " + e.toString());
						allExportsValid = false;
					}
				}
			}
		}

		//Update the variable
		try {
			PreparedStatement updateExportValidSetting = pikaConn.prepareStatement("INSERT INTO variables (name, value) VALUES ('last_export_valid', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
			updateExportValidSetting.setBoolean(1, allExportsValid);
			updateExportValidSetting.executeUpdate();
		} catch (Exception e) {
			logger.error("Error updating variable ", e);
			processLog.addNote("Error updating variable  " + e.toString());
		} finally {
			processLog.setFinished();
			processLog.saveToDatabase(pikaConn, logger);
		}
	}

	private ArrayList<IndexingProfile> loadIndexingProfiles(Connection pikaConn) {
		ArrayList<IndexingProfile> indexingProfiles = new ArrayList<>();
		try {
			PreparedStatement getIndexingProfilesStmt = pikaConn.prepareStatement("SELECT * FROM indexing_profiles");
			ResultSet         indexingProfilesRS      = getIndexingProfilesStmt.executeQuery();
			while (indexingProfilesRS.next()) {
				IndexingProfile profile = new IndexingProfile();
				profile.id                                = indexingProfilesRS.getLong(1);
				profile.name                              = indexingProfilesRS.getString("name");
				profile.marcPath                          = indexingProfilesRS.getString("marcPath");
				profile.individualMarcPath                = indexingProfilesRS.getString("individualMarcPath");
				profile.recordNumberTag                   = indexingProfilesRS.getString("recordNumberTag");
				profile.marcEncoding                      = indexingProfilesRS.getString("marcEncoding");
				profile.numCharsToCreateFolderFrom        = indexingProfilesRS.getInt("numCharsToCreateFolderFrom");
				profile.createFolderFromLeadingCharacters = indexingProfilesRS.getBoolean("createFolderFromLeadingCharacters");
				profile.setItemTag(indexingProfilesRS.getString("itemTag"));
				profile.setRecordNumberPrefix(indexingProfilesRS.getString("recordNumberPrefix"));
				profile.setRecordNumberTag(indexingProfilesRS.getString("recordNumberTag"));
				profile.setDoAutomaticEcontentSuppression(indexingProfilesRS.getBoolean("doAutomaticEcontentSuppression"));
				String eContentDescriptorStr = indexingProfilesRS.getString("eContentDescriptor");
				char   eContentDescriptor    = (eContentDescriptorStr == null || eContentDescriptorStr.trim().length() == 0) ? ' ' : eContentDescriptorStr.charAt(0);
				profile.setEContentDescriptor(eContentDescriptor);

				indexingProfiles.add(profile);
			}
		} catch (Exception e) {
			logger.error("Error loading indexing profiles", e);
			System.exit(1);
		}
		return indexingProfiles;
	}

	private RecordIdentifier getPrimaryIdentifierFromMarcRecord(Record marcRecord, IndexingProfile profile) {
		RecordIdentifier    identifier         = null;
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(profile.getRecordNumberTag());
		//Make sure we only get one ils identifier
		for (VariableField curVariableField : recordNumberFields) {
			if (curVariableField instanceof DataField) {
				DataField curRecordNumberField = (DataField) curVariableField;
				Subfield  subfieldA            = curRecordNumberField.getSubfield('a');
				//TODO: use indexing profile recordNumberField
				if (subfieldA != null && (profile.getRecordNumberPrefix().length() == 0 || subfieldA.getData().length() > profile.getRecordNumberPrefix().length())) {
					if (curRecordNumberField.getSubfield('a').getData().substring(0, profile.getRecordNumberPrefix().length()).equals(profile.getRecordNumberPrefix())) {
						String recordNumber = curRecordNumberField.getSubfield('a').getData().trim();
						identifier = new RecordIdentifier();
						identifier.setValue(profile.name, recordNumber);
						break;
					}
				}
			} else {
				//It's a control field
				ControlField curRecordNumberField = (ControlField) curVariableField;
				String       recordNumber         = curRecordNumberField.getData().trim();
				identifier = new RecordIdentifier();
				identifier.setValue(profile.name, recordNumber);
				break;
			}
		}
		if (identifier == null) {
			return null;
		}

		//Check to see if the record is an overdrive record
		if (profile.isDoAutomaticEcontentSuppression()) {
			if (profile.useEContentSubfield()) {
				//TODO: this suppression calculation should be standardized with the re-indexing version; probably put in a function to easily share across them
				boolean allItemsSuppressed = true;

				List<DataField> itemFields = getDataFields(marcRecord, profile.getItemTag());
				int             numItems   = itemFields.size();
				for (DataField itemField : itemFields) {
					if (itemField.getSubfield(profile.getEContentDescriptor()) != null) {
						//Check the protection types and sources
						String eContentData = itemField.getSubfield(profile.getEContentDescriptor()).getData();
						if (eContentData.indexOf(':') >= 0) {
							String[] eContentFields = eContentData.split(":");
							String   sourceType     = eContentFields[0].toLowerCase().trim();
							if (!sourceType.equals("overdrive") && !sourceType.equals("hoopla")) {
								allItemsSuppressed = false;
							}
						} else {
							allItemsSuppressed = false;
						}
					} else {
						allItemsSuppressed = false;
					}
				}
				if (numItems == 0) {
					allItemsSuppressed = false;
				}
				if (allItemsSuppressed) {
					//Don't return a primary identifier for this record (we will suppress the bib and just use OverDrive APIs)
					identifier.setSuppressed(true);
					identifier.setSuppressionReason("All Items suppressed");
				}
			} else {
				//Check the 856 for an overdrive url
				List<DataField> linkFields = getDataFields(marcRecord, "856");
				for (DataField linkField : linkFields) {
					if (linkField.getSubfield('u') != null) {
						//Check the url to see if it is from OverDrive or Hoopla
						String linkData = linkField.getSubfield('u').getData().trim();
						if (linkData.matches("(?i)^http://.*?lib\\.overdrive\\.com/ContentDetails\\.htm\\?id=[\\da-f]{8}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{12}$")) {
							identifier.setSuppressed(true);
							identifier.setSuppressionReason("OverDrive Title");
						}
					}
				}
			}
		}

		if (identifier.isValid()) {
			return identifier;
		} else {
			return null;
		}
	}

	private List<DataField> getDataFields(Record marcRecord, String tag) {
		List            variableFields       = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields) {
			if (variableField instanceof DataField) {
				variableFieldsReturn.add((DataField) variableField);
			}
		}
		return variableFieldsReturn;
	}
}