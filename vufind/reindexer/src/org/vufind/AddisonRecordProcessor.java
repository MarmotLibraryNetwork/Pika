package org.vufind;

import org.apache.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;
import java.util.Set;

public class AddisonRecordProcessor extends IIIRecordProcessor {
    AddisonRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
        super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

        loadOrderInformationFromExport();
//        loadVolumesFromExport(vufindConn);

        validCheckedOutStatusCodes.add("o"); // Library Use Only
        validCheckedOutStatusCodes.add("d"); // Display  //TODO: is this a good one to use?  (Sacramento does)
    }

    @Override
    protected boolean isItemAvailable(ItemInfo itemInfo) {
        boolean available      = false;
        String status          = itemInfo.getStatusCode();
        String dueDate         = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();
        String availableStatus = "-oyp";

        if (!status.isEmpty() && availableStatus.indexOf(status.charAt(0)) >= 0) {
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

    // This is just taken from Sacramento Processor
    @Override
    protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record){
        List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
        //For arlington and sacramento, eContent will always have no items on the bib record.
        List<DataField> items = MarcUtil.getDataFields(record, itemTag);
        if (items.size() > 0){
            return unsuppressedEcontentRecords;
        }else{
            //No items so we can continue on.

            String specifiedEcontentSource = "Econtent Source";
//            String specifiedEcontentSource = MarcUtil.getFirstFieldVal(record, "901a");
//            if (specifiedEcontentSource != null){
                //Get the url
                String url = MarcUtil.getFirstFieldVal(record, "856u");

                if (url != null){

                    //Get the bib location
                    String bibLocation = null;
                    Set<String> bibLocations = MarcUtil.getFieldList(record, sierraRecordFixedFieldsTag + "a");
                    for (String tmpBibLocation : bibLocations){
                        if (tmpBibLocation.matches("[a-zA-Z]{1,5}")){
                            bibLocation = tmpBibLocation;
                            break;
//                }else if (tmpBibLocation.matches("\\(\\d+\\)([a-zA-Z]{1,5})")){
//                    bibLocation = tmpBibLocation.replaceAll("\\(\\d+\\)", "");
//                    break;
                        }
                    }

                    ItemInfo itemInfo = new ItemInfo();
                    itemInfo.setIsEContent(true);
                    itemInfo.setLocationCode(bibLocation);
                    itemInfo.seteContentProtectionType("external");
                    itemInfo.setCallNumber("Online");
                    itemInfo.seteContentSource(specifiedEcontentSource);
//                  itemInfo.setShelfLocation(econtentSource); // this sets the owning location facet.  This isn't needed for Sacramento
                    itemInfo.setIType("eCollection");
                    itemInfo.setDetailedStatus("Available Online");
                    RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier);
                    relatedRecord.setSubSource(profileType);
                    relatedRecord.addItem(itemInfo);
                    itemInfo.seteContentUrl(url);

                    // Use the same format determination process for the econtent record (should just be the MatType)
                    loadPrintFormatInformation(relatedRecord, record);


                    unsuppressedEcontentRecords.add(relatedRecord);
                }
//            }
        }
        return unsuppressedEcontentRecords;
    }

}
