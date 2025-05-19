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
	SideLoadedPhysicalRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
			super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
			if (formatSource.equals("item")){
				logger.error("'item' is not a valid option for side loaded physical collection format determination");
			}
		}

		@Override
		protected boolean isItemAvailable(ItemInfo itemInfo) {
			return true;
		}

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
			//No iTypes for Side loaded eContent
			//itemInfo.setITypeCode();
			//itemInfo.setIType();

			//itemInfo.setCallNumber("External " + indexingProfileSourceDisplayName);
			// Skip setting a callNumber for now

			itemInfo.setItemIdentifier(identifier.getIdentifier());
			itemInfo.setShelfLocation(indexingProfileSourceDisplayName);

			//No Collection for Side loaded eContent
			//itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

			//itemInfo.seteContentSource(indexingProfileSourceDisplayName);
			//TODO: create External Source Facet?

			RecordInfo relatedRecord = groupedWork.addRelatedRecord(identifier);
			relatedRecord.addItem(itemInfo);
			//loadEContentUrl(record, itemInfo, identifier);

			loadPrintFormatInformation(relatedRecord, record);

			itemInfo.setDetailedStatus("Available Externally");

			return relatedRecord;
		}

	}
