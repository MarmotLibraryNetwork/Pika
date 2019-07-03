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
	private PreparedStatement getDateAddedStmt; // to set date added for ils (itemless) econtent records

	AddisonRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		availableStatus          = "-oy";
		validOnOrderRecordStatus = "o1a";

		// Note: Mat-Type "v" is video games; they are to be excluded for the better default format determination

		loadOrderInformationFromExport();
//        loadVolumesFromExport(pikaConn);

		validCheckedOutStatusCodes.add("o"); // Library Use Only
		validCheckedOutStatusCodes.add("d"); // Display

		try {
			getDateAddedStmt = pikaConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE ilsId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		} catch (Exception e) {
			logger.error("Unable to setup prepared statement for date added to catalog");
		}
	}


	// This is based on version from Sacramento Processor
	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record) {
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
				itemInfo.seteContentProtectionType("external");
				itemInfo.setCallNumber("Online");
				itemInfo.setIType("eCollection");
				itemInfo.setDetailedStatus("Available Online");

				itemInfo.seteContentUrl(url);
				itemInfo.setLocationCode(bibLocation);
				itemInfo.seteContentSource(specifiedEcontentSource);
				loadDateAddedForItemlessEcontent(identifier, itemInfo);
//                itemInfo.seteContentSource(specifiedEcontentSource == null ? "Econtent" : specifiedEcontentSource);
//                itemInfo.setShelfLocation(econtentSource); // this sets the owning location facet.  This isn't needed for Sacramento
				RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier);
				relatedRecord.setSubSource(profileType);
				relatedRecord.addItem(itemInfo);

				// Use the same format determination process for the econtent record (should just be the MatType)
				loadPrintFormatInformation(relatedRecord, record);


				unsuppressedEcontentRecords.add(relatedRecord);
			} else {
//                //TODO: temporary. just for debugging econtent records
//                if (urls.size() > 0) {
//                    logger.warn("Itemless record " + identifier + " had 856u URLs but none had a expected econtent source.");
//                }
			}

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

	// to set date added for ils (itemless) econtent records
	private void loadDateAddedForItemlessEcontent(String identfier, ItemInfo itemInfo) {
		try {
			getDateAddedStmt.setString(1, identfier);
			ResultSet getDateAddedRS = getDateAddedStmt.executeQuery();
			if (getDateAddedRS.next()) {
				long timeAdded = getDateAddedRS.getLong(1);
				Date curDate   = new Date(timeAdded * 1000);
				itemInfo.setDateAdded(curDate);
				getDateAddedRS.close();
			} else {
				logger.debug("Could not determine date added for " + identfier);
			}
		} catch (Exception e) {
			logger.error("Unable to load date added for " + identfier);
		}
	}
}
