package org.pika;

import org.apache.logging.log4j.Logger;
import org.json.JSONException;
import org.json.JSONObject;

import javax.net.ssl.HttpsURLConnection;
import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Base64;
import java.util.Date;
import java.util.concurrent.atomic.AtomicInteger;

public class multipleOfflineCircs implements Runnable {

	static AtomicInteger numProcessed = new AtomicInteger(0);

	private Logger              logger;
	private Connection          pikaConn;
	private CronProcessLogEntry processLog;
	PreparedStatement updateCirculationEntry;
	String            baseSierraApiUrl;
	long              circulationEntryId;
	String            itemBarcode;
	String            login;
	String            patronBarcode;

	public multipleOfflineCircs(String baseSierraApiUrl, ResultSet circulationEntriesToProcessRS, Connection pikaConn, Logger logger, CronProcessLogEntry processLog) {
		this.logger           = logger;
		this.pikaConn         = pikaConn;
		this.baseSierraApiUrl = baseSierraApiUrl;
		this.processLog       = processLog;
		try {
			updateCirculationEntry = pikaConn.prepareStatement("UPDATE offline_circulation SET timeProcessed = ?, status = ?, notes = ? WHERE id = ?");
			circulationEntryId     = circulationEntriesToProcessRS.getLong("id");
			itemBarcode            = circulationEntriesToProcessRS.getString("itemBarcode");
			login                  = circulationEntriesToProcessRS.getString("login");
			patronBarcode          = circulationEntriesToProcessRS.getString("patronBarcode");
		} catch (SQLException e) {
			logger.error("Error : ", e);
		}

	}

	public void run() {
		processOfflineCirc();
	}

	void processOfflineCirc() {
		try {
			logger.debug("Processing " + itemBarcode + " for patron " + patronBarcode);
			updateCirculationEntry.clearParameters();
			updateCirculationEntry.setLong(1, new Date().getTime() / 1000);
			updateCirculationEntry.setLong(4, circulationEntryId);
			OfflineCirculationResult result = processOfflineCheckout(baseSierraApiUrl, login, itemBarcode, patronBarcode);
			if (result.isSuccess()) {
				processLog.incUpdated();
				updateCirculationEntry.setString(2, "Processing Succeeded");
			} else {
				processLog.incErrors();
				updateCirculationEntry.setString(2, "Processing Failed");
			}
			int processed = numProcessed.incrementAndGet();
			if (processed % 10 == 0){
				logger.info(processed + " circs processed so far.");
				processLog.saveToDatabase(pikaConn, logger);
			}
			updateCirculationEntry.setString(3, result.getNote());
			updateCirculationEntry.executeUpdate();
		} catch (SQLException e) {
			logger.error("SQl error : ", e);
		}

	}

	private OfflineCirculationResult processOfflineCheckout(String baseSierraApiUrl, String sierraCircLogin, String itemBarcode, String patronBarcode) {
		OfflineCirculationResult result      = new OfflineCirculationResult();
		String                   checkoutUrl = baseSierraApiUrl + "/patrons/checkout";
		try {
			String checkoutJson = new JSONObject()
							.put("patronBarcode", patronBarcode)
							.put("itemBarcode", itemBarcode)
							.put("username", sierraCircLogin).toString();
			JSONObject response = callSierraApiURL(checkoutUrl, checkoutJson/*, true*/);
			if (response != null) {
				if (response.has("id")) {
					result.setSuccess(true);
				} else {
					logger.info("Check out failed. Request : " + checkoutJson + "  Response : " + response);
					String error = response.getString("name");
					if (response.has("description")){
						error += " : " + response.getString("description");
					}
					result.setSuccess(false);
					result.setNote(error);
				}

			}
		} catch (JSONException e) {
			String message = "JSON error with post data or response";
			logger.error(message, e);
			result.setSuccess(false);
			result.setNote(message);
		}
		return result;
	}

	private static String sierraAPIToken;
	private static String sierraAPITokenType;
	private static long   sierraAPIExpiration;

