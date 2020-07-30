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
import org.marc4j.marc.Subfield;

import java.sql.Connection;
import java.sql.ResultSet;

/**
 * Custom Record Processing for Santa Fe
 *
 * Pika
 * User: Mark Noble

 */
class SantaFeRecordProcessor extends IIIRecordProcessor {

	SantaFeRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);
		availableStatus = "-o";
		validOnOrderRecordStatus = "o1a";

		loadOrderInformationFromExport();
	}

	protected boolean isBibSuppressed(Record record) {
		DataField field907 = record.getDataField("907");
		if (field907 != null){
			Subfield suppressionSubfield = field907.getSubfield('c');
			if (suppressionSubfield != null){
				String bCode3 = suppressionSubfield.getData().toLowerCase().trim();
				if (bCode3.matches("^[dns]$")){
					logger.debug("Bib record is suppressed due to bcode3 " + bCode3);
					return true;
				}
			}
		}
		return false;
	}

	protected boolean isItemSuppressed(DataField curItem) {
		Subfield icode2Subfield = curItem.getSubfield(iCode2Subfield);
		if (icode2Subfield != null) {
			String icode2 = icode2Subfield.getData().toLowerCase().trim();
			String status = curItem.getSubfield(statusSubfieldIndicator).getData().trim();

			//Suppress based on combination of status and icode2
			if ((icode2.equals("2") || icode2.equals("3")) && status.equals("f")){
				logger.debug("Item record is suppressed due to icode2 / status");
				return true;
			}else if (icode2.equals("d") && (status.equals("$") || status.equals("s") || status.equals("m") || status.equals("r") || status.equals("z"))){
				logger.debug("Item record is suppressed due to icode2 / status");
				return true;
			}else if (icode2.equals("x") && status.equals("n")){
				logger.debug("Item record is suppressed due to icode2 / status");
				return true;
			}else if (icode2.equals("c")){
				logger.debug("Item record is suppressed due to icode2 / status");
				return true;
			}

		}
		return super.isItemSuppressed(curItem);
	}

}
