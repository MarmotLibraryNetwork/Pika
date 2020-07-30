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
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.*;
import java.util.regex.Pattern;

/**
 * ILS Indexing with customizations specific to Anythink
 * Pika
 * User: Mark Noble
 * Date: 2/21/14
 * Time: 3:00 PM
 */
class AnythinkRecordProcessor extends IlsRecordProcessor {
	private PreparedStatement getDateAddedStmt;
	AnythinkRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		try{
			getDateAddedStmt = pikaConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE source = ? AND ilsId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		}catch (Exception e){
			logger.error("Unable to setup prepared statement for date added to catalog");
		}
	}

	@Override
	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
		Set<String> printFormatsRaw = MarcUtil.getFieldList(record, "949c");
		HashSet<String> printFormats = new HashSet<>();
		for (String curFormat : printFormatsRaw){
			printFormats.add(curFormat.toLowerCase());
		}

		HashSet<String> translatedFormats = translateCollection("format", printFormats, recordInfo.getRecordIdentifier());
		HashSet<String> translatedFormatCategories = translateCollection("format_category", printFormats, recordInfo.getRecordIdentifier());
		recordInfo.addFormats(translatedFormats);
		recordInfo.addFormatCategories(translatedFormatCategories);
		Long formatBoost = 0L;
		HashSet<String> formatBoosts = translateCollection("format_boost", printFormats, recordInfo.getRecordIdentifier());
		for (String tmpFormatBoost : formatBoosts){
			if (Util.isNumeric(tmpFormatBoost)) {
				Long tmpFormatBoostLong = Long.parseLong(tmpFormatBoost);
				if (tmpFormatBoostLong > formatBoost) {
					formatBoost = tmpFormatBoostLong;
				}
			}
		}
		recordInfo.setFormatBoost(formatBoost);
	}

	private static Pattern suppressedItemPattern = Pattern.compile("eqx|ill|laptop|u|vf");
	protected boolean isItemSuppressed(DataField curItem) {
		//Suppressed if |c is w
		Subfield subfieldC = curItem.getSubfield('c');
		if (subfieldC != null){
			if (suppressedItemPattern.matcher(subfieldC.getData()).matches()){
				return true;
			}
		}
		return super.isItemSuppressed(curItem);
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String status = itemInfo.getStatusCode();
		if (status.equals("i") || status.equals("s") || status.equals("y")) {
			available = true;
		}
		return available;
	}

	protected Set<String> getBisacSubjects(Record record){
		return MarcUtil.getFieldList(record, "690a");
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For Anythink, load audiences based on collection code rather than based on the 008 and 006 fields
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems){
			String collection = printItem.getShelfLocationCode();
			if (collection != null) {
				targetAudiences.add(collection.toLowerCase());
			}
		}

		HashSet<String> translatedAudiences = translateCollection("target_audience", targetAudiences, identifier);
		groupedWork.addTargetAudiences(translatedAudiences);
		groupedWork.addTargetAudiencesFull(translatedAudiences);
	}

	@Override
	protected void loadDateAdded(RecordIdentifier identifier, DataField itemField, ItemInfo itemInfo) {
		try {
			getDateAddedStmt.setString(1, identifier.getSource());
			getDateAddedStmt.setString(2, identifier.getIdentifier());
			try (ResultSet getDateAddedRS = getDateAddedStmt.executeQuery()) {
				if (getDateAddedRS.next()) {
					long timeAdded = getDateAddedRS.getLong(1);
					Date curDate   = new Date(timeAdded * 1000);
					itemInfo.setDateAdded(curDate);
				} else {
					logger.debug("Could not determine date added for " + identifier);
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load date added for " + identifier);
		}
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, RecordIdentifier identifier) {
		String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String location = translateValue("location", locationCode, identifier);
		String shelvingLocation = getItemSubfieldData(shelvingLocationSubfield, itemField);
		if (shelvingLocation != null && !shelvingLocation.equals(locationCode)){
			location += " - " + translateValue("shelf_location", shelvingLocation, identifier);
		}
		return location;
	}
}
