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

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.*;

/**
 * ILS Indexing with customizations specific to Flatirons Library Consortium
 *
 * Pika
 * User: Mark Noble
 * Date: 12/29/2014
 * Time: 10:25 AM
 */
class FlatironsRecordProcessor extends IIIRecordProcessor {
	char locationsSubfield = 'b'; // usually stored in the 998, but for flatirons it is in the record number tag (907)

	FlatironsRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
	}

	@Override
	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record) {
		IsRecordEContent isRecordEContent = new IsRecordEContent(record);
		boolean          isEContent       = isRecordEContent.isEContent();
		if (!isEContent) {
			//The record is print
			List<DataField>  itemRecords      = MarcUtil.getDataFields(record, itemTag);
			for (DataField itemField : itemRecords) {
				if (!isItemSuppressed(itemField)) {
					getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				}
			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		IsRecordEContent isRecordEContent            = new IsRecordEContent(record);
		boolean          isEContent                  = isRecordEContent.isEContent();
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		if (isEContent) {
			List<DataField>  itemRecords                 = MarcUtil.getDataFields(record, itemTag);
			for (DataField itemField : itemRecords) {
				if (!isItemSuppressed(itemField)) {
					//Check to see if the item has an eContent indicator
					RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
					if (eContentRecord != null) {
						unsuppressedEcontentRecords.add(eContentRecord);

						//Set the target audience based on the location code for the record based on the item locations
						this.loadTargetAudiences(groupedWork, record, eContentRecord.getRelatedItems(), identifier);
					}
				}
			}
			if (itemRecords.size() == 0) {
				//Much of the econtent for flatirons has no items.  Need to determine the location based on the 907b field
				String eContentLocation = MarcUtil.getFirstFieldVal(record, recordNumberTag + locationsSubfield);
				if (eContentLocation == null){
					eContentLocation = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + 'h');
					// This is a fallback; Sierra includes the bibLevelLocationsSubfield in Fixed field tag
					// in the subfield h  (Standard for other sites is a)
					if (eContentLocation == null){
						eContentLocation = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + 'a');
						// This is a fallback; The Pika extractor puts the bibLevelLocationsSubfield in Fixed field tag
						// subfield h is proper and correct for Flatirons.
					}
				}
				//TODO: multiple locations?
				if (eContentLocation != null) {
					ItemInfo itemInfo = new ItemInfo();
					itemInfo.setIsEContent(true);
					itemInfo.setLocationCode(eContentLocation);

					//Set the target audience based on the location code for the record based on the bib level location
					String lastCharacter = eContentLocation.substring(eContentLocation.length() - 1);
					groupedWork.addTargetAudience(translateValue("target_audience", lastCharacter, identifier));
					groupedWork.addTargetAudienceFull(translateValue("target_audience", lastCharacter, identifier));

					//Check the 856 tag to see if there is a link there
					loadEContentUrl(record, itemInfo, identifier);
					if (itemInfo.geteContentUrl() == null) {
						itemInfo.seteContentUrl(isRecordEContent.getUrl());
					}

					itemInfo.seteContentSource("eContent");
					String url = itemInfo.geteContentUrl();
					if (url.contains("ebrary.com")) {
						itemInfo.seteContentSource("ebrary");
					} else if (url.contains("gutenberg.org")){
						itemInfo.seteContentSource("Project Gutenberg");
					} else if (url.contains("safaribooksonline.com")){
						itemInfo.seteContentSource("Safari Books");
					} else if (url.contains("uniteforliteracy.com")){
						itemInfo.seteContentSource("Unite For Literacy");
					} else if (url.contains("galegroup.com")){
						itemInfo.seteContentSource("Gale");
					}

					itemInfo.setCallNumber("Online");
					itemInfo.setShelfLocation(translateValue("shelf_location", eContentLocation, identifier));
					RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier.getIdentifier());
					relatedRecord.setSubSource(indexingProfileSource);
					relatedRecord.addItem(itemInfo);

					loadEContentFormatInformation(record, relatedRecord, itemInfo);

					itemInfo.setDetailedStatus("Available Online");

					unsuppressedEcontentRecords.add(relatedRecord);
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	/**
	 * Determine an ILS eContent source for records with item records attached
	 *
	 * @param record  Marc Data
	 * @param itemField Item Record
	 * @return
	 */
	@Override
	protected String getILSeContentSourceType(Record record, DataField itemField) {
		if (itemField.getSubfield(locationSubfieldIndicator) != null) {
			final String itemLocationCode = itemField.getSubfield(locationSubfieldIndicator).getData();
			if (itemLocationCode.equals("laopa")){
				return "Lafayette Online Photographs";
			}
			if (itemLocationCode.startsWith("bc")) {
				return "Carnegie Online";
			}
		}
		return "eContent";
	}

	protected boolean isBibSuppressed(Record record) {
		if (super.isBibSuppressed(record)) {
			return true;
		}
		//TODO: this should already be in effect at the grouping level. Better handled for overdrive. Only the hoopla record gets suppressed here.
		else if (doAutomaticEcontentSuppression) {
			IsRecordEContent theBib     = new IsRecordEContent(record);
			boolean          isEContent = theBib.isEContent();
			if (isEContent) {
				String url = theBib.getUrl();
				//Suppress if the url is an overdrive or hoopla url
				if (url != null)
					return url.contains("lib.overdrive") || url.contains("hoopla");
			}
		}
		return false;
	}

	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		String format    = "online_resource";
		String bibFormat = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
		//Load the eContent Format from the sierra Bcode2  (format)
		// Flatirons' export profile labels this Format (bcode2).
		// However the API uses the same fixed field for MatType for this, so we will also
		bibFormat = (bibFormat == null) ? "" : bibFormat.trim();
		switch (bibFormat) {
			case "3":
				format = "eBook";
				break;
			case "v":
				format = "eVideo";
				break;
			case "u":
				format = "eAudiobook";
				break;
			case "y":
				format = "eMusic";
				break;
			case "t":
				//Check to see if this is a serial resource
				String leader = record.getLeader().toString();
				if (leader.length() >= 7) {
					// check the Leader at position 7
					char leaderBit = leader.charAt(7);
					if (leaderBit == 's' || leaderBit == 'S') {
						format = "eJournal";
					}
				}
				break;
			default:
				if (econtentItem != null) {
					// Lafayette Online Photographs
					if (econtentItem.getLocationCode() != null && econtentItem.getLocationCode().equals("laopa")){
						format = "Photo";
					}
					// For the Carnegie records with item records, check the call number for format hints
					else if (econtentItem.getCallNumber() != null) {
						if (econtentItem.getCallNumber().contains("PHOTO")) {
							format = "Photo";
						} else if (econtentItem.getCallNumber().contains("OH")) {
							format = "Oral History";
						}
					}
				}
		}

		String translatedFormat         = translateValue("format", format, econtentRecord.getRecordIdentifier());
		String translatedFormatCategory = translateValue("format_category", format, econtentRecord.getRecordIdentifier());
		String translatedFormatBoost    = translateValue("format_boost", format, econtentRecord.getRecordIdentifier());
		econtentItem.setFormat(translatedFormat);
		econtentItem.setFormatCategory(translatedFormatCategory);
		try {
			econtentRecord.setFormatBoost(Long.parseLong(translatedFormatBoost));
		} catch (NumberFormatException e) {
			logger.warn("Could not get format boost for format " + format);
			econtentRecord.setFormatBoost(1);
		}
	}

	/**
	 *  Set target audience based on last character of the item location code
	 *
	 * @param groupedWork
	 * @param record
	 * @param printItems
	 * @param identifier
	 */
	protected void loadTargetAudiences(GroupedWorkSolr groupedWork, Record record, HashSet<ItemInfo> printItems, RecordIdentifier identifier) {
		//For Flatirons, load audiences based on the final character of the location codes
		HashSet<String> targetAudiences = new HashSet<>();
		for (ItemInfo printItem : printItems) {
			String locationCode = printItem.getLocationCode();
			if (locationCode.length() > 0) {
				String lastCharacter = locationCode.substring(locationCode.length() - 1);
				targetAudiences.add(lastCharacter);
			}
		}

		groupedWork.addTargetAudiences(translateCollection("target_audience", targetAudiences, identifier.getSourceAndId()));
		groupedWork.addTargetAudiencesFull(translateCollection("target_audience", targetAudiences, identifier.getSourceAndId()));
	}

	private class IsRecordEContent {
		private String           url;
		private boolean          isEContent = false;

		IsRecordEContent(Record record) {
			url         = MarcUtil.getFirstFieldVal(record, "856u");

			String bibFormat = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
			bibFormat = (bibFormat == null) ? "" : bibFormat.trim();
			boolean isEContentBibFormat = bibFormat.equals("3") || bibFormat.equals("t") || bibFormat.equals("m") || bibFormat.equals("w") || bibFormat.equals("u");
			boolean has856              = url != null;

			if (isEContentBibFormat && has856) {
				isEContent = true;
			} else {
				//Check to see if this is Carnegie eContent
				List<DataField> itemRecords = MarcUtil.getDataFields(record, itemTag);
				for (DataField itemField : itemRecords) {
					// if Location code start with BC and has an 856 url or 962 urls
					if (itemField.getSubfield(locationSubfieldIndicator) != null) {
						final String itemLocationCode = itemField.getSubfield(locationSubfieldIndicator).getData();
						if (itemLocationCode.equals("laopa") || itemLocationCode.startsWith("bc")) {
							//Check to see if we have related links
							if (has856) {
								isEContent = true;
								break;
							} else {
								//Check the 962 (carnegie Items)
								List<DataField> additionalLinks = MarcUtil.getDataFields(record, "962");
								for (DataField additionalLink : additionalLinks) {
									if (additionalLink.getSubfield('u') != null) {
										url        = additionalLink.getSubfield('u').getData();
										isEContent = true;
										break;
									}
								}
								if (isEContent) {
									break;
								}
							}
						}
					}
				}
			}
		}

		public String getUrl() {
			return url;
		}

		boolean isEContent() {
			return isEContent;
		}

	}

}
