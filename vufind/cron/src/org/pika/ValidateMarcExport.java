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
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.regex.Pattern;

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
		try (
			PreparedStatement validatedMARCSQL = pikaConn.prepareStatement("SELECT * FROM indexing_profile_marc_validation WHERE source = ? AND fileName = ?");
			PreparedStatement fileValidationResults = pikaConn.prepareStatement("INSERT INTO indexing_profile_marc_validation " +
							"(source,fileName,fileLastModifiedTime,validationTime,validated,totalRecords,recordSuppressed,errors)" +
							" VALUES (?,?,?,?,?,?,?,?)" +
							" ON DUPLICATE KEY UPDATE " +
							"fileLastModifiedTime=VALUES(fileLastModifiedTime), " +
							"validationTime=VALUES(validationTime), " +
							"validated=VALUES(validated), " +
							"totalRecords=VALUES(totalRecords), " +
							"recordSuppressed=VALUES(recordSuppressed), " +
							"errors=VALUES(errors) "
			)

		){
			for (IndexingProfile curProfile : indexingProfiles) {
				String marcPath     = curProfile.marcPath;
				String marcEncoding = curProfile.marcEncoding;
				processLog.addNote("Processing profile " + curProfile.sourceName + " using marc encoding " + marcEncoding);

				File[] catalogBibFiles = new File(marcPath).listFiles();
				if (catalogBibFiles != null) {
					for (File curBibFile : catalogBibFiles) {
						Pattern      filesToMatchPattern = Pattern.compile(curProfile.filenamesToInclude, Pattern.CASE_INSENSITIVE);
						final String curBibFileName      = curBibFile.getName();
						if (filesToMatchPattern.matcher(curBibFileName).matches()) {
							final long curBibFileLastModified = curBibFile.lastModified() / 1000;
							boolean    validateFile           = true;
							Long       validationTime         = null;
							validatedMARCSQL.setString(1, curProfile.sourceName);
							validatedMARCSQL.setString(2, curBibFileName);
							try (ResultSet resultSet = validatedMARCSQL.executeQuery()) {
								if (resultSet.next()) {
									validationTime = resultSet.getLong("validationTime");
									long fileLastModifiedTime = resultSet.getLong("fileLastModifiedTime");
									if (fileLastModifiedTime == curBibFileLastModified) {
										boolean validated = resultSet.getBoolean("validated");
										if (validated) {
											validateFile = false;
										}
									}
								}
							}
							if (validateFile) {
								boolean isFileValid          = false;
								int     numRecordsRead       = 0;
								int     numSuppressedRecords = 0;
								int     numRecordsToIndex    = 0;
								int     numErrors            = 0;
								try {
									String lastRecordProcessed       = "";
									String lastProcessedRecordLogged = "";
									processLog.addNote("&nbsp;&nbsp;Processing file " + curBibFileName);
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
													numErrors++;
													processLog.incErrors();
													processLog.saveToDatabase(pikaConn, logger);
													lastProcessedRecordLogged = lastRecordProcessed;
												}
											}
										}
										marcFileStream.close();
										isFileValid = true;
										processLog.addNote("&nbsp;&nbsp;&nbsp;&nbsp;File is valid.  Found " + numRecordsToIndex + " records that will be indexed and " + numSuppressedRecords + " records that will be suppressed.");
									} catch (Exception e) {
										logger.error("&nbsp;&nbsp;&nbsp;&nbsp;Error loading catalog bibs on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed, e);
										processLog.addNote("Error loading catalog bibs on record " + numRecordsRead + " of " + curBibFile.getAbsolutePath() + " the last record processed was " + lastRecordProcessed + ". " + e.toString());
										allExportsValid = false;
										processLog.saveToDatabase(pikaConn, logger);
									}
								} catch (Exception e) {
									logger.error("Error validating marc records in file " + curBibFile.getAbsolutePath(), e);
									processLog.addNote("Error validating marc records " + curBibFile.getAbsolutePath() + "  " + e.toString());
									allExportsValid = false;
								}
								try {
									//Store results of MARC validation
									fileValidationResults.setString(1, curProfile.sourceName);
									fileValidationResults.setString(2, curBibFileName);
									fileValidationResults.setLong(3, curBibFileLastModified);
									fileValidationResults.setLong(4, new Date().getTime() / 1000);
									fileValidationResults.setBoolean(5, isFileValid);
									fileValidationResults.setLong(6, numRecordsRead);
									fileValidationResults.setLong(7, numSuppressedRecords);
									fileValidationResults.setLong(8, numErrors);
									fileValidationResults.executeUpdate();
								} catch (SQLException e) {
									logger.error("Error storing MARC validation results to database", e);
								}
							} else {
								final String note = curBibFileName + " previously validated on " + validationTime;
								processLog.addNote(note);
								logger.info(note);
							}
						}
					}
				}
			}
		} catch (SQLException e) {
			logger.error("SQL Error while validating MARC", e);
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
				profile.sourceName                        = indexingProfilesRS.getString("SourceName");
				profile.marcPath                          = indexingProfilesRS.getString("marcPath");
				profile.filenamesToInclude                = indexingProfilesRS.getString("filenamesToInclude");
				profile.individualMarcPath                = indexingProfilesRS.getString("individualMarcPath");
				profile.recordNumberTag                   = indexingProfilesRS.getString("recordNumberTag");
				profile.recordNumberField                 = getCharFromString(indexingProfilesRS.getString("recordNumberField"));
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
	private char getCharFromString(String stringValue) {
		char result = ' ';
		if (stringValue != null && stringValue.length() > 0) {
			result = stringValue.charAt(0);
		}
		return result;
	}

	private static final Pattern overdrivePattern = Pattern.compile("(?i)^http://.*?/ContentDetails\\.htm\\?id=[\\da-f]{8}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{12}$|^http://link\\.overdrive\\.com.*");

	private RecordIdentifier getPrimaryIdentifierFromMarcRecord(Record marcRecord, IndexingProfile profile) {
		RecordIdentifier    identifier         = null;
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(profile.getRecordNumberTag());
		//Make sure we only get one ils identifier
		for (VariableField curVariableField : recordNumberFields) {
			if (curVariableField instanceof DataField) {
				DataField curRecordNumberField = (DataField) curVariableField;
				Subfield  recordNumberSubfield = curRecordNumberField.getSubfield(profile.recordNumberField);
				if (recordNumberSubfield != null && (profile.getRecordNumberPrefix().length() == 0 || recordNumberSubfield.getData().length() > profile.getRecordNumberPrefix().length())) {
					if (curRecordNumberField.getSubfield(profile.recordNumberField).getData().startsWith(profile.getRecordNumberPrefix())) {
						String recordNumber = curRecordNumberField.getSubfield(profile.recordNumberField).getData().trim();
						identifier = new RecordIdentifier(profile.sourceName, recordNumber);
						break;
					}
				}
			} else {
				//It's a control field
				ControlField curRecordNumberField = (ControlField) curVariableField;
				String       recordNumber         = curRecordNumberField.getData().trim();
				identifier = new RecordIdentifier(profile.sourceName, recordNumber);
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
				boolean allItemsSuppressed = true; //TODO: itemless bib suppression switch check??

				List<DataField> itemFields = getDataFields(marcRecord, profile.getItemTag());
				int             numItems   = itemFields.size();
				if (numItems == 0) {
					allItemsSuppressed = false;
				} else {
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
						if (overdrivePattern.matcher(linkData).matches()) {
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