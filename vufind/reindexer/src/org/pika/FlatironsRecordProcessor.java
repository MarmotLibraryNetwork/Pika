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
	char sierraBCode2      = 'e'; // aka bib format (different than matType)
	char locationsSubfield = 'b'; // usually stored in the 998, but for flatirons it is in the record number tag (907)

	FlatironsRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		availableStatus = "-oyj";
		validOnOrderRecordStatus = "o1aqfd";

		loadOrderInformationFromExport();
	}

	@Override
	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, RecordIdentifier identifier, Record record) {
		IsRecordEContent isRecordEContent = new IsRecordEContent(record).invoke();
		boolean          isEContent       = isRecordEContent.isEContent();
		List<DataField>  itemRecords      = isRecordEContent.getItemRecords();
		if (!isEContent) {
			//The record is print
			for (DataField itemField : itemRecords) {
				if (!isItemSuppressed(itemField)) {
					getPrintIlsItem(groupedWork, recordInfo, record, itemField, identifier);
				}
			}
		}
	}

	@Override
	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, RecordIdentifier identifier, Record record) {
		IsRecordEContent isRecordEContent            = new IsRecordEContent(record).invoke();
		boolean          isEContent                  = isRecordEContent.isEContent();
		List<DataField>  itemRecords                 = isRecordEContent.getItemRecords();
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		String           url                         = isRecordEContent.getUrl();
		if (isEContent) {
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
				if (eContentLocation != null) {
					ItemInfo itemInfo = new ItemInfo();
					itemInfo.setIsEContent(true);
					itemInfo.setLocationCode(eContentLocation);

					//Set the target audience based on the location code for the record based on the bib level location
					String lastCharacter = eContentLocation.substring(eContentLocation.length() - 1);
					groupedWork.addTargetAudience(translateValue("target_audience", lastCharacter, identifier));
					groupedWork.addTargetAudienceFull(translateValue("target_audience", lastCharacter, identifier));

					itemInfo.seteContentSource("External eContent");
//					itemInfo.seteContentProtectionType("external");
					if (url.contains("ebrary.com")) {
						itemInfo.seteContentSource("ebrary");
					} else {
						itemInfo.seteContentSource("Unknown");
					}
					itemInfo.setCallNumber("Online");
					itemInfo.setShelfLocation(itemInfo.geteContentSource());
					RecordInfo relatedRecord = groupedWork.addRelatedRecord("external_econtent", identifier.getIdentifier());
					relatedRecord.setSubSource(indexingProfileSource);
					relatedRecord.addItem(itemInfo);
					//Check the 856 tag to see if there is a link there
					loadEContentUrl(record, itemInfo, identifier);
					if (itemInfo.geteContentUrl() == null) {
						itemInfo.seteContentUrl(url);
					}

					loadEContentFormatInformation(record, relatedRecord, itemInfo);

					itemInfo.setDetailedStatus("Available Online");

					unsuppressedEcontentRecords.add(relatedRecord);
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	@Override
	protected String getILSeContentSourceType(Record record, DataField itemField) {
		if (itemField.getSubfield(locationSubfieldIndicator) != null && itemField.getSubfield(locationSubfieldIndicator).getData().startsWith("bc")) {
			return "Carnegie Online";
		}
		return "Unknown Source";
	}

	protected boolean isBibSuppressed(Record record) {
		if (super.isBibSuppressed(record)) {
			return true;
		} else if (doAutomaticEcontentSuppression) {
			IsRecordEContent theBib     = new IsRecordEContent(record).invoke();
			boolean          isEContent = theBib.isEContent();
			boolean          has856     = theBib.getUrl() != null;

			if (isEContent && has856) {
				String url = theBib.getUrl();
				//Suppress if the url is an overdrive or hoopla url
				if (url.contains("lib.overdrive") || url.contains("hoopla")) {
					return true;
				}
			}
		}
		return false;
	}

	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		//Load the eContent Format from the sierra Bcode2  (format)
		String bibFormat = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + sierraBCode2);
		if (bibFormat != null) {
			bibFormat = bibFormat.trim();
		} else {
			bibFormat = "";
		}
		String format;
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
				boolean isSerial = false;
				if (leader.length() >= 7) {
					// check the Leader at position 7
					char leaderBit = leader.charAt(7);
					if (leaderBit == 's' || leaderBit == 'S') {
						isSerial = true;
					}
				}
				if (isSerial) {
					format = "eJournal";
				} else {
					format = "online_resource";
				}
				break;
			default:
				//Check based off of other information
				if (econtentItem == null || econtentItem.getCallNumber() == null) {
					format = "online_resource";
				} else {
					if (econtentItem.getCallNumber().contains("PHOTO")) {
						format = "Photo";
					} else if (econtentItem.getCallNumber().contains("OH")) {
						format = "Oral History";
					} else {
						format = "online_resource";
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
		private Record           record;
		private String           url;
		private List<DataField>  itemRecords;
		private boolean          isEContent;

		IsRecordEContent(Record record) {
			this.record = record;
		}

		public String getUrl() {
			return url;
		}

		List<DataField> getItemRecords() {
			return itemRecords;
		}


		boolean isEContent() {
			return isEContent;
		}

		IsRecordEContent invoke() {
			String bibFormat = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + sierraBCode2);
			if (bibFormat != null) {
				bibFormat = bibFormat.trim();
			} else {
				bibFormat = "";
			}
			boolean isEContentBibFormat = bibFormat.equals("3") || bibFormat.equals("t") || bibFormat.equals("m") || bibFormat.equals("w") || bibFormat.equals("u");
			url = MarcUtil.getFirstFieldVal(record, "856u");
			boolean has856 = url != null;

			itemRecords                 = MarcUtil.getDataFields(record, itemTag);

			isEContent = false;

			if (isEContentBibFormat && has856) {
				isEContent = true;
			} else {
				//Check to see if this is Carnegie eContent
				for (DataField itemField : itemRecords) {
					// if Location code start with BC and has an 856 url or 962 urls
					if (itemField.getSubfield(locationSubfieldIndicator) != null && itemField.getSubfield(locationSubfieldIndicator).getData().startsWith("bc")) {
						//TODO: this will make all the items on this BIB econtent, which is not *always* the case
						//Check to see if we have related links
						if (has856) {
							isEContent = true;
							break;
						} else {
							//Check the 962
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
			return this;
		}
	}

}
