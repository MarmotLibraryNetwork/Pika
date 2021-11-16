/*
 * Copyright (C) 2021  Marmot Library Network
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

/**
 * Pika
 */
public class CleanUpMarcRecs implements IProcessHandler {
	private       Logger logger;
	private final Long   startTime                  = new Date().getTime();
	private final long   oneHundredAndEightyDaysAgo = startTime - (long) 180 * 24 * 3600 * 1000;  // basically 6 months aga (not precise)

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Clean Up Individual Marc Records");

		try (
						PreparedStatement groupedWorkIdentifiersSQL = pikaConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers WHERE type = ? AND identifier = ?")
		) {
			ArrayList<IndexingProfile> indexingProfiles  = loadIndexingProfiles(pikaConn);
			int                        filesDeletedTotal = 0;
			for (IndexingProfile curProfile : indexingProfiles) {
				processLog.addNote("Cleaning out individual marc files for profile " + curProfile.sourceName);
				String marcRecsPath                 = curProfile.individualMarcPath;
				File[] individualMarcPathSubFolders = new File(marcRecsPath).listFiles();
				int    filesDeletedForProfile       = 0;
				int    filesRetainedForProfile      = 0;
				if (individualMarcPathSubFolders != null) {
					for (File subFolder : individualMarcPathSubFolders) {
						if (subFolder.isDirectory()) {
							File[] individualMarcFiles = new File(subFolder.getPath()).listFiles();
							if (individualMarcFiles != null) {
								for (File individualMarcFile : individualMarcFiles) {
									final String fileName = individualMarcFile.getName();
									if (fileName.endsWith(".mrc")) {
										final long fileLastModified = individualMarcFile.lastModified();
										if (fileLastModified < oneHundredAndEightyDaysAgo) {
											// File is older than 6 months (basically)
											String identifier = null;
											// Read the MARC data to get the official record ID
											try (FileInputStream marcFileStream = new FileInputStream(individualMarcFile)) {
												MarcReader catalogReader = new MarcPermissiveStreamReader(marcFileStream, true, true, "UTF8");
												if (catalogReader.hasNext()) {
													try {
														Record curBib = catalogReader.next();
														identifier = getIdentifierFromMarcRecord(curBib, curProfile);
													} catch (MarcException e) {
														logger.error("Error reading MARC file " + individualMarcFile, e);
														processLog.incErrors();
													}
												}
											} catch (Exception e) {
												logger.error("Error reading MARC file " + individualMarcFile, e);
												processLog.incErrors();
											}
											//The file has to be closed in order to delete, so must process the marc try with resources block first
											if (identifier != null && !identifier.isEmpty()) {
												groupedWorkIdentifiersSQL.setString(1, curProfile.sourceName);
												groupedWorkIdentifiersSQL.setString(2, identifier);
												try (ResultSet resultSet = groupedWorkIdentifiersSQL.executeQuery()) {
													if (!resultSet.next()) {
														// The record is not being grouped, so delete the MARC file
														if (individualMarcFile.delete()) {
															if (logger.isDebugEnabled()) {
																logger.debug("Deleted file " + individualMarcFile);
															}
															filesDeletedForProfile++;
															processLog.incUpdated();
														} else {
															processLog.incErrors();

															String note = "Unable to delete file " + individualMarcFile;
															logger.error(note);
															//processLog.addNote(note);
															filesRetainedForProfile++;
														}
													} else {
														// The file still being grouped and belongs to the collection
														filesRetainedForProfile++;
													}
												} catch (SQLException e) {
													logger.error("Error looking up grouped work primary identifier for file " + fileName);
													processLog.incErrors();
												}
											} else {
												logger.error("Failed to find record id for " + individualMarcFile);
												processLog.incErrors();
											}

										}
									}
								}
							}
						}
					}
				}
				String note = "Deleted " + filesDeletedForProfile + " files for profile " + curProfile.sourceName;
				logger.info(note);
				processLog.addNote(note);
				note = "Retained " + filesRetainedForProfile + " files for profile " + curProfile.sourceName;
				logger.info(note);
				processLog.addNote(note);
				processLog.saveToDatabase(pikaConn, logger);
				filesDeletedTotal += filesDeletedForProfile;
			}
			String note = "Total individual Marc Files deleted : " + filesDeletedTotal;
			logger.info(note);
			processLog.addNote(note);
		} catch (SQLException e) {
			logger.error("SQL Error while cleaning up individual marc files", e);
			processLog.incErrors();
		}
		processLog.saveToDatabase(pikaConn, logger);
	}

	/**
	 * The individual marc files names should be the ID of the record also, but in case it isn't
	 * this method reads the MARC to get the ID according to the indexing profile settings.
	 *
	 * @param marcRecord The MARC data
	 * @param profile    The indexing profile associated with this MARC record
	 * @return The official ID for this marc record based on settings from the indexing profile
	 */
	private String getIdentifierFromMarcRecord(Record marcRecord, IndexingProfile profile) {
		String              recordNumber       = null;
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(profile.getRecordNumberTag());
		//Make sure we only get one ils identifier
		for (VariableField curVariableField : recordNumberFields) {
			if (curVariableField instanceof DataField) {
				DataField curRecordNumberField = (DataField) curVariableField;
				Subfield  recordNumberSubfield = curRecordNumberField.getSubfield(profile.recordNumberField);
				if (recordNumberSubfield != null && (profile.getRecordNumberPrefix().length() == 0 || recordNumberSubfield.getData().length() > profile.getRecordNumberPrefix().length())) {
					if (curRecordNumberField.getSubfield(profile.recordNumberField).getData().startsWith(profile.getRecordNumberPrefix())) {
						recordNumber = curRecordNumberField.getSubfield(profile.recordNumberField).getData().trim();
						break;
					}
				}
			} else {
				//It's a control field
				ControlField curRecordNumberField = (ControlField) curVariableField;
				recordNumber = curRecordNumberField.getData().trim();
				break;
			}
		}
		return recordNumber;
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


}
