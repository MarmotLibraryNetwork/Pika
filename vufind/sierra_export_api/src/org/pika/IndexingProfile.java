package org.pika;

import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.regex.Pattern;

import org.apache.log4j.Logger;

/**
 * A copy of indexing profile information from the database
 *
 * Pika
 * User: Mark Noble
 * Date: 6/30/2015
 * Time: 10:38 PM
 */
public class IndexingProfile {
	Long             id;
	String           name;
	String           sourceName;
	String           marcEncoding;
	String           individualMarcPath;
	String           marcPath;
	int              numCharsToCreateFolderFrom;
	boolean          createFolderFromLeadingCharacters;
	String           recordNumberTag;
	char             recordNumberField;
	String           recordNumberPrefix;
	String           itemTag;
	char             itemRecordNumberSubfield;
	char             barcodeSubfield;
	char             locationSubfield;
	char             itemStatusSubfield;
	char             dueDateSubfield;
	String           dueDateFormat;
	SimpleDateFormat dueDateFormatter;
	char             totalCheckoutsSubfield;
	char             lastYearCheckoutsSubfield;
	char             yearToDateCheckoutsSubfield;
	char             totalRenewalsSubfield;
	char             dateCreatedSubfield;
	String           dateCreatedFormat;
	SimpleDateFormat dateCreatedFormatter;
	char             lastCheckinDateSubfield;
	String           lastCheckinFormat;
	SimpleDateFormat lastCheckinFormatter;
	char             shelvingLocationSubfield;
	char             iCode2Subfield;
	char             callNumberPrestampSubfield;
	char             callNumberSubfield;
	char             callNumberCutterSubfield;
	char             callNumberPoststampSubfield;
	char             volume;
	char             itemUrl;
	char             iTypeSubfield;
	String           sierraRecordFixedFieldsTag;
	char             bcode3Subfield;
	char             materialTypeSubField;
	String           materialTypesToIgnore;
	char             sierraLanguageFixedField;
	boolean          doAutomaticEcontentSuppression;
	String           formatSource;
	char             format;
	char             eContentDescriptor;
	String           specifiedFormatCategory;


	// Fields needed for Record Grouping
	String           formatDeterminationMethod;
	String           filenamesToInclude;
	String           groupingClass;
	boolean          useICode2Suppression;
	boolean          groupUnchangedFiles;
	boolean          usingSierraAPIExtract        = true;
//	String           specifiedFormat;
	String           specifiedGroupingCategory;
//	int              specifiedFormatBoost;
	char             collectionSubfield;
	Pattern          statusesToSuppressPattern    = null;
	Pattern          locationsToSuppressPattern   = null;
	Pattern          collectionsToSuppressPattern = null;
	Pattern          iTypesToSuppressPattern      = null;
	Pattern          iCode2sToSuppressPattern     = null;

	// Sierra API Field Mapping
	String APIItemCallNumberFieldTag;
	String APIItemCallNumberPrestampSubfield;
	String APIItemCallNumberSubfield;
	String APIItemCallNumberCutterSubfield;
	String APICallNumberPoststampSubfield;
	String APIItemVolumeFieldTag;
	String APIItemURLFieldTag;
	String APIItemEContentExportFieldTag;

	private char getCharFromString(String stringValue) {
		char result = ' ';
		if (stringValue != null && stringValue.length() > 0) {
			result = stringValue.charAt(0);
		}
		return result;
	}

	private void setRecordNumberField(String recordNumberField) {
		this.recordNumberField = getCharFromString(recordNumberField);
	}

	private void setItemRecordNumberSubfield(String itemRecordNumberSubfield) {
		this.itemRecordNumberSubfield = getCharFromString(itemRecordNumberSubfield);
	}

	private void setLastCheckinDateSubfield(String lastCheckinDateSubfield) {
		this.lastCheckinDateSubfield = getCharFromString(lastCheckinDateSubfield);
	}

	private void setLocationSubfield(String locationSubfield) {
		this.locationSubfield = getCharFromString(locationSubfield);
	}

	private void setItemStatusSubfield(String itemStatusSubfield) {
		this.itemStatusSubfield = getCharFromString(itemStatusSubfield);
	}

	private void setDueDateSubfield(String dueDateSubfield) {
		this.dueDateSubfield = getCharFromString(dueDateSubfield);
	}

