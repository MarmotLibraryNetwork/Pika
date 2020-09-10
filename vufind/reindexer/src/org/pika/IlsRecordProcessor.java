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
	char locationSubfieldIndicator;
	private Pattern nonHoldableLocations;
	Pattern statusesToSuppressPattern    = null;
	Pattern locationsToSuppressPattern   = null;
	Pattern collectionsToSuppressPattern = null;
	Pattern iTypesToSuppressPattern      = null;
	Pattern iCode2sToSuppressPattern     = null;
	Pattern bCode3sToSuppressPattern     = null;
	char    subLocationSubfield;
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

	//Fields for loading order information
	private String orderTag;
	private char orderLocationSubfield;
	private char singleOrderLocationSubfield;
	private char orderCopiesSubfield;
	private char orderStatusSubfield;
	private char orderCode3Subfield;

	private boolean addOnOrderShelfLocations = false;

	private int numCharsToCreateFolderFrom;
	private boolean createFolderFromLeadingCharacters;

	private HashMap<String, Integer> numberOfHoldsByIdentifier = new HashMap<>();

	HashMap<String, TranslationMap> translationMaps = new HashMap<>();
	private ArrayList<TimeToReshelve> timesToReshelve = new ArrayList<>();

	private FormatDetermination formatDetermination;

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
				if (pattern != null && pattern.length() > 0) {
					nonHoldableLocations = Pattern.compile("^(" + pattern + ")$");
				}
			} catch (Exception e) {
				logger.error("Could not load non holdable locations", e);
			}
			subLocationSubfield      = getSubfieldIndicatorFromConfig(indexingProfileRS, "subLocation");
			shelvingLocationSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "shelvingLocation");
			collectionSubfield       = getSubfieldIndicatorFromConfig(indexingProfileRS, "collection");

			String locationsToSuppress = indexingProfileRS.getString("locationsToSuppress");
			if (locationsToSuppress != null && locationsToSuppress.length() > 0) {
				locationsToSuppressPattern = Pattern.compile(locationsToSuppress);
			}
			String collectionsToSuppress = indexingProfileRS.getString("collectionsToSuppress");
			if (collectionsToSuppress != null && collectionsToSuppress.length() > 0) {
				collectionsToSuppressPattern = Pattern.compile(collectionsToSuppress);
			}
			String statusesToSuppress = indexingProfileRS.getString("statusesToSuppress");
			if (statusesToSuppress != null && statusesToSuppress.length() > 0) {
				statusesToSuppressPattern = Pattern.compile(statusesToSuppress);
			}
			String bCode3sToSuppress = indexingProfileRS.getString("bCode3sToSuppress");
			if (bCode3sToSuppress != null && bCode3sToSuppress.length() > 0) {
				bCode3sToSuppressPattern = Pattern.compile(bCode3sToSuppress);
			}
			String iCode2sToSuppress = indexingProfileRS.getString("iCode2sToSuppress");
			if (iCode2sToSuppress != null && iCode2sToSuppress.length() > 0) {
				iCode2sToSuppressPattern = Pattern.compile(iCode2sToSuppress);
			}
			String iTypesToSuppress = indexingProfileRS.getString("iTypesToSuppress");
			if (iTypesToSuppress != null && iTypesToSuppress.length() > 0) {
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
				if (pattern != null && pattern.length() > 0) {
					nonHoldableStatuses = Pattern.compile("^(" + pattern + ")$");
				}
			} catch (Exception e) {
				logger.error("Could not load non holdable statuses", e);
			}

			dueDateSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "dueDate");
			String dueDateFormat = indexingProfileRS.getString("dueDateFormat");
			if (dueDateFormat.length() > 0) {
				dueDateFormatter = new SimpleDateFormat(dueDateFormat);
			}

			ytdCheckoutSubfield      = getSubfieldIndicatorFromConfig(indexingProfileRS, "yearToDateCheckouts");
			lastYearCheckoutSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "lastYearCheckouts");
			totalCheckoutSubfield    = getSubfieldIndicatorFromConfig(indexingProfileRS, "totalCheckouts");

			iTypeSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "iType");
			try {
				String pattern = indexingProfileRS.getString("nonHoldableITypes");
				if (pattern != null && pattern.length() > 0) {
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

			orderTag                    = indexingProfileRS.getString("orderTag");
			orderLocationSubfield       = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderLocation");
			singleOrderLocationSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderLocationSingle");
			orderCopiesSubfield         = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderCopies");
			orderStatusSubfield         = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderStatus");
			orderCode3Subfield          = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderCode3");

			loadTranslationMapsForProfile(pikaConn, indexingProfileRS.getLong("id"));
			formatDetermination = new FormatDetermination(indexingProfileRS, translationMaps, logger);

			loadTimeToReshelve(pikaConn, indexingProfileRS.getLong("id"));
			loadHoldsByIdentifier(pikaConn, logger);
		}catch (Exception e){
			logger.error("Error loading indexing profile information from database", e);
		}
	}

	private void loadTimeToReshelve(Connection pikaConn, long id) throws SQLException{
		PreparedStatement getTimesToReshelveStmt = pikaConn.prepareStatement("SELECT * FROM time_to_reshelve WHERE indexingProfileId = ? ORDER by weight");
		getTimesToReshelveStmt.setLong(1, id);
		ResultSet timesToReshelveRS = getTimesToReshelveStmt.executeQuery();
		while (timesToReshelveRS.next()){
			TimeToReshelve timeToReshelve = new TimeToReshelve();
			timeToReshelve.setLocations(timesToReshelveRS.getString("locations"));
			timeToReshelve.setNumHoursToOverride(timesToReshelveRS.getLong("numHoursToOverride"));
			timeToReshelve.setStatus(timesToReshelveRS.getString("status"));
			timeToReshelve.setGroupedStatus(timesToReshelveRS.getString("groupedStatus"));
			timesToReshelve.add(timeToReshelve);
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
						logger.error("Error loading translation map " + mapName, e);
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
	public void processRecord(GroupedWorkSolr groupedWork, RecordIdentifier identifier){
		Record record = loadMarcRecordFromDisk(identifier.getIdentifier());

		if (record != null){
			try{
				updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier);
			}catch (Exception e) {
				logger.error("Error updating solr based on marc record", e);
			}
		//No need to warn here, we already have a warning when getting it
		//}else{
			//logger.info("Could not load marc record from disk for " + identifier);
		}
	}

	private Record loadMarcRecordFromDisk(String identifier) {
		Record record = null;
		String individualFilename = getFileForIlsRecord(identifier);
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
		if (createFolderFromLeadingCharacters){
			subFolderName        = shortId.substring(0, numCharsToCreateFolderFrom);
		}else{
			subFolderName        = shortId.substring(0, shortId.length() - numCharsToCreateFolderFrom);
		}

		String basePath           = individualMarcPath + "/" + subFolderName;
		return basePath + "/" + shortId + ".mrc";
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier) {
		//For ILS Records, we can create multiple different records, one for print and order items,
		//and one or more for eContent items.
		HashSet<RecordInfo> allRelatedRecords = new HashSet<>();

		try{
			//If the entire bib is suppressed, update stats and bail out now.
			if (isBibSuppressed(record)){
				if (logger.isDebugEnabled()) {
					logger.debug("Bib record " + identifier + " is suppressed, skipping");
				}
				return;
			}

			// Let's first look for the print/order record
			RecordInfo recordInfo = groupedWork.addRelatedRecord(identifier);
			if (logger.isDebugEnabled()) {
				logger.debug("Added record for " + identifier + " work now has " + groupedWork.getNumRecords() + " records");
			}
			loadUnsuppressedPrintItems(groupedWork, recordInfo, identifier, record);
			loadOnOrderItems(groupedWork, recordInfo, record, recordInfo.getNumPrintCopies() > 0);
			//If we don't get anything remove the record we just added
			if (checkIfBibShouldBeRemovedAsItemless(recordInfo)) {
				groupedWork.removeRelatedRecord(recordInfo);
				if (logger.isDebugEnabled()) {
					logger.debug("Removing related print record for " + identifier + " because there are no print copies, no on order copies and suppress itemless bibs is on");
				}
			}else{
				allRelatedRecords.add(recordInfo);
			}

			//Since print formats are loaded at the record level, do it after we have loaded items
			loadPrintFormatInformation(recordInfo, record);

			//Now look for any eContent that is defined within the ils
			List<RecordInfo> econtentRecords = loadUnsuppressedEContentItems(groupedWork, identifier, record);
			allRelatedRecords.addAll(econtentRecords);

			//Do updates based on the overall bib (shared regardless of scoping)
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
			updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier.getIdentifier(), primaryFormat);

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
			loadPopularity(groupedWork, identifier.getIdentifier());
			groupedWork.addBarcodes(MarcUtil.getFieldList(record, itemTag + barcodeSubfield));

			loadOrderIds(groupedWork, record);

			int numPrintItems = recordInfo.getNumPrintCopies();

			numPrintItems = checkForNonSuppressedItemlessBib(numPrintItems);
			groupedWork.addHoldings(numPrintItems + recordInfo.getNumCopiesOnOrder());

			for (ItemInfo curItem : recordInfo.getRelatedItems()){
				String itemIdentifier = curItem.getItemIdentifier();
				if (itemIdentifier != null && itemIdentifier.length() > 0) {
					groupedWork.addAlternateId(itemIdentifier);
				}
			}

			for (RecordInfo recordInfoTmp: allRelatedRecords) {
				scopeItems(recordInfoTmp, groupedWork, record);
			}
		}catch (Exception e){
			logger.error("Error updating grouped work " + groupedWork.getId() + " for MARC record with identifier " + identifier, e);
		}
	}

	boolean checkIfBibShouldBeRemovedAsItemless(RecordInfo recordInfo) {
		return recordInfo.getNumPrintCopies() == 0 && recordInfo.getNumCopiesOnOrder() == 0 && suppressItemlessBibs;
	}

	/**
	 * Check to see if we should increment the number of print items by one.   For bibs without items that should not be
	 * suppressed.
	 *
	 * @param numPrintItems the number of print titles on the record
	 * @return number of items that should be counted
	 */
	private int checkForNonSuppressedItemlessBib(int numPrintItems) {
		if (!suppressItemlessBibs && numPrintItems == 0){
			numPrintItems = 1;
		}
		return numPrintItems;
	}

	protected boolean isBibSuppressed(Record record) {
		if (bCode3sToSuppressPattern != null && sierraRecordFixedFieldsTag != null && sierraRecordFixedFieldsTag.length() > 0 && bCode3Subfield != ' ') {
			DataField sierraFixedField = record.getDataField(sierraRecordFixedFieldsTag);
			if (sierraFixedField != null){
				Subfield suppressionSubfield = sierraFixedField.getSubfield(bCode3Subfield);
				if (suppressionSubfield != null){
					String bCode3 = suppressionSubfield.getData().toLowerCase().trim();
					if (bCode3sToSuppressPattern.matcher(bCode3).matches()){
						if (logger.isDebugEnabled()) {
							logger.debug("Bib record is suppressed due to BCode3 " + bCode3);
						}
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

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record, boolean hasTangibleItems){
		List<DataField> orderFields = MarcUtil.getDataFields(record, orderTag);
		for (DataField curOrderField : orderFields){
			//Check here to make sure the order item is valid before doing further processing.
			String status = "";
			if (curOrderField.getSubfield(orderStatusSubfield) != null) {
				status = curOrderField.getSubfield(orderStatusSubfield).getData();
			}
			String code3 = null;
			if (orderCode3Subfield != ' ' && curOrderField.getSubfield(orderCode3Subfield) != null){
				code3 = curOrderField.getSubfield(orderCode3Subfield).getData();
			}

			if (isOrderItemValid(status, code3)){
				int copies = 0;
				//If the location is multi, we actually have several records that should be processed separately
				List<Subfield> detailedLocationSubfield = curOrderField.getSubfields(orderLocationSubfield);
				if (detailedLocationSubfield.size() == 0){
					//Didn't get detailed locations
					if (curOrderField.getSubfield(orderCopiesSubfield) != null){
						copies = Integer.parseInt(curOrderField.getSubfield(orderCopiesSubfield).getData());
					}
					String locationCode = "multi";
					if (curOrderField.getSubfield(singleOrderLocationSubfield) != null){
						locationCode = curOrderField.getSubfield(singleOrderLocationSubfield).getData().trim();
					}
					createAndAddOrderItem(recordInfo, curOrderField, locationCode, copies);
				} else {
					for (Subfield curLocationSubfield : detailedLocationSubfield) {
						String curLocation = curLocationSubfield.getData();
						if (curLocation.startsWith("(")) {
							//There are multiple copies for this location
							String tmpLocation = curLocation;
							try {
								copies = Integer.parseInt(tmpLocation.substring(1, tmpLocation.indexOf(")")));
								curLocation = tmpLocation.substring(tmpLocation.indexOf(")") + 1).trim();
							} catch (StringIndexOutOfBoundsException e) {
								logger.error("Error parsing copies and location for order item " + tmpLocation);
							}
						} else {
							//If we only get one location in the detailed copies, we need to read the copies subfield rather than
							//hard coding to 1
							copies = 1;
							if (orderCopiesSubfield != ' ') {
								if (detailedLocationSubfield.size() == 1 && curOrderField.getSubfield(orderCopiesSubfield) != null) {
									String copiesData = curOrderField.getSubfield(orderCopiesSubfield).getData().trim();
									try {
										copies = Integer.parseInt(copiesData);
									} catch (StringIndexOutOfBoundsException e) {
										logger.error("StringIndexOutOfBoundsException loading number of copies " + copiesData, e);
									} catch (Exception e) {
										logger.error("Exception loading number of copies " + copiesData, e);
									} catch (Error e) {
										logger.error("Error loading number of copies " + copiesData, e);
									}
								}
							}
						}
						if (createAndAddOrderItem(recordInfo, curOrderField, curLocation, copies)) {
							//For On Order Items, increment popularity based on number of copies that are being purchased.
							groupedWork.addPopularity(copies);
						}
					}
				}
			}
		}
		if (recordInfo.getNumCopiesOnOrder() > 0 && !hasTangibleItems){
			groupedWork.addKeywords("On Order");
			groupedWork.addKeywords("Coming Soon");
			/*//Don't do this anymore, see D-1893
			HashSet<String> additionalOrderSubjects = new HashSet<>();
			additionalOrderSubjects.add("On Order");
			additionalOrderSubjects.add("Coming Soon");
			groupedWork.addTopic(additionalOrderSubjects);
			groupedWork.addTopicFacet(additionalOrderSubjects);*/
		}
	}

	private boolean createAndAddOrderItem(RecordInfo recordInfo, DataField curOrderField, String location, int copies) {
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
		itemInfo.setIsOrderItem(true);
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
			Scope.InclusionResult result = scope.isItemPartOfScope(indexingProfileSource, location, "", null, audiences, format, true, true, false, record, originalUrl);
			if (result.isIncluded){
				ScopingInfo scopingInfo = itemInfo.addScope(scope);
				if (scopingInfo == null){
					logger.error("Could not add scoping information for " + scope.getScopeName() + " for item " + itemInfo.getFullRecordIdentifier());
					continue;
				}
				if (scope.isLocationScope()) {
					scopingInfo.setLocallyOwned(scope.isItemOwnedByScope(indexingProfileSource, location, ""));
					if (scope.getLibraryScope() != null) {
						boolean libraryOwned = scope.getLibraryScope().isItemOwnedByScope(indexingProfileSource, location, "");
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
					boolean libraryOwned = scope.isItemOwnedByScope(indexingProfileSourceDisplayName, location, "");
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

	//TODO: this should be an abstract method; this version doesn't really apply to non- III library systems
	protected boolean isOrderItemValid(String status, String code3) {
		return status.equals("o") || status.equals("1");
	}

//TODO: this should move to the iii handler; and a blank method put here instead
	private void loadOrderIds(GroupedWorkSolr groupedWork, Record record) {
		//Load order ids from recordNumberTag
		Set<String> recordIds = MarcUtil.getFieldList(record, recordNumberTag + "a"); //TODO: refactor to use the record number subfield indicator
		for(String recordId : recordIds){
			if (recordId.startsWith(".o")){
				groupedWork.addAlternateId(recordId);
			}
		}
	}

	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record){
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
		if (logger.isDebugEnabled()) {
			logger.debug("Found " + itemRecords.size() + " items for record " + identifier);
		}
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField)){
				getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				//Can return null if the record does not have status and location
				//This happens with secondary call numbers sometimes.
			}else if (logger.isDebugEnabled()){
				logger.debug("item was suppressed");
			}
		}
	}

	RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, DataField itemField) {
		String   itemLocation    = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String   itemSublocation = getItemSubfieldData(subLocationSubfield, itemField);
		String   iTypeValue      = getItemSubfieldData(iTypeSubfield, itemField);
		ItemInfo itemInfo        = new ItemInfo();

		loadDateAdded(identifier, itemField, itemInfo);
		itemInfo.setIsEContent(true);
		itemInfo.setLocationCode(itemLocation);
		if (itemSublocation != null && itemSublocation.length() > 0) {
			itemInfo.setSubLocation(translateValue("sub_location", itemSublocation, identifier));
		}
		itemInfo.setITypeCode(iTypeValue);
		itemInfo.setIType(translateValue("itype", getItemSubfieldData(iTypeSubfield, itemField), identifier));
		loadItemCallNumber(record, itemField, itemInfo);
		itemInfo.setItemIdentifier(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField));
		itemInfo.setShelfLocation(getShelfLocationForItem(itemInfo, itemField, identifier));
		itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

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
//			itemInfo.seteContentProtectionType("external");
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


	protected void loadDateAdded(RecordIdentifier recordIdentifier, DataField itemField, ItemInfo itemInfo) {
		String dateAddedStr = getItemSubfieldData(dateCreatedSubfield, itemField);
		if (dateAddedStr != null && dateAddedStr.length() > 0) {
			try {
				if (dateAddedFormatter == null){
					dateAddedFormatter = new SimpleDateFormat(dateAddedFormat);
				}
				Date dateAdded = dateAddedFormatter.parse(dateAddedStr);
				itemInfo.setDateAdded(dateAdded);
			} catch (ParseException e) {
				logger.error("Error processing date added for record identifier " + recordIdentifier + " profile " + indexingProfileSourceDisplayName + " using format " + dateAddedFormat, e);
			}
		}
	}

	protected String getILSeContentSourceType(Record record, DataField itemField) {
		return "Unknown Source";
	}

	private SimpleDateFormat dateAddedFormatter = null;
	private SimpleDateFormat lastCheckInFormatter = null;
	ItemInfo getPrintIlsItem(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record, DataField itemField, RecordIdentifier identifier) {
		if (dateAddedFormatter == null){
			dateAddedFormatter = new SimpleDateFormat(dateAddedFormat);
		}
		if (lastCheckInFormatter == null && lastCheckInFormat != null && lastCheckInFormat.length() > 0){
			lastCheckInFormatter = new SimpleDateFormat(lastCheckInFormat);
		}
		ItemInfo itemInfo = new ItemInfo();
		//Load base information from the Marc Record
		itemInfo.setItemIdentifier(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField));

		String itemStatus   = getItemStatus(itemField, identifier.getSourceAndId());
		String itemLocation = getItemSubfieldData(locationSubfieldIndicator, itemField);
		itemInfo.setLocationCode(itemLocation);
		String itemSublocation = getItemSubfieldData(subLocationSubfield, itemField);
		if (itemSublocation == null){
			itemSublocation = "";
		}
		itemInfo.setSubLocationCode(itemSublocation);
		if (itemSublocation.length() > 0){
			itemInfo.setSubLocation(translateValue("sub_location", itemSublocation, identifier));
		}else{
			itemInfo.setSubLocation("");
		}

		//if the status and location are null, we can assume this is not a valid item
		if (!isItemValid(itemStatus, itemLocation)) return null;
		if (itemStatus.isEmpty()) {
			logger.warn("Item contained no status value for item " + itemInfo.getItemIdentifier() + " for location " + itemLocation + " in record " + identifier);
		}

		setShelfLocationCode(itemField, itemInfo, identifier);
		itemInfo.setShelfLocation(getShelfLocationForItem(itemInfo, itemField, identifier));

		loadDateAdded(identifier, itemField, itemInfo);
		getDueDate(itemField, itemInfo);

		if (iTypeSubfield != ' ') {
			itemInfo.setITypeCode(getItemSubfieldData(iTypeSubfield, itemField));
			itemInfo.setIType(translateValue("itype", getItemSubfieldData(iTypeSubfield, itemField), identifier));
		}

		double itemPopularity = getItemPopularity(itemField, identifier);
		groupedWork.addPopularity(itemPopularity);

		loadItemCallNumber(record, itemField, itemInfo);

		itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

		if (lastCheckInFormatter != null) {
			String lastCheckInDate = getItemSubfieldData(lastCheckInSubfield, itemField);
			Date lastCheckIn = null;
			if (lastCheckInDate != null && lastCheckInDate.length() > 0)
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
					if (formatBoost != null && formatBoost.length() > 0) {
						recordInfo.setFormatBoost(Integer.parseInt(formatBoost));
					}
				} catch (Exception e) {
					logger.warn("Could not get boost for format " + format);
				}
			}
		}

		//This is done later so we don't need to do it here.
		//loadScopeInfoForPrintIlsItem(recordInfo, groupedWork.getTargetAudiences(), itemInfo, record);

		groupedWork.addKeywords(itemLocation);
		if (itemSublocation.length() > 0){
			groupedWork.addKeywords(itemSublocation);
		}

		recordInfo.addItem(itemInfo);
		return itemInfo;
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
			Scope.InclusionResult result = curScope.isItemPartOfScope(indexingProfileSource, itemLocation, "", null, groupedWork.getTargetAudiences(), format, false, false, true, record, originalUrl);
			if (result.isIncluded){
				ScopingInfo scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(true);
				scopingInfo.setStatus("Available Online");
				scopingInfo.setGroupedStatus("Available Online");
				scopingInfo.setHoldable(false);
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(indexingProfileSource, itemLocation, ""));
					if (curScope.getLibraryScope() != null) {
						scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(indexingProfileSource, itemLocation, ""));
					}
				}
				if (curScope.isLibraryScope()) {
					scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(indexingProfileSource, itemLocation, ""));
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
		String displayStatus        = getDisplayStatus(itemInfo, recordInfo.getRecordIdentifier());
		String groupedDisplayStatus = getDisplayGroupedStatus(itemInfo, recordInfo.getRecordIdentifier());
		String overiddenStatus      = getOverriddenStatus(itemInfo, true);
		if (overiddenStatus != null && !overiddenStatus.equals("On Shelf") && !overiddenStatus.equals("Library Use Only") && !overiddenStatus.equals("Available Online")){
			available = false;
		}

		String itemLocation    = itemInfo.getLocationCode();
		String itemSublocation = itemInfo.getSubLocationCode();

		HoldabilityInformation isHoldableUnscoped = isItemHoldableUnscoped(itemInfo);
		BookabilityInformation isBookableUnscoped = isItemBookableUnscoped();
		String                 originalUrl        = itemInfo.geteContentUrl();
		String                 primaryFormat      = recordInfo.getPrimaryFormat();
		for (Scope curScope : indexer.getScopes()) {
			//Check to see if the record is holdable for this scope
			HoldabilityInformation isHoldable = isItemHoldable(itemInfo, curScope, isHoldableUnscoped);

			Scope.InclusionResult result = curScope.isItemPartOfScope(indexingProfileSourceDisplayName, itemLocation, itemSublocation, itemInfo.getITypeCode(), audiences, primaryFormat, isHoldable.isHoldable(), false, false, record, originalUrl);
			if (result.isIncluded){
				BookabilityInformation isBookable  = isItemBookable(itemInfo, curScope, isBookableUnscoped);
				ScopingInfo            scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(available);
				scopingInfo.setHoldable(isHoldable.isHoldable());
				scopingInfo.setHoldablePTypes(isHoldable.getHoldablePTypes());
				scopingInfo.setBookable(isBookable.isBookable());
				scopingInfo.setBookablePTypes(isBookable.getBookablePTypes());

				scopingInfo.setInLibraryUseOnly(determineLibraryUseOnly(itemInfo, curScope));

				scopingInfo.setStatus(displayStatus);
				scopingInfo.setGroupedStatus(groupedDisplayStatus);
				if (originalUrl != null && !originalUrl.equals(result.localUrl)){
					scopingInfo.setLocalUrl(result.localUrl);
				}
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation, itemSublocation));
					if (curScope.getLibraryScope() != null) {
						scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation, itemSublocation));
					}
				}
				if (curScope.isLibraryScope()) {
					scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(indexingProfileSourceDisplayName, itemLocation, itemSublocation));
				}
			}
		}
	}

	protected boolean determineLibraryUseOnly(ItemInfo itemInfo, Scope curScope) {
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
		if (itemInfo.getLastCheckinDate() != null) {
			for (TimeToReshelve timeToReshelve : timesToReshelve) {
				if (timeToReshelve.getLocationsPattern().matcher(itemInfo.getLocationCode()).matches()) {
					long now = new Date().getTime();
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
		return overriddenStatus;
	}

	protected String getDisplayGroupedStatus(ItemInfo itemInfo, String identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, true);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			return translateValue("item_grouped_status", itemInfo.getStatusCode(), identifier);
		}
	}

	protected String getDisplayStatus(ItemInfo itemInfo, String identifier) {
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
			ytdCheckouts = Integer.parseInt(ytdCheckoutsField);
		}
		String lastYearCheckoutsField = getItemSubfieldData(lastYearCheckoutSubfield, itemField);
		int lastYearCheckouts = 0;
		if (lastYearCheckoutsField != null){
			lastYearCheckouts = Integer.parseInt(lastYearCheckoutsField);
		}
		double itemPopularity = ytdCheckouts + .5 * (lastYearCheckouts) + .1 * (totalCheckouts - lastYearCheckouts - ytdCheckouts);
		if (itemPopularity == 0){
			itemPopularity = 1;
		}
		return itemPopularity;
	}

	protected boolean isItemValid(String itemStatus, String itemLocation) {
		return !(itemStatus == null && itemLocation == null);
	}

	void loadItemCallNumber(Record record, DataField itemField, ItemInfo itemInfo) {
		boolean hasCallNumber = false;
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
				if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
					fullCallNumber.append(' ');
				}
				fullCallNumber.append(callNumber);
				sortableCallNumber.append(callNumber);
			}
			if (callNumberCutter != null){
				if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
					fullCallNumber.append(' ');
				}
				fullCallNumber.append(callNumberCutter);
				if (sortableCallNumber.length() > 0 && sortableCallNumber.charAt(sortableCallNumber.length() - 1) != ' '){
					sortableCallNumber.append(' ');
				}
				sortableCallNumber.append(callNumberCutter);
			}
			if (callNumberPostStamp != null){
				if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
					fullCallNumber.append(' ');
				}
				fullCallNumber.append(callNumberPostStamp);
				if (sortableCallNumber.length() > 0 && sortableCallNumber.charAt(sortableCallNumber.length() - 1) != ' '){
					sortableCallNumber.append(' ');
				}
				sortableCallNumber.append(callNumberPostStamp);
			}
			//ARL-203 do not create an item level call number that is just a volume
			if (volume != null && fullCallNumber.length() > 0){
				if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
					fullCallNumber.append(' ');
				}
				fullCallNumber.append(volume);
			}
			if (fullCallNumber.length() > 0){
				hasCallNumber = true;
				itemInfo.setCallNumber(fullCallNumber.toString().trim());
				itemInfo.setSortableCallNumber(sortableCallNumber.toString().trim());
			}
		}
		if (!hasCallNumber){
			String callNumber = null;
			if (use099forBibLevelCallNumbers()) {
				DataField localCallNumberField = record.getDataField("099");
				if (localCallNumberField != null) {
					callNumber = "";
					for (Subfield curSubfield : localCallNumberField.getSubfields()) {
						callNumber += " " + curSubfield.getData().trim();
					}
				}
			}
			//MDN #ARL-217 do not use 099 as a call number
			if (callNumber == null) {
				DataField deweyCallNumberField = record.getDataField("092");
				if (deweyCallNumberField != null) {
					callNumber = "";
					for (Subfield curSubfield : deweyCallNumberField.getSubfields()) {
						callNumber += " " + curSubfield.getData().trim();
					}
				}
			}
			// Sacramento - look in the 932
			if (callNumber == null) {
				DataField sacramentoCallNumberField = record.getDataField("932");
				if (sacramentoCallNumberField != null) {
					callNumber = "";
					for (Subfield curSubfield : sacramentoCallNumberField.getSubfields()) {
						callNumber += " " + curSubfield.getData().trim();
					}
				}
			}
			if (callNumber != null) {

				if (volume != null && volume.length() > 0 && !callNumber.endsWith(volume)){
					if (callNumber.length() > 0 && callNumber.charAt(callNumber.length() - 1) != ' '){
						callNumber += " ";
					}
					callNumber += volume;
				}
				itemInfo.setCallNumber(callNumber.trim());
				itemInfo.setSortableCallNumber(callNumber.trim());
			}
		}
	}
