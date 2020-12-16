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

package org.innovative;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.json.JSONException;
import org.json.JSONObject;
import org.pika.*;

import java.io.IOException;
import java.io.InputStream;
import java.net.*;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Date;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Processes holds and checkouts that were done offline when the system comes back up.
 * Pika
 * User: Mark Noble
 * Date: 8/5/13
 * Time: 5:18 PM
 */
public class OfflineCirculation implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private Logger              logger;
	private CookieManager       manager = new CookieManager();
	private String              ils     = "Sierra";
	@Override
	public void doCronProcess(String servername, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		this.logger = logger;
		processLog  = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Offline Circulation");
		processLog.saveToDatabase(pikaConn, logger);

//		ils = PikaConfigIni.getIniValue("Catalog", "ils"); //TODO: remove; was only used to check if millennium

		manager.setCookiePolicy(CookiePolicy.ACCEPT_ALL);
		CookieHandler.setDefault(manager);

		//Check to see if the system is offline
		if (PikaConfigIni.getBooleanIniValue("Catalog", "offline")) {
			logger.warn("Pika Offline Mode is currently on. Ensure the ILS is available before running OfflineCirculation.");
//			processLog.addNote("Not processing offline circulation because the system is currently offline.");
		}
//		else{
		//process checkouts and check ins (do this before holds)
		processOfflineCirculationEntries(pikaConn);

		//process holds
		processOfflineHolds(pikaConn);
//		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	/**
	 * Enters any holds that were entered while the catalog was offline
	 *
	 * @param pikaConn  Connection to the database
	 */
	private void processOfflineHolds(Connection pikaConn) {
		processLog.addNote("Processing offline holds");
		String baseUrl         = PikaConfigIni.getIniValue("Site", "url");
//		String barcodeProperty = PikaConfigIni.getIniValue("Catalog", "barcodeProperty");
		try (
			PreparedStatement holdsToProcessStmt = pikaConn.prepareStatement("SELECT offline_hold.*, cat_username, cat_password FROM offline_hold LEFT JOIN user ON user.id = offline_hold.patronId WHERE status='Not Processed' ORDER BY timeEntered ASC");
			// Match by Pika patron ID

//			PreparedStatement holdsToProcessStmt = pikaConn.prepareStatement("SELECT offline_hold.*, cat_username, cat_password FROM `offline_hold` LEFT JOIN `user` ON (user." + barcodeProperty + " = offline_hold.patronBarcode) WHERE status = 'Not Processed' ORDER BY timeEntered ASC");
			// This was used for a data migration of holds transactions (where the assumption that a patron has logged into Pika is invalid)
			// This matches by patron barcode when the barcode is saved in the cat_password field

			PreparedStatement updateHold = pikaConn.prepareStatement("UPDATE offline_hold set timeProcessed = ?, status = ?, notes = ? where id = ?")
		){
			try (ResultSet holdsToProcessRS = holdsToProcessStmt.executeQuery()) {
				while (holdsToProcessRS.next()) {
					processOfflineHold(updateHold, baseUrl, holdsToProcessRS);
				}
			}
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline holds " + e.toString());
		}

	}

	private void processOfflineHold(PreparedStatement updateHold, String baseUrl, ResultSet holdsToProcessRS) throws SQLException {
		long holdId = holdsToProcessRS.getLong("id");
		updateHold.clearParameters();
		updateHold.setLong(1, new Date().getTime() / 1000);
		updateHold.setLong(4, holdId);
		try {
			String patronPassword = encode(holdsToProcessRS.getString("cat_password"));
			String patronName     = holdsToProcessRS.getString("cat_username");
			if (patronName == null || patronName.length() == 0) {
				patronName = holdsToProcessRS.getString("patronName");
			}
			patronName = encode(patronName);
			String bibId  = encode(holdsToProcessRS.getString("bibId"));
			String itemId = holdsToProcessRS.getString("itemId");
			URL    placeHoldUrl;
			if (itemId != null && itemId.length() > 0) {
				placeHoldUrl = new URL(baseUrl + "/API/UserAPI?method=placeItemHold&username=" + patronName + "&password=" + patronPassword + "&bibId=" + bibId + "&itemId=" + itemId);
			} else {
				placeHoldUrl = new URL(baseUrl + "/API/UserAPI?method=placeHold&username=" + patronName + "&password=" + patronPassword + "&bibId=" + bibId);
			}

			Object placeHoldDataRaw = placeHoldUrl.getContent();
			if (placeHoldDataRaw instanceof InputStream) {
				String placeHoldDataJson = Util.convertStreamToString((InputStream) placeHoldDataRaw);
				processLog.addNote("Result = " + placeHoldDataJson);
				JSONObject placeHoldData = new JSONObject(placeHoldDataJson);
				JSONObject result        = placeHoldData.getJSONObject("result");
				if (result.getBoolean("success")) {
					updateHold.setString(2, "Hold Succeeded");
				} else {
					updateHold.setString(2, "Hold Failed");
				}
				if (result.has("holdMessage")) {
					updateHold.setString(3, result.getString("holdMessage"));
				} else {
					String message = result.getString("message");
					if (message == null || message.isEmpty()) {
						message = "Did not get valid message response from place hold attempt";
					}
					if (message.length() > 512) { // Column size of the offline hold note field is 512
						message = message.substring(0, 511);
					}
					updateHold.setString(3, message);
				}
			}
			processLog.incUpdated();
		} catch (JSONException e) {
			processLog.incErrors();
			processLog.addNote("Error Loading JSON response for placing hold " + holdId + " - '" + e.toString());
			updateHold.setString(2, "Hold Failed");
			updateHold.setString(3, "Error Loading JSON response for placing hold " + holdId + " - " + e.toString());

		} catch (IOException | SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline hold " + holdId + " - " + e.toString());
			updateHold.setString(2, "Hold Failed");
			updateHold.setString(3, "Error processing offline hold " + holdId + " - " + e.toString());
		}
		try {
			updateHold.executeUpdate();
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error updating hold status for hold " + holdId + " - " + e.toString());
		}
	}

	/**
	 * Processes any checkouts and check-ins that were done while the system was offline.
	 *
	 * @param pikaConn Connection to the database
	 */
	private void processOfflineCirculationEntries(Connection pikaConn) {
		processLog.addNote("Processing offline checkouts and check-ins");
		try {
			PreparedStatement circulationEntryToProcessStmt = pikaConn.prepareStatement("SELECT offline_circulation.* FROM offline_circulation WHERE status='Not Processed' ORDER BY login ASC, initials ASC, patronBarcode ASC, timeEntered ASC");
			PreparedStatement updateCirculationEntry        = pikaConn.prepareStatement("UPDATE offline_circulation SET timeProcessed = ?, status = ?, notes = ? WHERE id = ?");
			String baseUrl                                  = PikaConfigIni.getIniValue("Catalog", "url") + "/iii/airwkst";
			int numProcessed = 0;
			try (ResultSet circulationEntriesToProcessRS = circulationEntryToProcessStmt.executeQuery()) {
				while (circulationEntriesToProcessRS.next()) {
					processOfflineCirculationEntry(updateCirculationEntry, baseUrl, circulationEntriesToProcessRS);
					numProcessed++;
				}
			}
			if (numProcessed > 0) {
				//Logout of the system
				Util.getURL(baseUrl + "/airwkstcore?action=AirWkstReturnToWelcomeAction", logger);
			}
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline holds " + e.toString());
		}
	}

	private void processOfflineCirculationEntry(PreparedStatement updateCirculationEntry, String baseAirpacUrl, ResultSet circulationEntriesToProcessRS) throws SQLException {
		long circulationEntryId = circulationEntriesToProcessRS.getLong("id");
		updateCirculationEntry.clearParameters();
		updateCirculationEntry.setLong(1, new Date().getTime() / 1000);
		updateCirculationEntry.setLong(4, circulationEntryId);
		String itemBarcode      = circulationEntriesToProcessRS.getString("itemBarcode");
		String login            = circulationEntriesToProcessRS.getString("login");
		String loginPassword    = circulationEntriesToProcessRS.getString("loginPassword");
		String initials         = circulationEntriesToProcessRS.getString("initials");
		String initialsPassword = circulationEntriesToProcessRS.getString("initialsPassword");
		String type             = circulationEntriesToProcessRS.getString("type");
		Long timeEntered        = circulationEntriesToProcessRS.getLong("timeEntered");
		OfflineCirculationResult result;
		if (type.equals("Check In")){
			result = processOfflineCheckIn(baseAirpacUrl, login, loginPassword, initials, initialsPassword, itemBarcode, timeEntered);
		} else{
			String patronBarcode = circulationEntriesToProcessRS.getString("patronBarcode");
			result = processOfflineCheckout(baseAirpacUrl, login, loginPassword, initials, initialsPassword, itemBarcode, patronBarcode);
		}
		if (result.isSuccess()){
			processLog.incUpdated();
			updateCirculationEntry.setString(2, "Processing Succeeded");
		}else{
			processLog.incErrors();
			updateCirculationEntry.setString(2, "Processing Failed");
		}
		updateCirculationEntry.setString(3, result.getNote());
		updateCirculationEntry.executeUpdate();
	}

	private void logCookies(){
		logger.debug("Cookies:");
		for(HttpCookie cookie : manager.getCookieStore().getCookies()){
			logger.debug(cookie.toString());
		}
	}

	private String lastLogin;
	private String lastInitials;
	private String lastPatronBarcode;
	private boolean lastPatronHadError;
	private OfflineCirculationResult processOfflineCheckout(String baseAirpacUrl, String login, String loginPassword, String initials, String initialsPassword, String itemBarcode, String patronBarcode) {
		OfflineCirculationResult result = new OfflineCirculationResult();
		try{
			//Login to airpac (login)
			URLPostResponse homePageResponse = Util.getURL(baseAirpacUrl + "/", logger);
			//logger.debug("Home page Response\r\n" + homePageResponse.getMessage());
			//logCookies();
			boolean bypassLogin           = true;
			URLPostResponse loginResponse = null;
			login                         = encode(login);
			loginPassword                 = encode(loginPassword);
			if (lastLogin == null || !lastLogin.equals(login)){
				bypassLogin = false;
				if (lastLogin != null){
					//Logout of the system
					Util.getURL(baseAirpacUrl + "/airwkstcore?action=AirWkstReturnToWelcomeAction", logger);
				}
				lastLogin = login;
			}
			if (!bypassLogin){
				StringBuilder loginParams = new StringBuilder("action=ValidateAirWkstUserAction")
						.append("&login=").append(login)
						.append("&loginpassword=").append(loginPassword)
						.append("&nextaction=null")
						.append("&purpose=null")
						.append("&submit.x=47")
						.append("&submit.y=8")
						.append("&subpurpose=null")
						.append("&validationstatus=needlogin");
				loginResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + loginParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
			}
			//logCookies();
			if (bypassLogin || (loginResponse.isSuccess() && (loginResponse.getMessage().contains("needinitials")) || ils.equalsIgnoreCase("sierra"))){
				URLPostResponse initialsResponse;
				boolean bypassInitials = true;
				initials               = encode(initials);
//				if (ils.equalsIgnoreCase("millennium") && (lastInitials == null || lastInitials.equals(initials))){
//					bypassInitials = false;
//					lastInitials   = initials;
//				}
				if (!bypassInitials){
					//Login to airpac (initials)
					initialsPassword             = encode(initialsPassword);
					StringBuilder initialsParams = new StringBuilder("action=ValidateAirWkstUserAction")
							.append("&initials=").append(initials)
							.append("&initialspassword=").append(initialsPassword)
							.append("&nextaction=null")
							.append("&purpose=null")
							.append("&submit.x=47")
							.append("&submit.y=8")
							.append("&subpurpose=null")
							.append("&validationstatus=needinitials");
					initialsResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + initialsParams.toString(), null, "text/html", baseAirpacUrl + "/airwkstcore", logger);
				}else{
					initialsResponse = loginResponse;
				}
				String errorMessage = null;
				String message = "";
				if (initialsResponse != null) {
					message        = initialsResponse.getMessage();
					errorMessage   = getErrorMessage(message);
				}
				if ((bypassInitials && initialsResponse == null) || (initialsResponse != null && errorMessage == null && message.contains("Check Out"))) {
					//Go to the checkout page
					boolean bypassPatronPage = false;
					patronBarcode            = encode(patronBarcode);
					if (lastPatronBarcode == null || !lastPatronBarcode.equals(patronBarcode) || lastPatronHadError) {
						bypassPatronPage = false;
						if (lastPatronBarcode != null) {
							//Go back to the home page
							URLPostResponse circaMenuPageResponse = Util.getURL(baseAirpacUrl, logger);
						}
						lastPatronBarcode  = patronBarcode;
						lastPatronHadError = false;
					}
					URLPostResponse patronBarcodeResponse = null;
					if (bypassPatronPage == false) {
						URLPostResponse checkOutPageResponse = Util.getURL(baseAirpacUrl + "/?action=GetAirWkstUserInfoAction&purpose=checkout", logger);
						StringBuilder patronBarcodeParams = new StringBuilder("action=LogInAirWkstPatronAction")
								.append("&patronbarcode=").append(patronBarcode)
								.append("&purpose=checkout")
								.append("&submit.x=42")
								.append("&submit.y=12")
								.append("&sourcebrowse=airwkstpage");
						patronBarcodeResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + patronBarcodeParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
					}
					if (bypassPatronPage || (patronBarcodeResponse.isSuccess() && patronBarcodeResponse.getMessage().contains("Please scan item barcode"))) {
						lastPatronHadError = false;
						itemBarcode        = encode(itemBarcode);
						StringBuilder itemBarcodeParams = new StringBuilder("action=GetAirWkstItemOneAction")
								.append("&prevscreen=AirWkstItemRequestPage")
								.append("&purpose=checkout")
								.append("&searchstring=").append(itemBarcode)
								.append("&searchtype=b")
								.append("&sourcebrowse=airwkstpage");
						URLPostResponse itemBarcodeResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + itemBarcodeParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
						String    itemBarcodeMessage            = itemBarcodeResponse.getMessage();
						if (itemBarcodeResponse.isSuccess()) {
							if (itemBarcodeMessage.contains("<h4>Item has message")){
								// Additional confirmation required due to item message
								itemBarcodeParams = new StringBuilder("action=CheckOutAirWkstItemAction&purpose=checkout&checkoutdespiteiormmessage=true&itembarcode=").append(itemBarcode);
								//Tested example also included this param: &itemrecordkey=i12755587
								itemBarcodeResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + itemBarcodeParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
								itemBarcodeMessage = itemBarcodeResponse.getMessage();
							}
							errorMessage = getErrorMessage(itemBarcodeMessage);
							if (errorMessage != null) {
								result.setSuccess(false);
								result.setNote(errorMessage);
							} else {

								//Everything seems to have worked
								if (itemBarcodeMessage.contains("checked out; due")){
									// Success page says item is checked out, and gives the due date
									result.setSuccess(true);
								} else {
									result.setSuccess(false);
									result.setNote("Expected a success message but did not find it. (Did not find an error message either.) ");
								}
							}
						} else {
							logger.debug("Item Barcode response\r\n" + itemBarcodeMessage);
							result.setSuccess(false);
							result.setNote("Could not process check out because the item response was not successful");
						}

//					} else if (patronBarcodeResponse.isSuccess() && patronBarcodeResponse.getMessage().contains("<h[123] class=\"error\">")) {
					} else if (patronBarcodeResponse.isSuccess() && patronBarcodeResponse.getMessage().contains(" class=\"error\">")) {
						lastPatronHadError = true;
						Pattern regex = Pattern.compile("<h[123] class=\"error\">(.*?)</h[123]>");
						Matcher matcher = regex.matcher(patronBarcodeResponse.getMessage());
						if (matcher.find()) {
							String error = matcher.group(1);
							result.setSuccess(false);
							result.setNote(error);
						} else {
							result.setSuccess(false);
							result.setNote("Unknown error loading patron");
						}
					} else {
						lastPatronHadError = true;
						logger.debug("Patron Barcode response\r\n" + patronBarcodeResponse.getMessage());
						result.setSuccess(false);
						result.setNote("Could not process check out because the patron could not be logged in");
					}
				} else {
					if (errorMessage != null) {
						logger.debug("Initials/Login error: " + errorMessage);
						result.setSuccess(false);
						result.setNote("Could not login : " + errorMessage);
					} else {
						logger.debug("Initials response\r\n" + initialsResponse.getMessage());
						result.setSuccess(false);
						result.setNote("Could not process check out because initials were incorrect");
					}
				}
			} else{
				logger.debug("Login response\r\n" + loginResponse.getMessage());
				result.setSuccess(false);
				result.setNote("Could not process check out because login information was incorrect");
			}
		}catch(Exception e){
			result.setSuccess(false);
			result.setNote("Unexpected error processing check in " + e.toString());
		}

		return result;
	}

	private OfflineCirculationResult processOfflineCheckIn(String baseAirpacUrl, String login, String loginPassword, String initials, String initialsPassword, String itemBarcode, Long timeEntered) {
		OfflineCirculationResult result = new OfflineCirculationResult();
		Pattern errorRegex              = Pattern.compile("<h[123] class=\"error\">(.*?)</h[123]>");
		try{
			//Login to airpac (login)
			URLPostResponse homePageResponse = Util.getURL(baseAirpacUrl + "/", logger);
			StringBuilder loginParams = new StringBuilder("action=ValidateAirWkstUserAction")
					.append("&login=").append(login)
					.append("&loginpassword=").append(loginPassword)
					.append("&nextaction=null")
					.append("&purpose=null")
					.append("&submit.x=47")
					.append("&submit.y=8")
					.append("&subpurpose=null")
					.append("&validationstatus=needlogin");
			URLPostResponse loginResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + loginParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
			if (loginResponse.isSuccess() && loginResponse.getMessage().contains("needinitials")){
				//Login to airpac (initials)
				StringBuilder initialsParams = new StringBuilder("action=ValidateAirWkstUserAction")
						.append("&initials=").append(initials)
						.append("&initialspassword=").append(initialsPassword)
						.append("&nextaction=null")
						.append("&purpose=null")
						.append("&submit.x=47")
						.append("&submit.y=8")
						.append("&subpurpose=null")
						.append("&validationstatus=needinitials");
				URLPostResponse initialsResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + initialsParams.toString(), null, "text/html", baseAirpacUrl + "/airwkstcore", logger);
				if (initialsResponse.isSuccess() && initialsResponse.getMessage().contains("Check In")){
					//Go to the checkin page
					URLPostResponse checkinPageResponse = Util.getURL(baseAirpacUrl + "/?action=GetAirWkstUserInfoAction&purpose=fullcheckin", logger);
					//Process the barcode
					StringBuilder checkinParams = new StringBuilder("action=GetAirWkstItemOneAction")
							.append("&prevscreen=AirWkstItemRequestPage")
							.append("&purpose=fullcheckin")
							.append("&searchstring=").append(itemBarcode)
							.append("&searchtype=b")
							.append("&sourcebrowse=airwkstpage");
					URLPostResponse checkinResponse = Util.postToURL(baseAirpacUrl + "/airwkstcore?" + checkinParams.toString(), null, "text/html", baseAirpacUrl + "/", logger);
					if (checkinResponse.isSuccess()){
//						Pattern Regex = Pattern.compile("<h3 class=\"error\">(.*?)</h3>", Pattern.CANON_EQ);
						Matcher RegexMatcher = errorRegex.matcher(checkinResponse.getMessage());
						if (RegexMatcher.find()) {
							String error = RegexMatcher.group(1);
							result.setSuccess(false);
							result.setNote(error);
						}else{
							//Everything seems to have worked
							result.setSuccess(true);
						}
					} else {
						result.setSuccess(false);
						result.setNote("Could not process check in because check in page did not load properly");
					}
				} else{
					result.setSuccess(false);
					result.setNote("Could not process check in because initials were incorrect");
				}
			} else{
				result.setSuccess(false);
				result.setNote("Could not process check in because login information was incorrect");
			}
		}catch(Exception e){
			result.setSuccess(false);
			result.setNote("Unexpected error processing check in " + e.toString());
		}

		return result;
	}

	private String getErrorMessage(String message) {
		Pattern errorRegex   = Pattern.compile("<h[123] class=\"error\">(.*?)</h[123]>");
		Matcher RegexMatcher = errorRegex.matcher(message);
		if (RegexMatcher.find()) {
			String error = RegexMatcher.group(1);
			return error;
		}else{
			//Everything seems to have worked
			return null;
		}
	}

	private static final String VALUES = "!#$&'()*+,/:;=?@[] \"%-.<>\\^_`{|}~";

	private static String encode(String input) {
		if (input == null || input.isEmpty()) {
			return input;
		}
		StringBuilder result = new StringBuilder(input);
		for (int i = input.length() - 1; i >= 0; i--) {
			if (VALUES.indexOf(input.charAt(i)) != -1) {
				result.replace(i, i + 1,
						"%" + Integer.toHexString(input.charAt(i)).toUpperCase());
			}
		}
		return result.toString();
	}
}
