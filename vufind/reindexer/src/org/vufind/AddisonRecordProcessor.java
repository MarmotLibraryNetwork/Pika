package org.vufind;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;

public class AddisonRecordProcessor extends IIIRecordProcessor {
    AddisonRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
        super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

//        loadOrderInformationFromExport();

//        loadVolumesFromExport(vufindConn);

//        validCheckedOutStatusCodes.add("o");
    }

    @Override
    protected boolean isItemAvailable(ItemInfo itemInfo) {
        boolean available = false;
        String status = itemInfo.getStatusCode();
        String dueDate = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();
        String availableStatus = "-o"; //TODO: get actual values
        if (availableStatus.indexOf(status.charAt(0)) >= 0) {
            if (dueDate.length() == 0 || dueDate.trim().equals("-  -")) {
                available = true;
            }
        }
        return available;
    }

    @Override
    protected boolean loanRulesAreBasedOnCheckoutLocation() {
        return false;
    }

}
