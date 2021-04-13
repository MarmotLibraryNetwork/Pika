/*
 * Copyright (C) 2021  Marmot Library Network
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
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;
import java.util.Set;

/**
 * Pika
 *
 * @author pbrammeier
 * 				Date:   3/17/2021
 */
public class NorthernWatersRecordProcessor extends IIIRecordProcessor {
//	private final String econtentSourceField = "901a";
	NorthernWatersRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		List<DataField>  items                       = MarcUtil.getDataFields(record, itemTag);
		if (items.size() > 0) {
			String url = MarcUtil.getFirstFieldVal(record, "856u");
			if (url != null && !url.isEmpty()) {
				for (DataField itemField : items) {
					if (!isItemSuppressed(itemField)) {
						RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
						unsuppressedEcontentRecords.add(eContentRecord);
					}
				}
			}
		}
			return unsuppressedEcontentRecords;
	}

	@Override
	protected String getILSeContentSourceType(Record record, DataField itemField) {
		//Note: this was done quickly using existing examples (that will be suppressed and removed)
		// If Northern Waters wants to use ILS eContent, this functionality will need to be updated.
		String url = MarcUtil.getFirstFieldVal(record, "856u");
		if (url != null && !url.isEmpty()) {
			if (url.contains("overdrive.com") || url.contains("/ContentDetails.htm")){
				return "OverDrive";
			}
		}
		return super.getILSeContentSourceType(record, itemField);
	}

	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		//Note: this was done quickly using existing examples (that will be suppressed and removed)
		// If Northern Waters wants to use ILS eContent, this functionality will need to be updated.
		// Presuming format will be based on MatType, of which they have one for econtent (E-Media)
		if (formatDeterminationMethod.equalsIgnoreCase("matType")) {
			if (sierraRecordFixedFieldsTag != null && !sierraRecordFixedFieldsTag.isEmpty()) {
				if (materialTypeSubField != null && !materialTypeSubField.isEmpty()) {
					String matType        = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
					String formatBoostStr = translateValue("format_boost", matType, econtentRecord.getFullIdentifier());
					econtentItem.setFormat(translateValue("format", matType, econtentRecord.getFullIdentifier()));
					econtentItem.setFormatCategory(translateValue("format_category", matType, econtentRecord.getFullIdentifier()));
					try {
						long formatBoost = Long.parseLong(formatBoostStr);
						econtentRecord.setFormatBoost(formatBoost);
					} catch (Exception e) {
						logger.warn("Unable to parse format boost " + formatBoostStr + " for format " + matType + " " + econtentRecord.getFullIdentifier());
						econtentRecord.setFormatBoost(1);
					}
					return;
				}
			}
		}
		// Fallback to default determination  (Likely a set up error if we get to this point)
		super.loadEContentFormatInformation(record, econtentRecord, econtentItem);
	}
}
