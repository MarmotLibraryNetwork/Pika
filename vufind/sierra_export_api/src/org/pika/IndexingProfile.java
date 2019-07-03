package org.pika;

import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.text.SimpleDateFormat;

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
	//Used in record grouping
	Long id;

	String name;
	private String  individualMarcPath;
	public  String  marcPath;
	private int     numCharsToCreateFolderFrom;
	private boolean createFolderFromLeadingCharacters;

	//Used in record grouping
	String recordNumberTag;
	char   recordNumberField;
	String recordNumberPrefix;

	public char getMaterialTypeSubField() {
		return materialTypeSubField;
	}

	char materialTypeSubField;

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
	private String dateCreatedFormat;
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

	String sierraBibLevelFieldTag;
	char   bcode3Subfield;

	//These are used from Record Grouping and Reindexing
	boolean doAutomaticEcontentSuppression;

	String formatSource;
	char   format;
	char   eContentDescriptor;
	String specifiedFormatCategory;

	String callNumberExportFieldTag;
	String callNumberPrestampExportSubfield;
	String callNumberExportSubfield;
	String callNumberCutterExportSubfield;
	String callNumberPoststampExportSubfield;
	String volumeExportFieldTag;
	String urlExportFieldTag;
	String eContentExportFieldTag;

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

	static IndexingProfile loadIndexingProfile(Connection pikaConn, String profileToLoad, Logger logger) {
		//Get the Indexing Profile from the database
		IndexingProfile indexingProfile = new IndexingProfile();
		try {
			try (PreparedStatement getIndexingProfileStmt = pikaConn.prepareStatement("SELECT * FROM indexing_profiles where name ='" + profileToLoad + "'");
				 ResultSet indexingProfileRS = getIndexingProfileStmt.executeQuery()) {
				if (indexingProfileRS.next()) {

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
					indexingProfile.name                              = indexingProfileRS.getString("name");
					indexingProfile.numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
					indexingProfile.createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");
					indexingProfile.doAutomaticEcontentSuppression    = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");
					indexingProfile.recordNumberTag                   = indexingProfileRS.getString("recordNumberTag");
					indexingProfile.recordNumberPrefix                = indexingProfileRS.getString("recordNumberPrefix");
					indexingProfile.formatSource                      = indexingProfileRS.getString("formatSource");
					indexingProfile.specifiedFormatCategory           = indexingProfileRS.getString("specifiedFormatCategory");

					indexingProfile.setEContentDescriptor(indexingProfileRS.getString("eContentDescriptor"));
					indexingProfile.setRecordNumberField(indexingProfileRS.getString("recordNumberField"));
					indexingProfile.setFormatSubfield(indexingProfileRS.getString("format"));
					indexingProfile.setItemRecordNumberSubfield(indexingProfileRS.getString("itemRecordNumber"));
					indexingProfile.setBarcodeSubfield(indexingProfileRS.getString("barcode"));
					indexingProfile.setLocationSubfield(indexingProfileRS.getString("location"));
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
					indexingProfile.sierraBibLevelFieldTag = indexingProfileRS.getString("sierraRecordFixedFieldsTag");
					indexingProfile.setBcode3Subfield(indexingProfileRS.getString("bCode3"));


					indexingProfile.setShelvingLocationSubfield(indexingProfileRS.getString("shelvingLocation"));


					PreparedStatement getSierraFieldMappingsStmt = pikaConn.prepareStatement("SELECT * FROM sierra_export_field_mapping where indexingProfileId =" + indexingProfile.id);
					ResultSet         getSierraFieldMappingsRS   = getSierraFieldMappingsStmt.executeQuery();
					if (getSierraFieldMappingsRS.next()) {
						indexingProfile.callNumberExportFieldTag          = getSierraFieldMappingsRS.getString("callNumberExportFieldTag");
						indexingProfile.callNumberPrestampExportSubfield  = getSierraFieldMappingsRS.getString("callNumberPrestampExportSubfield");
						indexingProfile.callNumberExportSubfield          = getSierraFieldMappingsRS.getString("callNumberExportSubfield");
						indexingProfile.callNumberCutterExportSubfield    = getSierraFieldMappingsRS.getString("callNumberCutterExportSubfield");
						indexingProfile.callNumberPoststampExportSubfield = getSierraFieldMappingsRS.getString("callNumberPoststampExportSubfield");
						indexingProfile.volumeExportFieldTag              = getSierraFieldMappingsRS.getString("volumeExportFieldTag");
						indexingProfile.urlExportFieldTag                 = getSierraFieldMappingsRS.getString("urlExportFieldTag");
						indexingProfile.eContentExportFieldTag            = getSierraFieldMappingsRS.getString("eContentExportFieldTag");

						getSierraFieldMappingsRS.close();
					}
					getSierraFieldMappingsStmt.close();
				} else {
					logger.error("Unable to find " + profileToLoad + " indexing profile, please create a profile with the name ils.");
				}
			}

		} catch (Exception e) {
			logger.error("Error reading index profile for CarlX", e);
		}
		return indexingProfile;
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

	private void setBarcodeSubfield(String barcodeSubfield) {
		this.barcodeSubfield = getCharFromString(barcodeSubfield);
	}

	public void setICode2Subfield(String ICode2Subfield) {
		this.iCode2Subfield = getCharFromString(ICode2Subfield);
	}
}
