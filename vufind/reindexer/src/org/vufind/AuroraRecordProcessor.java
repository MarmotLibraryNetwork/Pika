package org.vufind;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;

class AuroraRecordProcessor extends IIIRecordProcessor  {
	AuroraRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

	}
	private String availableStatus          = "-oy";

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

}