	private void setDateCreatedSubfield(String dateCreatedSubfield) {
		this.dateCreatedSubfield = getCharFromString(dateCreatedSubfield);
	}

	private void setCallNumberPrestampSubfield(String callNumberPrestampSubfield) {
		this.callNumberPrestampSubfield = getCharFromString(callNumberPrestampSubfield);
	}

	private void setCallNumberSubfield(String callNumberSubfield) {
		this.callNumberSubfield = getCharFromString(callNumberSubfield);
	}

	private void setCallNumberCutterSubfield(String callNumberCutterSubfield) {
		this.callNumberCutterSubfield = getCharFromString(callNumberCutterSubfield);
	}

	private void setCallNumberPoststampSubfield(String callNumberPoststampSubfield) {
		this.callNumberPoststampSubfield = getCharFromString(callNumberPoststampSubfield);
	}

	private void setTotalCheckoutsSubfield(String totalCheckoutsSubfield) {
		this.totalCheckoutsSubfield = getCharFromString(totalCheckoutsSubfield);
	}

	private void setYearToDateCheckoutsSubfield(String yearToDateCheckoutsSubfield) {
		this.yearToDateCheckoutsSubfield = getCharFromString(yearToDateCheckoutsSubfield);
	}

	private void setLastYearCheckoutsSubfield(String lastYearCheckoutsSubfield) {
		this.lastYearCheckoutsSubfield = getCharFromString(lastYearCheckoutsSubfield);
	}

	private void setTotalRenewalsSubfield(String totalRenewalsSubfield) {
		this.totalRenewalsSubfield = getCharFromString(totalRenewalsSubfield);
	}

	private void setShelvingLocationSubfield(String shelvingLocationSubfield) {
		this.shelvingLocationSubfield = getCharFromString(shelvingLocationSubfield);
	}

	private void setEContentDescriptor(String eContentDescriptorSubfield) {
		this.eContentDescriptor = getCharFromString(eContentDescriptorSubfield);
	}

	private void setVolume(String subfield) {
		this.volume = getCharFromString(subfield);
	}

	private void setItemUrl(String subfield) {
		this.itemUrl = getCharFromString(subfield);
	}

	private void setFormatSubfield(String formatSubfield) {
		this.format = getCharFromString(formatSubfield);
	}

	private void setITypeSubfield(String iTypeSubfield) {
		this.iTypeSubfield = getCharFromString(iTypeSubfield);
	}

	private void setBcode3Subfield(String bcode3Subfield) {
		this.bcode3Subfield = getCharFromString(bcode3Subfield);
	}

	private void setBarcodeSubfield(String barcodeSubfield) {
		this.barcodeSubfield = getCharFromString(barcodeSubfield);
	}

	public void setICode2Subfield(String ICode2Subfield) {
		this.iCode2Subfield = getCharFromString(ICode2Subfield);
	}

	public void setMaterialTypeSubField(String subfield) {
		this.materialTypeSubField = getCharFromString(subfield);
	}

	public void setSierraLanguageFixedField(String subfield) {
		this.sierraLanguageFixedField = getCharFromString(subfield);
	}

