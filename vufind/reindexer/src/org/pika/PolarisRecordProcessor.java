/*
 * Copyright (C) 2024  Marmot Library Network
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

import org.apache.logging.log4j.Logger;
import org.marc4j.marc.DataField;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.HashSet;

abstract public class PolarisRecordProcessor  extends IlsRecordProcessor {
	PolarisRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		try {
			String availableStatusString = indexingProfileRS.getString("availableStatuses");
			//String checkedOutStatuses     = indexingProfileRS.getString("checkedOutStatuses");
			String libraryUseOnlyStatuses = indexingProfileRS.getString("libraryUseOnlyStatuses");
			if (availableStatusString != null && !availableStatusString.isEmpty()) {
				availableStatusCodes.addAll(Arrays.asList(availableStatusString.split("\\|")));
			}
			//if (checkedOutStatuses != null && !checkedOutStatuses.isEmpty()){
			//	validCheckedOutStatusCodes.addAll(Arrays.asList(checkedOutStatuses.split("\\|")));
			//}
			if (libraryUseOnlyStatuses != null && !libraryUseOnlyStatuses.isEmpty()){
				libraryUseOnlyStatusCodes.addAll(Arrays.asList(libraryUseOnlyStatuses.split("\\|")));
			}

		} catch (SQLException e) {
			logger.error("Error loading indexing profile information from database for PolarisRecordProcessor", e);
		}
	}

	protected HashSet<String> availableStatusCodes       = new HashSet<String>();
	protected HashSet<String> libraryUseOnlyStatusCodes  = new HashSet<String>();
	//protected HashSet<String> validCheckedOutStatusCodes = new HashSet<String>();

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		String  status    = itemInfo.getStatusCode();
		return (status != null && !status.isEmpty() && availableStatusCodes.contains(status.toLowerCase()));
	}

	protected boolean isLibraryUseOnly(ItemInfo itemInfo) {
		String status = itemInfo.getStatusCode();
		return status != null && !status.isEmpty() && libraryUseOnlyStatusCodes.contains(status);
	}

	protected String getItemStatus(DataField itemField, RecordIdentifier recordIdentifier) {
		String itemStatus = super.getItemStatus(itemField, recordIdentifier);
		if (itemStatus != null && logger.isDebugEnabled()){
			logger.debug("Polaris indexer, record " + recordIdentifier + ", got status code : " + itemStatus);
		}
		return itemStatus;
	}

	}
