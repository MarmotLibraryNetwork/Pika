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

import org.apache.logging.log4j.Logger;
import org.ini4j.Profile;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.io.InputStream;
import java.math.BigInteger;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
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
	private CookieManager       manager      = new CookieManager();
	private String              ils          = "Sierra";
	private String              userApiToken = "";
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		processLog  = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Offline Circulation");
		processLog.saveToDatabase(pikaConn, logger);
		userApiToken = PikaConfigIni.getIniValue("System", "userApiToken");

//		ils = PikaConfigIni.getIniValue("Catalog", "ils"); //TODO: remove; was only used to check if millennium

		manager.setCookiePolicy(CookiePolicy.ACCEPT_ALL);
		CookieHandler.setDefault(manager);

		//Check to see if the system is offline
		Boolean offline_mode_when_offline_login_allowed = systemVariables.getBooleanValuedVariable("offline_mode_when_offline_login_allowed");
		if (offline_mode_when_offline_login_allowed == null){
			offline_mode_when_offline_login_allowed = false;
		}
		if (offline_mode_when_offline_login_allowed || PikaConfigIni.getBooleanIniValue("Catalog", "offline")) {
			logger.error("Pika Offline Mode is currently on. Ensure the ILS is available before running OfflineCirculation.");
			processLog.addNote("Not processing offline circulation because the system is currently offline.");
		}
		else {
			if (PikaConfigIni.getBooleanIniValue("Catalog", "useOfflineHoldsInsteadOfRegularHolds")) {
				logger.error("Pika useOfflineHoldsInsteadOfRegularHolds Mode is currently on. Disable this setting before running OfflineCirculation.");
				processLog.addNote("Not processing offline circulation because the useOfflineHoldsInsteadOfRegularHolds setting is currently on.");
			} else {
				//process checkouts and check ins (do this before holds)
				boolean offlineHoldsOnly    = false;
				String  offlineHoldsOnlyStr = processSettings.get("offlineHoldsOnly");
				if (offlineHoldsOnlyStr != null) {
					offlineHoldsOnly = offlineHoldsOnlyStr.equals("true") || offlineHoldsOnlyStr.equals("1");
				}
				if (!offlineHoldsOnly) {
					processOfflineCirculationEntries(pikaConn);
				} else {
					logger.info("Processing Offline Holds only.");
				}

				//process holds
				if (userApiToken == null || userApiToken.length() == 0) {
					// the userApiToken is needed for processing Offline Holds now.
					logger.error("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
					processLog.incErrors();
					processLog.addNote("Unable to get user API token for Pika in ConfigIni settings.  Please add token to the System section.");
				} else {
					processOfflineHolds(pikaConn);
				}
			}
		}
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
		int holdsProcessed = 0;
		String baseUrl         = PikaConfigIni.getIniValue("Site", "url");
		try (
			PreparedStatement holdsToProcessStmt =
//							pikaConn.prepareStatement("SELECT offline_hold.*, cat_username, cat_password FROM offline_hold LEFT JOIN user ON user.id = offline_hold.patronId WHERE status='Not Processed' ORDER BY timeEntered ASC, id ASC");
							pikaConn.prepareStatement("SELECT offline_hold.*, user.id AS userId, user.barcode FROM offline_hold LEFT JOIN user ON user.id = offline_hold.patronId WHERE status='Not Processed' ORDER BY timeEntered ASC, id ASC");
			// Match by Pika patron ID

//			PreparedStatement holdsToProcessStmt = pikaConn.prepareStatement("SELECT offline_hold.*, cat_username, cat_password FROM `offline_hold` LEFT JOIN `user` ON (user.barcode = offline_hold.patronBarcode) WHERE status = 'Not Processed' ORDER BY timeEntered ASC, id ASC");
			// This was used for a data migration of holds transactions (where the assumption that a patron has logged into Pika is invalid)
			// This matches by patron barcode when the barcode is saved in the cat_password field
			// secondary sort factor of id is needed for proper sorting when the timeEntered is the same

			PreparedStatement updateHold = pikaConn.prepareStatement("UPDATE offline_hold set timeProcessed = ?, status = ?, notes = ? where id = ?")
		){
			try (ResultSet holdsToProcessRS = holdsToProcessStmt.executeQuery()) {
				while (holdsToProcessRS.next()) {
					processOfflineHold(updateHold, baseUrl, holdsToProcessRS);
					holdsProcessed++;
				}
			}
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline holds " + e);
		}
		processLog.addNote(holdsProcessed + " offline holds processed.");
	}

	private void processOfflineHold(PreparedStatement updateHold, String baseUrl, ResultSet holdsToProcessRS) throws SQLException {
		long holdId = holdsToProcessRS.getLong("id");
		updateHold.clearParameters();
		updateHold.setLong(1, new Date().getTime() / 1000);
		updateHold.setLong(4, holdId);
		try {
			String userId  = holdsToProcessRS.getString("userId");
			String barcode = holdsToProcessRS.getString("barcode");
			if (!userId.isEmpty() && !barcode.isEmpty()) {
				String token           = md5(barcode);
				String bibId           = encode(holdsToProcessRS.getString("bibId"));
				String itemId          = encode(holdsToProcessRS.getString("itemId"));
				String pickUpLocation  = encode(holdsToProcessRS.getString("pickupLocation"));
				String placeHoldUrlStr = baseUrl + "/API/UserAPI?method=placeHold&userId=" + userId + "&token=" + token + "&bibId=" + bibId;

				if (itemId != null && itemId.length() > 0) {
					placeHoldUrlStr += "&itemId=" + itemId;
				}
				if (pickUpLocation != null && !pickUpLocation.isEmpty()){
					placeHoldUrlStr += "&campus=" + pickUpLocation;
				}

				URL    placeHoldUrl     = new URL(placeHoldUrlStr);
				Object placeHoldDataRaw = placeHoldUrl.getContent();
				if (placeHoldDataRaw instanceof InputStream) {
					String placeHoldDataJson = Util.convertStreamToString((InputStream) placeHoldDataRaw);
					if (logger.isInfoEnabled()) {
						logger.info("Result = " + placeHoldDataJson);
					}
					JSONObject placeHoldData = new JSONObject(placeHoldDataJson);
					JSONObject result        = placeHoldData.getJSONObject("result");
					String message = result.getString("message");
					if (message == null || message.isEmpty()) {
						message = "Did not get valid message response from place hold attempt";
					}
					if (message.length() > 512) { // Column size of the offline hold note field is 512
						message = message.substring(0, 511);
					}
					if (result.getBoolean("success") && (!result.has("items") || message.contains("item level hold"))) {
						// Mark as hold failure if an item-level hold is prompted for
						updateHold.setString(2, "Hold Succeeded");
					} else {
						updateHold.setString(2, "Hold Failed");
					}
					updateHold.setString(3, message);

				}
				processLog.incUpdated();
			}
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
		int numProcessed = 0;
		try (
			PreparedStatement circulationEntryToProcessStmt = pikaConn.prepareStatement("SELECT offline_circulation.* FROM offline_circulation WHERE status='Not Processed' ORDER BY login ASC, initials ASC, patronBarcode ASC, timeEntered ASC");
			PreparedStatement updateCirculationEntry        = pikaConn.prepareStatement("UPDATE offline_circulation SET timeProcessed = ?, status = ?, notes = ? WHERE id = ?");
			PreparedStatement sierraVendorOpacUrlStmt       = pikaConn.prepareStatement("SELECT vendorOpacUrl FROM account_profiles WHERE name = 'ils'")
		){
			try (ResultSet sierraVendorOpacUrlRS = sierraVendorOpacUrlStmt.executeQuery()) {
				if (sierraVendorOpacUrlRS.next()) {
					String baseUrl = sierraVendorOpacUrlRS.getString("vendorOpacUrl");
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
				}
			}
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline circs " + e);
		}
		processLog.addNote(numProcessed + " offline circs processed.");
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

	/**
	 * Build token for User API Calls
	 * @param string barcode to hash with the userApiToken
	 * @return Hash to use as url token for User API calls that support tokens
	 */
	private String md5(String string){
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