	static IndexingProfile loadIndexingProfile(Connection pikaConn, String profileToLoad, Logger logger) {
		//Get the Indexing Profile from the database
		IndexingProfile indexingProfile = new IndexingProfile();
		try {
			try (PreparedStatement getIndexingProfileStmt = pikaConn.prepareStatement("SELECT * FROM indexing_profiles WHERE sourceName ='" + profileToLoad + "'");
				 ResultSet indexingProfileRS = getIndexingProfileStmt.executeQuery()) {
				if (indexingProfileRS.next()) {

					setIndexingProfile(indexingProfile, indexingProfileRS);


					// Sierra API Item Field Mapping
					try (
							PreparedStatement getSierraFieldMappingsStmt = pikaConn.prepareStatement("SELECT * FROM sierra_export_field_mapping where indexingProfileId =" + indexingProfile.id);
							ResultSet getSierraFieldMappingsRS = getSierraFieldMappingsStmt.executeQuery()
					) {
						if (getSierraFieldMappingsRS.next()) {
							indexingProfile.APIItemCallNumberFieldTag         = getSierraFieldMappingsRS.getString("callNumberExportFieldTag");
							indexingProfile.APIItemCallNumberPrestampSubfield = getSierraFieldMappingsRS.getString("callNumberPrestampExportSubfield");
							indexingProfile.APIItemCallNumberSubfield         = getSierraFieldMappingsRS.getString("callNumberExportSubfield");
							indexingProfile.APIItemCallNumberCutterSubfield   = getSierraFieldMappingsRS.getString("callNumberCutterExportSubfield");
							indexingProfile.APICallNumberPoststampSubfield    = getSierraFieldMappingsRS.getString("callNumberPoststampExportSubfield");
							indexingProfile.APIItemVolumeFieldTag             = getSierraFieldMappingsRS.getString("volumeExportFieldTag");
							indexingProfile.APIItemURLFieldTag                = getSierraFieldMappingsRS.getString("urlExportFieldTag");
							indexingProfile.APIItemEContentExportFieldTag     = getSierraFieldMappingsRS.getString("eContentExportFieldTag");
						}
					}
				} else {
					logger.error("Unable to find " + profileToLoad + " indexing profile, please create a profile with the name ils.");
				}
			}

		} catch (Exception e) {
			logger.error("Error reading indexing profile for Sierra", e);
		}
		return indexingProfile;
	}

