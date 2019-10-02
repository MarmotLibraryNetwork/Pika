package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;


/**
 * Custom Record Processing for Sacramento
 *
 * Pika
 * User: Pascal Brammeier
 * Date: 5/24/2018
 */

class SacramentoRecordProcessor extends IIIRecordProcessor {
	private String kitKeeperMaterialType     = "o";
	private String bibLevelLocationsSubfield = "a";

	//TODO: These should be added to indexing profile
	private String materialTypeSubField     = "d";

	SacramentoRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);
		availableStatus          = "-od(j";

		validCheckedOutStatusCodes.add("o");
		validCheckedOutStatusCodes.add("d");

		loadOrderInformationFromExport();
	}

	// This version of this method has a special case for KitKeepers
	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String  status    = itemInfo.getStatusCode();
		String  dueDate   = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();

		if (!status.isEmpty()) {
			if (status.equals("KitKeeperStatus")) {
				available = true;
			} else if (availableStatus.indexOf(status.charAt(0)) >= 0) {
				if (isEmptyDueDate(itemInfo.getDueDate())) {
					available = true;
				}
			}
		}
		return available;
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For Sacramento, LION, Anythink, load audiences based on collection code rather than based on the 008 and 006 fields
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems) {
			String shelfLocationCode = printItem.getShelfLocationCode();
			if (shelfLocationCode != null) {
				targetAudiences.add(shelfLocationCode.toLowerCase());
			} else {
				// Because the order record location code is the same as a shelf location code, we can use that to set a target audience for records with only order records
				String shelfLocation = printItem.getShelfLocation();
				if (shelfLocation.equals("On Order")) {
					String locationCode = printItem.getLocationCode();
					targetAudiences.add(locationCode);
				}
			}
		}

		HashSet<String> translatedAudiences = translateCollection("target_audience", targetAudiences, identifier);
		groupedWork.addTargetAudiences(translatedAudiences);
		groupedWork.addTargetAudiencesFull(translatedAudiences);
	}


	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
		String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
		if (matType != null) {
			if (!matType.equals("-") && !matType.equals(" ")) {
				String translatedFormat = translateValue("material_type", matType, recordInfo.getRecordIdentifier());
				if (translatedFormat != null && !translatedFormat.equals(matType)) {
					String translatedFormatCategory = translateValue("format_category", matType, recordInfo.getRecordIdentifier());
					recordInfo.addFormat(translatedFormat);
					if (translatedFormatCategory != null) {
						recordInfo.addFormatCategory(translatedFormatCategory);
					}
					// use translated value
					String formatBoost = translateValue("format_boost", matType, recordInfo.getRecordIdentifier());
					try {
						Long tmpFormatBoostLong = Long.parseLong(formatBoost);
						recordInfo.setFormatBoost(tmpFormatBoostLong);
						return;
					} catch (NumberFormatException e) {
						logger.warn("Could not load format boost for format " + formatBoost + " profile " + profileType + "; Falling back to default format determination process");
					}
				} else {
					logger.info("Material Type " + matType + " had no translation, falling back to default format determination.");
				}
			} else {
				logger.info("Material Type for " + recordInfo.getRecordIdentifier() + " has empty value '" + matType + "', falling back to default format determination.");
			}
		} else {
			logger.info(recordInfo.getRecordIdentifier() + " did not have a material type, falling back to default format determination.");
		}
		super.loadPrintFormatInformation(recordInfo, record);
	}

	@Override
	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, String identifier, Record record) {
		super.loadUnsuppressedPrintItems(groupedWork, recordInfo, identifier, record);

		// Handle Special Itemless Print Bibs
		if (recordInfo.getNumPrintCopies() == 0){
			String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);

			// Handle KitKeeper Records
			if (matType != null && !matType.isEmpty() && kitKeeperMaterialType.indexOf(matType.charAt(0)) >= 0 ){

				String      url          = MarcUtil.getFirstFieldVal(record, "856u");
				Set<String> bibLocations = MarcUtil.getFieldList(record, sierraRecordFixedFieldsTag + bibLevelLocationsSubfield);
				for (String bibLocationField : bibLocations) {
					ItemInfo itemInfo     = new ItemInfo();
					String   locationCode = bibLocationField.trim();
					String   itemStatus   = "KitKeeperStatus";

					//if the status and location are null, we can assume this is not a valid item
					if (!isItemValid(itemStatus, locationCode)) return;

					itemInfo.setLocationCode(locationCode);
					itemInfo.setShelfLocationCode(locationCode);
					itemInfo.setShelfLocation(translateValue("shelf_location", locationCode, identifier));
//					// Don't use the regular method. Just translate and set based on bib locations
					itemInfo.setStatusCode(itemStatus);

					setDetailedStatus(itemInfo, null, itemStatus, identifier);
					loadItemCallNumber(record, null, itemInfo);

					// Get the url for the action button display
					itemInfo.seteContentUrl(url);

					recordInfo.addItem(itemInfo);
				}

			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		//For arlington and sacramento, eContent will always have no items on the bib record.
		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
		if (items.size() > 0) {
			return unsuppressedEcontentRecords;
		} else {
			//No items so we can continue on.

			String econtentSource = MarcUtil.getFirstFieldVal(record, "901a");
			if (econtentSource != null) {
				// For Sacramento, if the itemless record doesn't have a specified eContent source, don't treat it as an econtent record
				//Get the url
				String url = MarcUtil.getFirstFieldVal(record, "856u");

				if (url != null) {

					//Get the bib location
					String      bibLocation  = null;
					Set<String> bibLocations = MarcUtil.getFieldList(record, sierraRecordFixedFieldsTag + bibLevelLocationsSubfield);
					for (String tmpBibLocation : bibLocations) {
						if (tmpBibLocation.matches("[a-zA-Z]{1,5}")) {
							bibLocation = tmpBibLocation;
							break;
//                }else if (tmpBibLocation.matches("\\(\\d+\\)([a-zA-Z]{1,5})")){
//                    bibLocation = tmpBibLocation.replaceAll("\\(\\d+\\)", "");
//                    break;
						}
					}

					ItemInfo itemInfo = new ItemInfo();
					itemInfo.setIsEContent(true);
					itemInfo.setLocationCode(bibLocation);
//					itemInfo.seteContentProtectionType("external");
					itemInfo.setCallNumber("Online");
					itemInfo.seteContentSource(econtentSource);
//                  itemInfo.setShelfLocation(econtentSource); // this sets the owning location facet.  This isn't needed for Sacramento
					itemInfo.setIType("eCollection");
					itemInfo.setDetailedStatus("Available Online");
					RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier);
					relatedRecord.setSubSource(profileType);
					relatedRecord.addItem(itemInfo);
					itemInfo.seteContentUrl(url);

					// Use the same format determination process for the econtent record (should just be the MatType)
					loadPrintFormatInformation(relatedRecord, record);


					unsuppressedEcontentRecords.add(relatedRecord);
				}
			}
		}
		return unsuppressedEcontentRecords;
	}


}
