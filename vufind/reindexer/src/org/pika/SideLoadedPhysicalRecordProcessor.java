/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import org.apache.logging.log4j.Logger;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.Date;
import java.util.HashSet;

public class SideLoadedPhysicalRecordProcessor extends IlsRecordProcessor{

	final protected String availableExternallyStatus = "Available by Request";
	// This is also set in the default en.ini
	// To use an alternate status translation extend this class.

	SideLoadedPhysicalRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
			super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
			if (formatSource.equals("item")){
				logger.error("'item' is not a valid option for side loaded physical collection format determination. Using 'bib'");
				formatSource = "bib";
			}
		}

	/**
	 * Make Sideloaded Physical Records always available
	 * @param itemInfo The unused Item info object
	 * @return true
	 */
		@Override
		protected boolean isItemAvailable(ItemInfo itemInfo) {
			return true;
		}

	/**
	 * Make Sideloaded Physical Records not holdable.
	 * @param itemInfo The unused item info object
	 * @return that the title isn't holdable
	 */
	@Override
	protected HoldabilityInformation isItemHoldableUnscoped(ItemInfo itemInfo) {
		return new HoldabilityInformation(false, new HashSet<>());
	}

	/**
	 * The primary difference for this method is calling to loadPhyiscalRecord() instead of loadEContentRecord();
	 * And if there is no primary format found, Book will be used.
	 * @param groupedWork          The Solr Document
	 * @param record               MARC record
	 * @param identifier           Record ID
	 * @param loadedNovelistSeries whether the Novelist Series has been loaded
	 */
	@Override
		protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, boolean loadedNovelistSeries) {
			//For ILS Records, we can create multiple different records, one for print and order items,
			//and one or more for ILS eContent items.
			//For Sideloaded collections there will only be one related record

			try {
				RecordInfo          recordInfo        = loadPhysicalRecord(groupedWork, identifier, record);
				HashSet<RecordInfo> allRelatedRecords = new HashSet<RecordInfo>(){{add(recordInfo);}};
				String              primaryFormat     = recordInfo.getPrimaryFormat();

				if (primaryFormat == null) {
					logger.warn("No primary format found for {} record {}", indexingProfileSource, identifier);
					primaryFormat = "Book"; // Default to Book
					//TODO: Is BookClubKit a better default?
				}

				updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier, primaryFormat, loadedNovelistSeries);

				String fullDescription = Util.getCRSeparatedString(MarcUtil.getFieldList(record, "520a"));
				groupedWork.addDescription(fullDescription, primaryFormat);

				loadEditions(groupedWork, record, allRelatedRecords);
				loadPhysicalDescription(groupedWork, record, allRelatedRecords);
				loadLanguageDetails(groupedWork, record, allRelatedRecords, identifier);
				loadPublicationDetails(groupedWork, record, allRelatedRecords);
				loadSystemLists(groupedWork, record);

				if (record.getControlNumber() != null) {
					groupedWork.addKeywords(record.getControlNumber());
				}

				groupedWork.addPopularity(1);

				// Do updates based on the items
				scopeItems(recordInfo, groupedWork, record);
			} catch (Exception e) {
				logger.error("Error updating grouped work for Sideload record with identifier {}", identifier, e);
			}
		}

		private RecordInfo loadPhysicalRecord(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
			//We will always have a single record and a single item
			ItemInfo itemInfo = new ItemInfo();
			//itemInfo.setIsEContent(false);

			Date dateAdded = indexer.getDateFirstDetected(identifier);
			itemInfo.setDateAdded(dateAdded);

			itemInfo.setLocationCode(indexingProfileSourceDisplayName);
			//No iTypes for Side loaded content
			//itemInfo.setITypeCode();
			//itemInfo.setIType();

			//itemInfo.setCallNumber("External " + indexingProfileSourceDisplayName);
			// Skip setting a callNumber for now

			itemInfo.setItemIdentifier(identifier.getIdentifier());
			itemInfo.setShelfLocation(indexingProfileSourceDisplayName);
			//TODO: This gets excluded by location code checking I think

			//Set Physical Sideload name as the Collection facet value
			itemInfo.setCollection(indexingProfileSourceDisplayName);

			RecordInfo relatedRecord = groupedWork.addRelatedRecord(identifier);
			relatedRecord.addItem(itemInfo);

			// Set up link to external request page
			loadEContentUrl(record, itemInfo, identifier);

			loadPrintFormatInformation(relatedRecord, record);

			itemInfo.setDetailedStatus(availableExternallyStatus);

			return relatedRecord;
		}

	/**
	 * Set all statuses for Physical Sideloads to availableExternallyStatus
	 * @param itemInfo   The Unused Item info object
	 * @param identifier The record ID
	 * @return availableExternallyStatus
	 */
	@Override
	protected String getDisplayStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		return availableExternallyStatus;
	}

	/**
	 * Set all statuses for Physical Sideloads to availableExternallyStatus
	 * @param itemInfo   The Unused Item info object
	 * @param identifier The record ID
	 * @return availableExternallyStatus
	 */
	@Override
	protected String getDisplayGroupedStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		return availableExternallyStatus;
	}
}
