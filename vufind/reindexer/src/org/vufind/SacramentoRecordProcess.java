package org.vufind;

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.*;


/**
 * Custom Record Processing for Santa Fe
 *
 * Pika
 * User: Pascal Brammeier
 * Date: 5/24/2018
 */

class SacramentoRecordProcessor extends IIIRecordProcessor {
    private HashSet<String> recordsWithVolumes = new HashSet<>();

    SacramentoRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
        super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

        loadOrderInformationFromExport();

        loadVolumesFromExport(vufindConn);

        validCheckedOutStatusCodes.add("d");
        validCheckedOutStatusCodes.add("o");

    }

    private void loadVolumesFromExport(Connection vufindConn){
        try{
            PreparedStatement loadVolumesStmt = vufindConn.prepareStatement("SELECT distinct(recordId) FROM ils_volume_info", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
            ResultSet volumeInfoRS = loadVolumesStmt.executeQuery();
            while (volumeInfoRS.next()){
                String recordId = volumeInfoRS.getString(1);
                recordsWithVolumes.add(recordId);
            }
            volumeInfoRS.close();
        }catch (SQLException e){
            logger.error("Error loading volumes from the export", e);
        }
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
        String availableStatus = "-ocd(j";
        if (status.length() > 0 && availableStatus.indexOf(status.charAt(0)) >= 0) {
            if (dueDate.length() == 0) {
                available = true;
            }
        }
        return available;
    }

    protected boolean determineLibraryUseOnly(ItemInfo itemInfo, Scope curScope) {
        return itemInfo.getStatusCode().equals("o");
    }

}