	private static void setIndexingProfile(IndexingProfile indexingProfile, ResultSet indexingProfileRS) throws SQLException {
		indexingProfile.id                                = indexingProfileRS.getLong("id");
		indexingProfile.itemTag                           = indexingProfileRS.getString("itemTag");
		indexingProfile.dueDateFormat                     = indexingProfileRS.getString("dueDateFormat");
		indexingProfile.dueDateFormatter                  = new SimpleDateFormat(indexingProfile.dueDateFormat);
		indexingProfile.dateCreatedFormat                 = indexingProfileRS.getString("dateCreatedFormat");
		indexingProfile.dateCreatedFormatter              = new SimpleDateFormat(indexingProfile.dateCreatedFormat);
		indexingProfile.lastCheckinFormat                 = indexingProfileRS.getString("lastCheckinFormat");
		indexingProfile.lastCheckinFormatter              = new SimpleDateFormat(indexingProfile.lastCheckinFormat);
		indexingProfile.individualMarcPath                = indexingProfileRS.getString("individualMarcPath");
		indexingProfile.marcPath                          = indexingProfileRS.getString("marcPath");
		indexingProfile.sourceName                        = indexingProfileRS.getString("sourceName");
		indexingProfile.numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
		indexingProfile.createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");
		indexingProfile.doAutomaticEcontentSuppression    = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");
		indexingProfile.recordNumberTag                   = indexingProfileRS.getString("recordNumberTag");
		indexingProfile.recordNumberPrefix                = indexingProfileRS.getString("recordNumberPrefix");
		indexingProfile.formatSource                      = indexingProfileRS.getString("formatSource");
		indexingProfile.specifiedFormatCategory           = indexingProfileRS.getString("specifiedFormatCategory");
		indexingProfile.sierraRecordFixedFieldsTag        = indexingProfileRS.getString("sierraRecordFixedFieldsTag");
		indexingProfile.marcEncoding                      = indexingProfileRS.getString("marcEncoding");


		// Fields for grouping
		indexingProfile.formatDeterminationMethod         = indexingProfileRS.getString("formatDeterminationMethod");
		indexingProfile.filenamesToInclude                = indexingProfileRS.getString("filenamesToInclude");
		indexingProfile.groupingClass                  = indexingProfileRS.getString("groupingClass");
		indexingProfile.useICode2Suppression           = indexingProfileRS.getBoolean("useICode2Suppression");
		indexingProfile.specifiedGroupingCategory         = indexingProfileRS.getString("specifiedGroupingCategory");
		String locationsToSuppress = indexingProfileRS.getString("locationsToSuppress");
		if (locationsToSuppress != null && locationsToSuppress.length() > 0) {
			indexingProfile.locationsToSuppressPattern = Pattern.compile(locationsToSuppress);
		}
		String collectionsToSuppress = indexingProfileRS.getString("collectionsToSuppress");
		if (collectionsToSuppress != null && collectionsToSuppress.length() > 0) {
			indexingProfile.collectionsToSuppressPattern = Pattern.compile(collectionsToSuppress);
		}
		String statusesToSuppress = indexingProfileRS.getString("statusesToSuppress");
		if (statusesToSuppress != null && statusesToSuppress.length() > 0) {
			indexingProfile.statusesToSuppressPattern = Pattern.compile(statusesToSuppress);
		}
		String iCode2sToSuppress = indexingProfileRS.getString("iCode2sToSuppress");
		if (iCode2sToSuppress != null && iCode2sToSuppress.length() > 0) {
			indexingProfile.iCode2sToSuppressPattern = Pattern.compile(iCode2sToSuppress);
		}
		String iTypesToSuppress = indexingProfileRS.getString("iTypesToSuppress");
		if (iTypesToSuppress != null && iTypesToSuppress.length() > 0) {
			indexingProfile.iTypesToSuppressPattern = Pattern.compile(iTypesToSuppress);
		}



		indexingProfile.setEContentDescriptor(indexingProfileRS.getString("eContentDescriptor"));
		indexingProfile.setRecordNumberField(indexingProfileRS.getString("recordNumberField"));
		indexingProfile.setFormatSubfield(indexingProfileRS.getString("format"));
		indexingProfile.setItemRecordNumberSubfield(indexingProfileRS.getString("itemRecordNumber"));
		indexingProfile.setBarcodeSubfield(indexingProfileRS.getString("barcode"));
		indexingProfile.setLocationSubfield(indexingProfileRS.getString("location"));
		indexingProfile.setShelvingLocationSubfield(indexingProfileRS.getString("shelvingLocation"));
		indexingProfile.setCallNumberPrestampSubfield(indexingProfileRS.getString("callNumberPrestamp"));
		indexingProfile.setCallNumberSubfield(indexingProfileRS.getString("callNumber"));
		indexingProfile.setCallNumberCutterSubfield(indexingProfileRS.getString("callNumberCutter"));
		indexingProfile.setCallNumberPoststampSubfield(indexingProfileRS.getString("callNumberPoststamp"));
		indexingProfile.setItemStatusSubfield(indexingProfileRS.getString("status"));
		indexingProfile.setDueDateSubfield(indexingProfileRS.getString("dueDate"));
		indexingProfile.setTotalCheckoutsSubfield(indexingProfileRS.getString("totalCheckouts"));
		indexingProfile.setLastYearCheckoutsSubfield(indexingProfileRS.getString("lastYearCheckouts"));
		indexingProfile.setYearToDateCheckoutsSubfield(indexingProfileRS.getString("yearToDateCheckouts"));
		indexingProfile.setTotalRenewalsSubfield(indexingProfileRS.getString("totalRenewals"));
		indexingProfile.setITypeSubfield(indexingProfileRS.getString("iType"));
		indexingProfile.setDateCreatedSubfield(indexingProfileRS.getString("dateCreated"));
		indexingProfile.setLastCheckinDateSubfield(indexingProfileRS.getString("lastCheckinDate"));
		indexingProfile.setICode2Subfield(indexingProfileRS.getString("iCode2"));
		indexingProfile.setVolume(indexingProfileRS.getString("volume"));
		indexingProfile.setItemUrl(indexingProfileRS.getString("itemUrl"));
		indexingProfile.setBcode3Subfield(indexingProfileRS.getString("bCode3"));
		indexingProfile.setMaterialTypeSubField(indexingProfileRS.getString("materialTypeField"));
		indexingProfile.setSierraLanguageFixedField(indexingProfileRS.getString("sierraLanguageFixedField"));
	}

	File getFileForIlsRecord(String recordNumber) {
		StringBuilder shortId = new StringBuilder(recordNumber.replace(".", ""));
		while (shortId.length() < 9) {
			shortId.insert(0, "0");
		}

		String subFolderName;
		if (createFolderFromLeadingCharacters) {
			subFolderName = shortId.substring(0, numCharsToCreateFolderFrom);
		} else {
			subFolderName = shortId.substring(0, shortId.length() - numCharsToCreateFolderFrom);
		}

		String basePath           = individualMarcPath + "/" + subFolderName;
		String individualFilename = basePath + "/" + shortId + ".mrc";
		return new File(individualFilename);
	}

}
