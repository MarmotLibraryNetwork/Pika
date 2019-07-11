package org.vufind;

import au.com.bytecode.opencsv.CSVReader;
import org.apache.log4j.Logger;
import org.marc4j.MarcReader;
import org.marc4j.MarcStreamReader;
import org.marc4j.marc.*;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileReader;
import java.io.InputStreamReader;
import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;
import java.util.regex.Pattern;

/**
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 4/25/14
 * Time: 11:02 AM
 */
class AACPLRecordProcessor extends IlsRecordProcessor {
	private HashSet<String> bibsWithOrders = new HashSet<>();

	AACPLRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

		//get a list of bibs that have order records on them
		File ordersFile = new File(marcPath + "/Pika_orders.mrc");
		if (ordersFile.exists()) {
			try {
				MarcReader ordersReader = new MarcStreamReader(new FileInputStream(ordersFile));
				while (ordersReader.hasNext()) {
					Record marcRecord = ordersReader.next();
					VariableField recordNumberField = marcRecord.getVariableField("001");
					if (recordNumberField != null && recordNumberField instanceof ControlField) {
						ControlField recordNumberCtlField = (ControlField) recordNumberField;
						bibsWithOrders.add(recordNumberCtlField.getData());
					}

				}
				logger.info("Finished reading records with orders");
			} catch (Exception e) {
				logger.error("Error reading orders file ", e);
			}
		} else {
			logger.warn("Could not find orders file at " + ordersFile.getAbsolutePath());
		}
	}

	protected boolean isItemSuppressed(DataField curItem) {
		if (statusSubfieldIndicator != ' ') {
			Subfield statusSubfield = curItem.getSubfield(statusSubfieldIndicator);
			//For Anne Arundel, the status is blank if the item is on shelf
			if (statusSubfield != null) {
				if (statusesToSuppressPattern != null && statusesToSuppressPattern.matcher(statusSubfield.getData().trim()).matches()) {
					return true;
				}
			}
		}
		Subfield locationSubfield = curItem.getSubfield(locationSubfieldIndicator);
		if (locationSubfield == null) {
			return true;
		} else {
			if (locationsToSuppressPattern != null && locationsToSuppressPattern.matcher(locationSubfield.getData().trim()).matches()) {
				return true;
			}
		}
		if (collectionSubfield != ' ') {
			Subfield collectionSubfieldValue = curItem.getSubfield(collectionSubfield);
			if (collectionSubfieldValue != null) {
				if (collectionsToSuppressPattern != null && collectionsToSuppressPattern.matcher(collectionSubfieldValue.getData().trim()).matches()) {
					return true;
				}
			}
		}
		return false;
	}

	protected String getItemStatus(DataField itemField, String recordIdentifier) {
		String subfieldData = getItemSubfieldData(statusSubfieldIndicator, itemField);
		String shelfLocationData = getItemSubfieldData(shelvingLocationSubfield, itemField);
		if (shelfLocationData.equalsIgnoreCase("Z-ON-ORDER") || shelfLocationData.equalsIgnoreCase("ON-ORDER")) {
			subfieldData = "On Order";
		} else {
			if (subfieldData == null) {
				subfieldData = "ONSHELF";
			} else if (translateValue("item_status", subfieldData, recordIdentifier, false) == null) {
				subfieldData = "ONSHELF";
			}
		}
		return subfieldData;
	}


	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		if (itemInfo.getStatusCode().equals("ONSHELF")) {
			available = true;
		}
		return available;
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, String identifier) {
		String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String location = translateValue("location", locationCode, identifier);
		String shelvingLocation = itemInfo.getShelfLocationCode();
		if (location == null) {
			location = translateValue("shelf_location", shelvingLocation, identifier);
		} else {
			location += " - " + translateValue("shelf_location", shelvingLocation, identifier);
		}
		return location;
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For Wake County, load audiences based on collection code rather than based on the 008 and 006 fields
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems) {
			String collection = printItem.getCollection();
			if (collection != null) {
				targetAudiences.add(collection.toLowerCase());
			}
		}

		HashSet<String> translatedAudiences = translateCollection("audience", targetAudiences, identifier);
		groupedWork.addTargetAudiences(translatedAudiences);
		groupedWork.addTargetAudiencesFull(translatedAudiences);
	}

	@Override
	protected void loadLiteraryForms(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For Arlington we can load the literary forms based off of the location code:
		// ??f?? = Fiction
		// ??n?? = Non-Fiction
		// ??x?? = Other
		String literaryForm = null;
		for (ItemInfo printItem : printItems) {
			String locationCode = printItem.getShelfLocationCode();
			if (locationCode != null) {
				literaryForm = getLiteraryFormForLocation(locationCode);
				if (literaryForm != null) {
					break;
				}
			}
		}
		if (literaryForm == null) {
			literaryForm = "Other";
		}
		groupedWork.addLiteraryForm(literaryForm);
		groupedWork.addLiteraryFormFull(literaryForm);
	}

	private Pattern nonFicPattern = Pattern.compile(".*nonfic.*", Pattern.CASE_INSENSITIVE);
	private Pattern ficPattern = Pattern.compile(".*fic.*", Pattern.CASE_INSENSITIVE);

	private String getLiteraryFormForLocation(String locationCode) {
		String literaryForm = null;
		if (nonFicPattern.matcher(locationCode).matches()) {
			literaryForm = "Non Fiction";
		} else if (ficPattern.matcher(locationCode).matches()) {
			literaryForm = "Fiction";
		}
		return literaryForm;
	}

	protected void setShelfLocationCode(DataField itemField, ItemInfo itemInfo, String recordIdentifier) {
		//For Symphony the status field holds the location code unless it is currently checked out, on display, etc.
		//In that case the location code holds the permanent location
		String subfieldData = getItemSubfieldData(statusSubfieldIndicator, itemField);
		boolean loadFromPermanentLocation = false;
		if (subfieldData == null) {
			loadFromPermanentLocation = true;
		} else if (translateValue("item_status", subfieldData, recordIdentifier, false) != null) {
			loadFromPermanentLocation = true;
		}
		if (loadFromPermanentLocation) {
			subfieldData = getItemSubfieldData(shelvingLocationSubfield, itemField);
		}
		itemInfo.setShelfLocationCode(subfieldData);
	}

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record, boolean hasTangibleItems) {
		if (bibsWithOrders.contains(recordInfo.getRecordIdentifier())) {
			if (recordInfo.getNumPrintCopies() == 0 && recordInfo.getNumCopiesOnOrder() == 0) {
				ItemInfo itemInfo = new ItemInfo();
				itemInfo.setLocationCode("aacpl");
				itemInfo.setItemIdentifier(recordInfo.getRecordIdentifier());
				itemInfo.setNumCopies(1);
				itemInfo.setIsEContent(false);
				itemInfo.setIsOrderItem(true);
				itemInfo.setCallNumber("ON ORDER");
				itemInfo.setSortableCallNumber("ON ORDER");
				itemInfo.setDetailedStatus("On Order");
				Date tomorrow = new Date();
				tomorrow.setTime(tomorrow.getTime() + 1000 * 60 * 60 * 24);
				itemInfo.setDateAdded(tomorrow);
				//Format and Format Category should be set at the record level, so we don't need to set them here.

				//String formatByShelfLocation = translateValue("shelf_location_to_format", bibsWithOrders.get(recordInfo.getRecordIdentifier()), recordInfo.getRecordIdentifier());
				//itemInfo.setFormat(translateValue("format", formatByShelfLocation, recordInfo.getRecordIdentifier()));
				//itemInfo.setFormatCategory(translateValue("format_category", formatByShelfLocation, recordInfo.getRecordIdentifier()));
				itemInfo.setFormat("On Order");
				itemInfo.setFormatCategory("");

				//Add the library this is on order for
				itemInfo.setShelfLocation("On Order");

				recordInfo.addItem(itemInfo);
			} else {
				logger.debug("Skipping order item because there are print or order records available");
			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);

		// AACPL should only have 1 item record on a eContent record
		if (itemRecords.size() == 1) {
			for (DataField itemField : itemRecords) {
				String location = itemField.getSubfield(locationSubfieldIndicator).getData();
				if (location != null) {
					if (location.equalsIgnoreCase("Z-ELIBRARY") || location.equalsIgnoreCase("Z-ONLINEBK")) {
						RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
						unsuppressedEcontentRecords.add(eContentRecord);
					}
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, String identifier, DataField itemField){
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);
		RecordInfo relatedRecord = null;

		loadDateAdded(identifier, itemField, itemInfo);
		String itemLocation = getItemSubfieldData(locationSubfieldIndicator, itemField);
		itemInfo.setLocationCode(itemLocation);
//		String itemSublocation = getItemSubfieldData(subLocationSubfield, itemField);
//		if (itemSublocation == null){
//			itemSublocation = "";
//		}
//		if (itemSublocation.length() > 0){
//			itemInfo.setSubLocation(translateValue("sub_location", itemSublocation, identifier));
//		}
		itemInfo.setITypeCode(getItemSubfieldData(iTypeSubfield, itemField));
		itemInfo.setIType(translateValue("itype", getItemSubfieldData(iTypeSubfield, itemField), identifier));
		loadItemCallNumber(record, itemField, itemInfo);
		itemInfo.setItemIdentifier(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField));

		String econtentSource = MarcUtil.getFirstFieldVal(record, "092a");
		if (econtentSource == null || econtentSource.equals("")) {
			logger.warn("Did not find an econtent source for " + identifier);
		}
//		itemInfo.setShelfLocation(econtentSource);
		itemInfo.setShelfLocation("Online");

		itemInfo.setCollection(econtentSource);

		itemInfo.seteContentProtectionType("external");
		itemInfo.seteContentSource(econtentSource);
		itemInfo.setDetailedStatus("Available Online");

		//Get the url if any
		loadEContentUrl(record, itemInfo);

		relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier);
		relatedRecord.setSubSource(profileType);
		relatedRecord.addItem(itemInfo);
		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		return relatedRecord;
	}


	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		String protectionType = econtentItem.geteContentProtectionType();
		switch (protectionType) {
			case "external":
				String iType = econtentItem.getITypeCode();
				if (iType != null) {
					String translatedFormat = translateValue("econtent_itype_format", iType, econtentRecord.getRecordIdentifier());
					String translatedFormatCategory = translateValue("econtent_itype_format_category", iType, econtentRecord.getRecordIdentifier());
					String translatedFormatBoost = translateValue("econtent_itype_format_boost", iType, econtentRecord.getRecordIdentifier());
					econtentItem.setFormat(translatedFormat);
					econtentItem.setFormatCategory(translatedFormatCategory);
					econtentRecord.setFormatBoost(Long.parseLong(translatedFormatBoost));
				} else {
					logger.warn("Did not get a iType for external eContent " + econtentRecord.getFullIdentifier());
				}
				break;
			default:
				logger.warn("Unknown protection type " + protectionType);
				break;
		}
	}

}