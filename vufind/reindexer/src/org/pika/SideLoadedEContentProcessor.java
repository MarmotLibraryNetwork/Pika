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
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.*;

/**
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 12/15/2015
 * Time: 3:03 PM
 */
class SideLoadedEContentProcessor extends IlsRecordProcessor{
	SideLoadedEContentProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		return true;
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier) {
		//For ILS Records, we can create multiple different records, one for print and order items,
		//and one or more for ILS eContent items.
		//For Sideloaded Econtent there will only be one related record
		HashSet<RecordInfo> allRelatedRecords = new HashSet<>();

		try{
			//Now look for eContent items
			RecordInfo recordInfo = loadEContentRecord(groupedWork, identifier, record);
			allRelatedRecords.add(recordInfo);

			//Do updates based on the overall bib (shared regardless of scoping)
			String primaryFormat = null;
			for (RecordInfo ilsRecord : allRelatedRecords) {
				primaryFormat = ilsRecord.getPrimaryFormat();
				if (primaryFormat != null){
					break;
				}
			}
			if (primaryFormat == null) primaryFormat = "Unknown";
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

			groupedWork.addHoldings(1);

			scopeItems(recordInfo, groupedWork, record);
		}catch (Exception e){
			logger.error("Error updating grouped work for MARC record with identifier " + identifier, e);
		}
	}

	private RecordInfo loadEContentRecord(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record){
		//We will always have a single record
		return getEContentIlsRecord(groupedWork, record, identifier);
	}

	private RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier) {
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);

		Date dateAdded = indexer.getDateFirstDetected(identifier.getSource(), identifier.getIdentifier());
		itemInfo.setDateAdded(dateAdded);

		itemInfo.setLocationCode(indexingProfileSourceDisplayName);
		//No itypes for Side loaded econtent
		//itemInfo.setITypeCode();
		//itemInfo.setIType();
		itemInfo.setCallNumber("Online " + indexingProfileSourceDisplayName);
		itemInfo.setItemIdentifier(identifier.getIdentifier());
		itemInfo.setShelfLocation(indexingProfileSourceDisplayName);

		//No Collection for Side loaded eContent
		//itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

		itemInfo.seteContentSource(indexingProfileSourceDisplayName);
//		itemInfo.seteContentProtectionType("external");

		RecordInfo relatedRecord = groupedWork.addRelatedRecord(identifier);
		relatedRecord.addItem(itemInfo);
		loadEContentUrl(record, itemInfo, identifier);

		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		itemInfo.setDetailedStatus("Available Online");

		return relatedRecord;
	}

}
