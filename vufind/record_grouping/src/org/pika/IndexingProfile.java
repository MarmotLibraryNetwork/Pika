package org.pika;

import java.io.File;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.HashSet;
import java.util.regex.Pattern;

/**
 * A copy of indexing profile information from the database
 *
 * Pika
 * User: Mark Noble
 * Date: 6/30/2015
 * Time: 10:38 PM
 */
public class IndexingProfile {
	Long id;
	public String sourceName;
	String  marcPath;
	String  filenamesToInclude;
	String  marcEncoding;
	String  individualMarcPath;
	int     numCharsToCreateFolderFrom;
	boolean createFolderFromLeadingCharacters;
	String  groupingClass;
	String  recordNumberTag;
	char    recordNumberField;
	String  recordNumberPrefix;
	String  itemTag;
	char    iTypeSubfield;
	char    itemRecordNumberSubfield;
	char    itemStatusSubfield;
	char    iCode2Subfield;
	boolean useICode2Suppression;
	char    shelvingLocationSubfield;
	String  formatDeterminationMethod;
	String  formatSource;
	char    format;
	char    locationSubfield;
	char    eContentDescriptor;
	boolean doAutomaticEcontentSuppression;
	boolean groupUnchangedFiles;
	boolean usingSierraAPIExtract        = false;
	String  sierraRecordFixedFieldsTag;
	char    sierraLanguageFixedField     = ' ';
	char    materialTypeSubField;
	String  materialTypesToIgnore;
	String  specifiedFormat;
	String  specifiedFormatCategory;
	String  specifiedGroupingCategory;
	int     specifiedFormatBoost;
	char    collectionSubfield;
	Pattern statusesToSuppressPattern    = null;
	Pattern locationsToSuppressPattern   = null;
	Pattern collectionsToSuppressPattern = null;
	Pattern iTypesToSuppressPattern      = null;
	Pattern iCode2sToSuppressPattern     = null;

