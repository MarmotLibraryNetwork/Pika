package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

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
class MarcRecordGrouper extends RecordGroupingProcessor {

	private IndexingProfile profile;

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn   - The Connection to the Pika database
	 * @param profile        - The profile that we are grouping records for
	 * @param logger         - A logger to store debug and error messages to.
	 * @param fullRegrouping - Whether or not we are doing full regrouping or if we are only grouping changes.
	 *                       Determines if old works are loaded at the beginning.
	 */
	MarcRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger, boolean fullRegrouping) {
		super(logger, fullRegrouping);
		this.profile = profile;

		recordNumberTag     = profile.recordNumberTag;
		recordNumberField   = profile.recordNumberField;
		recordNumberPrefix  = profile.recordNumberPrefix;
		itemTag             = profile.itemTag;
		eContentDescriptor  = profile.eContentDescriptor;
		useEContentSubfield = profile.eContentDescriptor != ' ';


		super.setupDatabaseStatements(pikaConn);

		loadTranslationMaps(pikaConn);

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

	boolean processMarcRecord(Record marcRecord, boolean primaryDataChanged) {
		RecordIdentifier primaryIdentifier = getPrimaryIdentifierFromMarcRecord(marcRecord, profile.sourceName, profile.doAutomaticEcontentSuppression);

		if (primaryIdentifier != null) {
			//Get data for the grouped record
			GroupedWorkBase workForTitle = setupBasicWorkForIlsRecord(primaryIdentifier, marcRecord, profile);

			addGroupedWorkToDatabase(primaryIdentifier, workForTitle, primaryDataChanged);
			return true;
		} else {
			//The record is suppressed
			return false;
		}
	}

	@Override
	protected void setGroupingLanguageBasedOnMarc(Record marcRecord, GroupedWork5 workForTitle) {
		String languageCode = null;
		ControlField fixedField = (ControlField) marcRecord.getVariableField("008");
		String       oo8languageCode   = fixedField.getData();
		if (oo8languageCode.length() > 37) {
			oo8languageCode = oo8languageCode.substring(35, 38).toLowerCase();
			if (!oo8languageCode.equals("   ")){
				languageCode = oo8languageCode;
			}
		}
		if (languageCode == null) {
			if (profile.sierraRecordFixedFieldsTag != null && !profile.sierraRecordFixedFieldsTag.isEmpty() && profile.sierraLanguageFixedField != ' ') {
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
				// If we still don't have a language, try using the first 041a if present
				DataField languageField = marcRecord.getDataField("041");
				if (languageField != null){
					Subfield langaugeSubField = languageField.getSubfield('a');
					if (langaugeSubField != null){
						String language = langaugeSubField.getData().trim().toLowerCase();
						if (language.length() == 3){
							languageCode = language;
						}
					}
				}
			}
		}
		workForTitle.setGroupingLanguage(languageCode);
	}
}
