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

class SacramentoRecordProcessor extends SierraRecordProcessor {
	private final String kitKeeperMaterialType     = "o";
	private final String bibLevelLocationsSubfield = "a";
	private final String econtentSourceField       = "901a";

	SacramentoRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	// This version of this method has a special case for KitKeepers
	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String  status    = itemInfo.getStatusCode();

		if (!status.isEmpty()) {
			if (status.equals("KitKeeperStatus")) {
				available = true;
			} else if (availableStatusCodes.contains(status.toLowerCase())) {
				if (isEmptyDueDate(itemInfo.getDueDate())) {
					available = true;
				}
			}
		}
		return available;
	}

	@Override
	protected HoldabilityInformation isItemHoldable(ItemInfo itemInfo, Scope curScope, HoldabilityInformation isHoldableUnscoped) {
		String  status    = itemInfo.getStatusCode();
		if (status.equals("KitKeeperStatus")) {
			// If the record is Kit Keeper, make it not holdable.
			return new HoldabilityInformation(false, new HashSet<Long>());
		}
		return super.isItemHoldable(itemInfo, curScope, isHoldableUnscoped);
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, RecordIdentifier identifier) {
		//For Sacramento, LION, load audiences based on collection code rather than based on the 008 and 006 fields
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

	@Override
	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record) {
		super.loadUnsuppressedPrintItems(groupedWork, recordInfo, identifier, record);

		// Handle Special Itemless Print Bibs
		if (recordInfo.getNumPrintCopies() == 0){
			String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);

			// Handle KitKeeper Records
			if (matType != null && !matType.isEmpty() && kitKeeperMaterialType.equals(matType)){

				String      url          = MarcUtil.getFirstFieldVal(record, "856u");
				Set<String> bibLocations = MarcUtil.getFieldList(record, sierraRecordFixedFieldsTag + bibLevelLocationsSubfield);
				for (String bibLocationField : bibLocations) {
					ItemInfo itemInfo     = new ItemInfo();
					String   locationCode = bibLocationField.trim();
					String   itemStatus   = "KitKeeperStatus";

					//if the status and location are null, we can assume this is not a valid item
					if (itemNotValid(itemStatus, locationCode)) return;

					itemInfo.setLocationCode(locationCode);
					itemInfo.setShelfLocationCode(locationCode);
					itemInfo.setShelfLocation(translateValue("shelf_location", locationCode, identifier));
//					// Don't use the regular method. Just translate and set based on bib locations
					itemInfo.setStatusCode(itemStatus);

					setDetailedStatus(itemInfo, null, itemStatus, identifier);
					loadItemCallNumber(record, null, itemInfo, identifier);

					// Get the url for the action button display
					itemInfo.seteContentUrl(url);

					recordInfo.addItem(itemInfo);
				}

			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		//For arlington and sacramento, eContent will always have no items on the bib record.
		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
		if (!items.isEmpty()) {
			return unsuppressedEcontentRecords;
		} else {
			//No items so we can continue on.

			String econtentSource = MarcUtil.getFirstFieldVal(record, econtentSourceField);
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
					itemInfo.setCallNumber("Online");
					itemInfo.seteContentSource(econtentSource);
//                  itemInfo.setShelfLocation(econtentSource); // this sets the owning location facet.  This isn't needed for Sacramento
					itemInfo.setIType("eCollection");
					itemInfo.setDetailedStatus("Available Online");
					RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier.getIdentifier());
					relatedRecord.setSubSource(indexingProfileSource);
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
