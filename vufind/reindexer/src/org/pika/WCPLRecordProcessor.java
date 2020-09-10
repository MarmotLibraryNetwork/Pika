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
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 4/25/14
 * Time: 11:02 AM
 */
class WCPLRecordProcessor extends IlsRecordProcessor {

	WCPLRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	private Pattern availableStati = Pattern.compile("^(csa|dc|fd|i|int|os|s|ref|rs|rw|st)$");

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String  status    = itemInfo.getStatusCode();
		if (availableStati.matcher(status).matches()) {
			available = true;
		}
		return available;
	}

	@Override
	public void loadPrintFormatInformation(RecordInfo ilsRecord, Record record) {
		Set<String>     printFormatsRaw            = MarcUtil.getFieldList(record, itemTag + shelvingLocationSubfield);
		HashSet<String> translatedFormats          = translateCollection("format", printFormatsRaw, ilsRecord.getRecordIdentifier());
		HashSet<String> translatedFormatCategories = translateCollection("format_category", printFormatsRaw, ilsRecord.getRecordIdentifier());
		ilsRecord.addFormats(translatedFormats);
		ilsRecord.addFormatCategories(translatedFormatCategories);
		long            formatBoost  = 0L;
		HashSet<String> formatBoosts = translateCollection("format_boost", printFormatsRaw, ilsRecord.getRecordIdentifier());
		for (String tmpFormatBoost : formatBoosts) {
			if (Util.isNumeric(tmpFormatBoost)) {
				formatBoost = Math.max(formatBoost, Long.parseLong(tmpFormatBoost));
			}
		}

//		long            formatBoost  = translateCollection("format_boost", printFormatsRaw, ilsRecord.getRecordIdentifier())
//						.stream().map(Long::parseLong).max(Long::compare).get();

		ilsRecord.setFormatBoost(formatBoost);
	}

	@Override
	protected void loadSystemLists(GroupedWorkSolr groupedWork, Record record) {
		groupedWork.addSystemLists(MarcUtil.getFieldList(record, "449a"));
	}

//	protected boolean isItemSuppressed(DataField curItem) {
//		//Finally suppress staff items
//		Subfield staffSubfield = curItem.getSubfield('o');
//		if (staffSubfield != null) {
//			if (staffSubfield.getData().trim().equals("1")) {
//				return true;
//			}
//		}
//		return super.isItemSuppressed(curItem);
//	}

	@Override
	protected void loadDateAdded(RecordIdentifier identifier, DataField itemField, ItemInfo itemInfo) {
		Date dateAdded = indexer.getDateFirstDetected(identifier.getSource(), identifier.getIdentifier());
		itemInfo.setDateAdded(dateAdded);
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, RecordIdentifier identifier) {
		String locationCode     = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String location         = translateValue("location", locationCode, identifier);
		String shelvingLocation = getItemSubfieldData(shelvingLocationSubfield, itemField);
		if (shelvingLocation != null && !shelvingLocation.equals(locationCode)) {
			if (location == null) {
				location = translateValue("shelf_location", shelvingLocation, identifier);
			} else {
				location += " - " + translateValue("shelf_location", shelvingLocation, identifier);
			}
		}
		return location;
	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For Wake County, load audiences based on collection code rather than based on the 008 and 006 fields
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems) {
			String collection = printItem.getShelfLocationCode();
			if (collection != null) {
				targetAudiences.add(collection.toLowerCase());
			}
		}

		HashSet<String> translatedAudiences = translateCollection("audience", targetAudiences, identifier);
		groupedWork.addTargetAudiences(translatedAudiences);
		groupedWork.addTargetAudiencesFull(translatedAudiences);
	}
}