//	void loadItemCallNumber(Record record, DataField itemField, ItemInfo itemInfo) {
//		boolean hasCallNumber = false;
//		String volume = null;
//		if (itemField != null){
//			volume = getItemSubfieldData(volumeSubfield, itemField);
//		}
//		if (useItemBasedCallNumbers && itemField != null) {
//			String callNumberPreStamp  = getItemSubfieldDataWithoutTrimming(callNumberPrestampSubfield, itemField);
//			String callNumber          = getItemSubfieldDataWithoutTrimming(callNumberSubfield, itemField);
//			String callNumberCutter    = getItemSubfieldDataWithoutTrimming(callNumberCutterSubfield, itemField);
//			String callNumberPostStamp = getItemSubfieldData(callNumberPoststampSubfield, itemField);
//
//			StringBuilder fullCallNumber     = new StringBuilder();
//			StringBuilder sortableCallNumber = new StringBuilder();
//			if (callNumberPreStamp != null) {
//				fullCallNumber.append(callNumberPreStamp);
//			}
//			if (callNumber != null){
//				addTrailingSpace(fullCallNumber);
//				fullCallNumber.append(callNumber);
//				sortableCallNumber.append(callNumber);
//			}
//			if (callNumberCutter != null){
//				addTrailingSpace(fullCallNumber);
//				fullCallNumber.append(callNumberCutter);
//				addTrailingSpace(sortableCallNumber);
//				sortableCallNumber.append(callNumberCutter);
//			}
//			if (callNumberPostStamp != null){
//				addTrailingSpace(fullCallNumber);
//				fullCallNumber.append(callNumberPostStamp);
//				addTrailingSpace(sortableCallNumber);
//				sortableCallNumber.append(callNumberPostStamp);
//			}
//			//ARL-203 do not create an item level call number that is just a volume
//			if (volume != null && fullCallNumber.length() > 0){
//				addTrailingSpace(fullCallNumber);
//				fullCallNumber.append(volume);
//			}
//			if (fullCallNumber.length() > 0){
//				hasCallNumber = true;
//				itemInfo.setCallNumber(fullCallNumber.toString().trim());
//				itemInfo.setSortableCallNumber(sortableCallNumber.toString().trim());
//			}
//		}
//		if (!hasCallNumber){
//			String callNumber = null;
//			if (use099forBibLevelCallNumbers()) {
//				DataField localCallNumberField = record.getDataField("099");
//				if (localCallNumberField != null) {
//					callNumber = "";
//					for (Subfield curSubfield : localCallNumberField.getSubfields()) {
//						callNumber += " " + curSubfield.getData().trim();
//					}
//				}
//			}
//			//MDN #ARL-217 do not use 099 as a call number
//			if (callNumber == null) {
//				DataField deweyCallNumberField = record.getDataField("092");
//				if (deweyCallNumberField != null) {
//					callNumber = "";
//					for (Subfield curSubfield : deweyCallNumberField.getSubfields()) {
//						callNumber += " " + curSubfield.getData().trim();
//					}
//				}
//			}
//			// Sacramento - look in the 932
//			if (callNumber == null) {
//				DataField sacramentoCallNumberField = record.getDataField("932");
//				if (sacramentoCallNumberField != null) {
//					callNumber = "";
//					for (Subfield curSubfield : sacramentoCallNumberField.getSubfields()) {
//						callNumber += " " + curSubfield.getData().trim();
//					}
//				}
//			}
//			if (callNumber != null) {
//
//				if (volume != null && volume.length() > 0 && !callNumber.endsWith(volume)){
//					if (callNumber.length() > 0 && callNumber.charAt(callNumber.length() - 1) != ' '){
//						callNumber += " ";
//					}
//					callNumber += volume;
//				}
//				itemInfo.setCallNumber(callNumber.trim());
//				itemInfo.setSortableCallNumber(callNumber.trim());
//			}
//		}
//	}
//
//	private void addTrailingSpace(StringBuilder stringBuilder) {
//		if (stringBuilder.length() > 0 && stringBuilder.charAt(stringBuilder.length() - 1) != ' ') {
//			stringBuilder.append(' ');
//		}
//	}

	protected boolean use099forBibLevelCallNumbers() {
		return true;
	}

	private HashMap<String, Boolean> iTypesThatHaveHoldabilityChecked = new HashMap<>();
	private HashMap<String, Boolean> locationsThatHaveHoldabilityChecked = new HashMap<>();
	private HashMap<String, Boolean> statusesThatHaveHoldabilityChecked = new HashMap<>();

	private HoldabilityInformation isItemHoldableUnscoped(ItemInfo itemInfo){
		String itemItypeCode =  itemInfo.getITypeCode();
		if (nonHoldableITypes != null && itemItypeCode != null && itemItypeCode.length() > 0){
			if (!iTypesThatHaveHoldabilityChecked.containsKey(itemItypeCode)){
				iTypesThatHaveHoldabilityChecked.put(itemItypeCode, !nonHoldableITypes.matcher(itemItypeCode).matches());
			}
			if (!iTypesThatHaveHoldabilityChecked.get(itemItypeCode)){
				return new HoldabilityInformation(false, new HashSet<Long>());
			}
		}
		String itemLocationCode =  itemInfo.getLocationCode();
		if (nonHoldableLocations != null && itemLocationCode != null && itemLocationCode.length() > 0){
			if (!locationsThatHaveHoldabilityChecked.containsKey(itemLocationCode)){
				locationsThatHaveHoldabilityChecked.put(itemLocationCode, !nonHoldableLocations.matcher(itemLocationCode).matches());
			}
			if (!locationsThatHaveHoldabilityChecked.get(itemLocationCode)){
				return new HoldabilityInformation(false, new HashSet<Long>());
			}
		}
		String itemStatusCode = itemInfo.getStatusCode();
		if (nonHoldableStatuses != null && itemStatusCode != null && itemStatusCode.length() > 0){
			if (!statusesThatHaveHoldabilityChecked.containsKey(itemStatusCode)){
				statusesThatHaveHoldabilityChecked.put(itemStatusCode, !nonHoldableStatuses.matcher(itemStatusCode).matches());
			}
			if (!statusesThatHaveHoldabilityChecked.get(itemStatusCode)){


				return new HoldabilityInformation(false, new HashSet<Long>());
			}
		}
		return new HoldabilityInformation(true, new HashSet<Long>());
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
		if (shelfLocation == null || shelfLocation.length() == 0 || shelfLocation.equals("none")){
			return "";
		}else {
			return translateValue("shelf_location", shelfLocation, identifier);
		}
	}

	protected String getItemStatus(DataField itemField, String recordIdentifier){
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
			} else if (subfields.size() == 0) {
				return null;
			} else {
				StringBuilder subfieldData = new StringBuilder();
				for (Subfield subfield:subfields) {
					String trimmedValue = subfield.getData().trim();
					boolean okToAdd = false;
					if (trimmedValue.length() == 0){
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
						if (subfieldData.length() > 0 && subfieldData.charAt(subfieldData.length() - 1) != ' ') {
							subfieldData.append(' ');
						}
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
			} else if (subfields.size() == 0) {
				return null;
			} else {
				StringBuilder subfieldData = new StringBuilder();
				for (Subfield subfield:subfields) {
					if (subfieldData.length() > 0 && subfieldData.charAt(subfieldData.length() - 1) != ' '){
						subfieldData.append(' ');
					}
					subfieldData.append(subfield.getData());
				}
				return subfieldData.toString();
			}
		}
	}

	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record){
		return new ArrayList<>();
	}

	void loadPopularity(GroupedWorkSolr groupedWork, String recordIdentifier) {
		//Add popularity based on the number of holds (we have already done popularity for prior checkouts)
		//Active holds indicate that a title is more interesting so we will count each hold at double value
		double popularity = 2 * getIlsHoldsForTitle(recordIdentifier);
		groupedWork.addPopularity(popularity);
	}

	private int getIlsHoldsForTitle(String recordIdentifier) {
		if (numberOfHoldsByIdentifier.containsKey(recordIdentifier)){
			return numberOfHoldsByIdentifier.get(recordIdentifier);
		}else {
			return 0;
		}
	}

	protected boolean isItemSuppressed(DataField curItem) {
		if (statusesToSuppressPattern != null && statusSubfieldIndicator != ' ') {
			String status = getItemStatus(curItem, "");
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
			if (collectionSubfieldValue == null){ // suppress if subfield is missing
				return true;
			}else if (collectionsToSuppressPattern.matcher(collectionSubfieldValue.getData().trim()).matches()) {
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
					if (logger.isDebugEnabled()) {
						logger.debug("Item record is suppressed due to ICode2 " + iCode2);
					}
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

	private char getSubfieldIndicatorFromConfig(ResultSet indexingProfileRS, String subfieldName) throws SQLException{
		String subfieldString = indexingProfileRS.getString(subfieldName);
		char subfield = ' ';
		if (!indexingProfileRS.wasNull() && subfieldString.length() > 0)  {
			subfield = subfieldString.charAt(0);
		}
		return subfield;
	}

	public String translateValue(String mapName, String value, RecordIdentifier identifier){
		return translateValue(mapName, value, identifier.getIdentifier(), true);
	}
	public String translateValue(String mapName, String value, String identifier){
		return translateValue(mapName, value, identifier, true);
	}
	public String translateValue(String mapName, String value, String identifier, boolean reportErrors){
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

	HashSet<String> translateCollection(String mapName, Set<String> values, String identifier) {
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
