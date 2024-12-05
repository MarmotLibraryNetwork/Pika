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

import au.com.bytecode.opencsv.CSVReader;
import org.apache.logging.log4j.Logger;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.io.File;
import java.io.FileReader;
import java.sql.*;
import java.text.ParseException;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.time.format.DateTimeFormatter;
import java.time.temporal.ChronoUnit;
import java.util.*;
import java.util.Date;


abstract public class PolarisRecordProcessor extends IlsRecordProcessor {

	protected HashSet<String> availableStatusCodes       = new HashSet<>();
	protected HashSet<String> libraryUseOnlyStatusCodes  = new HashSet<>();
	protected HashSet<String> validCheckedOutStatusCodes = new HashSet<>();
	protected char isItemHoldableSubfield = '5';

	private PreparedStatement itemToRecordStatement;
	private PreparedStatement clearItemIdsForBibStatement;
	private PreparedStatement updateExtractInfoStatement;
	private int indexingProfileId;

	private       String                       exportPath;
	private final HashMap<String, LocalDate> dueDateInfoFromExport = new HashMap<>();

	PolarisRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		loadDateAddedFromRecord = true;

		try {
			indexingProfileId = indexingProfileRS.getInt("id");

			String availableStatusString = indexingProfileRS.getString("availableStatuses");
			String checkedOutStatuses     = indexingProfileRS.getString("checkedOutStatuses");
			String libraryUseOnlyStatuses = indexingProfileRS.getString("libraryUseOnlyStatuses");
			if (availableStatusString != null && !availableStatusString.isEmpty()) {
				availableStatusCodes.addAll(Arrays.asList(availableStatusString.split("\\|")));
			}
			if (checkedOutStatuses != null && !checkedOutStatuses.isEmpty()){
				validCheckedOutStatusCodes.addAll(Arrays.asList(checkedOutStatuses.split("\\|")));
			}
			if (libraryUseOnlyStatuses != null && !libraryUseOnlyStatuses.isEmpty()){
				libraryUseOnlyStatusCodes.addAll(Arrays.asList(libraryUseOnlyStatuses.split("\\|")));
			}
			try {
				exportPath = indexingProfileRS.getString("marcPath");
				loadDueDateInformation();
			} catch (Exception e) {
				logger.error("Unable to load marc path from indexing profile; or load due dates");
			}


		} catch (SQLException e) {
			logger.error("Error loading indexing profile information from database for PolarisRecordProcessor", e);
		}
		try {
			updateExtractInfoStatement = pikaConn.prepareStatement("INSERT INTO `ils_extract_info` (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)");
			// unique key is indexingProfileId and ilsId combined
			itemToRecordStatement       = pikaConn.prepareStatement("INSERT INTO `ils_itemid_to_ilsid` (itemId, ilsId) VALUES (?, ?) ON DUPLICATE KEY UPDATE ilsId=VALUE(ilsId)");
			clearItemIdsForBibStatement = pikaConn.prepareStatement("DELETE FROM `ils_itemid_to_ilsid` WHERE `ilsId` = ?");
		} catch (SQLException e) {
			logger.error("Error preparing statement for Polaris item to record Ids");
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

	@Override
	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record){
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
		if (!itemRecords.isEmpty()) {
			removeItemIdToRecordIdEntries(identifier);
			for (DataField itemField : itemRecords) {
				setItemIdToRecordIdEntry(getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField), identifier);
				if (!isItemSuppressed(itemField, identifier)) {
					getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				}
			}
		}
	}

	private void removeItemIdToRecordIdEntries(RecordIdentifier identifier) {
		try {
			clearItemIdsForBibStatement.setString(1, identifier.getIdentifier());
			int result = clearItemIdsForBibStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error delete item Ids from database for bibId {}", identifier, e);
		}
	}

	private void setItemIdToRecordIdEntry(String itemId, RecordIdentifier identifier) {
		try {
			// TODO: Ignore for suppressed items?
			// TODO: Should use a deleted date?  Delete with database clean-up at 6 months
			itemToRecordStatement.setString(1, itemId);
			itemToRecordStatement.setString(2, identifier.getIdentifier());
			int result = itemToRecordStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error setting item to record entry");
		}
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

	private static final DateTimeFormatter dueDateReportFormatter = DateTimeFormatter.ofPattern("M/d/yyyy h:mm:ss a").withZone(ZoneId.systemDefault());
	void loadDueDateInformation() {
		String filePath     = exportPath + "/due_dates.csv";
		File   dueDatesFile = new File(filePath);
		int    i            = 0;
		if (dueDatesFile.exists()) {
			if (fullReindex) {
				Instant fileDate = Instant.ofEpochMilli(dueDatesFile.lastModified());
				if (fileDate.isBefore(Instant.now().minus(1, ChronoUnit.DAYS))){
					logger.warn("Due date report more than a day old. Please investigate.");
				}
			}
			try (CSVReader reader = new CSVReader(new FileReader(dueDatesFile))) {
				reader.readNext(); // ignore first line
				String[] dueDateData;
				while ((dueDateData = reader.readNext()) != null) {
					LocalDate dueDate = LocalDate.parse(dueDateData[0], dueDateReportFormatter);
					String    itemId  = dueDateData[1];
					dueDateInfoFromExport.put(itemId, dueDate);
					i++;
				}
			} catch (Exception e) {
				logger.error("Error loading due dates from due date report", e);
			}
			logger.info("Loaded {} item due dates from {} file lines", dueDateInfoFromExport.size(), i);
		} else if (fullReindex) {
			logger.warn("Due dates report file not found. {}", filePath);
		}
	}

	protected void getDueDate(DataField itemField, ItemInfo itemInfo) {
		LocalDate dueDate = dueDateInfoFromExport.get(itemInfo.getItemIdentifier());
		if (dueDate == null) {
			itemInfo.setDueDate("");
		}else{
			itemInfo.setDueDate(displayDateFormatter.format(dueDate));
		}
	}

	protected void setDetailedStatus(ItemInfo itemInfo, DataField itemField, String itemStatus, RecordIdentifier identifier) {
		//See if we need to override based on the last check in date
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			itemInfo.setDetailedStatus(overriddenStatus);
		}else {
			if (validCheckedOutStatusCodes.contains(itemStatus)) {
				LocalDate dueDate = dueDateInfoFromExport.get(itemInfo.getItemIdentifier());
				if (dueDate == null) {
					itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
				}else{
					itemInfo.setDetailedStatus("Due " + getDisplayDueDate(dueDate, itemInfo.getItemIdentifier()));
				}
			} else {
				itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
			}
		}
	}

	private static final DateTimeFormatter displayDateFormatter = DateTimeFormatter.ofPattern("MMM d, yyyy").withZone(ZoneId.systemDefault());
	private String getDisplayDueDate(LocalDate dueDate, String identifier){
		try {
			return displayDateFormatter.format(dueDate);
		}catch (Exception e){
			logger.warn("Could not load display due date for dueDate {} for identifier {}", dueDate, identifier, e);
		}
		return "Unknown";
	}

	//TODO:
//	RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, DataField itemField){
//
//	}

}