	IndexingProfile(ResultSet indexingProfileRS) throws SQLException {
		this.id                                = indexingProfileRS.getLong("id");
		this.itemTag                           = indexingProfileRS.getString("itemTag");
		this.filenamesToInclude                = indexingProfileRS.getString("filenamesToInclude");
		this.individualMarcPath                = indexingProfileRS.getString("individualMarcPath");
		this.marcPath                          = indexingProfileRS.getString("marcPath");
		this.marcEncoding                      = indexingProfileRS.getString("marcEncoding");
		this.numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
		this.createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");
		this.sourceName                        = indexingProfileRS.getString("sourceName");
		this.doAutomaticEcontentSuppression    = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");
		this.recordNumberTag                   = indexingProfileRS.getString("recordNumberTag");
		this.recordNumberPrefix                = indexingProfileRS.getString("recordNumberPrefix");
		this.formatSource                      = indexingProfileRS.getString("formatSource");
		this.specifiedFormatCategory           = indexingProfileRS.getString("specifiedFormatCategory");
		this.specifiedGroupingCategory         = indexingProfileRS.getString("specifiedGroupingCategory");
		this.formatDeterminationMethod         = indexingProfileRS.getString("formatDeterminationMethod");
		this.materialTypesToIgnore             = indexingProfileRS.getString("materialTypesToIgnore");
		this.groupingClass                     = indexingProfileRS.getString("groupingClass");
		this.itemTag                           = indexingProfileRS.getString("itemTag");
		this.doAutomaticEcontentSuppression    = indexingProfileRS.getBoolean("doAutomaticEcontentSuppression");
		this.groupUnchangedFiles               = indexingProfileRS.getBoolean("groupUnchangedFiles");
		this.sierraRecordFixedFieldsTag        = indexingProfileRS.getString("sierraRecordFixedFieldsTag");
		this.useICode2Suppression              = indexingProfileRS.getBoolean("useICode2Suppression");

		this.setEContentDescriptor(indexingProfileRS.getString("eContentDescriptor"));
		this.setRecordNumberField(indexingProfileRS.getString("recordNumberField"));
		this.setFormatSubfield(indexingProfileRS.getString("format"));
		this.setItemRecordNumberSubfield(indexingProfileRS.getString("itemRecordNumber"));
		this.setLocationSubfield(indexingProfileRS.getString("location"));
		this.setShelvingLocationSubfield(indexingProfileRS.getString("shelvingLocation"));
		this.setItemStatusSubfield(indexingProfileRS.getString("status"));
		this.setITypeSubfield(indexingProfileRS.getString("iType"));
		this.setICode2Subfield(indexingProfileRS.getString("iCode2"));
		this.setMaterialTypeSubField(indexingProfileRS.getString("materialTypeField"));
		this.setSierraLanguageFixedField(indexingProfileRS.getString("sierraLanguageFixedField"));
		this.setCollectionSubfield(indexingProfileRS.getString("collection"));

		String locationsToSuppress = indexingProfileRS.getString("locationsToSuppress");
		if (locationsToSuppress != null && locationsToSuppress.length() > 0) {
			this.locationsToSuppressPattern = Pattern.compile(locationsToSuppress);
		}
		String collectionsToSuppress = indexingProfileRS.getString("collectionsToSuppress");
		if (collectionsToSuppress != null && collectionsToSuppress.length() > 0) {
			this.collectionsToSuppressPattern = Pattern.compile(collectionsToSuppress);
		}
		String statusesToSuppress = indexingProfileRS.getString("statusesToSuppress");
		if (statusesToSuppress != null && statusesToSuppress.length() > 0) {
			this.statusesToSuppressPattern = Pattern.compile(statusesToSuppress);
		}
		String iCode2sToSuppress = indexingProfileRS.getString("iCode2sToSuppress");
		if (iCode2sToSuppress != null && iCode2sToSuppress.length() > 0) {
			this.iCode2sToSuppressPattern = Pattern.compile(iCode2sToSuppress);
		}
		String iTypesToSuppress = indexingProfileRS.getString("iTypesToSuppress");
		if (iTypesToSuppress != null && iTypesToSuppress.length() > 0) {
			this.iTypesToSuppressPattern = Pattern.compile(iTypesToSuppress);
		}


//		this.dueDateFormat                     = indexingProfileRS.getString("dueDateFormat");
//		this.dueDateFormatter                  = new SimpleDateFormat(this.dueDateFormat);
//		this.dateCreatedFormat                 = indexingProfileRS.getString("dateCreatedFormat");
//		this.dateCreatedFormatter              = new SimpleDateFormat(this.dateCreatedFormat);
//		this.lastCheckinFormat                 = indexingProfileRS.getString("lastCheckinFormat");
//		this.lastCheckinFormatter              = new SimpleDateFormat(this.lastCheckinFormat);
//		this.setBarcodeSubfield(indexingProfileRS.getString("barcode"));
//		this.setCallNumberPrestampSubfield(indexingProfileRS.getString("callNumberPrestamp"));
//		this.setCallNumberSubfield(indexingProfileRS.getString("callNumber"));
//		this.setCallNumberCutterSubfield(indexingProfileRS.getString("callNumberCutter"));
//		this.setCallNumberPoststampSubfield(indexingProfileRS.getString("callNumberPoststamp"));
//		this.setDueDateSubfield(indexingProfileRS.getString("dueDate"));
//		this.setTotalCheckoutsSubfield(indexingProfileRS.getString("totalCheckouts"));
//		this.setLastYearCheckoutsSubfield(indexingProfileRS.getString("lastYearCheckouts"));
//		this.setYearToDateCheckoutsSubfield(indexingProfileRS.getString("yearToDateCheckouts"));
//		this.setTotalRenewalsSubfield(indexingProfileRS.getString("totalRenewals"));
//		this.setDateCreatedSubfield(indexingProfileRS.getString("dateCreated"));
//		this.setLastCheckinDateSubfield(indexingProfileRS.getString("lastCheckinDate"));
//		this.setVolume(indexingProfileRS.getString("volume"));
//		this.setItemUrl(indexingProfileRS.getString("itemUrl"));
//		this.setBcode3Subfield(indexingProfileRS.getString("bCode3"));

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

		String basePath = individualMarcPath + "/" + subFolderName;
		createBaseDirectory(basePath);
		String individualFilename = basePath + "/" + shortId + ".mrc";
		return new File(individualFilename);
	}

	private static HashSet<String> basePathsValidated = new HashSet<>();

