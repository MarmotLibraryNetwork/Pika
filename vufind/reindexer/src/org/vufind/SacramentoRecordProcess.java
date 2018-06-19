package org.vufind;

import org.apache.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;


/**
 * Custom Record Processing for Sacramento
 *
 * Pika
 * User: Pascal Brammeier
 * Date: 5/24/2018
 */

class SacramentoRecordProcessor extends IIIRecordProcessor {
    private HashSet<String> recordsWithVolumes = new HashSet<>();
    private String materialTypeSubField = "d";

    SacramentoRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
        super(indexer, vufindConn, indexingProfileRS, logger, fullReindex);

        loadOrderInformationFromExport();

        validCheckedOutStatusCodes.add("d");
        validCheckedOutStatusCodes.add("o");
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

    protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, String identifier) {
        //For Sacramento, LION, Anythink, load audiences based on collection code rather than based on the 008 and 006 fields
        HashSet<String> targetAudiences = new HashSet<>();
        for (ItemInfo printItem : printItems){
            String shelfLocationCode = printItem.getShelfLocationCode();
            if (shelfLocationCode != null) {
                targetAudiences.add(shelfLocationCode.toLowerCase());
            }
        }

        HashSet<String> translatedAudiences = translateCollection("target_audience", targetAudiences, identifier);
        groupedWork.addTargetAudiences(translatedAudiences);
        groupedWork.addTargetAudiencesFull(translatedAudiences);
    }


    public void loadPrintFormatInformation(RecordInfo recordInfo, Record record){
        String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
        if (matType != null) {
            if (!matType.equals("-") && !matType.equals(" ")) {
                String translatedFormat = translateValue("format", matType, recordInfo.getRecordIdentifier());
                if (!translatedFormat.equals(matType)) {
                    String translatedFormatCategory = translateValue("format_category", matType, recordInfo.getRecordIdentifier());
                    recordInfo.addFormat(translatedFormat);
                    if (translatedFormatCategory != null) {
                        recordInfo.addFormatCategory(translatedFormatCategory);
                    }
                    // use translated value
                    String formatBoost = translateValue("format_boost", matType, recordInfo.getRecordIdentifier());
                    try {
                        Long tmpFormatBoostLong = Long.parseLong(formatBoost);
                        recordInfo.setFormatBoost(tmpFormatBoostLong);
                        return;
                    } catch (NumberFormatException e) {
                        logger.warn("Could not load format boost for format " + formatBoost + " profile " + profileType);
                    }
                } else {
                    logger.warn("Material Type "+ matType + " had no translation, falling back to default format determination.");
                }
            } else {
                logger.info("Material Type for " + recordInfo.getRecordIdentifier() +" has empty value '"+ matType + "', falling back to default format determination.");
            }
        } else {
            logger.info(recordInfo.getRecordIdentifier() + " did not have a material type, falling back to default format determination.");
        }
        super.loadPrintFormatInformation(recordInfo, record);
    }


    @Override
    protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record){
        List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
        //For arlington and sacramento, eContent will always have no items on the bib record.
        List<DataField> items = MarcUtil.getDataFields(record, itemTag);
        if (items.size() > 0){
            return unsuppressedEcontentRecords;
        }else{
            //No items so we can continue on.

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
            //Get the url
            String url = MarcUtil.getFirstFieldVal(record, "856u");

            if (url != null && !url.toLowerCase().contains("lib.overdrive.com")){
                //Get the econtent source
                String urlLower = url.toLowerCase();
                String econtentSource;
                String specifiedSource = MarcUtil.getFirstFieldVal(record, "856x");
                if (specifiedSource != null){
                    econtentSource = specifiedSource;
                }else {
                    String urlText = MarcUtil.getFirstFieldVal(record, "856z");
                    if (urlText != null) {
                        // Searching URL to determine eContent Source
                        urlText = urlText.toLowerCase();
//                        if (urlText.contains("gpo.gov")) {
//                            econtentSource = "Federal Government Documents";
//                        }else
                            if (urlText.contains("gale virtual reference library")) {
                            econtentSource = "Gale Virtual Reference Library";
                        }else if (urlText.contains("gale virtual reference library")) {
                            econtentSource = "Gale Virtual Reference Library";
                        } else if (urlText.contains("gale directory library")) {
                            econtentSource = "Gale Directory Library";
                        } else if (urlText.contains("hoopla")) {
                            econtentSource = "Hoopla";
                        } else if (urlText.contains("national geographic virtual library")) {
                            econtentSource = "National Geographic Virtual Library";
                        } else if ((urlText.contains("ebscohost") || urlLower.contains("netlibrary") || urlLower.contains("ebsco"))) {
                            econtentSource = "EbscoHost";
                        } else {
                            econtentSource = "Premium Sites";
                        }
                    } else {
                        econtentSource = "Premium Sites";
                    }
                }

                ItemInfo itemInfo = new ItemInfo();
                itemInfo.setIsEContent(true);
                itemInfo.setLocationCode(bibLocation);
                itemInfo.seteContentProtectionType("external");
                itemInfo.setCallNumber("Online");
                itemInfo.seteContentSource(econtentSource);
                itemInfo.setShelfLocation(econtentSource);
                itemInfo.setIType("eCollection");
                RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier);
                relatedRecord.setSubSource(profileType);
                relatedRecord.addItem(itemInfo);
                itemInfo.seteContentUrl(url);

                //Set the format based on the material type
                String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
                itemInfo.setFormat(translateValue("format", matType, identifier));
                itemInfo.setFormatCategory(translateValue("format_category", matType, identifier));
                String boostStr = translateValue("format_boost", matType, identifier);
                try{
                    int boost = Integer.parseInt(boostStr);
                    relatedRecord.setFormatBoost(boost);
                } catch (Exception e){
                    logger.warn("Unable to load boost for " + identifier + " got boost " + boostStr + " for matType " + matType);
                }

                itemInfo.setDetailedStatus("Available Online");

                unsuppressedEcontentRecords.add(relatedRecord);
            }
        }
        return unsuppressedEcontentRecords;
    }

}
