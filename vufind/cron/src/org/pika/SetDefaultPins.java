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
import org.apache.logging.log4j.Logger;
import org.ini4j.Profile;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.*;
import java.math.BigInteger;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

import static org.apache.logging.log4j.core.util.NameUtil.md5;

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
			return;
		}

		userApiToken = PikaConfigIni.getIniValue("System", "userApiToken");
		if (userApiToken == null || userApiToken.length() == 0) {
			logger.error("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			processLog.incErrors();
			processLog.addNote("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
			return;
		}

		pikaUrl = PikaConfigIni.getIniValue("Site", "url");
		if (pikaUrl == null || pikaUrl.length() == 0) {
			logger.error("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.incErrors();
			processLog.addNote("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			return;
		}

		String ilsPatronExportFilePath = processSettings.get("ilsPatronExportFilePath");
		File   file                    = new File(ilsPatronExportFilePath);
		if (file.exists()) {
			try (
							PreparedStatement getUsersStmt = pikaConn.prepareStatement("SELECT id, barcode FROM user WHERE ilsUserId = ?");
							CSVReader patronExportReader = new CSVReader(new InputStreamReader(new FileInputStream(ilsPatronExportFilePath), StandardCharsets.UTF_8))
			) {
				patronExportReader.readNext(); // Skip header line
				String[] patronFields = patronExportReader.readNext();
				while (patronFields != null) {
					String ilsPatronId = patronFields[0];
					if (ilsPatronId != null && !ilsPatronId.isEmpty()) {
						try {
							getUsersStmt.setString(1, ilsPatronId);
							ResultSet userResults = getUsersStmt.executeQuery();
							if (userResults.next()) {
								// Set default pin for user
								Long   userId     = userResults.getLong("id");
								String barcode    = userResults.getString("barcode");
								String defaultPin = patronFields[1];
								if (defaultPin != null && !defaultPin.isEmpty()) {
									setPatronDefaultPin(userId, barcode, defaultPin);
								}
							} else {
								//TODO: write to report for non-pika patron
							}
						} catch (SQLException e) {
							throw new RuntimeException(e);
						}
					}
					patronFields = patronExportReader.readNext(); // fetch next row
				}

			} catch (FileNotFoundException e) {
				throw new RuntimeException(e);
			} catch (IOException e) {
				throw new RuntimeException(e);
			} catch (SQLException e) {
				throw new RuntimeException(e);
			}

		} else {
			String errMsg = "Did not find the ILS Patron Export file.";
			logger.error(errMsg);
			processLog.incErrors();
			processLog.addNote(errMsg);
			return;
		}

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
					//TODO: update count?
				} else {
					//TODO: Failed. try again?
				}
			} catch (JSONException e) {
				throw new RuntimeException(e);
			}

		} else {
			//TODO: handle bad response
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
