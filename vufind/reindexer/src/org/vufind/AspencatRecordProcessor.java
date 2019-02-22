package org.vufind;

import au.com.bytecode.opencsv.CSVReader;
import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;

/**
 * ILS Indexing with customizations specific to Aspencat
 * Pika
 * User: Mark Noble
 * Date: 2/21/14
 * Time: 3:00 PM
 */
class AspencatRecordProcessor extends IlsRecordProcessor {
	private HashSet<String> inTransitItems                 = new HashSet<>();
	private HashSet<String> onHoldShelfItems               = new HashSet<>();
	private boolean         doAutomaticEcontentSuppression = false;

	// Item subfields (Copied from Koha Export Main)
	private char withdrawnSubfield  = '0';
	private char damagedSubfield    = '4';
	private char lostSubfield       = '1';
	private char notforloanSubfield = '7'; //Primary status subfield
	private char restrictedSubfield = '5';
	private char dueDateSubfield    = 'q';
	private char itemSourceSubfield = 'e';

	AspencatRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

		try {
			doAutomaticEcontentSuppression = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");

			String marcPath           = indexingProfileRS.getString("marcPath");
			File   inTransitItemsFile = new File(marcPath + "/inTransitItems.csv");
			readCSVFile(inTransitItemsFile, inTransitItems);
			File onHoldShelfItemsFile = new File(marcPath + "/holdShelfItems.csv");
			readCSVFile(onHoldShelfItemsFile, onHoldShelfItems);

		} catch (Exception e) {
			logger.error("Error indexing Aspencat Records", e);
			//System.exit(1);
		}

	}

	private void readCSVFile(File CSVFile, HashSet<String> itemsList) {
		try {
			if (CSVFile.exists()){
				CSVReader csvReader = new CSVReader(new FileReader(CSVFile));
				//Skip the header
				csvReader.readNext();
				String[] itemData = csvReader.readNext();
				while (itemData != null){
					itemsList.add(itemData[0]);
	//				if (itemsList.containsKey(itemData[0])){
	//					itemsList.put(itemData[0], itemsList.get(itemData[0]) + 1);
	//				}else{
	//					itemsList.put(itemData[0], 1);
	//				}

					itemData = csvReader.readNext();
				}
				csvReader.close();
			}
		} catch (IOException e) {
			logger.error("Did not find file :" + CSVFile.getName(), e);
		}
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		String statusCode = itemInfo.getStatusCode();
		return statusCode.equals(ItemStatus.ONSHELF.toString()) || statusCode.equals(ItemStatus.LIBRARYUSEONLY.toString()) || statusCode.equals(ItemStatus.INPROCESSING.toString())
				|| statusCode.equals(ItemStatus.CATALOGING.toString() // TODO: this should be temporary
		);
	}

	// Since there is not a scope and/or ptype dependency on holdability, we will use the indexing profile setting that take effect in isItemHoldableUnscoped() in the ilsRecordProcessor
