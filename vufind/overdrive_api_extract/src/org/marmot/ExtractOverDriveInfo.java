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

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.net.HttpURLConnection;
import java.net.SocketTimeoutException;
import java.net.URL;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;
import java.util.zip.CRC32;

import javax.net.ssl.HttpsURLConnection;

import com.mysql.jdbc.exceptions.MySQLIntegrityConstraintViolationException;
import org.apache.commons.codec.binary.Base64;
import org.apache.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.pika.PikaConfigIni;
import org.pika.PikaSystemVariables;

class ExtractOverDriveInfo {
	private static Logger                   logger = Logger.getLogger(ExtractOverDriveInfo.class);
	private        Connection               pikaConn;
	private        Connection               econtentConn;
	private        PikaSystemVariables      systemVariables;
	private        OverDriveExtractLogEntry results;

	private Long   extractStartTime;
	private String lastUpdateTimeParam = "";

	//Overdrive API information
	private       String                  clientSecret;
	private       String                  clientKey;
	private final List<String>            accountIds              = new ArrayList<>();
	private       String                  overDriveAPIToken;
	private       String                  overDriveAPITokenType;
	private       long                    overDriveAPIExpiration;
	private       boolean                 forceMetaDataUpdate;
	private final TreeMap<String, String> overDriveProductsKeys      = new TreeMap<>(); // specifically <AccountId, overDriveProductsKey>
	private final TreeMap<Long, String>   libToOverDriveAPIKeyMap    = new TreeMap<>();
	private final TreeMap<Long, Long>     libToSharedCollectionIdMap = new TreeMap<>(); // specifically <libraryId, sharedCollectionId>
	private final Set<String>             overDriveFormats           = new HashSet<String>() {{
		add("audiobook-mp3");
		add("audiobook-overdrive");
		add("ebook-epub-adobe");
		add("ebook-epub-open");
		add("ebook-kindle");
		add("ebook-mediado");
		add("ebook-overdrive");
		add("ebook-pdf-adobe");
		add("ebook-pdf-open");
		add("magazine-overdrive");
		add("video-streaming");
	}};

	private HashMap<String, OverDriveRecordInfo> overDriveTitles             = new HashMap<>();
	private HashMap<String, Long>                advantageCollectionToLibMap = new HashMap<>();
	private HashMap<String, OverDriveDBInfo>     databaseProducts            = new HashMap<>();
	private HashMap<String, Long>                existingLanguageIds         = new HashMap<>();
	private HashMap<String, Long>                existingSubjectIds          = new HashMap<>();

	private PreparedStatement loadProductStmt;
	private PreparedStatement addProductStmt;
	private PreparedStatement setNeedsUpdateStmt;
	private PreparedStatement getNumProductsNeedingUpdatesStmt;
	private PreparedStatement getIndividualProductStmt;
	private PreparedStatement getProductsNeedingUpdatesStmt;
	private PreparedStatement updateProductStmt;
	private PreparedStatement deleteProductStmt;
	private PreparedStatement updateProductMetadataStmt;
	private PreparedStatement loadMetaDataStmt;
	private PreparedStatement addMetaDataStmt;
	private PreparedStatement updateMetaDataStmt;
	private PreparedStatement clearCreatorsStmt;
	private PreparedStatement addCreatorStmt;
	private PreparedStatement addLanguageStmt;
	private PreparedStatement clearLanguageRefStmt;
	private PreparedStatement addLanguageRefStmt;
	private PreparedStatement addSubjectStmt;
	private PreparedStatement clearSubjectRefStmt;
	private PreparedStatement addSubjectRefStmt;
	private PreparedStatement clearFormatsStmt;
	private PreparedStatement addFormatStmt;
	private PreparedStatement clearIdentifiersStmt;
	private PreparedStatement addIdentifierStmt;
	private PreparedStatement checkForExistingAvailabilityStmt;
	private PreparedStatement updateAvailabilityStmt;
	private PreparedStatement addAvailabilityStmt;
	private PreparedStatement deleteAvailabilityStmt;
	private PreparedStatement updateProductAvailabilityStmt;
	private PreparedStatement markGroupedWorkForBibAsChangedStmt;
	private boolean           hadTimeoutsFromOverDrive;

	private CRC32   checksumCalculator = new CRC32();
	private boolean errorsWhileLoadingProducts;

