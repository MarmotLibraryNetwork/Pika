/*
 * Copyright (C) 2024  Marmot Library Network
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
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.*;
import java.text.ParseException;
import java.time.temporal.ChronoUnit;
import java.util.Arrays;
import java.util.Date;
import java.util.HashSet;


abstract public class PolarisRecordProcessor extends IlsRecordProcessor {

	protected HashSet<String> availableStatusCodes       = new HashSet<String>();
	protected HashSet<String> libraryUseOnlyStatusCodes  = new HashSet<String>();
	//protected HashSet<String> validCheckedOutStatusCodes = new HashSet<String>();
	protected char isItemHoldableSubfield = '5';

	private PreparedStatement updateExtractInfoStatement;
	private int indexingProfileId;

	PolarisRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		loadDateAddedFromRecord = true;

		try {
			indexingProfileId = indexingProfileRS.getInt("id");

			String availableStatusString = indexingProfileRS.getString("availableStatuses");
			//String checkedOutStatuses     = indexingProfileRS.getString("checkedOutStatuses");
			String libraryUseOnlyStatuses = indexingProfileRS.getString("libraryUseOnlyStatuses");
			if (availableStatusString != null && !availableStatusString.isEmpty()) {
				availableStatusCodes.addAll(Arrays.asList(availableStatusString.split("\\|")));
			}
			//if (checkedOutStatuses != null && !checkedOutStatuses.isEmpty()){
			//	validCheckedOutStatusCodes.addAll(Arrays.asList(checkedOutStatuses.split("\\|")));
			//}
			if (libraryUseOnlyStatuses != null && !libraryUseOnlyStatuses.isEmpty()){
				libraryUseOnlyStatusCodes.addAll(Arrays.asList(libraryUseOnlyStatuses.split("\\|")));
			}

			updateExtractInfoStatement = pikaConn.prepareStatement("INSERT INTO `ils_extract_info` (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined

		} catch (SQLException e) {
			logger.error("Error loading indexing profile information from database for PolarisRecordProcessor", e);
		}
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		String status = itemInfo.getStatusCode();
		return (status != null && !status.isEmpty() && availableStatusCodes.contains(status.toLowerCase()));
	}

	protected boolean isLibraryUseOnly(ItemInfo itemInfo) {
		String status = itemInfo.getStatusCode();
		return status != null && !status.isEmpty() && libraryUseOnlyStatusCodes.contains(status);
	}

	/**
	 * @param itemInfo Check if the item's holdable field is on
	 * @return Unscoped HoldableInformation
	 */
	@Override
	protected HoldabilityInformation isItemHoldableUnscoped(ItemInfo itemInfo) {
		String  isHoldableCode = itemInfo.getIsHoldableCode();
		boolean holdable       = isHoldableCode != null && isHoldableCode.equals("1");
		if (holdable) {
			// Allow for disabling holdability through Pika profile settings
			return super.isItemHoldableUnscoped(itemInfo);
		}
		return new HoldabilityInformation(false, new HashSet<>());
	}

	@Override
	protected void setItemIsHoldableCode(DataField itemField, ItemInfo itemInfo, RecordIdentifier recordIdentifier) {
		String isItemHoldableCode = getItemSubfieldData(isItemHoldableSubfield, itemField);
		itemInfo.setIsHoldableCode(isItemHoldableCode);
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, RecordIdentifier identifier) {
		// Shelf location will be a combination of organization, shelf-location and collection
		String translatedShelfLocation = "";
		String collection              = itemInfo.getCollection();
		String locationCode            = itemInfo.getLocationCode();
		String shelfLocation           = null;
		if (itemField != null) {
			shelfLocation = getItemSubfieldData(shelvingLocationSubfield, itemField);
		}
		if (locationCode != null && !locationCode.isEmpty()){
			String organization = translateValue("organization", locationCode, identifier);
			translatedShelfLocation += organization;
		}
		if (shelfLocation != null && !shelfLocation.isEmpty()){
			String translation = translateValue("shelf_location", shelfLocation, identifier);
			if (translation != null && !translation.isEmpty()/* && !translation.equals(shelfLocation)*/) {
				if (!translatedShelfLocation.isEmpty()) {
					translatedShelfLocation += " ";
				}
				translatedShelfLocation += translation;
			}
		}
		if (collection != null && !collection.isEmpty()) {
			if (!translatedShelfLocation.isEmpty()) {
				translatedShelfLocation += " ";
			}
			translatedShelfLocation += collection;
		}
		return translatedShelfLocation;
	}

	private final Date tomorrow = Date.from(new Date().toInstant().plus(1, ChronoUnit.DAYS));

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record) {
		for (ItemInfo curItem : recordInfo.getRelatedItems()) {
			if (curItem.getDetailedStatus().equals("On Order")){
				curItem.setIsOrderItem();
				curItem.setDateAdded(tomorrow);
			}
		}
	}

	@Override
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
					logger.warn("Invalid date in 008 '{}' for {}; Marking to extract to correct.", dateAddedStr, identifier);
					// Mark for Extraction
					updateLastExtractTimeForRecord(identifier.getIdentifier());
				}
			}
		}

		// As fallback, use grouping first detected date
		Date dateAdded = indexer.getDateFirstDetected(identifier);
		itemInfo.setDateAdded(dateAdded);
	}
	@Override
	protected void updateLastExtractTimeForRecord(String identifier) {
		if (identifier != null && !identifier.isEmpty()) {
			try {
				updateExtractInfoStatement.setInt(1, indexingProfileId);
				updateExtractInfoStatement.setString(2, identifier);
				updateExtractInfoStatement.setNull(3, Types.INTEGER);
				int result = updateExtractInfoStatement.executeUpdate();
			} catch (SQLException e) {
				logger.error("Unable to update ils_extract_info table for {}", identifier, e);
			}
		}
	}

	//TODO:
//	RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, DataField itemField){
//
//	}

}