//	@Override
//	protected HoldabilityInformation isItemHoldable(ItemInfo itemInfo, Scope curScope, HoldabilityInformation isHoldableUnscoped) {
//		String                 statusCode   = itemInfo.getStatusCode();
//		boolean                isHoldable   = !statusCode.equals(ItemStatus.INREPAIRS.toString()) && !statusCode.equals(ItemStatus.LIBRARYUSEONLY.toString()); // Specifically, NotForLoan statuses In Repairs, Library Use Only & Staff Collection
//		HoldabilityInformation itemHoldInfo = new HoldabilityInformation(isHoldable, new HashSet<Long>());
//		return itemHoldInfo;
//	}

	@Override
	protected boolean determineLibraryUseOnly(ItemInfo itemInfo, Scope curScope) {
		return itemInfo.getStatusCode().equals(ItemStatus.LIBRARYUSEONLY.toString());
	}

	@Override
	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
		HashMap<String, Integer> itemCountsByItype = new HashMap<>();
		HashMap<String, String>  itemTypeToFormat  = new HashMap<>();
		int                      mostUsedCount     = 0;
		String                   mostPopularIType  = "";  //Get a list of all the formats based on the items
		String                   recordIdentifier  = recordInfo.getRecordIdentifier();
		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
		for(DataField item : items){
			if (!isItemSuppressed(item, recordIdentifier)) {
				Subfield iTypeSubField = item.getSubfield(iTypeSubfield);
				if (iTypeSubField != null) {
					String iType = iTypeSubField.getData().toLowerCase();
					if (itemCountsByItype.containsKey(iType)) {
						itemCountsByItype.put(iType, itemCountsByItype.get(iType) + 1);
					} else {
						itemCountsByItype.put(iType, 1);
						//Translate the iType to see what formats we get.  Some item types do not have a format by default and use the default translation
						//We still will want to record those counts.
						String translatedFormat = translateValue("format", iType, recordIdentifier);
						//If the format is book, ignore it for now.  We will use the default method later.
						if (translatedFormat == null || translatedFormat.equalsIgnoreCase("book")) {
							translatedFormat = "";
						}
						itemTypeToFormat.put(iType, translatedFormat);
					}

					if (itemCountsByItype.get(iType) > mostUsedCount) {
						mostPopularIType = iType;
						mostUsedCount = itemCountsByItype.get(iType);
					}
				}
			}
		}

		if (itemTypeToFormat.size() == 0 || itemTypeToFormat.get(mostPopularIType) == null || itemTypeToFormat.get(mostPopularIType).length() == 0){
			//We didn't get any formats from the collections, get formats from the base method (007, 008, etc).
			//logger.debug("All formats are books or there were no formats found, loading format information from the bib");
			super.loadPrintFormatFromBib(recordInfo, record);
		} else{
			//logger.debug("Using default method of loading formats from iType");
			recordInfo.addFormat(itemTypeToFormat.get(mostPopularIType));
			String translatedFormatCategory = translateValue("format_category", mostPopularIType, recordIdentifier);
			if (translatedFormatCategory == null){
				translatedFormatCategory = translateValue("format_category", itemTypeToFormat.get(mostPopularIType), recordIdentifier);
				if (translatedFormatCategory == null){
					translatedFormatCategory = mostPopularIType;
				}
			}
			recordInfo.addFormatCategory(translatedFormatCategory);
			Long formatBoost = 1L;
			String formatBoostStr = translateValue("format_boost", mostPopularIType, recordIdentifier);
			if (formatBoostStr == null){
				formatBoostStr = translateValue("format_boost", itemTypeToFormat.get(mostPopularIType), recordIdentifier);
			}
			if (Util.isNumeric(formatBoostStr)) {
				formatBoost = Long.parseLong(formatBoostStr);
			}
			recordInfo.setFormatBoost(formatBoost);
		}
	}

	private HashSet<String> additionalStatuses = new HashSet<>();
	protected String getItemStatus(DataField itemField, String recordIdentifier){
		ItemStatus status;
		String     itemIdentifier = getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField);

		//Determining status for Koha relies on a number of different fields
		// Determine simple statuses first
		status = getStatusFromBooleanSubfield(itemField, withdrawnSubfield, ItemStatus.WITHDRAWN);
		if (status != null) return status.toString();

		status = getStatusFromBooleanSubfield(itemField, damagedSubfield, ItemStatus.DAMAGED);
		if (status != null) return status.toString();