	void extractOverDriveInfo(PikaSystemVariables systemVariables, Connection pikaConn, Connection econtentConn, OverDriveExtractLogEntry logEntry, boolean doFullReload, String individualIdToProcess) {
		this.pikaConn        = pikaConn;
		this.econtentConn    = econtentConn;
		this.systemVariables = systemVariables;
		this.results         = logEntry;

		extractStartTime = new Date().getTime() / 1000;

		try {
			Long              maxProductsToUpdate         = systemVariables.getLongValuedVariable("overdriveMaxProductsToUpdate");
			if (maxProductsToUpdate == null){
				maxProductsToUpdate         = 2500L;
			}
			PreparedStatement loadLanguagesStmt          = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_languages");
			PreparedStatement loadSubjectsStmt           = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_subjects");
			loadProductStmt                              = econtentConn.prepareStatement("SELECT * FROM overdrive_api_products WHERE overdriveId = ?");
			addProductStmt                               = econtentConn.prepareStatement("INSERT INTO overdrive_api_products SET overdriveId = ?, crossRefId = ?, mediaType = ?, title = ?, subtitle = ?, series = ?, primaryCreatorRole = ?, primaryCreatorName = ?, cover = ?, dateAdded = ?, dateUpdated = ?, lastMetadataCheck = 0, lastMetadataChange = 0, lastAvailabilityCheck = 0, lastAvailabilityChange = 0, rawData=?", PreparedStatement.RETURN_GENERATED_KEYS);
			setNeedsUpdateStmt                           = econtentConn.prepareStatement("UPDATE overdrive_api_products SET needsUpdate = ? WHERE overdriveId = ?");
			getNumProductsNeedingUpdatesStmt             = econtentConn.prepareStatement("SELECT COUNT(overdrive_api_products.id) FROM overdrive_api_products WHERE needsUpdate = 1 AND deleted = 0");
			getProductsNeedingUpdatesStmt                = econtentConn.prepareStatement("SELECT overdrive_api_products.id, overdriveId, crossRefId, lastMetadataCheck, lastMetadataChange, lastAvailabilityCheck, lastAvailabilityChange FROM overdrive_api_products WHERE needsUpdate = 1 AND deleted = 0 LIMIT " + maxProductsToUpdate);
			getIndividualProductStmt                     = econtentConn.prepareStatement("SELECT overdrive_api_products.id, overdriveId, crossRefId, lastMetadataCheck, lastMetadataChange, lastAvailabilityCheck, lastAvailabilityChange FROM overdrive_api_products WHERE overdriveId = ?");
			updateProductStmt                            = econtentConn.prepareStatement("UPDATE overdrive_api_products SET crossRefId = ?, mediaType = ?, title = ?, subtitle = ?, series = ?, primaryCreatorRole = ?, primaryCreatorName = ?, cover = ?, dateUpdated = ?, deleted = 0, rawData=? WHERE id = ?");
			deleteProductStmt                            = econtentConn.prepareStatement("UPDATE overdrive_api_products SET deleted = 1, dateDeleted = ? WHERE id = ?");
			updateProductMetadataStmt                    = econtentConn.prepareStatement("UPDATE overdrive_api_products SET lastMetadataCheck = ?, lastMetadataChange = ? WHERE id = ?");
			loadMetaDataStmt                             = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_metadata WHERE productId = ?");
			updateMetaDataStmt                           = econtentConn.prepareStatement("UPDATE overdrive_api_product_metadata SET productId = ?, checksum = ?, sortTitle = ?, publisher = ?, publishDate = ?, isPublicDomain = ?, isPublicPerformanceAllowed = ?, shortDescription = ?, fullDescription = ?, starRating = ?, popularity =?, thumbnail=?, cover=?, isOwnedByCollections=?, rawData=? WHERE id = ?");
			addMetaDataStmt                              = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_metadata SET productId = ?, checksum = ?, sortTitle = ?, publisher = ?, publishDate = ?, isPublicDomain = ?, isPublicPerformanceAllowed = ?, shortDescription = ?, fullDescription = ?, starRating = ?, popularity =?, thumbnail=?, cover=?, isOwnedByCollections=?, rawData=?");
			clearCreatorsStmt                            = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_creators WHERE productId = ?");
			addCreatorStmt                               = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_creators SET productId = ?, role = ?, name = ?, fileAs = ?");
			addLanguageStmt                              = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_languages SET code =?, name = ?", PreparedStatement.RETURN_GENERATED_KEYS);
			clearLanguageRefStmt                         = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_languages_ref WHERE productId = ?");
			addLanguageRefStmt                           = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_languages_ref SET productId = ?, languageId = ?");
			addSubjectStmt                               = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_subjects SET name = ?", PreparedStatement.RETURN_GENERATED_KEYS);
			clearSubjectRefStmt                          = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_subjects_ref WHERE productId = ?");
			addSubjectRefStmt                            = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_subjects_ref SET productId = ?, subjectId = ?");
			clearFormatsStmt                             = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_formats WHERE productId = ?");
			addFormatStmt                                = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_formats SET productId = ?, textId = ?, name = ?, fileName = ?, fileSize = ?, partCount = ?, sampleSource_1 = ?, sampleUrl_1 = ?, sampleSource_2 = ?, sampleUrl_2 = ?", PreparedStatement.RETURN_GENERATED_KEYS);
			clearIdentifiersStmt                         = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_identifiers WHERE productId = ?");
			addIdentifierStmt                            = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_identifiers SET productId = ?, type = ?, value = ?");
			checkForExistingAvailabilityStmt             = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_availability WHERE productId = ? AND libraryId = ?");
			updateAvailabilityStmt                       = econtentConn.prepareStatement("UPDATE overdrive_api_product_availability SET available = ?, copiesOwned = ?, copiesAvailable = ?, numberOfHolds = ?, availabilityType = ? WHERE id = ?");
			addAvailabilityStmt                          = econtentConn.prepareStatement("INSERT INTO overdrive_api_product_availability SET productId = ?, libraryId = ?, available = ?, copiesOwned = ?, copiesAvailable = ?, numberOfHolds = ?, availabilityType = ?");
			deleteAvailabilityStmt                       = econtentConn.prepareStatement("DELETE FROM overdrive_api_product_availability WHERE id = ?");
			updateProductAvailabilityStmt                = econtentConn.prepareStatement("UPDATE overdrive_api_products SET lastAvailabilityCheck = ?, lastAvailabilityChange = ? WHERE id = ?");
			markGroupedWorkForBibAsChangedStmt           = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'overdrive' AND identifier = ?)");

			//Get the last time we extracted data from OverDrive
			if (individualIdToProcess != null) {
				logger.info("Updating a single record " + individualIdToProcess);
			} else if (doFullReload) {
				logger.info("Marking all records to do a full reload of all records.");
				PreparedStatement markAllAsNeedingUpdatesStmt = econtentConn.prepareStatement("UPDATE overdrive_api_products SET needsUpdate = 1 WHERE deleted = 0");
				markAllAsNeedingUpdatesStmt.executeUpdate();
			} else {
				//Check to see if a partial extract is running
				boolean partialExtractRunning = systemVariables.getBooleanValuedVariable("partial_overdrive_extract_running");
				if (partialExtractRunning) {
					//Oops, a overdrive extract is already running.
					logger.warn("A partial overdrive extract is already running, verify that multiple extracts are not running for best performance.");
					//return;
				} else {
					updatePartialExtractRunning(true);
				}
			}

			String[] tempAccountIds  = PikaConfigIni.getIniValue("OverDrive", "accountId").split(",");
			String[] tempProductKeys = PikaConfigIni.getIniValue("OverDrive", "productsKey").split(",");

			if (tempProductKeys.length == 0) {
				logger.warn("Warning no products key provided for OverDrive in configuration file.");
			}

			int i = 0;
			for (String tempAccountId : tempAccountIds) {
				String tempId         = tempAccountId.trim();
				String tempProductKey = tempProductKeys[i++].trim();
				if (tempId.length() > 0) {
					accountIds.add(tempId);
					if (tempProductKey.length() > 0) {
						overDriveProductsKeys.put(tempId, tempProductKey);
					}
				}
			}


			if (individualIdToProcess == null) {
				//Load last extract time regardless of if we are doing full index or partial index
				if (doFullReload) {
					logger.info("Full Reload: Starting reload of all Overdrive records");
					logEntry.addNote("Full Reload: Starting reload of all Overdrive records");
				} else {
					String           timestamp        = systemVariables.getStringValuedVariable("last_overdrive_extract_time");
					Long             lastExtractTime  = systemVariables.getLongValuedVariable("last_overdrive_extract_time");
					Date             lastExtractDate  = new Date(timestamp.length() >= 13 ? lastExtractTime : lastExtractTime * 1000); //TEMP check; converting from millisecond timestamp to second time stamp
					SimpleDateFormat lastUpdateFormat = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssZ");
					final String     lastUpdateDate   = lastUpdateFormat.format(lastExtractDate);
					final String     message          = "Loading all records that have changed since " + lastUpdateDate;
					logger.info(message);
					logEntry.addNote(message);
					lastUpdateTimeParam = "lastupdatetime=" + lastUpdateDate;
					//Simple Date Format doesn't give us quite the right timezone format so adjust
					lastUpdateTimeParam = lastUpdateTimeParam.substring(0, lastUpdateTimeParam.length() - 2) + ":" + lastUpdateTimeParam.substring(lastUpdateTimeParam.length() - 2);
				}
				logEntry.saveResults();
			}

			ResultSet loadLanguagesRS = loadLanguagesStmt.executeQuery();
			while (loadLanguagesRS.next()) {
				existingLanguageIds.put(loadLanguagesRS.getString("code"), loadLanguagesRS.getLong("id"));
			}

			ResultSet loadSubjectsRS = loadSubjectsStmt.executeQuery();
			while (loadSubjectsRS.next()) {
				existingSubjectIds.put(loadSubjectsRS.getString("name").toLowerCase(), loadSubjectsRS.getLong("id"));
			}

			try (
//					PreparedStatement advantageCollectionMapStmt = pikaConn.prepareStatement("SELECT libraryId, overdriveAdvantageName, overdriveAdvantageProductsKey FROM library WHERE overdriveAdvantageName > ''");
					PreparedStatement advantageCollectionMapStmt = pikaConn.prepareStatement("SELECT libraryId, overdriveAdvantageName, overdriveAdvantageProductsKey, sharedOverdriveCollection FROM library");
					ResultSet advantageCollectionMapRS = advantageCollectionMapStmt.executeQuery();
			) {
				while (advantageCollectionMapRS.next()) {
					//1 = (pika) libraryId, 2 = overDriveAdvantageName, 3 = overDriveAdvantageProductsKey

					final long   pikaLibraryId          = advantageCollectionMapRS.getLong(1);
					final String overDriveAdvantageName = advantageCollectionMapRS.getString(2);
					if (overDriveAdvantageName != null && !overDriveAdvantageName.isEmpty()) {
						advantageCollectionToLibMap.put(overDriveAdvantageName, pikaLibraryId);
						libToOverDriveAPIKeyMap.put(pikaLibraryId, advantageCollectionMapRS.getString(3));
					}
					long sharedCollectionId = advantageCollectionMapRS.getLong(4);
					if (sharedCollectionId < 0L) {
						libToSharedCollectionIdMap.put(pikaLibraryId, sharedCollectionId);
					}
				}
			} catch (SQLException e) {
				logger.error("Error loading Advantage Collection names", e);
			}

			//Load products from API 
			clientSecret        = PikaConfigIni.getIniValue("OverDrive", "clientSecret");
			clientKey           = PikaConfigIni.getIniValue("OverDrive", "clientKey");
			forceMetaDataUpdate = PikaConfigIni.getBooleanIniValue("OverDrive", "forceMetaDataUpdate");

			try {
				if (clientSecret == null || clientKey == null || clientSecret.length() == 0 || clientKey.length() == 0 || accountIds.isEmpty()) {
					logEntry.addNote("Did not find correct configuration in config.ini, not loading overdrive titles");
				} else {
					if (individualIdToProcess == null) {

						if (doFullReload) {
							//Load products from database this lets us know what is new, what has been deleted, and what has been updated
							if (!loadAllProductsFromDatabase()) {
								return;
							}
						}

						//Load products from API to figure out what is actually new, what is deleted, and what needs an update
						if (!loadProductsFromAPI()) {
							return;
						}

						//Update products in database
						updateDatabase(doFullReload);
					}

					//Get a list of records to get full details for.  We don't want this to take forever so only do a few thousand
					//records at the most
					updateMetadataAndAvailability(individualIdToProcess);
					for (long libraryId : availabilityChangesByLibrary.keySet()){
						final String message = libraryId + " had " + availabilityChangesByLibrary.get(libraryId) + " availability changes";
						results.addNote(message);
						logger.debug(message);
					}
				}
			} catch (SocketTimeoutException toe) {
				logger.info("Timeout while loading information from OverDrive, aborting");
				logEntry.addNote("Timeout while loading information from OverDrive, aborting");
				errorsWhileLoadingProducts = true;
			} catch (Exception e) {
				logger.error("Error while loading information from OverDrive, aborting");
				logEntry.addNote("Error while loading information from OverDrive, aborting");
				errorsWhileLoadingProducts = true;
			}

			//Mark the new last update time if we did not get errors loading products from the database
			if (individualIdToProcess == null) {
				if (errorsWhileLoadingProducts || results.hasErrors()) {
					logger.debug("Not setting last extract time since there were problems extracting products from the API");
				} else {
					String note = "There were " + availabilityChecksTotal + " availability checks in total.";
					results.addNote(note);
					logger.debug(note);
					note = "There were " + availabilityChangesTotal + " availability changes made in total.";
					results.addNote(note);
					logger.debug(note);
					systemVariables.setVariable("last_overdrive_extract_time", extractStartTime);
					note = "Setting last extract time to " + extractStartTime + " " + new Date(extractStartTime * 1000).toString();
					results.addNote(note);
					logger.debug(note);
  				}
				if (!doFullReload) {
					updatePartialExtractRunning(false);
				} else {
					logger.info("Full Reload: Finished reload of all Overdrive records");
					logEntry.addNote("Full Reload: Finished reload of all Overdrive records");
				}
			}
		} catch (SQLException e) {
			// handle any errors
			logger.error("Error initializing overdrive extraction", e);
			results.addNote("Error initializing overdrive extraction " + e.toString());
			results.incrementErrors();
		}
	}

	private void updateMetadataAndAvailability(String individualIdToProcess) {
		try {
			final String message = "Starting to update metadata and availability for products";
			logger.debug(message);
			results.addNote(message);
			results.saveResults();
			ResultSet productsNeedingUpdatesRS;
			if (individualIdToProcess == null) {
				ResultSet numProductsNeedingUpdatesRS = getNumProductsNeedingUpdatesStmt.executeQuery();
				numProductsNeedingUpdatesRS.next();
				final String message1 = "There are " + numProductsNeedingUpdatesRS.getInt(1) + " products that currently need updates.";
				logger.info(message1);
				results.addNote(message1);
				productsNeedingUpdatesRS = getProductsNeedingUpdatesStmt.executeQuery();
			} else {
				getIndividualProductStmt.setString(1, individualIdToProcess);
				productsNeedingUpdatesRS = getIndividualProductStmt.executeQuery();
			}


			TreeMap<Long, HashMap<String, SharedStats>> overdriveAccountsSharedStatsHashMaps = new TreeMap<>();
			for (Map.Entry<String, String> entry : overDriveProductsKeys.entrySet()) {
				String accountId          = entry.getKey();
				String productKey         = entry.getValue();
				Long   sharedCollectionId = (accountIds.indexOf(accountId) + 1) * -1L;
				libToOverDriveAPIKeyMap.put(sharedCollectionId, productKey);
			}

			ArrayList<MetaAvailUpdateData> productsToUpdate = new ArrayList<>();
			while (productsNeedingUpdatesRS.next()) {
				MetaAvailUpdateData productToUpdate = new MetaAvailUpdateData();
				productToUpdate.databaseId             = productsNeedingUpdatesRS.getLong("id");
				productToUpdate.crossRefId             = productsNeedingUpdatesRS.getLong("crossRefId");
				productToUpdate.lastMetadataCheck      = productsNeedingUpdatesRS.getLong("lastMetadataCheck");
				productToUpdate.lastMetadataChange     = productsNeedingUpdatesRS.getLong("lastMetadataChange");
				productToUpdate.lastAvailabilityChange = productsNeedingUpdatesRS.getLong("lastAvailabilityChange");
				productToUpdate.overDriveId            = productsNeedingUpdatesRS.getString("overdriveId");
				productsToUpdate.add(productToUpdate);
			}

			int batchSize = 25;
			int batchNum  = 1;
			while (productsToUpdate.size() > 0) {
				availabilityChangesMadeThisRound = 0;
				availabilitiesCheckedThisRound   = 0;
				ArrayList<MetaAvailUpdateData> productsToUpdateBatch = new ArrayList<>();
				int                            maxIndex              = Math.min(productsToUpdate.size(), batchSize);
				for (int i = 0; i < maxIndex; i++) {
					productsToUpdateBatch.add(productsToUpdate.get(i));
				}
				for (long j = -1L; j >= accountIds.size() * -1L; j--) {
					HashMap<String, SharedStats> sharedStatsHashMap = new HashMap<>();
					for (int i = 0; i < maxIndex; i++) {
						sharedStatsHashMap.put(productsToUpdate.get(i).overDriveId, new SharedStats());
					}
					overdriveAccountsSharedStatsHashMaps.put(j, sharedStatsHashMap);
				}
				productsToUpdate.removeAll(productsToUpdateBatch);

				updateOverDriveMetaDataBatch(productsToUpdateBatch); // We don't need to iterate of this to update the data for libraries. It will go through them all.
				if (logger.isInfoEnabled()){
					logger.info("Finished metadata update for batch #" + batchNum);
				}

				//Loop through the libraries first and then the products so we can get data as a batch.
				for (long libraryId : libToOverDriveAPIKeyMap.keySet()) {
					HashMap<String, SharedStats> sharedStatsHashMap;
					if (libraryId < 0) {
						sharedStatsHashMap = overdriveAccountsSharedStatsHashMaps.get(libraryId);
					} else {
						long sharedCollectionId = getSharedCollectionId(libraryId);
						sharedStatsHashMap = overdriveAccountsSharedStatsHashMaps.get(sharedCollectionId);
					}

					updateOverDriveAvailabilityBatchV3(libraryId, productsToUpdateBatch, sharedStatsHashMap);
				}

				if (logger.isInfoEnabled()){
					logger.info("Finished availability update for batch #" + batchNum);
				}

				//Do a final update to mark that the titles don't need to be updated by the extractor again.
				for (MetaAvailUpdateData productToUpdate : productsToUpdateBatch) {
					if (!productToUpdate.hadAvailabilityErrors && !productToUpdate.hadMetadataErrors) {

						results.incrementTitlesProcessed();
						int numChanges = setNeedsUpdated(productToUpdate.overDriveId, false);
						if (numChanges == 0) {
							logger.warn("Did not update that " + productToUpdate.overDriveId + " no longer needs update");
						}
					} else if (logger.isInfoEnabled()) {
						logger.info("Had errors updating metadata (" + productToUpdate.hadMetadataErrors + ") and/or availability (" + productToUpdate.hadAvailabilityErrors + ") for " + productToUpdate.overDriveId + " crossRefId " + productToUpdate.crossRefId);
					}
				}
				if (logger.isDebugEnabled()) {
					logger.debug("Processed availability and metadata batch " + batchNum + " records " + ((batchNum - 1) * batchSize) + " to " + (batchNum * batchSize));
					logger.debug(availabilitiesCheckedThisRound + " availabilities checked this round. " + availabilityChangesMadeThisRound + " availabilities changes made this round.");
				}
				batchNum++;
				results.saveResults();
			}
		} catch (Exception e) {
			logger.error("Error updating metadata and availability", e);
		}
	}

	/**
	 * Marks the overdrive title as needing further updating by the extractor of metadata & availability
	 * @param overdriveId  overdriveId
	 * @param needsUpdated does the title need updated
	 * @return number of titles that were updated (should be 1)
	 */
	private int setNeedsUpdated(String overdriveId, boolean needsUpdated){
		try {
			setNeedsUpdateStmt.setBoolean(1, needsUpdated);
			setNeedsUpdateStmt.setString(2, overdriveId);
			return setNeedsUpdateStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update needs updating for " + overdriveId, e);
		}
		return 0;
	}

	/**
	 * @param libraryId Pika library id for the Advantage library
	 * @return the shared collection that advantage library belongs to.  (-1L if there are no other shared accounts)
	 */
	private long getSharedCollectionId(Long libraryId){
		// Lookup Shared Collection Id for advantage library
		long sharedCollectionId = libToSharedCollectionIdMap.get(libraryId);
		if (sharedCollectionId == 0L) {
			sharedCollectionId = -1L;
			if (logger.isDebugEnabled()) {
				logger.debug("Failed to fetch shared collection for which the advantage library " + libraryId + " belongs to");
			}
		}
		return sharedCollectionId;
	}

	private void updateDatabase(boolean doFullReload) throws SocketTimeoutException {
		int numProcessed = 0;
		for (String overDriveId : overDriveTitles.keySet()) {
			OverDriveRecordInfo overDriveInfo = overDriveTitles.get(overDriveId);
			//Check to see if the title already exists within the database.
			try {
				econtentConn.setAutoCommit(false);
				if (doFullReload) {
					if (databaseProducts.containsKey(overDriveId)) {
						updateProductInDB(overDriveInfo, databaseProducts.get(overDriveId));
						databaseProducts.remove(overDriveId);
					} else {
						addProductToDB(overDriveInfo);
						//TODO: new products list?
					}
				} else {
					OverDriveDBInfo curProduct = loadAProductFromDatabase(overDriveId);
					if (curProduct != null){
						updateProductInDB(overDriveInfo, curProduct);
					} else {
						addProductToDB(overDriveInfo);
						//TODO: new products list?
					}
				}
				econtentConn.commit();
				econtentConn.setAutoCommit(true);
			} catch (SQLException e) {
				logger.info("Error saving/updating product ", e);
				results.addNote("Error saving/updating product " + e.toString());
				results.incrementErrors();
			}

			numProcessed++;
			if (numProcessed % 100 == 0) {
				results.saveResults();
				if (logger.isDebugEnabled()) {
					logger.debug("Updated database for " + numProcessed + " products from the API");
				}
			}
		}
		results.saveResults();

		//Delete any products that no longer exist, but only if we aren't only loading changes and also
		//should not update if we had any timeouts loading products since those products would have been skipped.
		if (lastUpdateTimeParam.length() == 0 && !hadTimeoutsFromOverDrive) {
			for (String overDriveId : databaseProducts.keySet()) {
				OverDriveDBInfo overDriveDBInfo = databaseProducts.get(overDriveId);
				if (!overDriveDBInfo.isDeleted()) {
					deleteProductInDB(databaseProducts.get(overDriveId));
				}
			}
		}
	}

	private void deleteProductInDB(OverDriveDBInfo overDriveDBInfo) {
		try {
			long curTime = new Date().getTime() / 1000;
			deleteProductStmt.setLong(1, curTime);
			deleteProductStmt.setLong(2, overDriveDBInfo.getDbId());
			deleteProductStmt.executeUpdate();
			results.incrementDeleted();
		} catch (SQLException e) {
			final String message = "Error deleting overdrive product " + overDriveDBInfo.getDbId();
			logger.info(message, e);
			results.addNote(message + e.toString());
			results.incrementErrors();
			results.saveResults();
		}
	}

	/**
	 * Update the main Overdrive Titles table in Pika, overdrive_api_products
	 * @param overDriveInfo  primary product data from the API for the main table
	 * @param overDriveDBInfo entry for the existing entry in the Pika overdrive_api_products table
	 */
	private void updateProductInDB(OverDriveRecordInfo overDriveInfo, OverDriveDBInfo overDriveDBInfo) {
		try {
			boolean updateMade = false;
			//Check to see if anything has changed.  If so, perform necessary updates. 
			if (!Util.compareStrings(overDriveInfo.getMediaType(), overDriveDBInfo.getMediaType()) ||
					!Util.compareStrings(overDriveInfo.getTitle(), overDriveDBInfo.getTitle()) ||
					!Util.compareStrings(overDriveInfo.getSubtitle(), overDriveDBInfo.getSubtitle()) ||
					!Util.compareStrings(overDriveInfo.getSeries(), overDriveDBInfo.getSeries()) ||
					!Util.compareStrings(overDriveInfo.getPrimaryCreatorRole(), overDriveDBInfo.getPrimaryCreatorRole()) ||
					!Util.compareStrings(overDriveInfo.getPrimaryCreatorName(), overDriveDBInfo.getPrimaryCreatorName()) ||
					!Util.compareStrings(overDriveInfo.getCoverImage(), overDriveDBInfo.getCover()) ||
					overDriveInfo.getCrossRefId() != overDriveDBInfo.getCrossRefId() ||
					overDriveDBInfo.isDeleted() ||
					!overDriveDBInfo.hasRawData()
			) {
				//Update the product in the database
				long curTime = new Date().getTime() / 1000;
				int  curCol  = 0;
				updateProductStmt.setLong(++curCol, overDriveInfo.getCrossRefId());
				updateProductStmt.setString(++curCol, overDriveInfo.getMediaType());
				updateProductStmt.setString(++curCol, overDriveInfo.getTitle());
				updateProductStmt.setString(++curCol, overDriveInfo.getSubtitle());
				updateProductStmt.setString(++curCol, overDriveInfo.getSeries());
				updateProductStmt.setString(++curCol, overDriveInfo.getPrimaryCreatorRole());
				updateProductStmt.setString(++curCol, overDriveInfo.getPrimaryCreatorName());
				updateProductStmt.setString(++curCol, overDriveInfo.getCoverImage());
				updateProductStmt.setLong(++curCol, curTime);
				updateProductStmt.setString(++curCol, overDriveInfo.getRawData());
				updateProductStmt.setLong(++curCol, overDriveDBInfo.getDbId());

				updateProductStmt.executeUpdate();

				updateMade = true;
			}

			// Mark that the title needs further metadata & availability updating from the extractor
			setNeedsUpdated(overDriveInfo.getId(), true);

			if (updateMade) {
				//Mark that the grouped work needs to be re-indexed
				//TODO: is this the appropriate place to do this? before the metadata and availability updates
				markGroupedWorkForBibAsChangedStmt.setLong(1, extractStartTime);
				markGroupedWorkForBibAsChangedStmt.setString(2, overDriveInfo.getId());
				markGroupedWorkForBibAsChangedStmt.executeUpdate();
				results.incrementUpdated();
			} else {
				results.incrementSkipped();
			}

		} catch (SQLException e) {
			final String message = "Error updating overdrive product " + overDriveInfo.getId();
			logger.info(message, e);
			results.addNote(message + e.toString());
			results.incrementErrors();
			results.saveResults();
		}

	}

	private void addProductToDB(OverDriveRecordInfo overDriveInfo) throws SocketTimeoutException {
		int curCol = 0;
		try {
			long curTime = new Date().getTime() / 1000;
			addProductStmt.setString(++curCol, overDriveInfo.getId());
			addProductStmt.setLong(++curCol, overDriveInfo.getCrossRefId());
			addProductStmt.setString(++curCol, overDriveInfo.getMediaType());
			addProductStmt.setString(++curCol, overDriveInfo.getTitle());
			addProductStmt.setString(++curCol, overDriveInfo.getSubtitle());
			addProductStmt.setString(++curCol, overDriveInfo.getSeries());
			addProductStmt.setString(++curCol, overDriveInfo.getPrimaryCreatorRole());
			addProductStmt.setString(++curCol, overDriveInfo.getPrimaryCreatorName());
			addProductStmt.setString(++curCol, overDriveInfo.getCoverImage());
			addProductStmt.setLong(++curCol, curTime);
			addProductStmt.setLong(++curCol, curTime);
			addProductStmt.setString(++curCol, overDriveInfo.getRawData());
			addProductStmt.executeUpdate();

			ResultSet newIdRS = addProductStmt.getGeneratedKeys();
			newIdRS.next();
			long productId = newIdRS.getLong(1);

			results.incrementAdded();

			//Update metadata based information
			//Do this the first time we detect it to be certain that all the data exists on the first extract.
			updateOverDriveMetaData(overDriveInfo, productId);
			initialOverDriveAvailability(overDriveInfo, productId);
			//TODO: group now
		} catch (MySQLIntegrityConstraintViolationException e1) {
			logger.warn("Error saving product " + overDriveInfo.getId() + " to the database, it was already added by another process");
			results.addNote("Error saving product " + overDriveInfo.getId() + " to the database, it was already added by another process");
			results.incrementErrors();
			results.saveResults();
		} catch (SQLException e) {
			logger.warn("Error saving product " + overDriveInfo.getId() + " to the database", e);
			results.addNote("Error saving product " + overDriveInfo.getId() + " to the database " + e.toString());
			results.incrementErrors();
			results.saveResults();
		}
	}

	/**
	 * When doing a full Reload, load All the titles we have into HashMap databaseProducts.
	 * Basically this enables us to mark as deleted any titles the API doesn't report as part of the collections
	 *
	 * @return Successfully loaded all products
	 */
	private boolean loadAllProductsFromDatabase() {
		try (
				PreparedStatement loadProductsStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_products");
				ResultSet loadProductsRS = loadProductsStmt.executeQuery()
		) {
			while (loadProductsRS.next()) {
				String          overdriveId = loadProductsRS.getString("overdriveId").toLowerCase();
				OverDriveDBInfo curProduct  = getAnOverDriveDBInfoFromAResultSet(loadProductsRS);
				databaseProducts.put(overdriveId, curProduct);
			}
			results.addNote("There are " + databaseProducts.size() + " products total in the database.");
			results.saveResults();
			return true;
		} catch (SQLException e) {
			logger.warn("Error loading products from database", e);
			results.addNote("Error loading products from database " + e.toString());
			results.incrementErrors();
			results.saveResults();
			return false;
		}
	}

	/**
	 * Fetch a single title from the Pika database that we are updating
	 *
	 * @param overDriveId The Id of the title to fetch
	 * @return The title to be fetched
	 */
	private OverDriveDBInfo loadAProductFromDatabase(String overDriveId) {
		try {
			loadProductStmt.setString(1, overDriveId);
			ResultSet loadProductsRS = loadProductStmt.executeQuery();
			if (loadProductsRS.next()) {
				return getAnOverDriveDBInfoFromAResultSet(loadProductsRS);
			}
		} catch (SQLException e) {
			final String message = "Error loading product from database for " + overDriveId;
			logger.warn(message, e);
			results.addNote(message + " " + e.toString());
			results.incrementErrors();
			results.saveResults();
		}
		return null;
	}

	private OverDriveDBInfo getAnOverDriveDBInfoFromAResultSet(ResultSet loadProductsRS) throws SQLException {
		OverDriveDBInfo curProduct  = new OverDriveDBInfo();
		String          rawData     = loadProductsRS.getString("rawData");
		curProduct.setHasRawData(rawData != null && rawData.length() > 0);
		curProduct.setDbId(loadProductsRS.getLong("id"));
		curProduct.setCrossRefId(loadProductsRS.getLong("crossRefId"));
		curProduct.setMediaType(loadProductsRS.getString("mediaType"));
		curProduct.setSeries(loadProductsRS.getString("series"));
		curProduct.setTitle(loadProductsRS.getString("title"));
		curProduct.setPrimaryCreatorRole(loadProductsRS.getString("primaryCreatorRole"));
		curProduct.setPrimaryCreatorName(loadProductsRS.getString("primaryCreatorName"));
		curProduct.setCover(loadProductsRS.getString("cover"));
		curProduct.setLastAvailabilityCheck(loadProductsRS.getLong("lastAvailabilityCheck"));
		curProduct.setLastAvailabilityChange(loadProductsRS.getLong("lastAvailabilityChange"));
		curProduct.setLastMetadataCheck(loadProductsRS.getLong("lastMetadataCheck"));
		curProduct.setLastMetadataChange(loadProductsRS.getLong("lastMetadataChange"));
		curProduct.setDeleted(loadProductsRS.getInt("deleted") == 1);
		return curProduct;
	}

	/**
	 * Make calls to the Overdrive API to fetch main product information
	 * and populate the HashMap overDriveTitles for futher processing by updateDatebase().
	 */
	private boolean loadProductsFromAPI() throws SocketTimeoutException {
		long sharedCollection = 0L;
		for (String accountId : accountIds) {
			sharedCollection--;
			WebServiceResponse libraryInfoResponse = callOverDriveURL("https://api.overdrive.com/v1/libraries/" + accountId);
			if (libraryInfoResponse.getResponseCode() == 200 && libraryInfoResponse.getResponse() != null) {
				JSONObject libraryInfo = libraryInfoResponse.getResponse();
				try {
					String mainLibraryName = libraryInfo.getString("name");
					String mainProductUrl  = libraryInfo.getJSONObject("links").getJSONObject("products").getString("href");
					loadProductsFromUrl(mainLibraryName, mainProductUrl, sharedCollection);
					if (logger.isInfoEnabled()) {
						logger.info("Loaded " + overDriveTitles.size() + " overdrive titles in shared collection " + sharedCollection);
					}
					//Get a list of advantage collections
					if (libraryInfo.getJSONObject("links").has("advantageAccounts")) {
						WebServiceResponse webServiceResponse = callOverDriveURL(libraryInfo.getJSONObject("links").getJSONObject("advantageAccounts").getString("href"));
						if (webServiceResponse.getResponseCode() == 200) {
							JSONObject advantageInfo = webServiceResponse.getResponse();
							if (advantageInfo.has("advantageAccounts")) {
								JSONArray advantageAccounts = advantageInfo.getJSONArray("advantageAccounts");
								for (int i = 0; i < advantageAccounts.length(); i++) {
									JSONObject curAdvantageAccount = advantageAccounts.getJSONObject(i);
									String     advantageName       = curAdvantageAccount.getString("name");
									long       advantageLibraryId  = getLibraryIdForOverDriveAccount(advantageName, accountId);
									if (advantageLibraryId > 0L) {
										String             advantageSelfUrl            = curAdvantageAccount.getJSONObject("links").getJSONObject("self").getString("href");
										WebServiceResponse advantageWebServiceResponse = callOverDriveURL(advantageSelfUrl);
										if (advantageWebServiceResponse.getResponseCode() == 200) {
											JSONObject advantageSelfInfo = advantageWebServiceResponse.getResponse();
											if (advantageSelfInfo != null) {
												String productUrl = advantageSelfInfo.getJSONObject("links").getJSONObject("products").getString("href");
												loadProductsFromUrl(advantageName, productUrl, advantageLibraryId);
											}
										} else {
											final String note = "Unable to load advantage information for " + advantageSelfUrl;
											logger.warn(note);
											results.addNote(note);
											if (advantageWebServiceResponse.getError() != null) {
												results.addNote(advantageWebServiceResponse.getError());
											}
										}
									} else if (logger.isInfoEnabled()) {
										logger.info("Skipping advantage account " + advantageName + " because it does not have a Pika library");
									}
								}
							}
						} else {
							results.addNote("The API indicates that the library has advantage accounts, but none were returned from " + libraryInfo.getJSONObject("links").getJSONObject("advantageAccounts").getString("href"));
							if (webServiceResponse.getError() != null) {
								results.addNote(webServiceResponse.getError());
							}
							results.incrementErrors();
						}
						if (logger.isInfoEnabled()) {
							logger.info("loaded " + overDriveTitles.size() + " overdrive titles in shared collection(s) and advantage collections");
						}
					}
					results.setNumProducts(overDriveTitles.size());
				} catch (SocketTimeoutException toe) {
					throw toe;
				} catch (Exception e) {
					results.addNote("error loading information from OverDrive API " + e.toString());
					results.incrementErrors();
					logger.info("Error loading overdrive titles", e);
					return false;
				}
			} else {
				results.addNote("Unable to load library information for library " + accountId);
				if (libraryInfoResponse.getError() != null) {
					results.addNote(libraryInfoResponse.getError());
				}
				results.incrementErrors();
				if (logger.isInfoEnabled()) {
					logger.info("Error loading overdrive titles " + libraryInfoResponse.getError());
				}
				return false;
			}
		}
		return true;
	}

	/**
	 * Make a call to the Overdrive API to fetch main product information and populate the HashMap overDriveTitles.
	 *
	 * @param libraryName Name of the collection
	 * @param productsUrl The products Url as provided by the API for the shared or advantage account
	 * @param libraryId   The pika library id for the Advantage collection or the shared collection id
	 * @throws JSONException
	 * @throws SocketTimeoutException
	 */
	private void loadProductsFromUrl(String libraryName, String productsUrl, long libraryId) throws JSONException, SocketTimeoutException {
		if (lastUpdateTimeParam.length() > 0) {
			// lastUpdateTimeParam is set when doing a partial extraction and is not set when doing a full Reload
			productsUrl += productsUrl.contains("?") ? "&" + lastUpdateTimeParam : "?" + lastUpdateTimeParam;
		}
		WebServiceResponse productsResponse = callOverDriveURL(productsUrl);
		if (productsResponse.getResponseCode() == 200) {
			JSONObject productInfo = productsResponse.getResponse();
			if (productInfo == null) {
				return;
			}
			long numProducts = productInfo.getLong("totalItems");
			if (logger.isInfoEnabled()) {
				logger.info(libraryName + " collection has " + numProducts + " products in it.  The libraryId for the collection is " + libraryId);
			}
			results.addNote("Loading OverDrive information for " + libraryName);
			results.saveResults();
			long batchSize = 300;
			for (int i = 0; i < numProducts; i += batchSize) {

				//Just search for the specific product
				String batchUrl = productsUrl;
				batchUrl += (productsUrl.contains("?") ? "&" : "?") + "offset=" + i + "&limit=" + batchSize;
				if (logger.isDebugEnabled()) {
					logger.debug("Processing " + libraryName + " batch from " + i + " to " + (i + batchSize));
				}

				WebServiceResponse productBatchInfoResponse = callOverDriveURL(batchUrl);
				if (productBatchInfoResponse.getResponseCode() == 200) {
					JSONObject productBatchInfo = productBatchInfoResponse.getResponse();
					if (productBatchInfo != null && productBatchInfo.has("products")) {
						numProducts = productBatchInfo.getLong("totalItems");
						JSONArray products = productBatchInfo.getJSONArray("products");
						if (logger.isDebugEnabled()) {
							logger.debug(" Found " + products.length() + " products");
						}
						for (int j = 0; j < products.length(); j++) {
							JSONObject          curProduct = products.getJSONObject(j);
							OverDriveRecordInfo curRecord  = loadOverDriveRecordFromJSON(libraryId, curProduct);
							if (curRecord != null) {
								final String id = curRecord.getId();
								if (overDriveTitles.containsKey(id)) {
									// If the title has already been loaded, just mark that collection for libraryId owns it also
									OverDriveRecordInfo oldRecord = overDriveTitles.get(id);
									oldRecord.getCollections().add(libraryId);
								} else {
									//logger.debug("Loading record " + curRecord.getId());
									overDriveTitles.put(id, curRecord);
								}
							} else {
								//Could not parse the record make sure we log that there was an error
								errorsWhileLoadingProducts = true;
								results.incrementErrors();
							}
						}
					}
				} else {
					final String note = "Could not load product batch " + productBatchInfoResponse.getResponseCode() + " - " + productBatchInfoResponse.getError();
					logger.info(note);
					results.addNote(note);
					errorsWhileLoadingProducts = true;
					results.incrementErrors();
				}

			}
		} else {
			errorsWhileLoadingProducts = true;
		}
	}

	/**
	 * Load a product entry from the API into an instance of OverDriveRecordInfo
	 *
	 * @param libraryId  the pika library id for an Advantage collection of the shared collection id
	 * @param curProduct The JSON Object representing the main title information from the API
	 * @return an instance of OverDriveRecordInfo with information for curProduct
	 * @throws JSONException
	 */
	private OverDriveRecordInfo loadOverDriveRecordFromJSON(Long libraryId, JSONObject curProduct) throws JSONException {
		OverDriveRecordInfo curRecord    = new OverDriveRecordInfo();
		String              curProductId = curProduct.getString("id");
		curRecord.setId(curProductId);
		//logger.debug("Processing overdrive title " + curRecord.getId());
		if (!curProduct.has("title")) {
			final String note = "Product " + curProductId + " did not have a title, skipping";
			logger.debug(note);
			results.addNote(note);
			return null;
		}
		curRecord.setTitle(curProduct.getString("title"));
		curRecord.setCrossRefId(curProduct.getLong("crossRefId"));
		if (curProduct.has("subtitle")) {
			curRecord.setSubtitle(curProduct.getString("subtitle"));
		}
		curRecord.setMediaType(curProduct.getString("mediaType"));
		if (curProduct.has("series")) {
			String series = curProduct.getString("series");
			if (series.length() > 215) {
				series = series.substring(0, 215);
				logger.warn("Product " + curProductId + " has a series name longer than database column. Series name will be truncated to '" + series + "'");
			}
			curRecord.setSeries(series);

		}
		if (curProduct.has("primaryCreator")) {
			curRecord.setPrimaryCreatorName(curProduct.getJSONObject("primaryCreator").getString("name"));
			curRecord.setPrimaryCreatorRole(curProduct.getJSONObject("primaryCreator").getString("role"));
		}
		if (curProduct.has("formats")) {
			for (int k = 0; k < curProduct.getJSONArray("formats").length(); k++) {
				curRecord.getFormats().add(curProduct.getJSONArray("formats").getJSONObject(k).getString("id"));
			}
		}
		if (curProduct.has("images") && curProduct.getJSONObject("images").has("thumbnail")) {
			String thumbnailUrl = curProduct.getJSONObject("images").getJSONObject("thumbnail").getString("href");
			curRecord.setCoverImage(thumbnailUrl);
		}
		curRecord.getCollections().add(libraryId);
		curRecord.setRawData(curProduct.toString(2));
		return curRecord;
	}

	/**
	 * @param libraryName Overdrive Advantage Library Name
	 * @param accountId The overall Overdrive Account Id
	 * @return Either the pika library id for the advantage library or the negative id for a shared collection id
	 */
	private long getLibraryIdForOverDriveAccount(String libraryName, String accountId) {
		if (advantageCollectionToLibMap.containsKey(libraryName)) {
			return advantageCollectionToLibMap.get(libraryName);
		} else {
			return (accountIds.indexOf(accountId) + 1) * -1L;
		}

	}

	private void updateOverDriveMetaData(OverDriveRecordInfo overDriveInfo, long productId) throws SocketTimeoutException {
		//Check to see if we need to load metadata
		long curTime = new Date().getTime() / 1000;

		//Get the url to call for meta data information (based on the first owning collection)
		long   firstCollection = overDriveInfo.getCollections().iterator().next();
		String apiKey          = firstCollection < 0L ? getProductsKeyForSharedCollection(firstCollection) : libToOverDriveAPIKeyMap.get(firstCollection);
		if (apiKey == null) {
			logger.error("Unable to get api key for any library for overdrive title " + overDriveInfo.getId());
			results.incrementErrors();
			return;
		}
		String             url              = "https://api.overdrive.com/v1/collections/" + apiKey + "/products/" + overDriveInfo.getId() + "/metadata";
		WebServiceResponse metaDataResponse = callOverDriveURL(url);
		if (metaDataResponse.getResponseCode() != 200) {
			if (logger.isInfoEnabled()) {
				logger.info("Could not load metadata from " + url);
				logger.info(metaDataResponse.getResponseCode() + ":" + metaDataResponse.getError());
			}
			results.addNote("Could not load metadata from " + url);
			results.incrementErrors();
		} else {
			JSONObject          metaData        = metaDataResponse.getResponse();
			MetaAvailUpdateData productToUpdate = new MetaAvailUpdateData();
			productToUpdate.databaseId  = productId;
			productToUpdate.overDriveId = overDriveInfo.getId();

			updateDBMetadataForProduct(productToUpdate, metaData, curTime);
		}
	}

	private void updateOverDriveMetaDataBatch(List<MetaAvailUpdateData> productsToUpdateBatch) throws SocketTimeoutException {
		if (productsToUpdateBatch.size() == 0) {
			return;
		}
		//Check to see if we need to load metadata
		long                           curTime                  = new Date().getTime() / 1000;
		String                         apiKey                   = libToOverDriveAPIKeyMap.get(-1L); // Use the key of the main Account Id
		StringBuilder                  url                      = new StringBuilder("https://api.overdrive.com/v1/collections/" + apiKey + "/bulkmetadata?reserveIds=");
		ArrayList<MetaAvailUpdateData> productsToUpdateMetadata = new ArrayList<>();
		for (MetaAvailUpdateData curProduct : productsToUpdateBatch) {
			if (!curProduct.metadataUpdated) {
				if (productsToUpdateMetadata.size() >= 1) {
					url.append(",");
				}
				url.append(curProduct.overDriveId);
				productsToUpdateMetadata.add(curProduct);
			}
		}

		if (productsToUpdateMetadata.size() == 0) {
			return;
		}

		WebServiceResponse metaDataResponse = callOverDriveURL(url.toString());
		if (metaDataResponse.getResponseCode() != 200) {
			//Doesn't exist in this collection, skip to the next.
			logger.error("Error " + metaDataResponse.getResponseCode() + " retrieving batch metadata for batch " + url + " " + metaDataResponse.getError());
		} else {
			JSONObject bulkResponse = metaDataResponse.getResponse();
			if (bulkResponse.has("metadata")) {
				try {
					JSONArray metaDataArray = bulkResponse.getJSONArray("metadata");
					for (int i = 0; i < metaDataArray.length(); i++) {
						JSONObject metadata = metaDataArray.getJSONObject(i);
						//Get the product to update
						for (MetaAvailUpdateData curProduct : productsToUpdateMetadata) {
							if (metadata.getString("id").equalsIgnoreCase(curProduct.overDriveId)) {
								if (metadata.getBoolean("isOwnedByCollections")) {
									updateDBMetadataForProduct(curProduct, metadata, curTime);
								} else {
									boolean ownedByAdvantage = false;
//									if (logger.isDebugEnabled()) {
//										logger.debug("Product " + curProduct.overDriveId + " is not owned by the shared collection -1, checking other shared and advantage collections.");
//									}
									//Sometimes a product is owned by just advantage accounts or other shared overdrive accounts so we need to check those accounts too
									for (String advantageKey : libToOverDriveAPIKeyMap.values()) {
										if (!advantageKey.equals(apiKey)) {
											url = new StringBuilder("https://api.overdrive.com/v1/collections/" + advantageKey + "/products/" + curProduct.overDriveId + "/metadata");
											WebServiceResponse advantageMetaDataResponse = callOverDriveURL(url.toString());
											if (advantageMetaDataResponse.getResponseCode() != 200) {
												//Doesn't exist in this collection, skip to the next.
												logger.error("Error " + advantageMetaDataResponse.getResponseCode() + " retrieving metadata for advantage account " + url + " " + metaDataResponse.getError());
											} else {
												JSONObject advantageMetadata = advantageMetaDataResponse.getResponse();
												if (advantageMetadata.getBoolean("isOwnedByCollections")) {
													updateDBMetadataForProduct(curProduct, advantageMetadata, curTime);
													ownedByAdvantage = true;
													break;
												}
											}
										}
									}
									if (!ownedByAdvantage) {
										//Not owned by any collections, make sure we set that it isn't owned.
										if (logger.isDebugEnabled()) {
											logger.debug("Product " + curProduct.overDriveId + " is not owned by any collections.");
										}
										updateDBMetadataForProduct(curProduct, metadata, curTime);
									}
								}

								curProduct.metadataUpdated = true;
								productsToUpdateMetadata.remove(curProduct);
								break;
							}
						}

					}
				} catch (Exception e) {
					logger.error("Error loading metadata within batch", e);
				}
			}
		}
	}

	private void updateDBMetadataForProduct(MetaAvailUpdateData updateData, JSONObject metaData, long curTime) {
		checksumCalculator.reset();
		checksumCalculator.update(metaData.toString().getBytes());
		long                metadataChecksum = checksumCalculator.getValue();
		OverDriveDBMetaData databaseMetaData = loadMetadataFromDatabase(updateData.databaseId);
		boolean             updateMetaData   = false;
		if (forceMetaDataUpdate || databaseMetaData.getId() == -1 || !databaseMetaData.hasRawData() || metadataChecksum != databaseMetaData.getChecksum()) {
			//The metadata has definitely changed.
			updateMetaData = true;
		} else if (updateData.lastMetadataCheck <= curTime - 14 * 24 * 60 * 60) {
			//If it's been two weeks since we last updated, give a 20% chance of updating
			//Don't update everything at once to spread out the number of calls and reduce time.
			double randomNumber = Math.random() * 100;
			if (randomNumber <= 20.0) {
				updateMetaData = true;
			}

		}
		if (updateMetaData) {
			try {
				int               curCol            = 0;
				PreparedStatement metaDataStatement = addMetaDataStmt;
				if (databaseMetaData.getId() != -1) {
					metaDataStatement = updateMetaDataStmt;
				}
				metaDataStatement.setLong(++curCol, updateData.databaseId);
				metaDataStatement.setLong(++curCol, metadataChecksum);
				metaDataStatement.setString(++curCol, metaData.has("sortTitle") ? metaData.getString("sortTitle") : "");
				metaDataStatement.setString(++curCol, metaData.has("publisher") ? metaData.getString("publisher") : "");
				//Grab the textual version of publish date rather than the actual date
				if (metaData.has("publishDateText")) {
					String publishDateText = metaData.getString("publishDateText");
					if (publishDateText.matches("\\d{2}/\\d{2}/\\d{4}")) {
						publishDateText = publishDateText.substring(6, 10);
						metaDataStatement.setLong(++curCol, Long.parseLong(publishDateText));
					} else {
						metaDataStatement.setNull(++curCol, Types.INTEGER);
					}
				} else {
					metaDataStatement.setNull(++curCol, Types.INTEGER);
				}

				metaDataStatement.setBoolean(++curCol, metaData.has("isPublicDomain") && metaData.getBoolean("isPublicDomain"));
				metaDataStatement.setBoolean(++curCol, metaData.has("isPublicPerformanceAllowed") && metaData.getBoolean("isPublicPerformanceAllowed"));
				metaDataStatement.setString(++curCol, metaData.has("shortDescription") ? metaData.getString("shortDescription") : "");
				metaDataStatement.setString(++curCol, metaData.has("fullDescription") ? metaData.getString("fullDescription") : "");
				metaDataStatement.setDouble(++curCol, metaData.has("starRating") ? metaData.getDouble("starRating") : 0);
				metaDataStatement.setInt(++curCol, metaData.has("popularity") ? metaData.getInt("popularity") : 0);
				String thumbnail = "";
				String cover     = "";
				if (metaData.has("images")) {
					JSONObject imagesData = metaData.getJSONObject("images");
					if (imagesData.has("thumbnail")) {
						thumbnail = imagesData.getJSONObject("thumbnail").getString("href");
					}
					if (imagesData.has("cover")) {
						cover = imagesData.getJSONObject("cover").getString("href");
					}
				}
				metaDataStatement.setString(++curCol, thumbnail);
				metaDataStatement.setString(++curCol, cover);
				metaDataStatement.setBoolean(++curCol, metaData.has("isOwnedByCollections") && metaData.getBoolean("isOwnedByCollections"));
				metaDataStatement.setString(++curCol, metaData.toString(2));

				if (databaseMetaData.getId() != -1) {
					metaDataStatement.setLong(++curCol, databaseMetaData.getId());
				}
				metaDataStatement.executeUpdate();

				clearCreatorsStmt.setLong(1, updateData.databaseId);
				clearCreatorsStmt.executeUpdate();
				if (metaData.has("creators")) {
					JSONArray contributors = metaData.getJSONArray("creators");
					for (int i = 0; i < contributors.length(); i++) {
						JSONObject contributor = contributors.getJSONObject(i);
						addCreatorStmt.setLong(1, updateData.databaseId);
						addCreatorStmt.setString(2, contributor.getString("role"));
						addCreatorStmt.setString(3, contributor.getString("name"));
						addCreatorStmt.setString(4, contributor.getString("fileAs"));
						addCreatorStmt.executeUpdate();
					}
				}

				clearLanguageRefStmt.setLong(1, updateData.databaseId);
				clearLanguageRefStmt.executeUpdate();
				if (metaData.has("languages")) {
					JSONArray languages = metaData.getJSONArray("languages");
					for (int i = 0; i < languages.length(); i++) {
						JSONObject language = languages.getJSONObject(i);
						String     code     = language.getString("code");
						long       languageId;
						if (existingLanguageIds.containsKey(code)) {
							languageId = existingLanguageIds.get(code);
						} else {
							addLanguageStmt.setString(1, code);
							addLanguageStmt.setString(2, language.getString("name"));
							addLanguageStmt.executeUpdate();
							ResultSet keys = addLanguageStmt.getGeneratedKeys();
							keys.next();
							languageId = keys.getLong(1);
							existingLanguageIds.put(code, languageId);
						}
						addLanguageRefStmt.setLong(1, updateData.databaseId);
						addLanguageRefStmt.setLong(2, languageId);
						addLanguageRefStmt.executeUpdate();
					}
				}

				clearSubjectRefStmt.setLong(1, updateData.databaseId);
				clearSubjectRefStmt.executeUpdate();
				if (metaData.has("subjects")) {
					HashSet<String> subjectsProcessed = new HashSet<>();
					JSONArray       subjects          = metaData.getJSONArray("subjects");
					for (int i = 0; i < subjects.length(); i++) {
						JSONObject subject      = subjects.getJSONObject(i);
						String     curSubject   = subject.getString("value").trim();
						String     lcaseSubject = curSubject.toLowerCase();
						//First make sure we haven't processed this, there are a few records where the same subject occurs twice
						if (subjectsProcessed.contains(lcaseSubject)) {
							continue;
						}
						long subjectId;
						if (existingSubjectIds.containsKey(lcaseSubject)) {
							subjectId = existingSubjectIds.get(lcaseSubject);
						} else {
							addSubjectStmt.setString(1, curSubject);
							addSubjectStmt.executeUpdate();
							ResultSet keys = addSubjectStmt.getGeneratedKeys();
							keys.next();
							subjectId = keys.getLong(1);
							existingSubjectIds.put(lcaseSubject, subjectId);
						}
						addSubjectRefStmt.setLong(1, updateData.databaseId);
						addSubjectRefStmt.setLong(2, subjectId);
						addSubjectRefStmt.executeUpdate();
						subjectsProcessed.add(lcaseSubject);
					}
				}

				clearFormatsStmt.setLong(1, updateData.databaseId);
				clearFormatsStmt.executeUpdate();
				clearIdentifiersStmt.setLong(1, updateData.databaseId);
				clearIdentifiersStmt.executeUpdate();
				if (metaData.has("formats")) {
					JSONArray       formats           = metaData.getJSONArray("formats");
					HashSet<String> uniqueIdentifiers = new HashSet<>();
					for (int i = 0; i < formats.length(); i++) {
						JSONObject format        = formats.getJSONObject(i);
						String     textFormat    = format.getString("id");
						if (!overDriveFormats.contains(textFormat)) {
							// Give us a heads-up that there is a new format to handle
							final String note = "Warning: new format for OverDrive found " + textFormat;
							logger.warn(note);
							results.addNote(note);
						}
						addFormatStmt.setLong(1, updateData.databaseId);
						addFormatStmt.setString(2, textFormat);
						addFormatStmt.setString(3, format.getString("name"));
						addFormatStmt.setString(4, format.has("filename") ? format.getString("fileName") : "");
						addFormatStmt.setLong(5, format.has("fileSize") ? format.getLong("fileSize") : 0L);
						addFormatStmt.setLong(6, format.has("partCount") ? format.getLong("partCount") : 0L);

						if (format.has("identifiers")) {
							JSONArray identifiers = format.getJSONArray("identifiers");
							for (int j = 0; j < identifiers.length(); j++) {
								JSONObject identifier = identifiers.getJSONObject(j);
								uniqueIdentifiers.add(identifier.getString("type") + ":" + identifier.getString("value"));
							}
						}
						//Default samples to null
						addFormatStmt.setString(7, null);
						addFormatStmt.setString(8, null);
						addFormatStmt.setString(9, null);
						addFormatStmt.setString(10, null);

						if (format.has("samples")) {
							JSONArray samples = format.getJSONArray("samples");
							for (int j = 0; j < samples.length(); j++) {
								JSONObject sample = samples.getJSONObject(j);
								if (j == 0) {
									addFormatStmt.setString(7, sample.getString("source"));
									addFormatStmt.setString(8, sample.getString("url"));
								} else if (j == 1) {
									addFormatStmt.setString(9, sample.getString("source"));
									addFormatStmt.setString(10, sample.getString("url"));
								}
							}
						}
						addFormatStmt.executeUpdate();
					}

					for (String curIdentifier : uniqueIdentifiers) {
						addIdentifierStmt.setLong(1, updateData.databaseId);
						String[] identifierInfo = curIdentifier.split(":");
						addIdentifierStmt.setString(2, identifierInfo[0]);
						addIdentifierStmt.setString(3, identifierInfo[1]);
						addIdentifierStmt.executeUpdate();
					}
				}
				//TODO: group an individual production?
				results.incrementMetadataChanges();
			} catch (Exception e) {
				final String message = "Error loading meta data for title " + updateData.overDriveId;
				logger.info(message, e);
				results.addNote(message + " " + e.toString());
				updateData.hadMetadataErrors = true;
			}
		}
		try {
			updateProductMetadataStmt.setLong(1, curTime);
			if (updateMetaData) {
				updateProductMetadataStmt.setLong(2, curTime);
			} else {
				updateProductMetadataStmt.setLong(2, updateData.lastMetadataChange);
			}
			updateProductMetadataStmt.setLong(3, updateData.databaseId);
			updateProductMetadataStmt.executeUpdate();
		} catch (SQLException e) {
			final String message = "Error updating product metadata summary " + updateData.overDriveId;
			logger.warn(message, e);
			results.addNote(message + " " + e.toString());
			updateData.hadMetadataErrors = true;
		}
	}

	private OverDriveDBMetaData loadMetadataFromDatabase(long databaseId) {
		OverDriveDBMetaData metaData = new OverDriveDBMetaData();
		try {
			loadMetaDataStmt.setLong(1, databaseId);
			ResultSet metaDataRS = loadMetaDataStmt.executeQuery();
			if (metaDataRS.next()) {
				String rawData = metaDataRS.getString("rawData");
				metaData.setId(metaDataRS.getLong("id"));
				metaData.setChecksum(metaDataRS.getLong("checksum"));
				metaData.setHasRawData(rawData != null && rawData.length() > 0);
			}
		} catch (SQLException e) {
			logger.warn("Error loading product metadata ", e);
			results.addNote("Error loading product metadata for " + databaseId + " " + e.toString());
			results.incrementErrors();
		}
		return metaData;
	}

	/**
	 * Populate Pika table with the initial availability information from the OverDrive API
	 * for when a new product(/title) is added to a Pika library overdrive collection.
	 *
	 * @param overDriveInfo Information about the title from the API
	 * @param productId  The overdrive_api_products table id for the title
	 * @throws SocketTimeoutException
	 */
	private void initialOverDriveAvailability(OverDriveRecordInfo overDriveInfo, long productId) throws SocketTimeoutException {
		long curTime = new Date().getTime() / 1000;

		//logger.debug("Loading availability, " + overDriveInfo.getId() + " is in " + overDriveInfo.getCollections().size() + " collections");
		boolean availabilityChanged = false;
		for (Long curCollection : overDriveInfo.getCollections()) {
			try {
				String apiKey;
				if (curCollection < 0L) {
					apiKey = getProductsKeyForSharedCollection(curCollection);
				} else {
					apiKey = libToOverDriveAPIKeyMap.get(curCollection);
				}
				if (apiKey == null || apiKey.isEmpty()) {
					logger.error("Unable to get api key for collection " + curCollection);
					results.addNote("Unable to get api key for collection " + curCollection);
					results.incrementErrors();
					continue;
				}
				String             url                  = "https://api.overdrive.com/v2/collections/" + apiKey + "/products/" + overDriveInfo.getId() + "/availability";
				WebServiceResponse availabilityResponse = callOverDriveURL(url);
				//404 is a message that availability has been deleted.
				if (availabilityResponse.getResponseCode() != 200 && availabilityResponse.getResponseCode() != 404) {
					//We got an error calling the OverDrive API, do nothing.
					final String message = "Error loading API for product " + overDriveInfo.getId();
					if (logger.isInfoEnabled()) {
						logger.info(message);
						logger.info(availabilityResponse.getResponseCode() + ":" + availabilityResponse.getError());
					}
					results.addNote(message);
					results.incrementErrors();
				} else if (availabilityResponse.getResponse() == null) {
					availabilityChanged = deleteOverDriveAvailability(productId, curCollection);
				} else {
					JSONObject availability = availabilityResponse.getResponse();
					//If availability is null, it isn't available for this collection
					try {
						boolean    available   = availability.has("available") && availability.getString("available").equals("true");
						JSONArray  allAccounts = availability.getJSONArray("accounts");
						JSONObject accountData = null;
						for (int i = 0; i < allAccounts.length(); i++) {
							accountData = allAccounts.getJSONObject(i);
							long accountId = accountData.getLong("id");
							if (curCollection < 0 && accountId == -1L) {
								break;
							} else if (curCollection > 0 && accountId != -1L) {
								//These don't match because overdrive has it's own number scheme.  There is only one that is not -1 though
								break;
							} else {
								accountData = null;
							}
						}

						if (accountData != null) {
							int copiesOwned = accountData.getInt("copiesOwned");
							int copiesAvailable;
							if (accountData.has("copiesAvailable")) {
								copiesAvailable = accountData.getInt("copiesAvailable");
							} else {
								if (logger.isInfoEnabled()) {
									logger.info("copiesAvailable was not provided for collection " + apiKey + " title " + overDriveInfo.getId());
								}
								copiesAvailable = 0;
							}
							int numberOfHolds       = curCollection < 0 ? availability.getInt("numberOfHolds") : 0;
							String availabilityType = fixAvailabilityType(copiesOwned, availability.getString("availabilityType"));
							availabilityChecksTotal++;
							if (updateAvailability(curCollection, available, copiesOwned, copiesAvailable, numberOfHolds, availabilityType, productId)){
								availabilityChanged = true;
							}
						} else {
							availabilityChanged = deleteOverDriveAvailability(productId, curCollection);
						}

					} catch (JSONException e) {
						final String message = "JSON Error loading availability for title "+ overDriveInfo.getId() ;
						logger.info(message, e);
						results.addNote(message + " " + e.toString());
						results.incrementErrors();
					}
				}
			} catch (SQLException e) {
				final String message = "SQL Error loading availability for title " + overDriveInfo.getId();
				logger.info(message, e);
				results.addNote(message + " " + e.toString());
				results.incrementErrors();
			}
		}
		//Update the product to indicate that we checked availability
		try {
			updateProductAvailabilityStmt.setLong(1, curTime);
			if (availabilityChanged) {
				updateProductAvailabilityStmt.setLong(2, curTime);
				results.incrementAvailabilityChanges();
				results.saveResults();
			}
			updateProductAvailabilityStmt.setLong(3, productId);
			updateProductAvailabilityStmt.executeUpdate();
		} catch (SQLException e) {
			logger.warn("Error updating product availability status ", e);
			results.addNote("Error updating product availability status " + overDriveInfo.getId() + " " + e.toString());
			results.incrementErrors();
		}
	}

	private ResultSet checkForExistingAvailability(long productId, Long curCollection) throws SQLException {
		checkForExistingAvailabilityStmt.setLong(1, productId);
		checkForExistingAvailabilityStmt.setLong(2, curCollection);

		return checkForExistingAvailabilityStmt.executeQuery();
	}

	private void updateOverDriveAvailabilityBatchV1(long libraryId, List<MetaAvailUpdateData> productsToUpdateBatch, HashMap<String, SharedStats> sharedStats) {
		//logger.debug("Loading availability, " + overDriveInfo.getId() + " is in " + overDriveInfo.getCollections().size() + " collections");
		long   curTime = new Date().getTime() / 1000;
		String apiKey  = libToOverDriveAPIKeyMap.get(libraryId);
		for (MetaAvailUpdateData curProduct : productsToUpdateBatch) {
			//If we have an error already don't bother
			if (!curProduct.hadAvailabilityErrors) {
				String             url                  = "https://api.overdrive.com/v2/collections/" + apiKey + "/products/" + curProduct.overDriveId + "/availability";
				int                numTries             = 0;
				WebServiceResponse availabilityResponse = null;
				while (numTries < 3) {
					try {
						availabilityResponse = callOverDriveURL(url);
						break;
					} catch (SocketTimeoutException e) {
						numTries++;
						if (numTries == 3){
							logger.error("Socket Time out 3 time while fetching availability info : " + url);
						}
					}
				}

				if (availabilityResponse == null || availabilityResponse.getResponseCode() != 200) {
					//Doesn't exist in this collection, skip to the next.
					if (availabilityResponse != null) {
						if (availabilityResponse.getResponseCode() == 404 || availabilityResponse.getResponseCode() == 500) {
							//No availability for this product
							deleteOverDriveAvailability(curProduct.databaseId, libraryId);
						} else {
							logger.error("Did not get availability (" + availabilityResponse.getResponseCode() + ") for batch " + url);
							curProduct.hadAvailabilityErrors = true;
						}
					} else {
						logger.error("Did not get availability; null response for batch call " + url);
						curProduct.hadAvailabilityErrors = true;
					}
				} else {
					JSONObject availability = availabilityResponse.getResponse();
					updateDBAvailabilityForProductV1(libraryId, curProduct, availability, curTime, sharedStats.get(curProduct.overDriveId));
				}
			} else {
				logger.debug("Not checking availability because we got an error earlier");
			}
		}
	}

	/**
	 * Removes entries from the pika overdrive_api_product_availability table
	 * @param productId The id of the overdrive title in the overdrive_api_products table
	 * @param libraryId The library id for the advantage or shared collection
	 */
	private boolean deleteOverDriveAvailability(long productId, long libraryId) {
		try {
			//No availability for this product
			ResultSet existingAvailabilityRS  = checkForExistingAvailability(productId, libraryId);
			boolean   hasExistingAvailability = existingAvailabilityRS.next();
			if (hasExistingAvailability) {
				deleteAvailabilityStmt.setLong(1, existingAvailabilityRS.getLong("id"));
				deleteAvailabilityStmt.executeUpdate();
				return true;
			}
		} catch (Exception e) {
			logger.error("Error deleting an availability entry", e);
		}
		return false;
	}

	private void updateOverDriveAvailabilityBatchV2(long libraryId, List<MetaAvailUpdateData> productsToUpdateBatch, HashMap<String, SharedStats> sharedStats) {
		long          curTime  = new Date().getTime() / 1000;
		String        apiKey   = libToOverDriveAPIKeyMap.get(libraryId);
		StringBuilder url      = new StringBuilder("https://api.overdrive.com/v2/collections/" + apiKey + "/availability?products=");
		int           numAdded = 0;
		ArrayList<MetaAvailUpdateData> productsToUpdateClone = new ArrayList<>(productsToUpdateBatch);
		for (MetaAvailUpdateData curProduct : productsToUpdateBatch) {
			if (numAdded > 0) {
				url.append(",");
			}
			url.append(curProduct.overDriveId);
			numAdded++;
		}

		int                numTries             = 0;
		WebServiceResponse availabilityResponse = null;
		while (numTries < 3) {
			try {
				availabilityResponse = callOverDriveURL(url.toString());
				break;
			} catch (SocketTimeoutException e) {
				numTries++;
				if (numTries == 3){
					logger.error("Socket Time out 3 time while fetching availability info : " + url);
				}
			}
		}

		if (availabilityResponse == null || availabilityResponse.getResponseCode() != 200) {
			//Doesn't exist in this collection, skip to the next.
			if (availabilityResponse != null) {
				logger.error("Did not get availability (" + availabilityResponse.getResponseCode() + ") for batch " + url);
			} else {
				logger.error("Did not get availability null response for batch " + url);
			}

			for (MetaAvailUpdateData curProduct : productsToUpdateClone) {
				curProduct.hadAvailabilityErrors = true;
			}
		} else {
			JSONObject bulkResponse = availabilityResponse.getResponse();
			if (bulkResponse.has("availability")) {
				try {
					JSONArray availabilityArray = bulkResponse.getJSONArray("availability");
					for (int i = 0; i < availabilityArray.length(); i++) {
						JSONObject availability = availabilityArray.getJSONObject(i);
						//Get the product to update
						for (MetaAvailUpdateData curProduct : productsToUpdateClone) {
							if (availability.has("reserveId") && availability.getString("reserveId").equals(curProduct.overDriveId)) {
								updateDBAvailabilityForProductV1(libraryId, curProduct, availability, curTime, sharedStats.get(curProduct.overDriveId));
								productsToUpdateClone.remove(curProduct);
								break;
							} else if (availability.has("titleId") && availability.getLong("titleId") == curProduct.crossRefId) {
								updateDBAvailabilityForProductV1(libraryId, curProduct, availability, curTime, sharedStats.get(curProduct.overDriveId));
								productsToUpdateClone.remove(curProduct);
								break;
							}
						}
					}

					//Anything that is still left should have availability removed from the database
					productsToUpdateClone.forEach((curProduct)-> deleteOverDriveAvailability(curProduct.databaseId, libraryId));

				} catch (Exception e) {
					logger.error("Error loading availability within batch", e);
				}
			}
		}
	}

	private void updateOverDriveAvailabilityBatchV3(long libraryId, List<MetaAvailUpdateData> productsToUpdateBatch, HashMap<String, SharedStats> sharedStats) {
		long                             curTime             = new Date().getTime() / 1000;
		String                           apiKey              = libToOverDriveAPIKeyMap.get(libraryId);
		StringBuilder                    url                 = new StringBuilder("https://api.overdrive.com/v2/collections/" + apiKey + "/availability?products=");
		int                              numAdded            = 0;
		Map<String, MetaAvailUpdateData> productsToUpdateMap = new HashMap<String, MetaAvailUpdateData>();
		for (MetaAvailUpdateData curProduct : productsToUpdateBatch) {
			if (numAdded > 0) {
				url.append(",");
			}
			url.append(curProduct.overDriveId);
			numAdded++;
			productsToUpdateMap.put(curProduct.overDriveId, curProduct);
		}

		int                numTries             = 0;
		WebServiceResponse availabilityResponse = null;
		while (numTries < 3) {
			try {
				availabilityResponse = callOverDriveURL(url.toString());
				break;
			} catch (SocketTimeoutException e) {
				numTries++;
				if (numTries == 3){
					logger.error("Socket Time out 3 time while fetching availability info : " + url);
				}
			}
		}

		if (availabilityResponse == null || availabilityResponse.getResponseCode() != 200) {
			//Doesn't exist in this collection, skip to the next.
			if (availabilityResponse != null) {
				logger.error("Did not get availability (" + availabilityResponse.getResponseCode() + ") for batch " + url);
			} else {
				logger.error("Did not get availability null response for batch " + url);
			}

			productsToUpdateMap.forEach((k,curProduct)-> curProduct.hadAvailabilityErrors = true);
		} else {
			JSONObject bulkResponse = availabilityResponse.getResponse();
			if (bulkResponse.has("availability")) {
				try {
					JSONArray availabilityArray = bulkResponse.getJSONArray("availability");
					String curOverDriveId;
					for (int i = 0; i < availabilityArray.length(); i++) {
						JSONObject availability = availabilityArray.getJSONObject(i);
						//Get the product to update
						if (availability.has("reserveId")){
							curOverDriveId = availability.getString("reserveId");
							MetaAvailUpdateData curProduct = productsToUpdateMap.get(curOverDriveId);
							updateDBAvailabilityForProductV1(libraryId, curProduct, availability, curTime, sharedStats.get(curProduct.overDriveId));
							productsToUpdateMap.remove(curOverDriveId);
//						} else if (availability.has("titleId")) {
//							logger.error("The availability call had a titleId instead of a reserveId : " + availability);
						} else {
							logger.error("The availability entry did not have a reserveId : " + availability);
						}
					}

					//Anything that is still left should have availability removed from the database
					productsToUpdateMap.forEach((k,curProduct)-> deleteOverDriveAvailability(curProduct.databaseId, libraryId));


				} catch (Exception e) {
					logger.error("Error loading availability within batch", e);
				}
			}
		}
	}

	int availabilityChangesTotal = 0;
	int availabilityChecksTotal = 0;
	int availabilityChangesMadeThisRound;
	int availabilitiesCheckedThisRound;
	private void updateDBAvailabilityForProductV1(long libraryId, MetaAvailUpdateData curProduct, JSONObject availability, long curTime, SharedStats sharedStats) {
		boolean availabilityChanged = false;

			//If availability is null, it isn't available for this collection
			try {
				boolean available = availability.has("available") && availability.getString("available").equals("true");

				int copiesOwned = availability.getInt("copiesOwned");
				int copiesAvailable;
				if (availability.has("copiesAvailable")) {
					copiesAvailable = availability.getInt("copiesAvailable");
					if (copiesAvailable < 0) {
						copiesAvailable = 0;
					}
				} else {
					logger.warn("copiesAvailable was not provided for library " + libraryId + " title " + curProduct.overDriveId);
					copiesAvailable = 0;
				}
				if (libraryId < 0) {
					sharedStats.copiesOwnedByShared     = copiesOwned;
					sharedStats.copiesAvailableInShared = copiesAvailable;
				} else {
					//This section determines how many copies are owned in the advantage collection by starting with the data from the shared collection.
					if (copiesOwned < sharedStats.copiesOwnedByShared) {
						logger.warn("Copies owned " + copiesOwned + " was less than copies owned " + sharedStats.copiesOwnedByShared + " by the shared collection for libraryId " + libraryId + " product " + curProduct.overDriveId);
						copiesOwned                      = 0;
						curProduct.hadAvailabilityErrors = true;
					} else {
						copiesOwned -= sharedStats.copiesOwnedByShared; // Remaining copies are the copied owned by this advantage collection
					}
					if (copiesAvailable < sharedStats.copiesAvailableInShared) {
						logger.warn("Copies available " + copiesAvailable + " was less than " + sharedStats.copiesAvailableInShared + " copies available in shared collection for libraryId " + libraryId + " product " + curProduct.overDriveId);
						copiesAvailable                  = 0;
						curProduct.hadAvailabilityErrors = true;
					} else {
						copiesAvailable -= sharedStats.copiesAvailableInShared;// Remaining copies are the copied available for this advantage collection
					}
				}

				//Don't restrict this to only the library since it could be owned by an advantage library only.
				int numberOfHolds;
				if (libraryId < 0 || sharedStats.copiesOwnedByShared > 0) {
					numberOfHolds = availability.getInt("numberOfHolds");
				} else {
					numberOfHolds = 0;
				}
				String    availabilityType        = fixAvailabilityType(copiesOwned, availability.getString("availabilityType"));
				availabilitiesCheckedThisRound++;
				availabilityChecksTotal++;
				if(updateAvailability(libraryId, available, copiesOwned, copiesAvailable, numberOfHolds, availabilityType, curProduct.databaseId)) {
					availabilityChanged = true;
				}

			} catch (JSONException | SQLException e) {
				final String message = "Error loading availability for title " + curProduct.overDriveId;
				logger.info(message, e);
				results.addNote(message + " " + e.toString());
				results.incrementErrors();
				curProduct.hadAvailabilityErrors = true;
			}

		//Update the product to indicate that we checked availability
		try {
			updateProductAvailabilityStmt.setLong(1, curTime);
			if (availabilityChanged) {
				updateProductAvailabilityStmt.setLong(2, curTime);
				results.incrementAvailabilityChanges();
			} else {
				updateProductAvailabilityStmt.setLong(2, curProduct.lastAvailabilityChange);
			}
			updateProductAvailabilityStmt.setLong(3, curProduct.databaseId);
			updateProductAvailabilityStmt.executeUpdate();
		} catch (SQLException e) {
			logger.warn("Error updating product availability status ", e);
			results.addNote("Error updating product availability status " + curProduct.overDriveId + " " + e.toString());
			results.incrementErrors();
			curProduct.hadAvailabilityErrors = true;
		}
	}

	/**
	 * OverDrive Availability API bulk calls incorrectly report AlwaysAvailable copies
	 * as Normal availabilityType (As of 10/7/2020). But we can infer AlwaysAvailable availabilityType
	 * from the copiesOwned count of 999,999 or higher; and LimitedAvailability availabilityType from a
	 * copiesOwned count of 500,000
	 *
	 * @param copiesOwned      The number of copies Owned for a title in an collection as reported by the API
	 * @param availabilityType The availabilityType for a title as reported by the API
	 * @return corrected availabilityType
	 */
	private String fixAvailabilityType(int copiesOwned, String availabilityType) {
		if (copiesOwned >= 999999 && availabilityType.equalsIgnoreCase("Normal")) return "AlwaysAvailable";
		if (copiesOwned == 500000 && availabilityType.equalsIgnoreCase("Normal")) return "LimitedAvailability";
		return availabilityType;
	}

	/**
	 * Updates the overdrive_api_product_availability table for an availability entry for a particular collection,
	 * (shared or advantage)
	 *
	 * @param libraryId        the advantage collection pika library id or the (negative) shared collection id
	 * @param available        Is this title available for this collection according to the API
	 * @param copiesOwned      The number of copies owned for this collection according to the API
	 * @param copiesAvailable  The number of copies available for this collection according to the API
	 * @param numberOfHolds    The number of holds on the title.  (This should be the same for
	 *                         shared and advantage collections for the same title)
	 * @param availabilityType The type of availability these copied have for this collection according to the API
	 * @param databaseId       The id for this title in the pika overdrive_api_products table
	 * @return Whether or not an availability entry was updated
	 * @throws SQLException
	 */
	private boolean updateAvailability(long libraryId, boolean available, int copiesOwned, int copiesAvailable, int numberOfHolds, String availabilityType, long databaseId) throws SQLException {
		//Get existing availability
		ResultSet existingAvailabilityRS  = checkForExistingAvailability(databaseId, libraryId);
		boolean   hasExistingAvailability = existingAvailabilityRS.next();
		if (hasExistingAvailability) {
			//Check to see if the availability has changed
			if (available != existingAvailabilityRS.getBoolean("available") ||
					copiesOwned != existingAvailabilityRS.getInt("copiesOwned") ||
					copiesAvailable != existingAvailabilityRS.getInt("copiesAvailable") ||
					numberOfHolds != existingAvailabilityRS.getInt("numberOfHolds") ||
					!availabilityType.equals(existingAvailabilityRS.getString("availabilityType"))
			) {
				long existingId = existingAvailabilityRS.getLong("id");
				updateAvailabilityStmt.setBoolean(1, available);
				updateAvailabilityStmt.setInt(2, copiesOwned);
				updateAvailabilityStmt.setInt(3, copiesAvailable);
				updateAvailabilityStmt.setInt(4, numberOfHolds);
				updateAvailabilityStmt.setString(5, availabilityType);
				updateAvailabilityStmt.setLong(6, existingId);
				updateAvailabilityStmt.executeUpdate();
				availabilityChangesTracking(libraryId);
				availabilityChangesMadeThisRound++;
				availabilityChangesTotal++;
				return true;
			}
		} else {
			addAvailabilityStmt.setLong(1, databaseId);
			addAvailabilityStmt.setLong(2, libraryId);
			addAvailabilityStmt.setBoolean(3, available);
			addAvailabilityStmt.setInt(4, copiesOwned);
			addAvailabilityStmt.setInt(5, copiesAvailable);
			addAvailabilityStmt.setInt(6, numberOfHolds);
			addAvailabilityStmt.setString(7, availabilityType);
			addAvailabilityStmt.executeUpdate();
			availabilityChangesTracking(libraryId);
			availabilityChangesMadeThisRound++;
			availabilityChangesTotal++;
			return true;
		}
		return false;
	}

	private HashMap<Long, Long> availabilityChangesByLibrary = new HashMap<>();

	/**
	 * Track the number of availability changes made per collection
	 *
	 * @param libraryId the pika library id for the advantage collection
	 *                  or the (negative) id of the shared collection
	 */
	private void availabilityChangesTracking(long libraryId) {
		if (availabilityChangesByLibrary.containsKey(libraryId)) {
			long count = availabilityChangesByLibrary.get(libraryId);
			availabilityChangesByLibrary.replace(libraryId, ++count);
		} else {
			availabilityChangesByLibrary.put(libraryId, 1L);
		}
	}

	private WebServiceResponse callOverDriveURL(String overdriveUrl) throws SocketTimeoutException {
		WebServiceResponse webServiceResponse = new WebServiceResponse();
		if (connectToOverDriveAPI(false)) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			StringBuilder     response = new StringBuilder();
			try {
				URL emptyIndexURL = new URL(overdriveUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				if (conn instanceof HttpsURLConnection) {
					HttpsURLConnection sslConn = (HttpsURLConnection) conn;
					sslConn.setHostnameVerifier((hostname, session) -> {
						//Do not verify host names
						return true;
					});
				}
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Authorization", overDriveAPITokenType + " " + overDriveAPIToken);
				conn.setReadTimeout(30000);
				conn.setConnectTimeout(30000);
				webServiceResponse.setResponseCode(conn.getResponseCode());

				if (conn.getResponseCode() == 200) {
					// Get the response
					try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						//logger.debug("  Finished reading response");
					}
					String responseString = response.toString();
					if (responseString.equals("null")) {
						webServiceResponse.setResponse(null);
					} else {
						webServiceResponse.setResponse(new JSONObject(response.toString()));
					}
				} else {
					// Get any errors
					try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						//logger.info("Received error " + conn.getResponseCode() + " connecting to overdrive API " + response.toString());
						//logger.debug("  Finished reading response");
						//logger.debug(response.toString());
						webServiceResponse.setError(response.toString());
					}
					hadTimeoutsFromOverDrive = true;
				}
			} catch (SocketTimeoutException toe) {
				throw toe;
			} catch (Exception e) {
				logger.debug("Error loading data from overdrive API ", e);
				hadTimeoutsFromOverDrive = true;
			}
		} else {
			logger.error("Unable to connect to API");
		}

		return webServiceResponse;
	}

	private boolean connectToOverDriveAPI(boolean getNewToken) throws SocketTimeoutException {
		//Check to see if we already have a valid token
		if (overDriveAPIToken != null && !getNewToken) {
			if (overDriveAPIExpiration - new Date().getTime() > 0) {
				//logger.debug("token is still valid");
				return true;
			} else {
				logger.debug("Token has expired");
			}
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL emptyIndexURL = new URL("https://oauth.overdrive.com/token");
			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			if (conn instanceof HttpsURLConnection) {
				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
				sslConn.setHostnameVerifier((hostname, session) -> {
					//Do not verify host names
					return true;
				});
			}
			conn.setRequestMethod("POST");
			conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			//logger.debug("Client Key is " + clientSecret);
			String encoded = Base64.encodeBase64String((clientKey + ":" + clientSecret).getBytes());
			conn.setRequestProperty("Authorization", "Basic " + encoded);
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setDoOutput(true);

			try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), "UTF8")) {
				wr.write("grant_type=client_credentials");
				wr.flush();
			}

			StringBuilder response = new StringBuilder();
			if (conn.getResponseCode() == 200) {
				// Get the response
				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
				}
				JSONObject parser = new JSONObject(response.toString());
				overDriveAPIToken     = parser.getString("access_token");
				overDriveAPITokenType = parser.getString("token_type");
				//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
				overDriveAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
				//logger.debug("OverDrive token is " + overDriveAPIToken);
			} else {
				logger.error("Received error " + conn.getResponseCode() + " connecting to overdrive authentication service");
				// Get any errors
				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
					if (logger.isDebugEnabled()) {
						logger.debug("  Finished reading response\r\n" + response);
					}
				}
				return false;
			}
		} catch (SocketTimeoutException toe) {
			throw toe;
		} catch (Exception e) {
			logger.error("Error connecting to overdrive API", e);
			return false;
		}
		return true;
	}

	private void updatePartialExtractRunning(boolean running) {
		//Update the last overdrive extract time in the variables table
		systemVariables.setVariable("partial_overdrive_extract_running", running);
	}

	private String getProductsKeyForSharedCollection(Long sharedCollectionId) {
		int i = (int) (Math.abs(sharedCollectionId) - 1);
		if (i < accountIds.size()) {
			String accountId   = accountIds.get(i);
			return overDriveProductsKeys.get(accountId);
		} else if (logger.isDebugEnabled()) {
			logger.debug("Shared Collection ID '" + sharedCollectionId.toString() + "' doesn't have a matching Overdrive Account Id. Failed to get corresponding Products key.");
		}
		return "";
	}
}
