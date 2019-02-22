package org.pika;

import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
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
	Long    id;
	String  name;
	String  marcPath;
	String  individualMarcPath;
	int     numCharsToCreateFolderFrom;
	boolean createFolderFromLeadingCharacters;
	String  recordNumberTag;
	String  itemTag;
	char    itemRecordNumberSubfield;
	String  lastCheckinFormat;
	String  dateCreatedFormat;
	String  dueDateFormat;
	char    lastCheckinDateSubfield;
	char    locationSubfield;
	char    subLocationSubfield;
	char    itemStatusSubfield;
	char    iTypeSubfield;
	char    shelvingLocationSubfield;
	char    yearToDateCheckoutsSubfield;
	char    totalCheckoutsSubfield;
	char    callNumberSubfield;
	char    dateCreatedSubfield;
	char    dueDateSubfield;




	private char getCharFromString(String stringValue) {
		char result = ' ';
		if (stringValue != null && stringValue.length() > 0){
			result = stringValue.charAt(0);
		}
		return result;
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

	private void setCallNumberSubfield(String callNumberSubfield) {
		this.callNumberSubfield = getCharFromString(callNumberSubfield);
	}

	private void setTotalCheckoutsSubfield(String totalCheckoutsSubfield) {
		this.totalCheckoutsSubfield = getCharFromString(totalCheckoutsSubfield);
	}

	private void setYearToDateCheckoutsSubfield(String yearToDateCheckoutsSubfield) {
		this.yearToDateCheckoutsSubfield = getCharFromString(yearToDateCheckoutsSubfield);
	}

	private void setShelvingLocationSubfield(String shelvingLocationSubfield) {
		this.shelvingLocationSubfield = getCharFromString(shelvingLocationSubfield);
	}

	public char getSubLocationSubfield() {
		return subLocationSubfield;
	}

	public void setSubLocationSubfield(String sublocationSubfield) {
		this.subLocationSubfield = getCharFromString(sublocationSubfield);
	}
	private void setITypeSubfield(String iTypeSubfield) {
		this.iTypeSubfield = getCharFromString(iTypeSubfield);
	}

	static IndexingProfile loadIndexingProfile(Connection pikaConn, String profileToLoad, Logger logger) {
		//Get the Indexing Profile from the database
		IndexingProfile indexingProfile = new IndexingProfile();
		try {
			PreparedStatement getIndexingProfileStmt = pikaConn.prepareStatement("SELECT * FROM indexing_profiles where name ='" + profileToLoad + "'");
			ResultSet         indexingProfileRS      = getIndexingProfileStmt.executeQuery();
			if (indexingProfileRS.next()) {

				indexingProfile.id                                 = indexingProfileRS.getLong("id");
				indexingProfile.itemTag                            = indexingProfileRS.getString("itemTag");
				indexingProfile.lastCheckinFormat                  = indexingProfileRS.getString("lastCheckinFormat");
				indexingProfile.dueDateFormat                      = indexingProfileRS.getString("dueDateFormat");
				indexingProfile.dateCreatedFormat                  = indexingProfileRS.getString("dateCreatedFormat");
				indexingProfile.individualMarcPath                 = indexingProfileRS.getString("individualMarcPath");
				indexingProfile.marcPath                           = indexingProfileRS.getString("marcPath");
				indexingProfile.name                               = indexingProfileRS.getString("name");
				indexingProfile.numCharsToCreateFolderFrom         = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
				indexingProfile.createFolderFromLeadingCharacters  = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");

				indexingProfile.setItemRecordNumberSubfield(indexingProfileRS.getString("itemRecordNumber"));
				indexingProfile.setLastCheckinDateSubfield(indexingProfileRS.getString("lastCheckinDate"));
				indexingProfile.setLocationSubfield(indexingProfileRS.getString("location"));
				indexingProfile.setSubLocationSubfield(indexingProfileRS.getString("subLocation"));
				indexingProfile.setItemStatusSubfield(indexingProfileRS.getString("status"));
				indexingProfile.setDueDateSubfield(indexingProfileRS.getString("dueDate"));
				indexingProfile.setDateCreatedSubfield(indexingProfileRS.getString("dateCreated"));
				indexingProfile.setCallNumberSubfield(indexingProfileRS.getString("callNumber"));
				indexingProfile.setTotalCheckoutsSubfield(indexingProfileRS.getString("totalCheckouts"));
				indexingProfile.setYearToDateCheckoutsSubfield(indexingProfileRS.getString("yearToDateCheckouts"));
				indexingProfile.setShelvingLocationSubfield(indexingProfileRS.getString("shelvingLocation"));
				indexingProfile.setITypeSubfield(indexingProfileRS.getString("iType"));

			} else {
				logger.error("Unable to find " + profileToLoad + " indexing profile, please create a profile with the name ils.");
			}

		}catch (Exception e){
			logger.error("Error reading index profile for Koha", e);
		}
		return indexingProfile;
	}

	File getFileForIlsRecord(String recordNumber) {
		String shortId = recordNumber.replace(".", "");
		while (shortId.length() < 9){
			shortId = "0" + shortId;
		}

		String subFolderName;
		if (createFolderFromLeadingCharacters){
			subFolderName        = shortId.substring(0, numCharsToCreateFolderFrom);
		}else{
			subFolderName        = shortId.substring(0, shortId.length() - numCharsToCreateFolderFrom);
		}

		String basePath           = individualMarcPath + "/" + subFolderName;
		String individualFilename = basePath + "/" + shortId + ".mrc";
		return new File(individualFilename);
	}
}