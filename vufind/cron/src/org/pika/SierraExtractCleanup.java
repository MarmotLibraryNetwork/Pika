package org.pika;

import org.apache.logging.log4j.Logger;
import org.ini4j.Profile;
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

public class SierraExtractCleanup implements IProcessHandler{

	private CronProcessLogEntry processLog;
	private Logger              logger;

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Sierra Extract Cleanup");
		processLog.saveToDatabase(pikaConn, logger);
		String ils = PikaConfigIni.getIniValue("Catalog", "ils");
		if (ils.equalsIgnoreCase("Sierra")) {
			indexingProfileName = "ils";
			try (
							PreparedStatement sierraVendorOpacUrlStmt = pikaConn.prepareStatement("SELECT vendorOpacUrl, name FROM account_profiles WHERE name = 'ils'");
							ResultSet sierraVendorOpacUrlRS = sierraVendorOpacUrlStmt.executeQuery()
			) {
				if (sierraVendorOpacUrlRS.next()) {
					String apiVersion = PikaConfigIni.getIniValue("Catalog", "api_version");
					if (apiVersion == null || apiVersion.isEmpty()) {
						String message = "Sierra API version must be set.";
						logger.error(message);
						processLog.addNote(message);
						processLog.setFinished();
						processLog.saveToDatabase(pikaConn, logger);
						return;
					} else {
						baseApiUrl = sierraVendorOpacUrlRS.getString("vendorOpacUrl") + "/iii/sierra-api/v" + apiVersion;
						PreparedStatement getIndexingProfileStmt = pikaConn.prepareStatement("SELECT id, name FROM indexing_profiles WHERE sourceName = \"" + indexingProfileName + "\"");
						ResultSet         getIndexProfileResult = getIndexingProfileStmt.executeQuery();
						if (getIndexProfileResult.next()){
							indexingProfileId = getIndexProfileResult.getLong("id");
						} else {
							String message = "Indexing profile id not found for " + indexingProfileName;
							logger.error(message);
							processLog.addNote(message);
							processLog.setFinished();
							processLog.saveToDatabase(pikaConn, logger);
							return;
						}
					}
				}
			} catch (SQLException e) {
				logger.error("SQL Error", e);
				processLog.incErrors();
			}

			int numPrimaryIdsDeleted      = 0;
			int numPrimaryIdsFailed       = 0;
			int numExtractsRemarked       = 0;
			int numExtractsFailedRemarked = 0;

			try (
							PreparedStatement clearSierraExtract = pikaConn.prepareStatement("UPDATE ils_extract_info SET lastExtracted = NULL, deleted = NULL WHERE indexingProfileId = " + indexingProfileId + " AND ilsId = ? LIMIT 1");
							PreparedStatement deleteGroupedWorkPrimaryIdentifier = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE id = ? LIMIT 1");
							PreparedStatement deletedExtractsWithGroupedWorkPrimaryIdentifiers = pikaConn.prepareStatement("SELECT ilsId, grouped_work_id, grouped_work_primary_identifiers.id as primaryIdentifierId FROM ils_extract_info CROSS JOIN pika.grouped_work_primary_identifiers ON (type = \"" + indexingProfileName + "\" AND identifier = ilsId) WHERE deleted IS NOT NULL", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
							ResultSet extractsResults = deletedExtractsWithGroupedWorkPrimaryIdentifiers.executeQuery()
			) {
				while (extractsResults.next()) {
					String  bibId           = extractsResults.getString("ilsId");
					long    shortId         = Long.parseLong(bibId.substring(2, bibId.length() - 1));
					boolean markedAsDeleted = isDeletedInAPI(shortId);
					if (logger.isDebugEnabled()){
						logger.debug("Checking record " +bibId);
					}
					if (!markedAsDeleted) {
						// Remove being marked as deleted and mark for re-extraction
						try {
							clearSierraExtract.setString(1, bibId);
							int updated = clearSierraExtract.executeUpdate();
							if (updated == 0) {
								logger.error("Failed to mark " + bibId + " for re-extraction and remove marked as deleted");
								numExtractsFailedRemarked++;
								processLog.incErrors();
							} else {
								numExtractsRemarked++;
								processLog.incUpdated();
							}
						} catch (SQLException e) {
							logger.error("Error marking ILS extract", e);
							numExtractsFailedRemarked++;
							processLog.incErrors();
						}
					} else {
						// Remove grouped work primary identifier
						long primaryIdentifierID = extractsResults.getLong("primaryIdentifierId");
						deleteGroupedWorkPrimaryIdentifier.setLong(1, primaryIdentifierID);
						try {
							int numDeleted = deleteGroupedWorkPrimaryIdentifier.executeUpdate();
							if (numDeleted > 0) {
								numPrimaryIdsDeleted++;
								processLog.incUpdated();
							} else {
								logger.error("Failed to remove a primary identifier entry " + primaryIdentifierID);
								numPrimaryIdsFailed++;
								processLog.incErrors();
							}
						} catch (SQLException e) {
							logger.error("Error deleting primary identifier entry " + primaryIdentifierID, e);
							numPrimaryIdsFailed++;
							processLog.incErrors();
						}
					}
					processLog.saveToDatabase(pikaConn, logger);
				}
			} catch (SQLException e) {
				logger.error("SQL error", e);
			}
			String note  = "Grouped work primary identifiers removed : " + numPrimaryIdsDeleted;
			String note1 = "ILS extractions remarked as not deleted and for re-extraction : " + numExtractsRemarked;
			String note2 = "Failed to remove grouped work primary identifiers : " + numPrimaryIdsFailed;
			String note3 = "Failed to remark ILS extractions : " + numExtractsFailedRemarked;
			processLog.addNote(note);
			processLog.addNote(note1);
			processLog.addNote(note2);
			processLog.addNote(note3);
			logger.info(note);
			logger.info(note1);
			logger.info(note2);
			logger.info(note3);
		} else {
			String message = "Not set as Sierra ILS";
			logger.error(message);
			processLog.addNote(message);
			processLog.incErrors();
		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private Long indexingProfileId;
	private String indexingProfileName;
	private String baseApiUrl;
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
		if (baseApiUrl == null || baseApiUrl.isEmpty()) {
			logger.error("Sierra API URL is not set");
			return false;
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL emptyIndexURL = new URL(baseApiUrl + "/token");
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
					//TODO: store in System Variables table
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

	private boolean isDeletedInAPI(long id) {
		String     url               = baseApiUrl + "/bibs/" + id + "?fields=id,deleted,suppressed";
		JSONObject isDeletedResponse = callSierraApiURL(url/*, debug*/);
		if (isDeletedResponse != null) {
			try {
				if (isDeletedResponse.has("deleted") && isDeletedResponse.getBoolean("deleted")) {
					return true;
				} else {
					return isDeletedResponse.has("suppressed") && isDeletedResponse.getBoolean("suppressed");
				}
			} catch (JSONException e) {
				logger.error("Error checking if a bib was deleted", e);
			}
		}
		return false;
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

	private static boolean lastCallTimedOut = false;

	private JSONObject callSierraApiURL(String sierraUrl/*, String postData*//*, boolean logErrors*/) {
		lastCallTimedOut = false;
		if (connectToSierraAPI()) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				checkForSSLConnection(conn);
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/json");
				conn.setRequestProperty("Content-Type", "application/json;charset=UTF-8");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

//				conn.setDoOutput(true);
//				try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
//					wr.write(postData);
//					wr.flush();
//				}


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
