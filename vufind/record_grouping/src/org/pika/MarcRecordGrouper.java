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
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.HashMap;
import java.util.List;

/**
 * A base class for setting title, author, and format for a MARC record
 * allows us to override certain information (especially format determination)
 * by library.
 *
 * Pika
 * User: Mark Noble
 * Date: 7/1/2015
 * Time: 2:05 PM
 */
public class MarcRecordGrouper extends RecordGroupingProcessor {

	private IndexingProfile profile;

	private boolean hasSierraLanguageFixedField;

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *  @param pikaConn   - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
 * @param logger         - A logger to store debug and error messages to.
	 */
	public MarcRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger) {
		this(pikaConn, profile, logger, false);
	}

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn   - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 */
	public MarcRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger,  boolean fullRegrouping) {
		super(pikaConn, logger, fullRegrouping);
		this.profile = profile;

		recordNumberTag     = profile.recordNumberTag;
		recordNumberField   = profile.recordNumberField;
		recordNumberPrefix  = profile.recordNumberPrefix;
		itemTag             = profile.itemTag;
		eContentDescriptor  = profile.eContentDescriptor;
		useEContentSubfield = profile.eContentDescriptor != ' ';


		super.setupDatabaseStatements(pikaConn);

		loadTranslationMaps(pikaConn);


		// Load language code to name Map
		hasSierraLanguageFixedField = profile.sierraRecordFixedFieldsTag != null && !profile.sierraRecordFixedFieldsTag.isEmpty() && profile.sierraLanguageFixedField != ' ';
		if (hasSierraLanguageFixedField) {
			File curFile = new File("../../sites/default/translation_maps/language_map.properties");
			if (curFile.exists()) {
				String mapName                        = curFile.getName().replace(".properties", "").replace("_map", "");
				translationMaps.put(mapName, loadTranslationMap(curFile, mapName));
			} else {
				logger.error("Language translation map for MARC grouping not found");
			}
		}

	}

	private void loadTranslationMaps(Connection pikaConn) {
		//TODO: only load maps needed for grouping?
		try (
				PreparedStatement loadTranslationMapsStmt = pikaConn.prepareStatement("SELECT * FROM translation_maps WHERE indexingProfileId = ?");
				PreparedStatement loadTranslationMapValuesStmt = pikaConn.prepareStatement("SELECT * FROM translation_map_values WHERE translationMapId = ?")
		) {
			loadTranslationMapsStmt.setLong(1, profile.id);
			try (ResultSet translationMapsRS = loadTranslationMapsStmt.executeQuery()) {
				while (translationMapsRS.next()) {
					String         mapName          = translationMapsRS.getString("name");
					TranslationMap translationMap   = new TranslationMap(profile.sourceName, mapName, false, translationMapsRS.getBoolean("usesRegularExpressions"), logger);
					long           translationMapId = translationMapsRS.getLong("id");
					loadTranslationMapValuesStmt.setLong(1, translationMapId);
					try (ResultSet translationMapValuesRS = loadTranslationMapValuesStmt.executeQuery()) {
						while (translationMapValuesRS.next()) {
							String value       = translationMapValuesRS.getString("value");
							String translation = translationMapValuesRS.getString("translation");

							translationMap.addValue(value, translation);
						}
						translationMaps.put(mapName, translationMap);
					} catch (Exception e) {
						logger.error("Error loading translation map " + mapName, e);
					}
				}
			}
		} catch (Exception e) {
			logger.error("Error loading translation maps", e);
		}
	}

	public boolean processMarcRecord(Record marcRecord, boolean primaryDataChanged) {
		RecordIdentifier primaryIdentifier = getPrimaryIdentifierFromMarcRecord(marcRecord, profile.sourceName, profile.doAutomaticEcontentSuppression);
		return processMarcRecord(marcRecord, primaryDataChanged, primaryIdentifier);
	}

	public boolean processMarcRecord(Record marcRecord, boolean primaryDataChanged, RecordIdentifier primaryIdentifier) {
		if (primaryIdentifier != null) {
			//Get data for the grouped record
			GroupedWorkBase workForTitle = setupBasicWorkForIlsRecord(primaryIdentifier, marcRecord, profile);

			addGroupedWorkToDatabase(primaryIdentifier, workForTitle, primaryDataChanged);
			return true;
		} else {
			//The record is not grouped
			return false;
		}
	}

	@Override
	protected void setGroupingLanguageBasedOnMarc(Record marcRecord, GroupedWork5 workForTitle, RecordIdentifier identifier) {
		ControlField fixedField   = (ControlField) marcRecord.getVariableField("008");
		String       languageCode = null;
		if (fixedField != null) {
			String oo8Data = fixedField.getData();
			if (oo8Data.length() > 37) {
				String oo8languageCode = oo8Data.substring(35, 38).toLowerCase().trim(); // (trim because some bad values will have spaces)
				if (hasSierraLanguageFixedField) {
					// Use the sierra language fixed field if the 008 isn't a valid language value
					String languageName = translationMaps.get("language").translateValue(oo8languageCode, identifier.toString());
					if (languageName != null && !languageName.equals("Unknown") && !languageName.equals(oo8languageCode)) {
						languageCode = oo8languageCode;
					}
				} else if (!oo8languageCode.equals("") && !oo8languageCode.equals("|||")) {
					//"   " (trimmed to "") & "|||" are equivalent to no language value being set
					languageCode = oo8languageCode;
				}
			}
		} else if (fullRegrouping){
			logger.warn("Missing 008 : " + identifier.toString());
		}
		if (languageCode == null) {
			if (hasSierraLanguageFixedField) {
				// Use the Sierra Fixed Field Language code if it is available
				List<DataField> dataFields = getDataFields(marcRecord, profile.sierraRecordFixedFieldsTag);
				for (DataField dataField : dataFields) {
					Subfield subfield = dataField.getSubfield(profile.sierraLanguageFixedField);
					if (subfield != null) {
						languageCode = subfield.getData().toLowerCase();
						if (!languageCode.isEmpty()) {
							break;
						}
					}
				}
			} else {
				// If we still don't have a language and not a Sierra record, try using the first 041a if present
				DataField languageField = marcRecord.getDataField("041");
				if (languageField != null) {
					Subfield languageSubField = languageField.getSubfield('a');
					if (languageSubField != null && languageField.getIndicator1() != '1' && languageField.getIndicator2() != '7') {
						// First indicator of 1 is for translations; 2nd indicator of 2 is for other language code schemes
						languageCode = languageSubField.getData().trim().toLowerCase().substring(0, 3);
						//substring(0,3) because some 041 tags will have multiple language codes within a single subfield.
						// We will just use the very first one.
					}
				}
			}
		}
		if (languageCode == null) languageCode = "";
		workForTitle.setGroupingLanguage(languageCode);
	}
}
