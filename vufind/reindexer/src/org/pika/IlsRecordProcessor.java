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
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.marc.*;

import java.io.*;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.regex.Pattern;

/**
 * Processes data that was exported from the ILS.
 *
 * Pika
 * User: Mark Noble
 * Date: 11/26/13
 * Time: 9:30 AM
 */
abstract class IlsRecordProcessor extends MarcRecordProcessor {
	protected boolean fullReindex;
	private   String  individualMarcPath;
	String marcPath;
	String indexingProfileSourceDisplayName;  // Value to display to end user's
	String indexingProfileSource;  // Value to use in database references

	String recordNumberTag;
	String itemTag;
	String formatSource; // How the format is determined
	String specifiedFormat;
	String specifiedFormatCategory;
	int    specifiedFormatBoost;
	String formatDeterminationMethod = "bib";
	String matTypesToIgnore          = "";
	char   formatSubfield;
	char   barcodeSubfield;
	char   statusSubfieldIndicator;
	private Pattern nonHoldableStatuses;
	char             shelvingLocationSubfield;
	char             collectionSubfield;
	char             dueDateSubfield;
	SimpleDateFormat dueDateFormatter;
	private char   lastCheckInSubfield;
	private String lastCheckInFormat;
	private char   dateCreatedSubfield;
	private String dateAddedFormat;
	protected boolean loadDateAddedFromRecord = false;
	char locationSubfieldIndicator;
	private Pattern nonHoldableLocations;
	Pattern statusesToSuppressPattern    = null;
	Pattern locationsToSuppressPattern   = null;
	Pattern collectionsToSuppressPattern = null;
	Pattern iTypesToSuppressPattern      = null;
	Pattern iCode2sToSuppressPattern     = null;
	Pattern bCode3sToSuppressPattern     = null;
	char    iTypeSubfield;
	private Pattern nonHoldableITypes;
	boolean useEContentSubfield            = false;
	boolean doAutomaticEcontentSuppression = false;
	char    eContentSubfieldIndicator;
	private char lastYearCheckoutSubfield;
	private char ytdCheckoutSubfield;
	private char totalCheckoutSubfield;
	boolean useICode2Suppression;
	char    iCode2Subfield;
	String  sierraRecordFixedFieldsTag;
	char    sierraFixedFieldLanguageSubField = ' ';
	String  materialTypeSubField;
	char    bCode3Subfield;
	private boolean useItemBasedCallNumbers;
	private char    callNumberPrestampSubfield;
	private char    callNumberSubfield;
	private char    callNumberCutterSubfield;
	private char    callNumberPoststampSubfield;
	private char    volumeSubfield;
	char itemRecordNumberSubfieldIndicator;
	private char itemUrlSubfieldIndicator;
	boolean suppressItemlessBibs;

	private int numCharsToCreateFolderFrom;
	private boolean createFolderFromLeadingCharacters;

	private final HashMap<String, Integer> numberOfHoldsByIdentifier = new HashMap<>();

	HashMap<String, TranslationMap> translationMaps = new HashMap<>();
	//The indexing profile based translation maps
	private final ArrayList<TimeToReshelve> timesToReshelve = new ArrayList<>();

	private FormatDetermination formatDetermination;

	protected char isItemHoldableSubfield;

	IlsRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, logger, fullReindex);
		this.fullReindex = fullReindex;
		try {

			indexingProfileSourceDisplayName  = indexingProfileRS.getString("name");
			indexingProfileSource             = indexingProfileRS.getString("sourceName");
			individualMarcPath                = indexingProfileRS.getString("individualMarcPath");
			marcPath                          = indexingProfileRS.getString("marcPath");
			numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
			createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");
			recordNumberTag                   = indexingProfileRS.getString("recordNumberTag");
			suppressItemlessBibs              = indexingProfileRS.getBoolean("suppressItemlessBibs");
			itemTag                           = indexingProfileRS.getString("itemTag");
			itemRecordNumberSubfieldIndicator = getSubfieldIndicatorFromConfig(indexingProfileRS, "itemRecordNumber");
			callNumberPrestampSubfield        = getSubfieldIndicatorFromConfig(indexingProfileRS, "callNumberPrestamp");
			callNumberSubfield                = getSubfieldIndicatorFromConfig(indexingProfileRS, "callNumber");
			callNumberCutterSubfield          = getSubfieldIndicatorFromConfig(indexingProfileRS, "callNumberCutter");
			callNumberPoststampSubfield       = getSubfieldIndicatorFromConfig(indexingProfileRS, "callNumberPoststamp");
			useItemBasedCallNumbers           = indexingProfileRS.getBoolean("useItemBasedCallNumbers");
			volumeSubfield                    = getSubfieldIndicatorFromConfig(indexingProfileRS, "volume");

			locationSubfieldIndicator = getSubfieldIndicatorFromConfig(indexingProfileRS, "location");
			try {
				String pattern = indexingProfileRS.getString("nonHoldableLocations");
				if (pattern != null && !pattern.isEmpty()) {
					nonHoldableLocations = Pattern.compile("^(" + pattern + ")$");
				}
			} catch (Exception e) {
				logger.error("Could not load non holdable locations", e);
			}
			shelvingLocationSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "shelvingLocation");
			collectionSubfield       = getSubfieldIndicatorFromConfig(indexingProfileRS, "collection");

			String locationsToSuppress = indexingProfileRS.getString("locationsToSuppress");
			if (locationsToSuppress != null && !locationsToSuppress.isEmpty()) {
				locationsToSuppressPattern = Pattern.compile(locationsToSuppress);
			}
			String collectionsToSuppress = indexingProfileRS.getString("collectionsToSuppress");
			if (collectionsToSuppress != null && !collectionsToSuppress.isEmpty()) {
				collectionsToSuppressPattern = Pattern.compile(collectionsToSuppress);
			}
			String statusesToSuppress = indexingProfileRS.getString("statusesToSuppress");
			if (statusesToSuppress != null && !statusesToSuppress.isEmpty()) {
				statusesToSuppressPattern = Pattern.compile(statusesToSuppress);
			}
			String bCode3sToSuppress = indexingProfileRS.getString("bCode3sToSuppress");
			if (bCode3sToSuppress != null && !bCode3sToSuppress.isEmpty()) {
				bCode3sToSuppressPattern = Pattern.compile(bCode3sToSuppress);
			}
			String iCode2sToSuppress = indexingProfileRS.getString("iCode2sToSuppress");
			if (iCode2sToSuppress != null && !iCode2sToSuppress.isEmpty()) {
				iCode2sToSuppressPattern = Pattern.compile(iCode2sToSuppress);
			}
			String iTypesToSuppress = indexingProfileRS.getString("iTypesToSuppress");
			if (iTypesToSuppress != null && !iTypesToSuppress.isEmpty()) {
				iTypesToSuppressPattern = Pattern.compile(iTypesToSuppress);
			}

			itemUrlSubfieldIndicator = getSubfieldIndicatorFromConfig(indexingProfileRS, "itemUrl");

			formatSource              = indexingProfileRS.getString("formatSource");
			formatDeterminationMethod = indexingProfileRS.getString("formatDeterminationMethod");
			if (formatDeterminationMethod == null) {
				formatDeterminationMethod = "";
			}
			matTypesToIgnore = indexingProfileRS.getString("materialTypesToIgnore");
			if (matTypesToIgnore == null) {
				matTypesToIgnore = "";
			}
			specifiedFormat         = indexingProfileRS.getString("specifiedFormat");
			specifiedFormatCategory = indexingProfileRS.getString("specifiedFormatCategory");
			specifiedFormatBoost    = indexingProfileRS.getInt("specifiedFormatBoost");
			formatSubfield          = getSubfieldIndicatorFromConfig(indexingProfileRS, "format");
			barcodeSubfield         = getSubfieldIndicatorFromConfig(indexingProfileRS, "barcode");
			statusSubfieldIndicator = getSubfieldIndicatorFromConfig(indexingProfileRS, "status");
			try {
				String pattern = indexingProfileRS.getString("nonHoldableStatuses");
				if (pattern != null && !pattern.isEmpty()) {
					nonHoldableStatuses = Pattern.compile("^(" + pattern + ")$");
				}
			} catch (Exception e) {
				logger.error("Could not load non holdable statuses", e);
			}

			dueDateSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "dueDate");
			String dueDateFormat = indexingProfileRS.getString("dueDateFormat");
			if (!dueDateFormat.isEmpty()) {
				dueDateFormatter = new SimpleDateFormat(dueDateFormat);
			}

			ytdCheckoutSubfield      = getSubfieldIndicatorFromConfig(indexingProfileRS, "yearToDateCheckouts");
			lastYearCheckoutSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "lastYearCheckouts");
			totalCheckoutSubfield    = getSubfieldIndicatorFromConfig(indexingProfileRS, "totalCheckouts");

			iTypeSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "iType");
			try {
				String pattern = indexingProfileRS.getString("nonHoldableITypes");
				if (pattern != null && !pattern.isEmpty()) {
					nonHoldableITypes = Pattern.compile("^(" + pattern + ")$");
				}
			} catch (Exception e) {
				logger.error("Could not load non holdable iTypes", e);
			}

			dateCreatedSubfield              = getSubfieldIndicatorFromConfig(indexingProfileRS, "dateCreated");
			dateAddedFormat                  = indexingProfileRS.getString("dateCreatedFormat");
			lastCheckInSubfield              = getSubfieldIndicatorFromConfig(indexingProfileRS, "lastCheckinDate");
			lastCheckInFormat                = indexingProfileRS.getString("lastCheckinFormat");
			iCode2Subfield                   = getSubfieldIndicatorFromConfig(indexingProfileRS, "iCode2");
			useICode2Suppression             = indexingProfileRS.getBoolean("useICode2Suppression");
			sierraRecordFixedFieldsTag       = indexingProfileRS.getString("sierraRecordFixedFieldsTag");
			bCode3Subfield                   = getSubfieldIndicatorFromConfig(indexingProfileRS, "bCode3");
			materialTypeSubField             = indexingProfileRS.getString("materialTypeField");
			sierraFixedFieldLanguageSubField = getSubfieldIndicatorFromConfig(indexingProfileRS, "sierraLanguageFixedField");
			eContentSubfieldIndicator        = getSubfieldIndicatorFromConfig(indexingProfileRS, "eContentDescriptor");
			useEContentSubfield              = eContentSubfieldIndicator != ' ';
			doAutomaticEcontentSuppression   = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");

			loadTranslationMapsForProfile(pikaConn, indexingProfileRS.getLong("id"));
			formatDetermination = new FormatDetermination(indexingProfileRS, translationMaps, logger);

			loadTimeToReshelve(pikaConn, indexingProfileRS.getLong("id"));
			loadHoldsByIdentifier(pikaConn, logger);
		}catch (Exception e){
			logger.error("Error loading indexing profile information from database", e);
		}
	}

	private void loadTimeToReshelve(Connection pikaConn, long indexingProfileId){
		try (PreparedStatement getTimesToReshelveStmt = pikaConn.prepareStatement("SELECT * FROM time_to_reshelve WHERE indexingProfileId = ? ORDER by weight")) {
			getTimesToReshelveStmt.setLong(1, indexingProfileId);
			try (ResultSet timesToReshelveRS = getTimesToReshelveStmt.executeQuery()) {
				while (timesToReshelveRS.next()) {
					TimeToReshelve timeToReshelve = new TimeToReshelve();
					timeToReshelve.setLocations(timesToReshelveRS.getString("locations"));
					timeToReshelve.setNumHoursToOverride(timesToReshelveRS.getLong("numHoursToOverride"));
					timeToReshelve.setStatusToOverride(timesToReshelveRS.getString("statusCodeToOverride"));
					timeToReshelve.setStatus(timesToReshelveRS.getString("status"));
					timeToReshelve.setGroupedStatus(timesToReshelveRS.getString("groupedStatus"));
					timesToReshelve.add(timeToReshelve);
				}
			}
		} catch (SQLException e){
			logger.warn("Error loading time to reshelve rules", e);
		}
	}

	private void loadTranslationMapsForProfile(Connection pikaConn, long id) {
		try (
				PreparedStatement loadTranslationMapsStmt = pikaConn.prepareStatement("SELECT * FROM translation_maps WHERE indexingProfileId = ?");
				PreparedStatement loadTranslationMapValuesStmt = pikaConn.prepareStatement("SELECT * FROM translation_map_values WHERE translationMapId = ?")
		) {
			loadTranslationMapsStmt.setLong(1, id);
			try (ResultSet translationMapsRS = loadTranslationMapsStmt.executeQuery()) {
				while (translationMapsRS.next()) {
					String         mapName          = translationMapsRS.getString("name");
					TranslationMap translationMap   = new TranslationMap(indexingProfileSource, mapName, fullReindex, translationMapsRS.getBoolean("usesRegularExpressions"), logger);
					long           translationMapId = translationMapsRS.getLong("id");
					loadTranslationMapValuesStmt.setLong(1, translationMapId);
					try (ResultSet translationMapValuesRS = loadTranslationMapValuesStmt.executeQuery()) {
						while (translationMapValuesRS.next()) {
							translationMap.addValue(translationMapValuesRS.getString("value"), translationMapValuesRS.getString("translation"));
						}
					} catch (Exception e) {
						logger.error("Error loading translation map {}", mapName, e);
					}
					translationMaps.put(translationMap.getMapName(), translationMap);
				}
			}
		} catch (Exception e) {
			logger.error("Error loading translation maps", e);
		}
	}

	private void loadHoldsByIdentifier(Connection pikaConn, Logger logger) {
		try (
			PreparedStatement loadHoldsStmt = pikaConn.prepareStatement("SELECT ilsId, numHolds FROM ils_hold_summary", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet         holdsRS       = loadHoldsStmt.executeQuery()
		){
			while (holdsRS.next()) {
				numberOfHoldsByIdentifier.put(holdsRS.getString("ilsId"), holdsRS.getInt("numHolds"));
			}

		} catch (Exception e) {
			logger.error("Unable to load hold data", e);
		}
	}

	@Override
	public void processRecord(GroupedWorkSolr groupedWork, RecordIdentifier identifier, boolean loadedNovelistSeries){
		Record record = loadMarcRecordFromDisk(identifier);

		if (record != null){
			try{
				updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier, loadedNovelistSeries);
			}catch (Exception e) {
				logger.error("Error updating solr based on marc record", e);
			}
		}
	}

	private Record loadMarcRecordFromDisk(RecordIdentifier identifier) {
		Record record = null;
		String individualFilename = getFileForIlsRecord(identifier.getIdentifier());
		try {
			byte[] fileContents = Util.readFileBytes(individualFilename);
			//FileInputStream inputStream = new FileInputStream(individualFile);
			try (InputStream inputStream = new ByteArrayInputStream(fileContents)) {
				//Don't need to use a permissive reader here since we've written good individual MARCs as part of record grouping
				//Actually we do need to since we can still get MARC records over the max length.
				// Assuming we have correctly saved the individual MARC file in utf-8 encoding; and should handle in utf-8 as well
				MarcReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF8");
				if (marcReader.hasNext()) {
					record = marcReader.next();
				}
			}
		}catch (FileNotFoundException fe){
			logger.warn("Could not find MARC record at " + individualFilename + " for " + identifier);
		} catch (Exception e) {
			logger.error("Error reading data from ils file " + individualFilename, e);
		}
		return record;
	}

	private String getFileForIlsRecord(String recordNumber) {
		StringBuilder shortId = new StringBuilder(recordNumber.replace(".", ""));
		while (shortId.length() < 9){
			shortId.insert(0, "0");
		}

		String subFolderName;
		if (createFolderFromLeadingCharacters) {
			subFolderName = shortId.substring(0, numCharsToCreateFolderFrom);
		} else {
			subFolderName = shortId.substring(0, shortId.length() - numCharsToCreateFolderFrom);
		}

		String basePath = individualMarcPath + "/" + subFolderName;
		return basePath + "/" + shortId + ".mrc";
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, boolean loadedNovelistSeries) {
		//For ILS Records, we can create multiple different records, one for print and order items,
		//and one or more for eContent items.
		HashSet<RecordInfo> allRelatedRecords = new HashSet<>();

		try{
			//If the entire bib is suppressed, update stats and bail out now.
			if (isBibSuppressed(record)) {
				logger.debug("Bib record {} is suppressed, skipping", identifier);
				return;
			}

			// Let's first look for the print/order record
			RecordInfo recordInfo = groupedWork.addRelatedRecord(identifier);
			if (logger.isDebugEnabled()) {
				logger.debug("Added record for " + identifier + " work now has " + groupedWork.getNumRecords() + " records");
			}
			loadUnsuppressedPrintItems(groupedWork, recordInfo, identifier, record);
			loadOnOrderItems(groupedWork, recordInfo, record);
			//If we don't get anything remove the record we just added
			boolean isItemlessPhysicalRecordToRemove = false;
			if (checkIfBibShouldBeRemovedAsItemless(recordInfo)) {
				isItemlessPhysicalRecordToRemove = true;
				groupedWork.removeRelatedRecord(recordInfo);
				logger.debug("Removing related print record for {} because there are no print copies, no on order copies and suppress itemless bibs is on", identifier);
			}else{
				allRelatedRecords.add(recordInfo);
			}

			//Now look for any eContent that is defined within the ils
			List<RecordInfo> eContentRecords = loadUnsuppressedEContentItems(groupedWork, identifier, record);
			if (isItemlessPhysicalRecordToRemove && eContentRecords.isEmpty()){
				// If the ILS record is both an itemless record and isn't eContent skip further processing of the record
				return;
			}
			allRelatedRecords.addAll(eContentRecords);

			//Since print formats are loaded at the record level, do it after we have loaded items
			loadPrintFormatInformation(recordInfo, record);

			//Do the updates based on the overall bib (shared regardless of scoping)
			String primaryFormat = null;
			for (RecordInfo ilsRecord : allRelatedRecords) {
				primaryFormat = ilsRecord.getPrimaryFormat();
				if (primaryFormat != null){
					break;
				}
			}
			if (primaryFormat == null/* || primaryFormat.equals("Unknown")*/) {
				primaryFormat = "Unknown";
				//logger.info("No primary format for " + identifier + " found setting to unknown to load standard marc data");
			}
			updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier, primaryFormat, loadedNovelistSeries);

			//Special processing for ILS Records
			String fullDescription = Util.getCRSeparatedString(MarcUtil.getFieldList(record, "520a"));
			for (RecordInfo ilsRecord : allRelatedRecords) {
				String primaryFormatForRecord = ilsRecord.getPrimaryFormat();
				if (primaryFormatForRecord == null){
					primaryFormatForRecord = "Unknown";
				}
				groupedWork.addDescription(fullDescription, primaryFormatForRecord);
			}
			loadEditions(groupedWork, record, allRelatedRecords);
			loadPhysicalDescription(groupedWork, record, allRelatedRecords);
			loadLanguageDetails(groupedWork, record, allRelatedRecords, identifier);
			loadPublicationDetails(groupedWork, record, allRelatedRecords);
			loadSystemLists(groupedWork, record);

			if (record.getControlNumber() != null){
				groupedWork.addKeywords(record.getControlNumber());
			}

			//Do updates based on items
			loadPopularity(groupedWork, identifier);
			groupedWork.addBarcodes(MarcUtil.getFieldList(record, itemTag + barcodeSubfield));

			// Add Order Record Ids if they are different from item ids
			loadOrderIds(groupedWork, record);

			for (ItemInfo curItem : recordInfo.getRelatedItems()){
				String itemIdentifier = curItem.getItemIdentifier();
				if (itemIdentifier != null && !itemIdentifier.isEmpty()) {
					groupedWork.addAlternateId(itemIdentifier);
				}
			}

			for (RecordInfo recordInfoTmp: allRelatedRecords) {
				scopeItems(recordInfoTmp, groupedWork, record);
			}
		}catch (Exception e){
			logger.error("Error updating grouped work {} for MARC record with identifier {}", groupedWork.getId(),  identifier, e);
		}
	}

	boolean checkIfBibShouldBeRemovedAsItemless(RecordInfo recordInfo) {
		return !recordInfo.hasPrintCopies() && !recordInfo.hasOnOrderCopies() && suppressItemlessBibs;
	}

	protected boolean isBibSuppressed(Record record) {
		if (bCode3sToSuppressPattern != null && sierraRecordFixedFieldsTag != null && !sierraRecordFixedFieldsTag.isEmpty() && bCode3Subfield != ' ') {
			DataField sierraFixedField = record.getDataField(sierraRecordFixedFieldsTag);
			if (sierraFixedField != null){
				Subfield suppressionSubfield = sierraFixedField.getSubfield(bCode3Subfield);
				if (suppressionSubfield != null){
					String bCode3 = suppressionSubfield.getData().toLowerCase().trim();
					if (bCode3sToSuppressPattern.matcher(bCode3).matches()){
						logger.debug("Bib record is suppressed due to BCode3 {}", bCode3);
						return true;
					}
				}
			}
		}
		return false;
	}

	protected void loadSystemLists(GroupedWorkSolr groupedWork, Record record) {
		//By default, do nothing
	}

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record){
		//By default, do nothing
	}

	protected boolean createAndAddOrderItem(RecordInfo recordInfo, DataField curOrderField, String location, int copies) {
		ItemInfo itemInfo = new ItemInfo();
		if (curOrderField.getSubfield('a') == null){
			//Skip if we have no identifier
			return false;
		}
		String orderNumber = curOrderField.getSubfield('a').getData();
		itemInfo.setLocationCode(location);
		itemInfo.setItemIdentifier(orderNumber);
		itemInfo.setNumCopies(copies);
		itemInfo.setIsEContent(false);
		itemInfo.setIsOrderItem();
		itemInfo.setCallNumber("ON ORDER");
		itemInfo.setSortableCallNumber("ON ORDER");
		itemInfo.setDetailedStatus("On Order");
		Date tomorrow = new Date();
		tomorrow.setTime(tomorrow.getTime() + 1000 * 60 * 60 * 24);
		itemInfo.setDateAdded(tomorrow);
		//Format and Format Category should be set at the record level, so we don't need to set them here.

		//Add the library this is on order for
		itemInfo.setShelfLocation("On Order");

		recordInfo.addItem(itemInfo);

		return true;
	}

	private void loadScopeInfoForOrderItem(String location, String format, TreeSet<String> audiences, ItemInfo itemInfo, Record record) {
		//Shelf Location also include the name of the ordering branch if possible
		boolean hasLocationBasedShelfLocation = false;
		boolean hasSystemBasedShelfLocation = false;
		String originalUrl = itemInfo.geteContentUrl();
		for (Scope scope: indexer.getScopes()){
			Scope.InclusionResult result = scope.isItemPartOfScope(indexingProfileSource, location, null, audiences, format, true, true, false, record, originalUrl);
			if (result.isIncluded){
				ScopingInfo scopingInfo = itemInfo.addScope(scope);
				if (scopingInfo == null){
					logger.error("Could not add scoping information for " + scope.getScopeName() + " for item " + itemInfo.getFullRecordIdentifier());
					continue;
				}
				if (scope.isLocationScope()) {
					scopingInfo.setLocallyOwned(scope.isItemOwnedByScope(indexingProfileSource, location));
					if (scope.getLibraryScope() != null) {
						boolean libraryOwned = scope.getLibraryScope().isItemOwnedByScope(indexingProfileSource, location);
						scopingInfo.setLibraryOwned(libraryOwned);
					}else{
						//Check to see if the scope is both a library and location scope
						if (!scope.isLibraryScope()){
							logger.warn("Location scope " + scope.getScopeName() + " does not have an associated library getting scope for order item " + itemInfo.getItemIdentifier() + " - " + itemInfo.getFullRecordIdentifier());
							continue;
						}
					}
				}
				if (scope.isLibraryScope()) {
					boolean libraryOwned = scope.isItemOwnedByScope(indexingProfileSourceDisplayName, location);
					scopingInfo.setLibraryOwned(libraryOwned);
					//TODO: Should this be here or should this only happen for consortia?
					if (libraryOwned && itemInfo.getShelfLocation().equals("On Order")){
						itemInfo.setShelfLocation(scopingInfo.getScope().getFacetLabel() + " On Order");
					}
				}
				if (scopingInfo.isLocallyOwned()){
					if (scope.isLibraryScope() && !hasLocationBasedShelfLocation && !hasSystemBasedShelfLocation){
						hasSystemBasedShelfLocation = true;
					}
					if (scope.isLocationScope() && !hasLocationBasedShelfLocation){
						hasLocationBasedShelfLocation = true;
						//TODO: Decide if this code should be activated
						/*if (itemInfo.getShelfLocation().equals("On Order")) {
							itemInfo.setShelfLocation(scopingInfo.getScope().getFacetLabel() + "On Order");
						}*/
					}
				}
				scopingInfo.setAvailable(false);
				scopingInfo.setHoldable(true);
				scopingInfo.setStatus("On Order");
				scopingInfo.setGroupedStatus("On Order");
				if (originalUrl != null && !originalUrl.equals(result.localUrl)){
					scopingInfo.setLocalUrl(result.localUrl);
				}
			}
		}
	}

	protected void loadOrderIds(GroupedWorkSolr groupedWork, Record record) {
		//Load order ids from recordNumberTag
//		Set<String> recordIds = MarcUtil.getFieldList(record, recordNumberTag + "a"); //TODO: refactor to use the record number subfield indicator
//		for(String recordId : recordIds){
//			if (recordId.startsWith(".o")){
//				groupedWork.addAlternateId(recordId);
//			}
//		}
	}

	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record){
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
		if (logger.isDebugEnabled()) {
			logger.debug("Found " + itemRecords.size() + " items for record " + identifier);
		}
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField, identifier)){
				getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				//Can return null if the record does not have status and location
				//This happens with secondary call numbers sometimes.
			}else if (logger.isDebugEnabled()){
				logger.debug("item was suppressed");
			}
		}
	}

	RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, DataField itemField) {
		ItemInfo itemInfo     = new ItemInfo();
		String   itemLocation = getItemSubfieldData(locationSubfieldIndicator, itemField);

		itemInfo.setIsEContent(true);
		itemInfo.setLocationCode(itemLocation);
		itemInfo.setItemIdentifier(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField));
		itemInfo.setShelfLocation(getShelfLocationForItem(itemInfo, itemField, identifier));
		if (loadDateAddedFromRecord){
			loadDateAddedFromRecord(itemInfo, record, identifier);
		} else {
			loadDateAdded(identifier, itemField, itemInfo);
		}
		loadItemCallNumber(record, itemField, itemInfo);
		if (iTypeSubfield != ' ') {
			String iTypeValue = getItemSubfieldData(iTypeSubfield, itemField);
			if (iTypeValue != null && !iTypeValue.isEmpty()) {
				itemInfo.setITypeCode(iTypeValue);
				itemInfo.setIType(translateValue("itype", getItemSubfieldData(iTypeSubfield, itemField), identifier));
			}
		}
		if (collectionSubfield != ' ') {
			final String collectionValue = getItemSubfieldData(collectionSubfield, itemField);
			if (collectionValue != null && !collectionValue.isEmpty()) {
				itemInfo.setCollection(translateValue("collection", collectionValue, identifier));
			}
		}

		Subfield eContentSubfield = itemField.getSubfield(eContentSubfieldIndicator);
		if (eContentSubfield != null) {
			String eContentData = eContentSubfield.getData().trim();
			if (eContentData.indexOf(':') > 0) {
				String[] eContentFields = eContentData.split(":");
				//First element is the source, and we will always have at least the source and protection type
				// The ':' was once used to separate additional pieces of data.  Those pieces aren't needed anymore
				itemInfo.seteContentSource(eContentFields[0].trim());
			} else if (!eContentData.isEmpty()) {
				itemInfo.seteContentSource(eContentData);
			} else {
				itemInfo.seteContentSource(getILSeContentSourceType(record, itemField));
			}
		} else {
			//This is for a "less advanced" catalog, set some basic info
			itemInfo.seteContentSource(getILSeContentSourceType(record, itemField));
		}

		RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier.getIdentifier());
		relatedRecord.setSubSource(indexingProfileSource);
		relatedRecord.addItem(itemInfo);

		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		//Get the url if any
		Subfield urlSubfield = itemField.getSubfield(itemUrlSubfieldIndicator);
		if (urlSubfield != null) {
			//Item-level 856 (Gets exported into the itemUrlSubfield)
			itemInfo.seteContentUrl(urlSubfield.getData().trim());
		} else {
			loadEContentUrl(record, itemInfo, identifier);
		}
		itemInfo.setDetailedStatus("Available Online");

		return relatedRecord;
	}

	protected String getILSeContentSourceType(Record record, DataField itemField) {
		return "Unknown Source";
	}

	protected final SimpleDateFormat dateFormat008 = new SimpleDateFormat("yyMMdd");

	protected void loadDateAddedFromRecord(ItemInfo itemInfo, Record record, RecordIdentifier identifier){
		ControlField fixedField008 = (ControlField) record.getVariableField("008");
		if (fixedField008 != null){
			String dateAddData = fixedField008.getData();
			if (dateAddData != null && dateAddData.length() >= 6){
				String dateAddedStr = dateAddData.substring(0, 6);
				try {
					Date dateAdded = dateFormat008.parse(dateAddedStr);
					itemInfo.setDateAdded(dateAdded);
					return;
				} catch (ParseException e) {
					logger.error("Invalid date in 008 '{}' for {}", dateAddedStr, identifier);
				}
			}
		}

		// As fallback, use grouping first detected date
		Date dateAdded = indexer.getDateFirstDetected(identifier);
		itemInfo.setDateAdded(dateAdded);
	}

	protected void loadDateAdded(RecordIdentifier recordIdentifier, DataField itemField, ItemInfo itemInfo) {
		String dateAddedStr = getItemSubfieldData(dateCreatedSubfield, itemField);
		if (dateAddedStr != null && !dateAddedStr.isEmpty()) {
			try {
				if (dateAddedFormatter == null){
					dateAddedFormatter = new SimpleDateFormat(dateAddedFormat);
				}
				Date dateAdded = dateAddedFormatter.parse(dateAddedStr);
				itemInfo.setDateAdded(dateAdded);
			} catch (ParseException e) {
				logger.error("Error processing date added for record identifier {} profile {} using format {}", recordIdentifier, indexingProfileSourceDisplayName, dateAddedFormat, e);
			}
		}
	}

	private SimpleDateFormat dateAddedFormatter = null;
	private SimpleDateFormat lastCheckInFormatter = null;

	void getPrintIlsItem(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record, DataField itemField, RecordIdentifier identifier) {
		if (dateAddedFormatter == null){
			dateAddedFormatter = new SimpleDateFormat(dateAddedFormat);
		}
		if (lastCheckInFormatter == null && lastCheckInFormat != null && !lastCheckInFormat.isEmpty()){
			lastCheckInFormatter = new SimpleDateFormat(lastCheckInFormat);
			lastCheckInFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
			// Assume last check in dates are set in zulu time.
			// This is needed for timeToReshelve intervals to be calculated correctly
		}
		ItemInfo itemInfo = new ItemInfo();
		//Load base information from the Marc Record
		itemInfo.setItemIdentifier(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField));
		if (itemInfo.getItemIdentifier() == null){
			logger.info("Found item with out identifier info {}", identifier);
		}

		String itemStatus   = getItemStatus(itemField, identifier);
		String itemLocation = getItemSubfieldData(locationSubfieldIndicator, itemField);
		itemInfo.setLocationCode(itemLocation);

		//if the status and location are null, we can assume this is not a valid item
		if (itemNotValid(itemStatus, itemLocation)) return;
		if (itemStatus == null || itemStatus.isEmpty()) {
			logger.warn("Item contained no status value for item {} for location {} in record {}", itemInfo.getItemIdentifier(), itemLocation, identifier);
		}
		itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));
		// Process Collection ahead of shelf location, so that the collection can be used for Polaris shelf location

		setShelfLocationCode(itemField, itemInfo, identifier);
		itemInfo.setShelfLocation(getShelfLocationForItem(itemInfo, itemField, identifier));

		setItemIsHoldableCode(itemField, itemInfo, identifier);

		if (loadDateAddedFromRecord){
			loadDateAddedFromRecord(itemInfo, record, identifier);
		} else {
			loadDateAdded(identifier, itemField, itemInfo);
		}
		getDueDate(itemField, itemInfo);

		if (iTypeSubfield != ' ') {
			itemInfo.setITypeCode(getItemSubfieldData(iTypeSubfield, itemField));
			itemInfo.setIType(translateValue("itype", getItemSubfieldData(iTypeSubfield, itemField), identifier));
		}

		double itemPopularity = getItemPopularity(itemField, identifier);
		groupedWork.addPopularity(itemPopularity);

		loadItemCallNumber(record, itemField, itemInfo);


		if (lastCheckInFormatter != null) {
			String lastCheckInDate = getItemSubfieldData(lastCheckInSubfield, itemField);
			Date lastCheckIn = null;
			if (lastCheckInDate != null && !lastCheckInDate.isEmpty())
				try {
					lastCheckIn = lastCheckInFormatter.parse(lastCheckInDate);
				} catch (ParseException e) {
					if (logger.isDebugEnabled()) {
						logger.debug("Could not parse check in date " + lastCheckInDate, e);
					}
				}
			itemInfo.setLastCheckinDate(lastCheckIn);
		}

		//set status towards the end so we can access date added and other things that may need to
		itemInfo.setStatusCode(itemStatus);
		if (itemStatus != null) {
			setDetailedStatus(itemInfo, itemField, itemStatus, identifier);
		}

		if (formatSource.equals("item") && formatSubfield != ' '){
			String format = getItemSubfieldData(formatSubfield, itemField);
			if (format != null) {
				itemInfo.setFormat(translateValue("format", format, identifier));
				itemInfo.setFormatCategory(translateValue("format_category", format, identifier));
				String formatBoost = translateValue("format_boost", format, identifier);
				try {
					if (formatBoost != null && !formatBoost.isEmpty()) {
						recordInfo.setFormatBoost(Integer.parseInt(formatBoost));
					}
				} catch (Exception e) {
					logger.warn("Could not get boost for format {}", format);
				}
			}
		}

		//This is done later so we don't need to do it here.
		//loadScopeInfoForPrintIlsItem(recordInfo, groupedWork.getTargetAudiences(), itemInfo, record);

		groupedWork.addKeywords(itemLocation);
		// Adds untranslated location codes, why?
		//TODO: explain use-case here; otherwise this looks unneeded and should be removed


		recordInfo.addItem(itemInfo);
	}

	protected void getDueDate(DataField itemField, ItemInfo itemInfo) {
		String dueDateStr = getItemSubfieldData(dueDateSubfield, itemField);
		itemInfo.setDueDate(dueDateStr);
	}

	protected void setShelfLocationCode(DataField itemField, ItemInfo itemInfo, RecordIdentifier recordIdentifier) {
		if (shelvingLocationSubfield != ' '){
			itemInfo.setShelfLocationCode(getItemSubfieldData(shelvingLocationSubfield, itemField));
		}else {
			itemInfo.setShelfLocationCode(getItemSubfieldData(locationSubfieldIndicator, itemField));
		}
	}

	protected void setItemIsHoldableCode(DataField itemField, ItemInfo itemInfo, RecordIdentifier recordIdentifier) {
		// Do not by default
	}

	void scopeItems(RecordInfo recordInfo, GroupedWorkSolr groupedWork, Record record){
		for (ItemInfo itemInfo : recordInfo.getRelatedItems()){
			if (itemInfo.isOrderItem()){
				loadScopeInfoForOrderItem(itemInfo.getLocationCode(), recordInfo.getPrimaryFormat(), groupedWork.getTargetAudiences(), itemInfo, record);
			}else if (itemInfo.isEContent()){
				loadScopeInfoForEContentItem(groupedWork, itemInfo, record);
			}else{
				loadScopeInfoForPrintIlsItem(recordInfo, groupedWork.getTargetAudiences(), itemInfo, record);
			}
		}
	}

	private void loadScopeInfoForEContentItem(GroupedWorkSolr groupedWork, ItemInfo itemInfo, Record record) {
		String itemLocation = itemInfo.getLocationCode();
		String originalUrl = itemInfo.geteContentUrl();
		for (Scope curScope : indexer.getScopes()){
			String format = itemInfo.getFormat();
			if (format == null){
				format = itemInfo.getRecordInfo().getPrimaryFormat();
			}
			Scope.InclusionResult result = curScope.isItemPartOfScope(indexingProfileSource, itemLocation, null, groupedWork.getTargetAudiences(), format, false, false, true, record, originalUrl);
			if (result.isIncluded){
				ScopingInfo scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(true);
				scopingInfo.setStatus("Available Online");
				scopingInfo.setGroupedStatus("Available Online");
				scopingInfo.setHoldable(false);
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(indexingProfileSource, itemLocation));
					if (curScope.getLibraryScope() != null) {
						scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(indexingProfileSource, itemLocation));
					}
				}
				if (curScope.isLibraryScope()) {
					scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(indexingProfileSource, itemLocation));
				}
				//Check to see if we need to do url rewriting
				if (originalUrl != null && !originalUrl.equals(result.localUrl)){
					scopingInfo.setLocalUrl(result.localUrl);
				}
			}
		}
	}

	private void loadScopeInfoForPrintIlsItem(RecordInfo recordInfo, TreeSet<String> audiences, ItemInfo itemInfo, Record record) {
		//Determine Availability
		boolean available = isItemAvailable(itemInfo);

		//Determine which scopes have access to this record
		//TODO: redundancy: each display function calls getOverriddenStatus() itself
		String displayStatus        = getDisplayStatus(itemInfo, recordInfo.getRecordIdentifier());
		String groupedDisplayStatus = getDisplayGroupedStatus(itemInfo, recordInfo.getRecordIdentifier());
		String overiddenStatus      = getOverriddenStatus(itemInfo, true);
		if (overiddenStatus != null && !overiddenStatus.equals("On Shelf") && !overiddenStatus.equals("Library Use Only") && !overiddenStatus.equals("Available Online")){
			available = false;
		}

		String itemLocation    = itemInfo.getLocationCode();

		HoldabilityInformation isHoldableUnscoped = isItemHoldableUnscoped(itemInfo);
		BookabilityInformation isBookableUnscoped = isItemBookableUnscoped();
		String                 originalUrl        = itemInfo.geteContentUrl();
		String                 primaryFormat      = recordInfo.getPrimaryFormat();
		for (Scope curScope : indexer.getScopes()) {
			//Check to see if the record is holdable for this scope
			HoldabilityInformation isHoldable = isItemHoldable(itemInfo, curScope, isHoldableUnscoped);

			Scope.InclusionResult result = curScope.isItemPartOfScope(indexingProfileSource, itemLocation, itemInfo.getITypeCode(), audiences, primaryFormat, isHoldable.isHoldable(), false, false, record, originalUrl);
			if (result.isIncluded){
				BookabilityInformation isBookable  = isItemBookable(itemInfo, curScope, isBookableUnscoped);
				ScopingInfo            scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(available);
				scopingInfo.setHoldable(isHoldable.isHoldable());
				scopingInfo.setHoldablePTypes(isHoldable.getHoldablePTypes());
				scopingInfo.setBookable(isBookable.isBookable());
				scopingInfo.setBookablePTypes(isBookable.getBookablePTypes());

				scopingInfo.setInLibraryUseOnly(isLibraryUseOnly(itemInfo));

				scopingInfo.setStatus(displayStatus);
				scopingInfo.setGroupedStatus(groupedDisplayStatus);
				if (originalUrl != null && !originalUrl.equals(result.localUrl)){
					scopingInfo.setLocalUrl(result.localUrl);
				}
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation));
					if (curScope.getLibraryScope() != null) {
						scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation));
					}
				}
				if (curScope.isLibraryScope()) {
					scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation));
				}
			}
		}
	}

	protected boolean isLibraryUseOnly(ItemInfo itemInfo) {
		return false;
	}

	protected void setDetailedStatus(ItemInfo itemInfo, DataField itemField, String itemStatus, RecordIdentifier identifier) {
		//See if we need to override based on the last check in date
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			itemInfo.setDetailedStatus(overriddenStatus);
		}else {
			itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
		}
	}

	String getOverriddenStatus(ItemInfo itemInfo, boolean groupedStatus) {
		String overriddenStatus = null;
		if (!timesToReshelve.isEmpty() && itemInfo.getLastCheckinDate() != null) {
			for (TimeToReshelve timeToReshelve : timesToReshelve) {
				if (itemInfo.getStatusCode().equalsIgnoreCase(timeToReshelve.getStatusToOverride())) {
					// Compare statuses first since that is simpler than location code regexes
					if (timeToReshelve.getLocationsPattern().matcher(itemInfo.getLocationCode()).matches()) {
						long now = new Date().getTime();
						// Only get the time if a timeToReshelve rule applies, since this will be exception rather than norm.
						// Considered using the indexing start time, so that one value is used,
						// but a full reindex may run over many hours so the current time is more appropriate.
						if (now - itemInfo.getLastCheckinDate().getTime() <= timeToReshelve.getNumHoursToOverride() * 60 * 60 * 1000) {
							if (groupedStatus){
								overriddenStatus = timeToReshelve.getGroupedStatus();
							} else{
								overriddenStatus = timeToReshelve.getStatus();
							}
							break;
						}
					}
				}
			}
		}
		return overriddenStatus;
	}

	protected String getDisplayGroupedStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, true);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			return translateValue("item_grouped_status", itemInfo.getStatusCode(), identifier);
		}
	}

	protected String getDisplayStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			return translateValue("item_status", itemInfo.getStatusCode(), identifier);
		}
	}

	protected double getItemPopularity(DataField itemField, RecordIdentifier identifier) {
		String totalCheckoutsField = getItemSubfieldData(totalCheckoutSubfield, itemField);
		int totalCheckouts = 0;
		if (totalCheckoutsField != null){
			try{
				totalCheckouts = Integer.parseInt(totalCheckoutsField);
			}catch (NumberFormatException e){
				logger.warn("Did not get a number for total checkouts. Got " + totalCheckoutsField + " for " + identifier);
			}

		}
		String ytdCheckoutsField = getItemSubfieldData(ytdCheckoutSubfield, itemField);
		int ytdCheckouts = 0;
		if (ytdCheckoutsField != null){
			try {
				ytdCheckouts = Integer.parseInt(ytdCheckoutsField);
			} catch (NumberFormatException e) {
				logger.warn("Did not get a number for year to date checkouts. Got " + ytdCheckoutsField + " for " + identifier);
			}
		}
		String lastYearCheckoutsField = getItemSubfieldData(lastYearCheckoutSubfield, itemField);
		int lastYearCheckouts = 0;
		if (lastYearCheckoutsField != null){
			try {
				lastYearCheckouts = Integer.parseInt(lastYearCheckoutsField);
			} catch (NumberFormatException e) {
				logger.warn("Did not get a number for last year checkouts. Got " + lastYearCheckoutsField + " for " + identifier);
			}
		}
		double itemPopularity = ytdCheckouts + .5 * (lastYearCheckouts) + .1 * (totalCheckouts - lastYearCheckouts - ytdCheckouts);
		if (itemPopularity == 0){
			itemPopularity = 1;
		}
		return itemPopularity;
	}

	/**
	 * Check if the item is invalid
	 */
	protected boolean itemNotValid(String itemStatus, String itemLocation) {
		return itemStatus == null && itemLocation == null;
	}

	void loadItemCallNumber(Record record, DataField itemField, ItemInfo itemInfo) {
		String volume = null;
		if (itemField != null){
			volume = getItemSubfieldData(volumeSubfield, itemField);
		}
		if (useItemBasedCallNumbers && itemField != null) {
			String callNumberPreStamp  = getItemSubfieldDataWithoutTrimming(callNumberPrestampSubfield, itemField);
			String callNumber          = getItemSubfieldDataWithoutTrimming(callNumberSubfield, itemField);
			String callNumberCutter    = getItemSubfieldDataWithoutTrimming(callNumberCutterSubfield, itemField);
			String callNumberPostStamp = getItemSubfieldData(callNumberPoststampSubfield, itemField);

			StringBuilder fullCallNumber     = new StringBuilder();
			StringBuilder sortableCallNumber = new StringBuilder();
			if (callNumberPreStamp != null) {
				fullCallNumber.append(callNumberPreStamp);
			}
			if (callNumber != null){
				addTrailingSpace(fullCallNumber);
				fullCallNumber.append(callNumber);
				sortableCallNumber.append(callNumber);
			}
			if (callNumberCutter != null){
				addTrailingSpace(fullCallNumber);
				fullCallNumber.append(callNumberCutter);
				addTrailingSpace(sortableCallNumber);
				sortableCallNumber.append(callNumberCutter);
			}
			if (callNumberPostStamp != null){
				addTrailingSpace(fullCallNumber);
				fullCallNumber.append(callNumberPostStamp);
				addTrailingSpace(sortableCallNumber);
				sortableCallNumber.append(callNumberPostStamp);
			}
			if (fullCallNumber.length() > 0 && volume != null && !volume.isEmpty()){
				addTrailingSpace(fullCallNumber);
				fullCallNumber.append(volume);
			}
			if (fullCallNumber.length() > 0){
				itemInfo.setCallNumber(fullCallNumber.toString().trim());
				itemInfo.setSortableCallNumber(sortableCallNumber.toString().trim());
				return;
			}
		}
		// Attempt to build bib-level call number now
		StringBuilder callNumber = null;
		if (use099forBibLevelCallNumbers()) {
			DataField localCallNumberField = record.getDataField("099");
			if (localCallNumberField != null) {
				callNumber = new StringBuilder();
				for (Subfield curSubfield : localCallNumberField.getSubfields()) {
					callNumber.append(" ").append(curSubfield.getData().trim());
				}
			}
		}
		//MDN #ARL-217 do not use 099 as a call number
		if (callNumber == null) {
			DataField deweyCallNumberField = record.getDataField("092");
			if (deweyCallNumberField != null) {
				callNumber = new StringBuilder();
				for (Subfield curSubfield : deweyCallNumberField.getSubfields()) {
					callNumber.append(" ").append(curSubfield.getData().trim());
				}
			}
		}
		// Sacramento - look in the 932
		if (callNumber == null) {
			DataField sacramentoCallNumberField = record.getDataField("932");
			if (sacramentoCallNumberField != null) {
				callNumber = new StringBuilder();
				for (Subfield curSubfield : sacramentoCallNumberField.getSubfields()) {
					callNumber.append(" ").append(curSubfield.getData().trim());
				}
			}
		}
		if (callNumber != null) {
			if (volume != null && !volume.isEmpty() && !callNumber.toString().endsWith(volume)){
				addTrailingSpace(callNumber);
				callNumber.append(volume);
			}
			final String str = callNumber.toString().trim();
			itemInfo.setCallNumber(str);
			itemInfo.setSortableCallNumber(str);
			return;
		}
		// Create an item level call number that is just a volume See D-782
		// This is needed for periodicals which may only have the volume part for the call number
		// (It is also needed for when selecting an item in item-level holds in Sierra)
		if (useItemBasedCallNumbers && volume != null && !volume.isEmpty()){
			itemInfo.setCallNumber(volume);
			itemInfo.setSortableCallNumber(volume);
		}
	}

	private void addTrailingSpace(StringBuilder stringBuilder) {
		if (stringBuilder.length() > 0 && stringBuilder.charAt(stringBuilder.length() - 1) != ' ') {
			stringBuilder.append(' ');
		}
	}

	protected boolean use099forBibLevelCallNumbers() {
		return true;
	}

	private final HashMap<String, Boolean> iTypesThatHaveHoldabilityChecked    = new HashMap<>();
	private final HashMap<String, Boolean> locationsThatHaveHoldabilityChecked = new HashMap<>();
	private final HashMap<String, Boolean> statusesThatHaveHoldabilityChecked  = new HashMap<>();

	protected HoldabilityInformation isItemHoldableUnscoped(ItemInfo itemInfo){
		String itemItypeCode =  itemInfo.getITypeCode();
		if (nonHoldableITypes != null && itemItypeCode != null && !itemItypeCode.isEmpty()){
			if (!iTypesThatHaveHoldabilityChecked.containsKey(itemItypeCode)){
				iTypesThatHaveHoldabilityChecked.put(itemItypeCode, !nonHoldableITypes.matcher(itemItypeCode).matches());
			}
			if (!iTypesThatHaveHoldabilityChecked.get(itemItypeCode)){
				return new HoldabilityInformation(false, new HashSet<>());
			}
		}
		String itemLocationCode =  itemInfo.getLocationCode();
		if (nonHoldableLocations != null && itemLocationCode != null && !itemLocationCode.isEmpty()){
			if (!locationsThatHaveHoldabilityChecked.containsKey(itemLocationCode)){
				locationsThatHaveHoldabilityChecked.put(itemLocationCode, !nonHoldableLocations.matcher(itemLocationCode).matches());
			}
			if (!locationsThatHaveHoldabilityChecked.get(itemLocationCode)){
				return new HoldabilityInformation(false, new HashSet<>());
			}
		}
		String itemStatusCode = itemInfo.getStatusCode();
		if (nonHoldableStatuses != null && itemStatusCode != null && !itemStatusCode.isEmpty()){
			if (!statusesThatHaveHoldabilityChecked.containsKey(itemStatusCode)){
				statusesThatHaveHoldabilityChecked.put(itemStatusCode, !nonHoldableStatuses.matcher(itemStatusCode).matches());
			}
			if (!statusesThatHaveHoldabilityChecked.get(itemStatusCode)){
				return new HoldabilityInformation(false, new HashSet<>());
			}
		}
		return new HoldabilityInformation(true, new HashSet<>());
	}

	protected HoldabilityInformation isItemHoldable(ItemInfo itemInfo, Scope curScope, HoldabilityInformation isHoldableUnscoped){
		return isHoldableUnscoped;
	}

	private BookabilityInformation isItemBookableUnscoped(){
		return new BookabilityInformation(false, new HashSet<Long>());
	}

	protected BookabilityInformation isItemBookable(ItemInfo itemInfo, Scope curScope, BookabilityInformation isBookableUnscoped) {
		return isBookableUnscoped;
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, RecordIdentifier identifier) {
		String shelfLocation = null;
		if (itemField != null) {
			shelfLocation = getItemSubfieldData(locationSubfieldIndicator, itemField);
		}
		if (shelfLocation == null || shelfLocation.isEmpty() || shelfLocation.equals("none")){
			return "";
		}else {
			return translateValue("shelf_location", shelfLocation, identifier);
		}
	}

	protected String getItemStatus(DataField itemField, RecordIdentifier recordIdentifier){
		return getItemSubfieldData(statusSubfieldIndicator, itemField);
	}

	protected abstract boolean isItemAvailable(ItemInfo itemInfo);

	String getItemSubfieldData(char subfieldIndicator, DataField itemField) {
		if (subfieldIndicator == ' '){
			return null;
		}else {
//			return itemField.getSubfield(subfieldIndicator) != null ? itemField.getSubfield(subfieldIndicator).getData().trim() : null;

			List<Subfield> subfields = itemField.getSubfields(subfieldIndicator);
			if (subfields.size() == 1) {
				return subfields.get(0).getData().trim();
			} else if (subfields.isEmpty()) {
				return null;
			} else {
				StringBuilder subfieldData = new StringBuilder();
				for (Subfield subfield:subfields) {
					String trimmedValue = subfield.getData().trim();
					boolean okToAdd = false;
					if (trimmedValue.isEmpty()){
						continue;
					}
					try {
						if (subfieldData.length() == 0) {
							okToAdd = true;
						} else if (subfieldData.length() < trimmedValue.length()) {
							okToAdd = true;
						} else if (!subfieldData.substring(subfieldData.length() - trimmedValue.length()).equals(trimmedValue)) {
							okToAdd = true;
						}
					}catch (Exception e){
						logger.error("Error determining if the new value of subfield is already part of the string", e);
					}
					if (okToAdd) {
						addTrailingSpace(subfieldData);
						subfieldData.append(trimmedValue);
					}else if (logger.isDebugEnabled()){
						logger.debug("Not appending subfield because the value looks redundant");
					}
				}
				return subfieldData.toString().trim();
			}

		}
	}

	private String getItemSubfieldDataWithoutTrimming(char subfieldIndicator, DataField itemField) {
		if (subfieldIndicator == ' '){
			return null;
		}else {
//			return itemField.getSubfield(subfieldIndicator) != null ? itemField.getSubfield(subfieldIndicator).getData() : null;

			List<Subfield> subfields = itemField.getSubfields(subfieldIndicator);
			if (subfields.size() == 1) {
				return subfields.get(0).getData();
			} else if (subfields.isEmpty()) {
				return null;
			} else {
				StringBuilder subfieldData = new StringBuilder();
				for (Subfield subfield:subfields) {
					addTrailingSpace(subfieldData);
					subfieldData.append(subfield.getData());
				}
				return subfieldData.toString();
			}
		}
	}

	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record){
		return new ArrayList<>();
	}

	void loadPopularity(GroupedWorkSolr groupedWork, RecordIdentifier identifier) {
		//Add popularity based on the number of holds (we have already done popularity for prior checkouts)
		//Active holds indicate that a title is more interesting so we will count each hold at double value
		double popularity = 2 * getIlsHoldsForTitle(identifier);
		groupedWork.addPopularity(popularity);
	}

	private int getIlsHoldsForTitle(RecordIdentifier identifier) {
		return numberOfHoldsByIdentifier.getOrDefault(identifier.getIdentifier(), 0);
	}

	protected boolean isItemSuppressed(DataField curItem, RecordIdentifier identifier) {
		if (statusesToSuppressPattern != null && statusSubfieldIndicator != ' ') {
			String status = getItemStatus(curItem, identifier);
			if (status == null) { // suppress if subfield is missing
				return true;
			} else if (statusesToSuppressPattern.matcher(status).matches()) {
					return true;
				}
		}
		if (locationsToSuppressPattern != null && locationSubfieldIndicator != ' ') {
			Subfield locationSubfield = curItem.getSubfield(locationSubfieldIndicator);
			if (locationSubfield == null){ // suppress if subfield is missing
				return true;
			}else if (locationsToSuppressPattern.matcher(locationSubfield.getData().trim()).matches()) {
				return true;
			}
		}
		if (collectionsToSuppressPattern != null && collectionSubfield != ' '){
			Subfield collectionSubfieldValue = curItem.getSubfield(collectionSubfield);
			// Do not suppress if there isn't a collection subfield
			if (collectionSubfieldValue != null && collectionsToSuppressPattern.matcher(collectionSubfieldValue.getData().trim()).matches()) {
				return true;
			}
		}
		if (iTypesToSuppressPattern != null && iTypeSubfield != ' '){
			Subfield iTypeSubfieldValue = curItem.getSubfield(iTypeSubfield);
			if (iTypeSubfieldValue == null){ // suppress if subfield is missing
				return true;
			}else{
				String iType = iTypeSubfieldValue.getData().trim();
				if (iTypesToSuppressPattern.matcher(iType).matches()){
					if (logger.isDebugEnabled()) {
						logger.debug("Item record is suppressed due to Itype " + iType);
					}
					return true;
				}
			}
		}
		if (useICode2Suppression && iCode2sToSuppressPattern != null && iCode2Subfield != ' ') {
			Subfield icode2Subfield = curItem.getSubfield(iCode2Subfield);
			if (icode2Subfield != null) {
				String iCode2 = icode2Subfield.getData().toLowerCase().trim();

				//Suppress iCode2 codes
				if (iCode2sToSuppressPattern.matcher(iCode2).matches()) {
					logger.debug("Item record is suppressed due to ICode2 {}", iCode2);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine Record Format(s)
	 */
	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
			formatDetermination.loadPrintFormatInformation(recordInfo, record);
	}

	/**
	 * Load information about eContent formats.
	 *
	 * @param record         The MARC record information
	 * @param econtentRecord The record to load format information for
	 * @param econtentItem   The item to load format information for
	 */
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
			formatDetermination.loadEContentFormatInformation(record, econtentRecord, econtentItem);
	}

	protected char getSubfieldIndicatorFromConfig(ResultSet indexingProfileRS, String subfieldName) throws SQLException{
		String subfieldString = indexingProfileRS.getString(subfieldName);
		char subfield = ' ';
		if (!indexingProfileRS.wasNull() && !subfieldString.isEmpty())  {
			subfield = subfieldString.charAt(0);
		}
		return subfield;
	}

	public String translateValue(String mapName, String value, RecordIdentifier identifier){
		return translateValue(mapName, value, identifier, true);
	}
	public String translateValue(String mapName, String value, RecordIdentifier identifier, boolean reportErrors){
		if (value == null){
			return null;
		}
		TranslationMap translationMap = translationMaps.get(mapName);
		String translatedValue;
		if (translationMap == null){
			logger.error("Unable to find translation map for " + mapName + " in profile " + indexingProfileSource);
			translatedValue = value;
		}else{
			translatedValue = translationMap.translateValue(value, identifier, reportErrors);
		}
		return translatedValue;
	}

	HashSet<String> translateCollection(String mapName, Set<String> values, RecordIdentifier identifier) {
		TranslationMap translationMap = translationMaps.get(mapName);
		HashSet<String> translatedValues;
		if (translationMap == null){
			logger.error("Unable to find translation map for " + mapName + " in profile " + indexingProfileSource);
//			if (values instanceof HashSet){
//				translatedValues = (HashSet<String>)values;
//			}else{
			translatedValues = new HashSet<>(values);
//			}

		}else{
			translatedValues = translationMap.translateCollection(values, identifier);
		}
		return translatedValues;

	}

//	private Boolean find4KUltraBluRayPhrases(String subject) {
//		subject = subject.toLowerCase();
//		return
//			subject.contains("4k ultra hd blu-ray") ||
//			subject.contains("4k ultra hd bluray") ||
//			subject.contains("4k ultrahd blu-ray") ||
//			subject.contains("4k ultrahd bluray") ||
//			subject.contains("4k uh blu-ray") ||
//			subject.contains("4k uh bluray") ||
//			subject.contains("4k ultra high-definition blu-ray") ||
//			subject.contains("4k ultra high-definition bluray") ||
//			subject.contains("4k ultra high definition blu-ray") ||
//			subject.contains("4k ultra high definition bluray")
//			;
//	}
}
