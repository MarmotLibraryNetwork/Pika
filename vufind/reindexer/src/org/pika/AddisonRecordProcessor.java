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
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Set;

public class AddisonRecordProcessor extends IIIRecordProcessor {

	AddisonRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	// Note: Mat-Type "v" is video games; they are to be excluded for the better default format determination

	// This is based on version from Sacramento Processor
	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		//For arlington and sacramento, eContent will always have no items on the bib record.
		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
		if (items.size() > 0) {
			return unsuppressedEcontentRecords;
		} else {
			//No items so we can continue on.

			String      url                     = null;
			String      specifiedEcontentSource = null;
			Set<String> urls                    = MarcUtil.getFieldList(record, "856u");
			for (String tempUrl : urls) {
				specifiedEcontentSource = determineEcontentSourceByURL(tempUrl);
				if (specifiedEcontentSource != null) {
					url = tempUrl;
					break;
				}
			}
			if (url != null) {

				//Get the bib location
				String      bibLocation  = null;
				Set<String> bibLocations = MarcUtil.getFieldList(record, sierraRecordFixedFieldsTag + "a");
				for (String tmpBibLocation : bibLocations) {
					if (tmpBibLocation.matches("[a-zA-Z]{1,5}")) {
						bibLocation = tmpBibLocation;
						break;
//                }else if (tmpBibLocation.matches("\\(\\d+\\)([a-zA-Z]{1,5})")){
//                    bibLocation = tmpBibLocation.replaceAll("\\(\\d+\\)", "");
//                    break;
					}
				}

//                String specifiedEcontentSource = determineEcontentSourceByURL(url);

				ItemInfo itemInfo = new ItemInfo();
				itemInfo.setIsEContent(true);
				itemInfo.setCallNumber("Online");
				itemInfo.setIType("eCollection");
				itemInfo.setDetailedStatus("Available Online");

				itemInfo.seteContentUrl(url);
				itemInfo.setLocationCode(bibLocation);
				itemInfo.seteContentSource(specifiedEcontentSource);

				Date dateAdded = indexer.getDateFirstDetected(identifier.getSource(), identifier.getIdentifier());
				itemInfo.setDateAdded(dateAdded);

//                itemInfo.seteContentSource(specifiedEcontentSource == null ? "Econtent" : specifiedEcontentSource);
//                itemInfo.setShelfLocation(econtentSource); // this sets the owning location facet.  This isn't needed for Sacramento
				RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier.getIdentifier());
				relatedRecord.setSubSource(indexingProfileSource);
				relatedRecord.addItem(itemInfo);

				// Use the same format determination process for the econtent record (should just be the MatType)
				loadPrintFormatInformation(relatedRecord, record);


				unsuppressedEcontentRecords.add(relatedRecord);
			}
//			else {
//                //TODO: temporary. just for debugging econtent records
//                if (urls.size() > 0) {
//                    logger.warn("Itemless record " + identifier + " had 856u URLs but none had a expected econtent source.");
//                }
//			}

		}
		return unsuppressedEcontentRecords;
	}

	private String determineEcontentSourceByURL(String url) {
		String econtentSource = null;
		if (url != null && !url.isEmpty()) {
			url = url.toLowerCase();
			if (url.contains("axis360")) {
				econtentSource = "Axis 360";
			} else if (url.contains("bkflix")) {
				econtentSource = "BookFlix";
			} else if (url.contains("tfx.")) {
				econtentSource = "TrueFlix";
			} else if (url.contains("biblioboard")) {
				econtentSource = "Biblioboard";
			} else if (url.contains("learningexpress")) {
				econtentSource = "Learning Express";
			} else if (url.contains("rbdigital")) {
				econtentSource = "RBdigital";
			} else if (url.contains("enkilibrary")) {
				econtentSource = "ENKI Library";
			} else if (url.contains("cloudlibrary") || url.contains("3m")) {
				econtentSource = "Cloud Library";
			} else if (url.contains("ebsco")) {
				econtentSource = "EBSCO";
			} else if (url.contains("gale")) {
				econtentSource = "Gale";
			}
		}
		return econtentSource;
	}

}
