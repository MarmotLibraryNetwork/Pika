package org.pika;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;

class AuroraRecordProcessor extends IIIRecordProcessor  {
	AuroraRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		availableStatus          = "-doj";
		validCheckedOutStatusCodes.add("o"); // Library Use Only
		validCheckedOutStatusCodes.add("d"); // Display

		loadOrderInformationFromExport();

	}

}