	private static void createBaseDirectory(String basePath) {
		if (basePathsValidated.contains(basePath)) {
			return;
		}
		File baseFile = new File(basePath);
		if (!baseFile.exists()) {
			if (!baseFile.mkdirs()) {
				System.out.println("Could not create directory to store individual marc");
			}
		}
		basePathsValidated.add(basePath);
	}

	private static char getCharFromString(String stringValue) {
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

//	private void setLastCheckinDateSubfield(String lastCheckinDateSubfield) {
//		this.lastCheckinDateSubfield = getCharFromString(lastCheckinDateSubfield);
//	}

	private void setLocationSubfield(String locationSubfield) {
		this.locationSubfield = getCharFromString(locationSubfield);
	}

	private void setItemStatusSubfield(String itemStatusSubfield) {
		this.itemStatusSubfield = getCharFromString(itemStatusSubfield);
	}

//	private void setDueDateSubfield(String dueDateSubfield) {
//		this.dueDateSubfield = getCharFromString(dueDateSubfield);
//	}
//
//		private void setDateCreatedSubfield(String dateCreatedSubfield) {
//		this.dateCreatedSubfield = getCharFromString(dateCreatedSubfield);
//	}
//
//	private void setCallNumberPrestampSubfield(String callNumberPrestampSubfield) {
//		this.callNumberPrestampSubfield = getCharFromString(callNumberPrestampSubfield);
//	}
//
//	private void setCallNumberSubfield(String callNumberSubfield) {
//		this.callNumberSubfield = getCharFromString(callNumberSubfield);
//	}
//
//	private void setCallNumberCutterSubfield(String callNumberCutterSubfield) {
//		this.callNumberCutterSubfield = getCharFromString(callNumberCutterSubfield);
//	}
//
//	private void setCallNumberPoststampSubfield(String callNumberPoststampSubfield) {
//		this.callNumberPoststampSubfield = getCharFromString(callNumberPoststampSubfield);
//	}
//
//	private void setTotalCheckoutsSubfield(String totalCheckoutsSubfield) {
//		this.totalCheckoutsSubfield = getCharFromString(totalCheckoutsSubfield);
//	}
//
//	private void setYearToDateCheckoutsSubfield(String yearToDateCheckoutsSubfield) {
//		this.yearToDateCheckoutsSubfield = getCharFromString(yearToDateCheckoutsSubfield);
//	}
//
//	private void setLastYearCheckoutsSubfield(String lastYearCheckoutsSubfield) {
//		this.lastYearCheckoutsSubfield = getCharFromString(lastYearCheckoutsSubfield);
//	}
//
//	private void setTotalRenewalsSubfield(String totalRenewalsSubfield) {
//		this.totalRenewalsSubfield = getCharFromString(totalRenewalsSubfield);
//	}
//
	private void setShelvingLocationSubfield(String shelvingLocationSubfield) {
		this.shelvingLocationSubfield = getCharFromString(shelvingLocationSubfield);
	}

	private void setEContentDescriptor(String eContentDescriptorSubfield) {
		this.eContentDescriptor = getCharFromString(eContentDescriptorSubfield);
	}

//	private void setVolume(String subfield) {
//		this.volume = getCharFromString(subfield);
//	}
//
//	private void setItemUrl(String subfield) {
//		this.itemUrl = getCharFromString(subfield);
//	}

	private void setFormatSubfield(String formatSubfield) {
		this.format = getCharFromString(formatSubfield);
	}

	private void setITypeSubfield(String iTypeSubfield) {
		this.iTypeSubfield = getCharFromString(iTypeSubfield);
	}

//	private void setBcode3Subfield(String bcode3Subfield) {
//		this.bcode3Subfield = getCharFromString(bcode3Subfield);
//	}
//
//	private void setBarcodeSubfield(String barcodeSubfield) {
//		this.barcodeSubfield = getCharFromString(barcodeSubfield);
//	}

	public void setICode2Subfield(String ICode2Subfield) {
		this.iCode2Subfield = getCharFromString(ICode2Subfield);
	}

	public void setMaterialTypeSubField(String subfield) {
		this.materialTypeSubField = getCharFromString(subfield);
	}

	public void setSierraLanguageFixedField(String subfield) {
		this.sierraLanguageFixedField = getCharFromString(subfield);
	}

	public void setCollectionSubfield(String collectionSubfield) {
		this.collectionSubfield = getCharFromString(collectionSubfield);
	}

}
