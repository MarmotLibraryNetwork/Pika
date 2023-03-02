/*
 * Copyright (C) 2023  Marmot Library Network
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

import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
import org.apache.logging.log4j.Logger;
import org.ini4j.Profile;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.*;
import java.math.BigInteger;
//import java.net.HttpURLConnection;
//import java.net.MalformedURLException;
//import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

//import static org.apache.logging.log4j.core.util.NameUtil.md5;

/**
 * Pika
 *
 * @author pbrammeier
 * 				Date:   1/17/2023
 */
public class SetDefaultPins implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private String              pikaUrl;
	private Logger              logger;
	private String              userApiToken = "";
	private int linesOfFileProcessed = 0;
	private int numTotalUsersProcessed = 0;
	private int numUsersNotFoundinPikaUserTable = 0;
	private int numPinSet = 0;
	private int numAlreadyHasPinSet = 0;
	private int numPinsFailedToSet = 0;

	/**
	 * @param serverName
	 * @param processSettings
	 * @param pikaConn
	 * @param econtentConn
	 * @param cronEntry
	 * @param logger
	 * @param systemVariables
	 */
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Set Default Pins");
		processLog.saveToDatabase(pikaConn, logger);

		this.logger = logger;
		logger.info("Setting Default Pins");
		processLog.addNote("Setting Default Pins");

		boolean allowSettingDefaultPins = PikaConfigIni.getBooleanIniValue("System", "allowSetDefaultPin");
		if (!allowSettingDefaultPins) {
			// Prevent cron process from running without configuration setting in place, as additional precaution.
			String errMsg = "Setting default pins not allowed";
			logger.error(errMsg);
			processLog.incErrors();
			processLog.addNote(errMsg);
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}

		userApiToken = PikaConfigIni.getIniValue("System", "userApiToken");
		if (userApiToken == null || userApiToken.length() == 0) {
			logger.error("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			processLog.incErrors();
			processLog.addNote("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}

		pikaUrl = PikaConfigIni.getIniValue("Site", "url");
		if (pikaUrl == null || pikaUrl.length() == 0) {
			logger.error("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.incErrors();
			processLog.addNote("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}

		String  systemVariableName  = this.getClass().getSimpleName() + "_linesProcessed";
		Integer linesReadPreviously = systemVariables.getIntValuedVariable(systemVariableName);
		if (linesReadPreviously == null) {
			linesReadPreviously = 1; // Skip header line
		}
		linesOfFileProcessed = linesReadPreviously;

		String ilsPatronExportFilePath = processSettings.get("ilsPatronExportFilePath"); // Full path and name of the csv file to process
		if (ilsPatronExportFilePath == null || ilsPatronExportFilePath.length() == 0) {
			String message = "Default Password CSV setting not set";
			logger.error(message);
			processLog.incErrors();
			processLog.addNote(message);
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}
		String neverPikaPatronsFilePath = processSettings.get("neverPikaPatronsFilePath");
		if (neverPikaPatronsFilePath == null || neverPikaPatronsFilePath.length() == 0) {
			String message = "Never Pika CSV setting not set";
			logger.error(message);
			processLog.incErrors();
			processLog.addNote(message);
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}
		File file   = new File(ilsPatronExportFilePath);
		File report = new File(neverPikaPatronsFilePath);
		if (file.exists()) {
			boolean appendReport = report.exists();
			try (
							PreparedStatement getUsersStmt = pikaConn.prepareStatement("SELECT id, barcode, password FROM user WHERE ilsUserId = ?");
							CSVReader patronExportReader = new CSVReader(new InputStreamReader(Files.newInputStream(Paths.get(ilsPatronExportFilePath)), StandardCharsets.UTF_8),',', '"', '\\', linesReadPreviously);
							CSVWriter neverPikaReport = new CSVWriter(new FileWriter(report, appendReport))
			) {
				logger.info("Starting at line " + (linesReadPreviously + 1) + " of the file.");
				String[] patronFields = patronExportReader.readNext(); linesOfFileProcessed++;
				while (patronFields != null) {
					String originalIlsPatronId = patronFields[0];
					String ilsPatronId         = patronFields[0];
					if (ilsPatronId != null && !ilsPatronId.isEmpty()) {
						// ils Ids extracted from Sierra begin with a p and end with an a. eg. p0001a
						if (ilsPatronId.startsWith("p")){
							ilsPatronId = ilsPatronId.substring(1);
						}
						if (ilsPatronId.endsWith("a")){
							ilsPatronId = ilsPatronId.substring(0, ilsPatronId.length() - 1);
						}
						try {
							getUsersStmt.setString(1, ilsPatronId);
							ResultSet userResults = getUsersStmt.executeQuery();
							if (userResults.next()) {
								// Set default pin for user
								Long   userId     = userResults.getLong("id");
								String barcode    = userResults.getString("barcode");
								String password   = userResults.getString("password");
								if (password == null || password.isEmpty()) {
									String defaultPin = patronFields[1];
									if (defaultPin != null && !defaultPin.isEmpty()) {
										setPatronDefaultPin(userId, barcode, defaultPin);
									} else {
										logger.error("Default PIN was empty in CSV for " + originalIlsPatronId);
									}
								} else {
									numAlreadyHasPinSet++;
									logger.debug("pin already set for " + originalIlsPatronId);
								}
							} else {
								numUsersNotFoundinPikaUserTable++;
								String[] neverPikaPatron = new String[3];
								neverPikaPatron[0] = originalIlsPatronId;
								neverPikaPatron[1] = patronFields[2];
								neverPikaPatron[2] = patronFields[3];
								neverPikaReport.writeNext(neverPikaPatron);
								logger.debug("Never Pika User : original p number : " + originalIlsPatronId);
							}
						} catch (SQLException e) {
							logger.error(e.getMessage(), e);
						}
					} else {
						logger.error("Empty user");
					}
					numTotalUsersProcessed++;

					if (linesOfFileProcessed % 100 == 0){
						systemVariables.setVariable(systemVariableName, (long) linesOfFileProcessed);
						logger.info("Processed " + linesOfFileProcessed + " of the file");
						processLog.saveToDatabase(pikaConn, logger);
					}

					patronFields = patronExportReader.readNext(); linesOfFileProcessed++; // fetch next row
				}

			} catch (IOException | SQLException e) {
				logger.error(e.getMessage(), e);
			}

		} else {
			String errMsg = "Did not find the ILS Patron Export file.";
			logger.error(errMsg);
			processLog.incErrors();
			processLog.addNote(errMsg);
			processLog.saveToDatabase(pikaConn, logger);
			return;
		}


		String note = "Total entries processed : " + numTotalUsersProcessed;
		processLog.addNote(note);
		logger.info(note);
		note = "Number of users pins set for : " + numPinSet;
		processLog.addNote(note);
		note = "Number of users failed to set pins for : " + numPinsFailedToSet;
		logger.info(note);
		processLog.addNote(note);
		note = "Number of users not found in Pika user table : " + numUsersNotFoundinPikaUserTable;
		logger.info(note);
		processLog.addNote(note);
		note = "Number of users with a password already set in Pika user table : " + numAlreadyHasPinSet;
		logger.info(note);
		processLog.addNote(note);
		note = "Number of line processed in file : " + linesOfFileProcessed;
		logger.info(note);
		processLog.addNote(note);
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void setPatronDefaultPin(Long userId, String barcode, String defaultPin) {
		String          token      = md5(barcode);
		String          requestUrl = pikaUrl + "/API/UserAPI?method=setDefaultPin&userId=" + userId;
		String          postData    = "defaultPin=" + defaultPin + "&token=" + token;
		URLPostResponse response   = Util.postToURL(requestUrl, postData, "application/x-www-form-urlencoded", null, logger);
		if (response.isSuccess()) {
			String patronDataJson = response.getMessage();
			try {
				JSONObject result = new JSONObject(patronDataJson);
				if (result.getBoolean("success")) {
					numPinSet++;
					processLog.incUpdated();
				} else {
					numPinsFailedToSet++;
					logger.error("Failed to set pin for user " + userId + " with barcode " + barcode);
					processLog.incErrors();
				}
			} catch (JSONException e) {
				processLog.incErrors();
				logger.error("JSON Error" + e.getMessage(), e);
			}
		} else {
			numPinsFailedToSet++;
			processLog.incErrors();
			logger.error("Failed to get response to set pin for user " + userId + " with barcode " + barcode);
		}
	}

	/**
	 * Build token for User API Calls
	 *
	 * @param string barcode to hash with the userApiToken
	 * @return Hash to use as url token for User API calls that support tokens
	 */
	private String md5(String string) {
		StringBuilder md5 = new StringBuilder();
		try {
			string = string + userApiToken;
			MessageDigest messageDigest = MessageDigest.getInstance("MD5");
			messageDigest.reset();
			messageDigest.update(string.getBytes(StandardCharsets.UTF_8));
			md5 = new StringBuilder(new BigInteger(1, messageDigest.digest()).toString(16));
			while (md5.length() < 32) {
				md5.insert(0, "0");
			}
		} catch (NoSuchAlgorithmException e) {
			logger.error("Error hashing string", e);
		}
		return md5.toString();
	}

}
