/*
 * Copyright (C) 2023  Marmot Library Network
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

import au.com.bytecode.opencsv.CSVReader;
import org.apache.logging.log4j.Logger;
import org.marc4j.marc.Record;

import java.io.File;
import java.io.FileReader;
import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.HashMap;

/**
 * Pika
 *
 * Generic Sierra Record Processor to use when a site requires no
 * special code overrides
 *
 * @author pbrammeier
 * 				Date:   9/17/2020
 */
public class SierraRecordProcessor extends IIIRecordProcessor {
	private final HashMap<String, ArrayList<OrderInfo>> orderInfoFromExport = new HashMap<>();

	private String                                exportPath;

	SierraRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		try {
			exportPath = indexingProfileRS.getString("marcPath");
		} catch (SQLException e) {
			logger.error("SQL error :", e);
		}

		// Fetch On Order csv file generated by Sierra Extractor process
		loadOrderInformationFromExport();

//		loadDueDateInformation();


		//For Sierra libraries that use volume records (.j records, this is different from item volume),
		// fetch Volume records extracted in the Sierra Extractor
//		loadVolumesFromExport(pikaConn);

	}

	/**
	 * @param groupedWork
	 * @param record
	 * @param identifier
	 * @param loadedNovelistSeries
	 */
	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, boolean loadedNovelistSeries) {
		super.updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier, loadedNovelistSeries);
		String identifierStr              = identifier.getIdentifier();
		String shortBibId                 = identifierStr.replace(".b", "b");
		String bibIdWithoutCheckDigit     = identifierStr.substring(0, identifierStr.length() - 1);
		String shorBibIdWithoutCheckDigit = shortBibId.substring(0, shortBibId.length() - 1);
		groupedWork.addAlternateId(shortBibId);
		groupedWork.addAlternateId(bibIdWithoutCheckDigit);
		groupedWork.addAlternateId(shorBibIdWithoutCheckDigit);
	}

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record){
		if (!orderInfoFromExport.isEmpty()){
			ArrayList<OrderInfo> orderItems = orderInfoFromExport.get(recordInfo.getRecordIdentifier().getIdentifier());
			if (orderItems != null) {
				for (OrderInfo orderItem : orderItems) {
					createAndAddOrderItem(groupedWork, recordInfo, orderItem, record);
				}
				if (!recordInfo.hasPrintCopies() && recordInfo.hasOnOrderCopies()) {
					groupedWork.addKeywords("On Order");
					groupedWork.addKeywords("Coming Soon");
				}
			}
		}
//		else{
//			super.loadOnOrderItems(groupedWork, recordInfo, record);
//		}
	}

	void loadOrderInformationFromExport() {
		File activeOrders = new File(this.exportPath + "/active_orders.csv");
		if (activeOrders.exists()){
			try{
				try (CSVReader reader = new CSVReader(new FileReader(activeOrders))) {
					//First line is headers
					reader.readNext();
					String[] orderData;
					while ((orderData = reader.readNext()) != null) {
						OrderInfo orderRecord   = new OrderInfo();
						String    recordId      = ".b" + orderData[0] + getCheckDigit(orderData[0]);
						String    orderRecordId = ".o" + orderData[1] + getCheckDigit(orderData[1]);
						orderRecord.setOrderRecordId(orderRecordId);
						orderRecord.setStatus(orderData[3]);
						orderRecord.setNumCopies(Integer.parseInt(orderData[4]));
						//Get the order record based on the accounting unit
						orderRecord.setLocationCode(orderData[5]);
						if (orderInfoFromExport.containsKey(recordId)) {
							orderInfoFromExport.get(recordId).add(orderRecord);
						} else {
							ArrayList<OrderInfo> orderRecordColl = new ArrayList<>();
							orderRecordColl.add(orderRecord);
							orderInfoFromExport.put(recordId, orderRecordColl);
						}
					}
					if (logger.isDebugEnabled()) {
						logger.debug("Loaded " + orderInfoFromExport.size() + " records with order info loaded from export");
					}
				}
			}catch(Exception e){
				logger.error("Error loading order records from active orders", e);
			}
		}
	}

	/**
	 * Calculates a check digit for a III identifier
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	private static String getCheckDigit(String basedId) {
		int sumOfDigits = 0;
		for (int i = 0; i < basedId.length(); i++){
			int multiplier = ((basedId.length() +1 ) - i);
			sumOfDigits += multiplier * Integer.parseInt(basedId.substring(i, i+1));
		}
		int modValue = sumOfDigits % 11;
		if (modValue == 10){
			return "x";
		}else{
			return Integer.toString(modValue);
		}
	}

//Retaining load Sierra volume data (.j records) in case it is ever needed for indexing
//	private void loadVolumesFromExport(Connection pikaConn){
//		try{
//			PreparedStatement loadVolumesStmt = pikaConn.prepareStatement("SELECT distinct(recordId) FROM ils_volume_info", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
//			ResultSet volumeInfoRS = loadVolumesStmt.executeQuery();
//			while (volumeInfoRS.next()){
//				String recordId = volumeInfoRS.getString(1);
//				recordsWithVolumes.add(recordId);
//			}
//			volumeInfoRS.close();
//		}catch (SQLException e){
//			logger.error("Error loading volumes from the export", e);
//		}
//	}

//	void loadDueDateInformation() {
//		File dueDatesFile = new File(this.exportPath + "/due_dates.csv");
//		if (dueDatesFile.exists()){
//			try{
//				CSVReader reader = new CSVReader(new FileReader(dueDatesFile));
//				String[] dueDateData;
//				while ((dueDateData = reader.readNext()) != null){
//					DueDateInfo dueDateInfo = new DueDateInfo(dueDateData[0], dueDateData[1]);
//					dueDateInfoFromExport.put(dueDateInfo.getItemId(), dueDateInfo);
//				}
//			}catch(Exception e){
//				logger.error("Error loading order records from active orders", e);
//			}
//		}
//	}

}
