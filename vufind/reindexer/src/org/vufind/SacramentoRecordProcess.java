package org.vufind;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;

/**
 * Custom Record Processing for Santa Fe
 *
 * Pika
 * User: Pascal Brammeier
 * Date: 5/24/2018
 */

class SacramentoRecordProcessor extends IIIRecordProcessor {

    SacramentoRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
        super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);
    }

    @Override
    protected boolean loanRulesAreBasedOnCheckoutLocation() {
        return false;
    }

    @Override
    protected boolean isItemAvailable(ItemInfo itemInfo) {
        boolean available = false;
        String status = itemInfo.getStatusCode();
        String dueDate = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();
        String availableStatus = "-o";
        if (status.length() > 0 && availableStatus.indexOf(status.charAt(0)) >= 0) {
            if (dueDate.length() == 0) {
                available = true;
            }
        }
        return available;
    }
}
