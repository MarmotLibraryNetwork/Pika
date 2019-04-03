package org.vufind;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;

class AuroraRecordProcessor extends IIIRecordProcessor  {
	AuroraRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		validCheckedOutStatusCodes.add("o"); // Library Use Only
		validCheckedOutStatusCodes.add("d"); // Display

		loadOrderInformationFromExport();

	}
	private String materialTypeSubField     = "d";
	private String availableStatus          = "-do";
	private String libraryUseOnlyStatus     = "o";

	@Override
	//TODO: this could become the base III method when statuses settings are added to the index
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String  status    = itemInfo.getStatusCode();
		String  dueDate   = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();

		if (!status.isEmpty() && availableStatus.indexOf(status.charAt(0)) >= 0) {
			if (dueDate.length() == 0 || dueDate.trim().equals("-  -")) {
				available = true;
			}
		}
		return available;
	}

	//TODO: this could become the base method when statuses settings are added to the index
	protected boolean determineLibraryUseOnly(ItemInfo itemInfo, Scope curScope) {
		String status = itemInfo.getStatusCode();
		return !status.isEmpty() && libraryUseOnlyStatus.indexOf(status.charAt(0)) >= 0;
	}

}
