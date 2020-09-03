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

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;

/**
 * ILS Indexing with customizations specific to Marmot.  Handles processing
 * - print items
 * - econtent items stored within Sierra
 * - order items
 *
 * Pika
 * User: Mark Noble
 * Date: 2/21/14
 * Time: 3:00 PM
 */
class MarmotRecordProcessor extends IIIRecordProcessor {
	MarmotRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		availableStatus      = "-dowju(";
		libraryUseOnlyStatus = "ohu";

		loadOrderInformationFromExport();

		validCheckedOutStatusCodes.add("d");
		validCheckedOutStatusCodes.add("o");
		validCheckedOutStatusCodes.add("u");
	}

	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record) {
		List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
		for (DataField itemField : itemRecords) {
			if (!isItemSuppressed(itemField)) {
				//Check to see if the item has an eContent indicator
				boolean isEContent  = false;
				boolean isOverDrive = false;
				if (useEContentSubfield) {
					if (itemField.getSubfield(eContentSubfieldIndicator) != null) {
						String eContentData = itemField.getSubfield(eContentSubfieldIndicator).getData();
						if (eContentData != null && !eContentData.isEmpty()) {
							isEContent = true;
							if (doAutomaticEcontentSuppression) {
								String[] eContentFields = eContentData.split(":");
								String   sourceType     = eContentFields[0].toLowerCase().trim();
								if (sourceType.equals("overdrive")) {
									isOverDrive = true;
								}
							}
						}
					}
				}
				if (!isOverDrive && !isEContent) {
					getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				}
			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		List<DataField>  itemRecords                 = MarcUtil.getDataFields(record, itemTag);
		for (DataField itemField : itemRecords) {
			if (!isItemSuppressed(itemField)) {
				//Check to see if the item has an eContent indicator
				if (useEContentSubfield) {
					if (itemField.getSubfield(eContentSubfieldIndicator) != null) {
						String eContentData = itemField.getSubfield(eContentSubfieldIndicator).getData();
						if (eContentData != null && !eContentData.isEmpty()) {
							// Is an eContent Item
							RecordInfo eContentRecord = null;
							if (doAutomaticEcontentSuppression) {
								// Skip Hoopla and Overdrive items
								String source;
								if (eContentData.indexOf(':') >= 0) {
									//The econtent field used to require multiple parts separated by a colon; this in now longer required,
									//But this will take the source from data that still has the other pieces
									String[] eContentFields = eContentData.split(":");
									source = eContentFields[0].trim().toLowerCase();
								} else {
									source = itemField.getSubfield(eContentSubfieldIndicator).getData().trim().toLowerCase();
								}
								// Don't index Overdrive or Hoopla Items
								if (!source.contains("overdrive") && !source.contains("hoopla")) {
									eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
								}
							} else {
								eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
							}
							if (eContentRecord != null) {
								unsuppressedEcontentRecords.add(eContentRecord);
							}
						}
					}
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		String iType = econtentItem.getITypeCode();
		if (iType != null) {
			String translatedFormat         = translateValue("econtent_itype_format", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatCategory = translateValue("econtent_itype_format_category", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatBoost    = translateValue("econtent_itype_format_boost", iType, econtentRecord.getRecordIdentifier());
			econtentItem.setFormat(translatedFormat);
			econtentItem.setFormatCategory(translatedFormatCategory);
			econtentRecord.setFormatBoost(Long.parseLong(translatedFormatBoost));
		} else {
			logger.warn("Did not get a iType for external eContent " + econtentRecord.getFullIdentifier());
		}

	}

}
