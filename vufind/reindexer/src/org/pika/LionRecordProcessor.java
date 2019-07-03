package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.HashSet;

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
class LionRecordProcessor extends IIIRecordProcessor {
	LionRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		availableStatus          = "-&covy";
		validOnOrderRecordStatus = "o1aq";

		validCheckedOutStatusCodes.add("&");
		validCheckedOutStatusCodes.add("c");
		validCheckedOutStatusCodes.add("o");
		validCheckedOutStatusCodes.add("y");
		validCheckedOutStatusCodes.add("u");

		loadOrderInformationFromExport();

	}


// Attempting to use default format determination for LION D-2722
//	@Override
//	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
//		HashMap<String, Integer> itemCountsByItype = new HashMap<>();
//		HashMap<String, String> itemTypeToFormat = new HashMap<>();
//		int mostUsedCount = 0;
//		String mostPopularIType = "";		//Get a list of all the formats based on the items
//		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
//		for(DataField item : items){
//			Subfield iTypeSubField = item.getSubfield(iTypeSubfield);
//			if (iTypeSubField != null){
//				String iType = iTypeSubField.getData().toLowerCase();
//				if (itemCountsByItype.containsKey(iType)){
//					itemCountsByItype.put(iType, itemCountsByItype.get(iType) + 1);
//				}else{
//					itemCountsByItype.put(iType, 1);
//					//Translate the iType to see what formats we get.  Some item types do not have a format by default and use the default translation
//					//We still will want to record those counts.
//					String translatedFormat = translateValue("format", iType, recordInfo.getRecordIdentifier());
//					itemTypeToFormat.put(iType, translatedFormat);
//				}
//
//				if (itemCountsByItype.get(iType) > mostUsedCount){
//					mostPopularIType = iType;
//					mostUsedCount = itemCountsByItype.get(iType);
//				}
//			}
//		}
//
//		if (itemTypeToFormat.size() == 0 || itemTypeToFormat.get(mostPopularIType) == null || itemTypeToFormat.get(mostPopularIType).length() == 0){
//			//We didn't get any formats from the collections, get formats from the base method (007, 008, etc).
//			//logger.debug("All formats are books or there were no formats found, loading format information from the bib");
//			super.loadPrintFormatFromBib(recordInfo, record);
//		} else{
//			//logger.debug("Using default method of loading formats from iType");
//			recordInfo.addFormat(itemTypeToFormat.get(mostPopularIType));
//			String translatedFormatCategory = translateValue("format_category", mostPopularIType, recordInfo.getRecordIdentifier());
//			if (translatedFormatCategory != null) {
//				recordInfo.addFormatCategory(translatedFormatCategory);
//			}
//			Long formatBoost = 1L;
//			String formatBoostStr = translateValue("format_boost", mostPopularIType, recordInfo.getRecordIdentifier());
//			if (formatBoostStr == null){
//				formatBoostStr = translateValue("format_boost", itemTypeToFormat.get(mostPopularIType), recordInfo.getRecordIdentifier());
//			}
//			if (formatBoostStr != null && Util.isNumeric(formatBoostStr)) {
//				formatBoost = Long.parseLong(formatBoostStr);
//			}
//			recordInfo.setFormatBoost(formatBoost);
//		}
//	}

	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
		//For LION, Anythink, load audiences based on collection code rather than based on the 008 and 006 fields
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems) {
			String collection = printItem.getShelfLocationCode();
			if (collection != null) {
				targetAudiences.add(collection.toLowerCase());
			}
		}

		HashSet<String> translatedAudiences = translateCollection("target_audience", targetAudiences, identifier);
		groupedWork.addTargetAudiences(translatedAudiences);
		groupedWork.addTargetAudiencesFull(translatedAudiences);
	}

}