	private boolean connectToSierraAPI() {
		//Check to see if we already have a valid token
		if (sierraAPIToken != null) {
			if (sierraAPIExpiration - new Date().getTime() > 0) {
				//logger.debug("token is still valid");
				return true;
			} else {
				logger.debug("Token has expired");
			}
		}
		if (baseSierraApiUrl == null || baseSierraApiUrl.isEmpty()) {
			logger.error("Sierra API URL is not set");
			return false;
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL    emptyIndexURL = new URL(baseSierraApiUrl + "/token");
			String clientKey     = PikaConfigIni.getIniValue("Catalog", "clientKey");
			String clientSecret  = PikaConfigIni.getIniValue("Catalog", "clientSecret");
			String encoded       = Base64.getEncoder().encodeToString((clientKey + ":" + clientSecret).getBytes());

			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			checkForSSLConnection(conn);
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setRequestMethod("POST");
			conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			conn.setRequestProperty("Authorization", "Basic " + encoded);
			conn.setDoOutput(true);
			try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
				wr.write("grant_type=client_credentials");
				wr.flush();
			}

			StringBuilder response;
			if (conn.getResponseCode() == 200) {
				// Get the response
				response = getTheResponse(conn.getInputStream());
				try {
					JSONObject parser = new JSONObject(response.toString());
					sierraAPIToken     = parser.getString("access_token");
					sierraAPITokenType = parser.getString("token_type");
					//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
					sierraAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
					//logger.debug("Sierra token is " + sierraAPIToken);
				} catch (JSONException jse) {
					logger.error("Error parsing response to json " + response.toString(), jse);
					return false;
				}

			} else {
				logger.error("Received error " + conn.getResponseCode() + " connecting to sierra authentication service");
				// Get any errors
				response = getTheResponse(conn.getErrorStream());
				logger.error(response);
				return false;
			}

		} catch (Exception e) {
			logger.error("Error connecting to sierra API", e);
			return false;
		}
		return true;
	}

	private StringBuilder getTheResponse(InputStream inputStream) {
		StringBuilder response = new StringBuilder();
		try (BufferedReader rd = new BufferedReader(new InputStreamReader(inputStream, StandardCharsets.UTF_8))) {
			// Setting the charset is apparently important sometimes. See D-4555
			String line;
			while ((line = rd.readLine()) != null) {
				response.append(line);
			}
		} catch (Exception e) {
			logger.warn("Error reading response :", e);
		}
		return response;
	}

	private JSONObject callSierraApiURL(String sierraUrl, String postData/*, boolean logErrors*/) {
		boolean lastCallTimedOut = false;
		if (connectToSierraAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL url = new URL(sierraUrl);
				conn = (HttpURLConnection) url.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod("POST");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/json");
				conn.setRequestProperty("Content-Type", "application/json;charset=UTF-8");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				conn.setDoOutput(true);
				try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
					wr.write(postData);
					wr.flush();
				}


				StringBuilder response;
				int           responseCode = conn.getResponseCode();
				if (responseCode == 200) {
					// Get the response
					response = getTheResponse(conn.getInputStream());
					try {
						return new JSONObject(response.toString());
					} catch (JSONException jse) {
						logger.error("Error parsing response \n" + response, jse);
						return null;
					}

				} else if (responseCode == 500 || responseCode == 404) {
					// 404 is record not found
					//if (logErrors) {
					// Get any errors
					if (logger.isInfoEnabled()) {
						logger.info("Received response code " + responseCode + " calling sierra API " + sierraUrl);
						response = getTheResponse(conn.getErrorStream());
						logger.info("Finished reading response : " + response);
						return new JSONObject(response.toString());
					}
					//}
				} else {
					//if (logErrors) {
					logger.error("Received error " + responseCode + " calling sierra API " + sierraUrl);
					// Get any errors
					response = getTheResponse(conn.getErrorStream());
					logger.error("Finished reading response : " + response);
					return new JSONObject(response.toString());
					//}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.error("Socket timeout talking to sierra API (callSierraApiURL) " + sierraUrl + " - " + e);
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.error("Timeout connecting to sierra API (callSierraApiURL) " + sierraUrl + " - " + e);
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.error("Error loading data from sierra API (callSierraApiURL) " + sierraUrl + " - ", e);
			}
		}
		return null;
	}

	private static void checkForSSLConnection(HttpURLConnection conn) {
		if (conn instanceof HttpsURLConnection) {
			HttpsURLConnection sslConn = (HttpsURLConnection) conn;
			sslConn.setHostnameVerifier((hostname, session) -> {
				return true; //Do not verify host names
			});
		}
	}

}