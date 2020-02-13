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
	private PreparedStatement getDateAddedStmt;
	SideLoadedEContentProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		try{
			getDateAddedStmt = pikaConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE ilsId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		}catch (Exception e){
			logger.error("Unable to setup prepared statement for date added to catalog");
		}
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		return true;
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
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
			updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier, primaryFormat);

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
			loadPopularity(groupedWork, identifier);

			groupedWork.addHoldings(1);

			scopeItems(recordInfo, groupedWork, record);
		}catch (Exception e){
			logger.error("Error updating grouped work for MARC record with identifier " + identifier, e);
		}
	}

	private RecordInfo loadEContentRecord(GroupedWorkSolr groupedWork, String identifier, Record record){
		//We will always have a single record
		return getEContentIlsRecord(groupedWork, record, identifier);
	}

	private RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, String identifier) {
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);

		loadDateAdded(identifier, itemInfo);
		itemInfo.setLocationCode(profileType);
		//No itypes for Side loaded econtent
		//itemInfo.setITypeCode();
		//itemInfo.setIType();
		itemInfo.setCallNumber("Online " + profileType);
		itemInfo.setItemIdentifier(identifier);
		itemInfo.setShelfLocation(profileType);

		//No Collection for Side loaded eContent
		//itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

		itemInfo.seteContentSource(profileType);
//		itemInfo.seteContentProtectionType("external");

		RecordInfo relatedRecord = groupedWork.addRelatedRecord(profileType, identifier);
		relatedRecord.addItem(itemInfo);
		loadEContentUrl(record, itemInfo, identifier);

		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		itemInfo.setDetailedStatus("Available Online");

		return relatedRecord;
	}

	private void loadDateAdded(String identfier, ItemInfo itemInfo) {
		try {
			getDateAddedStmt.setString(1, identfier);
			ResultSet getDateAddedRS = getDateAddedStmt.executeQuery();
			if (getDateAddedRS.next()) {
				long timeAdded = getDateAddedRS.getLong(1);
				Date curDate = new Date(timeAdded * 1000);
				itemInfo.setDateAdded(curDate);
				getDateAddedRS.close();
			}else{
				logger.debug("Could not determine date added for " + identfier);
			}
		}catch (Exception e){
			logger.error("Unable to load date added for " + identfier);
		}
	}
}