//		status = getStatusFromBooleanSubfield(itemField, restrictedSubfield, ItemStatus.);
//		if (status != null) return status.toString();

		// Now Try more complicated statuses
		status = getStatusFromLostSubfield(itemField); // Any value that isn't 0, will set the status to LOST
		if (status != null) return status.toString();

		status = getStatusFromDueDateSubfield(itemField);
		if (status != null) return status.toString();

		if (inTransitItems.contains(itemIdentifier)){
			status = ItemStatus.INTRANSIT;
			return status.toString();
		}
		if (onHoldShelfItems.contains(itemIdentifier)){
			status = ItemStatus.ONHOLDSHELF;
			return status.toString();
		}

		// Finally Check the Not For Loan subfield
		status = getStatusFromNotForLoanSubfield(itemField, recordIdentifier, itemIdentifier);
		if (status != null) return status.toString();

		return ItemStatus.ONSHELF.toString();
	}

	private ItemStatus getStatusFromNotForLoanSubfield(DataField itemField, String recordIdentifier, String itemIdentifier) {
		if (itemField.getSubfield(notforloanSubfield) != null){
			String fieldData = itemField.getSubfield(notforloanSubfield).getData();

			//Aspencat Koha NOT_LOAN values
			switch (fieldData) {
				case "0":
					return ItemStatus.ONSHELF;
				case "-2": //Cataloging
					return ItemStatus.INPROCESSING;
				case "-1": // Ordered
					return ItemStatus.ONORDER;
				case "1": // Not for Loan
				case "2": // Staff Collection
					return ItemStatus.LIBRARYUSEONLY;
				case "3": // In Repairs
					return ItemStatus.INREPAIRS;
				case "4":
					return ItemStatus.SUPPRESSED;
				case "5": // Cataloging
					//TODO: should be a temporary status
					return ItemStatus.CATALOGING;
				default:
					if (!additionalStatuses.contains(fieldData)){
						logger.warn("Found new status " + fieldData + " for subfield " + notforloanSubfield + " for item " + itemIdentifier + " on bib " + recordIdentifier);
						additionalStatuses.add(fieldData);
					}
			}
		}
		return null;
	}

	private ItemStatus getStatusFromDueDateSubfield(DataField itemField) {
		if (itemField.getSubfield(dueDateSubfield) != null) {
			String fieldData = itemField.getSubfield(dueDateSubfield).getData();

			if (fieldData.matches("\\d{4}-\\d{2}-\\d{2}")) {
				return ItemStatus.CHECKEDOUT;
			}
		}
		return null;
	}

	private ItemStatus getStatusFromBooleanSubfield(DataField itemField, char subfield, ItemStatus defaultStatus) {
		if (itemField.getSubfield(subfield) != null){
			String fieldData = itemField.getSubfield(subfield).getData().trim();
			if (!fieldData.equals("0")) {
				if (fieldData.equals("1")) {
					return defaultStatus;
				}
			}
		}
		return null;
	}

	private ItemStatus getStatusFromLostSubfield(DataField itemField) {
		// If there is any value set for the lost subfield, the status is LOST
		if (itemField.getSubfield(lostSubfield) != null){
			String fieldData = itemField.getSubfield(lostSubfield).getData();
			if (!fieldData.equals("0")) {
				return ItemStatus.LOST;
			}
		}
		return null;
	}

	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, String identifier, Record record){
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField, identifier) && !isEContent(itemField)) {
				getPrintIlsItem(groupedWork, recordInfo, record, itemField);
			}
		}
	}

	private boolean isEContent(DataField itemField) {
		if (itemField.getSubfield(iTypeSubfield) != null){
			String iType = itemField.getSubfield(iTypeSubfield).getData().toLowerCase();
			return iType.equals("ebook") || iType.equals("eaudio") || iType.equals("online");
		}
		return false;
	}

	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record){
		List<DataField>  itemRecords                 = MarcUtil.getDataFields(record, itemTag);
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField)){
				//Check to see if the item has an eContent indicator
				boolean isEContent  = isEContent(itemField);
				if (isEContent) {
					if (doAutomaticEcontentSuppression){
						boolean isOverDrive = false;
						boolean isHoopla    = false;
						String  sourceType  = getILSeContentSourceType(record, itemField);
						if (sourceType != null && !sourceType.equals("Unknown Source")) {
							sourceType = sourceType.toLowerCase().trim();
							if (sourceType.contains("overdrive")) {
								isOverDrive = true;
								logger.warn("Found overdrive item record " + identifier); //These records shouldn't be in the ILS any more, so I want to see if there are any
							} else if (sourceType.contains("hoopla")) {
								isHoopla = true;
								logger.warn("Found hoopla item record " + identifier); //These records shouldn't be in the ILS any more, so I want to see if there are any
							} else {
								logger.debug("Found eContent Source " + sourceType);
							}
						} else {
							//Need to figure out how to load a source
							logger.warn("Did not find an eContent source for " + identifier);
						}
						if (!isOverDrive && !isHoopla){
							RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
							if (eContentRecord != null) {
								unsuppressedEcontentRecords.add(eContentRecord);
							}
						}
					} else {
						RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
						if (eContentRecord != null) {
							unsuppressedEcontentRecords.add(eContentRecord);
						}
					}
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		if (econtentItem.getITypeCode() != null) {
			String iType                    = econtentItem.getITypeCode().toLowerCase();
			String translatedFormat         = translateValue("format", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatCategory = translateValue("format_category", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatBoost    = translateValue("format_boost", iType, econtentRecord.getRecordIdentifier());
			econtentItem.setFormat(translatedFormat);
			econtentItem.setFormatCategory(translatedFormatCategory);
			econtentRecord.setFormatBoost(Long.parseLong(translatedFormatBoost));
			if (iType.equals(translatedFormat)){
				logger.warn("Itype " + iType + "has no format translation value (or they are the same.)");
			}
		}
	}

	protected String getILSeContentSourceType(Record record, DataField itemField) {
		//Try to figure out the source
		String sourceType = "Unknown Source";
		//Try Item Source subfield
		if (itemField.getSubfield(itemSourceSubfield) != null){
			sourceType = itemField.getSubfield(itemSourceSubfield).getData();
		}else{
			//Try 949a (LocalData tag)
			DataField field949 = record.getDataField("949");
			if (field949 != null && field949.getSubfield('a') != null){
				sourceType = field949.getSubfield('a').getData();
			}else{
				// Try 037b (Source of Acquisition tag, Source of stock number/acquisition subfield)
				DataField field037 = record.getDataField("037");
				if (field037 != null && field037.getSubfield('b') != null){
					sourceType = field037.getSubfield('b').getData();
				}else{
					List<DataField> urlFields = record.getDataFields("856");
					for (DataField urlDataField : urlFields){
						if (urlDataField.getSubfield('3') != null) {
							if (urlDataField.getIndicator1() == '4' || urlDataField.getIndicator1() == ' ') {
								//Technically, should not include indicator 2 of 2, but AspenCat has lots of records with an indicator 2 of 2 that are valid.
								char indicator2 = urlDataField.getIndicator2();
								if (indicator2 == ' ' || indicator2 == '0' || indicator2 == '1' || indicator2 == '2') {
									sourceType = urlDataField.getSubfield('3').getData().trim();
									break;
								}
							}
						}
					}
				}
			}
		}
		return sourceType;
	}

// Moved Withdrawn & Lost subfield checking to the indexing profile item status suppression since withdrawn and lost are calculated statuses
// but status is uniquely calculated for Aspencat, so status suppression needs slight customization. (probably should go in a Koha Record Processor
	protected boolean isItemSuppressed(DataField curItem, String identifier) {
		String status = getItemStatus(curItem, identifier);
		if (statusesToSuppressPattern != null && statusesToSuppressPattern.matcher(status).matches()) {
			return true;
		}
		return  super.isItemSuppressed(curItem);
	}

//	protected boolean isItemSuppressed(DataField curItem) {
//		return isItemSuppressed(curItem, null);
//	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, String identifier) {
		/*String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String location = translateValue("location", locationCode);*/
		String location        = "";
		String subLocationCode = getItemSubfieldData(subLocationSubfield, itemField);
		if (subLocationCode != null && subLocationCode.length() > 0) {
			location += translateValue("sub_location", subLocationCode, identifier);
		} else {
			String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
			location = translateValue("location", locationCode, identifier);
		}
		String shelvingLocation = getItemSubfieldData(shelvingLocationSubfield, itemField);
		if (shelvingLocation != null && shelvingLocation.length() > 0) {
			if (location.length() > 0) {
				location += " - ";
			}
			location += translateValue("shelf_location", shelvingLocation, identifier);
		}
		return location;
	}

}
