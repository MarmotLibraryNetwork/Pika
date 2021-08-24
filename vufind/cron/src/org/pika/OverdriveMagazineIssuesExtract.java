package org.pika;


import org.apache.log4j.Logger;
import org.ini4j.Profile;
import org.json.*;
import javax.net.ssl.HttpsURLConnection;
import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.net.HttpURLConnection;
import java.net.SocketTimeoutException;
import java.net.URL;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;


public class OverdriveMagazineIssuesExtract implements IProcessHandler {
	private       String                  clientSecret;
	private       String                  clientKey;
	private       String                  bookCoverUrl;
	private final List<String>            accountIds                  = new ArrayList<>();
	private       String                  overDriveAPIToken;
	private       String                  overDriveAPITokenType;
	private       long                    overDriveAPIExpiration;
	private final TreeMap<String, String> overDriveProductsKeys       = new TreeMap<>(); // specifically <AccountId, overDriveProductsKey>
	private final TreeMap<Long, String>   libToOverDriveAPIKeyMap     = new TreeMap<>();
	private final TreeMap<Long, Long>     libToSharedCollectionIdMap  = new TreeMap<>(); // specifically <libraryId, sharedCollectionId>
	private final HashMap<String, Long>   advantageCollectionToLibMap = new HashMap<>();
	private       PreparedStatement       insertIssues;
	private       PreparedStatement       updateIssues;
	private       PreparedStatement       doesIdExist;

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Overdrive Magazine Issues Extract");
		processLog.saveToDatabase(pikaConn, logger);

		try {
			if (processSettings.get("fullReindex") != null) {
				processLog.addNote("Adding/Updating all magazine issues");
			} else {
				processLog.addNote("Adding New Magazine Issues");
			}
			processLog.saveToDatabase(pikaConn, logger);

			String[] tempAccountIds  = PikaConfigIni.getIniValue("OverDrive", "accountId").split(",");
			String[] tempProductKeys = PikaConfigIni.getIniValue("OverDrive", "productsKey").split(",");

			if (tempProductKeys.length == 0) {
				logger.warn("Warning no products key provided for OverDrive in configuration file.");
			}
			int i = 0;
			for (String tempAccountId : tempAccountIds) {
				String tempId         = tempAccountId.trim();
				String tempProductKey = tempProductKeys[i++].trim();
				if (tempId.length() > 0) {
					accountIds.add(tempId);
					if (tempProductKey.length() > 0) {
						overDriveProductsKeys.put(tempId, tempProductKey);
					}
				}
			}
			// When an advantage account is shared, we will take over the pikalibraryIds so that 1 is the shared advantage account for the first overall account, 2 for the second advantage account for the 2nd shared
			String sharedAdvantageAccountKey = PikaConfigIni.getIniValue("OverDrive", "sharedAdvantageAccountKey");
			if (sharedAdvantageAccountKey != null && !sharedAdvantageAccountKey.isEmpty()) {
				String[] tempSharedAdvantageAccountKey = sharedAdvantageAccountKey.split(",");
				i = 0;
				for (String sharedAdvantageKey : tempSharedAdvantageAccountKey) {
					long pikaLibraryId = ++i;
					advantageCollectionToLibMap.put(sharedAdvantageKey, pikaLibraryId);
					libToSharedCollectionIdMap.put(pikaLibraryId, -pikaLibraryId);
				}
			}
			if (advantageCollectionToLibMap.isEmpty()) {
				// If the advantageCollectionToLibMap is already populated, skip this step as we are using shared advantage accounts
				try (
								PreparedStatement advantageCollectionMapStmt = pikaConn.prepareStatement("SELECT libraryId, overdriveAdvantageProductsKey, sharedOverdriveCollection FROM library WHERE enableOverdriveCollection = 1");
								// Only include libraries that have enabled Overdrive Collection in Pika, even if they have an Advantage account  (eg. CMC)
								ResultSet advantageCollectionMapRS = advantageCollectionMapStmt.executeQuery();
				) {
					while (advantageCollectionMapRS.next()) {
						//1 = (pika) libraryId, 2 = overDriveAdvantageName, 3 = overDriveAdvantageProductsKey

						final long   pikaLibraryId              = advantageCollectionMapRS.getLong(1);
						final String overDriveAdvantageProducts = advantageCollectionMapRS.getString(2);
						if (overDriveAdvantageProducts != null && !overDriveAdvantageProducts.isEmpty()) {
							advantageCollectionToLibMap.put(overDriveAdvantageProducts, pikaLibraryId);

						}
						long sharedCollectionId = advantageCollectionMapRS.getLong(3);
						if (sharedCollectionId < 0L) {
							libToSharedCollectionIdMap.put(pikaLibraryId, sharedCollectionId);
						}
					}
				} catch (SQLException e) {
					logger.error("Error loading Advantage Collection names", e);
				}
			}
			//Load products from API
			clientSecret = PikaConfigIni.getIniValue("OverDrive", "clientSecret");
			clientKey    = PikaConfigIni.getIniValue("OverDrive", "clientKey");
			for (Map.Entry<String, String> entry : overDriveProductsKeys.entrySet()) {
				String accountId          = entry.getKey();
				String productKey         = entry.getValue();
				Long   sharedCollectionId = (accountIds.indexOf(accountId) + 1) * -1L;
				libToOverDriveAPIKeyMap.put(sharedCollectionId, productKey);
			}
			if (clientSecret == null || clientKey == null || clientSecret.length() == 0 || clientKey.length() == 0 || accountIds.isEmpty()) {
				logger.info("Did not find correct configuration in config.ini, not loading overdrive magazines");
			} else {
				insertIssues = econtentConn.prepareStatement("INSERT INTO `overdrive_api_magazine_issues` " +
								"SET overdriveId = ?, crossRefId = ?, title = ?, edition = ?, pubDate = ?, coverUrl = ?, parentId = ?, " +
								"description = ?, dateAdded = ?, dateUpdated = ?");
				updateIssues = econtentConn.prepareStatement("UPDATE `overdrive_api_magazine_issues` " +
								"SET crossRefId = ?, title = ?, edition = ?, pubDate = ?, coverUrl = ?, parentId = ?, " +
								"description = ?, dateUpdated = ? WHERE overdriveId = ? ");
				doesIdExist = econtentConn.prepareStatement("SELECT id FROM `overdrive_api_magazine_issues` WHERE overdriveId =?");
				bookCoverUrl = PikaConfigIni.getIniValue("Site", "url");
				bookCoverUrl += "/bookcover.php?size=medium&type=overdrive&reload&id=";

				try (
				PreparedStatement magazineIdStatement = econtentConn.prepareStatement("SELECT overdriveId, crossRefId from overdrive_api_products WHERE mediaType='magazine' AND deleted != 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet         magazines           = magazineIdStatement.executeQuery()
				) {
					if (!magazines.wasNull()) {
						while (magazines.next()) {
							if (processSettings.get("fullReindex") != null) {
								getAllMagazineIssuesById(magazines.getString("overdriveId"), logger, processLog, econtentConn, pikaConn);
							} else {
								getNewMagazineIssuesById(magazines.getString("overdriveId"), logger, processLog, econtentConn, pikaConn);
							}
							if (logger.isDebugEnabled()) {
								logger.debug("Processed " + magazines.getString("overdriveId"));
							}
						}
						processLog.addNote("Completed updates.");
					} else {
						logger.info("Zero magazines found in database");
					}
				}
			}
		} catch (Exception e) {
			logger.error(e.getMessage());
		}
		processLog.addNote("Finished cron process.");
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	public void getAllMagazineIssuesById(String magazineParentId, Logger logger, CronProcessLogEntry processLog, Connection econtentConn, Connection pikaConn) {
		try {
			final int  issuesPerQuery = 2000;
			JSONObject jsonObject     = null;
			String     overDriveUrl   = "";
			boolean    issuesFound    = false;
			for (Map.Entry<Long, String> sharedCollections : libToOverDriveAPIKeyMap.entrySet()) {
				final String sharedCollectionsProductKey = sharedCollections.getValue();

				overDriveUrl = "https://api.overdrive.com/v1/collections/" + sharedCollectionsProductKey + "/products/" + magazineParentId + "/issues?limit=" + issuesPerQuery + "&sort=saledate:asc";
				WebServiceResponse overdriveCall     = callOverDriveURL(overDriveUrl, logger);
				boolean            overdriveHasError = false;
				if (overdriveCall.getResponseCode() != 200) {
					JSONObject overdriveError = new JSONObject(overdriveCall.getError());
					if (overdriveError.has("errorCode") && !overdriveError.get("errorCode").equals("NoIssuesAvailable")) {
						String errorMessage = overdriveError.getString("message");
						logger.error(errorMessage + " " + magazineParentId);
						overdriveHasError = true;
					}
				} else {
					jsonObject  = overdriveCall.getResponse();
					issuesFound = jsonObject.getInt("totalItems") != 0;
				}
				if (issuesFound){
					break;
				}
			}

			if (!issuesFound) {
				// Try Advantage accounts for Issues if we still haven't found them
				boolean overdriveHasError = false;
				if (logger.isInfoEnabled()) {
					logger.info("No issues found for " + magazineParentId + " trying advantage accounts");
				}
				for (String advantageCollections : advantageCollectionToLibMap.keySet()) {
					overDriveUrl = "https://api.overdrive.com/v1/collections/" + advantageCollections + "/products/" + magazineParentId + "/issues?limit=" + issuesPerQuery + "&sort=saledate:asc";
					WebServiceResponse overdriveCall = callOverDriveURL(overDriveUrl, logger);

					if (overdriveCall.getResponseCode() != 200) {
						JSONObject overdriveError = new JSONObject(overdriveCall.getError());
						if (overdriveError.has("errorCode") && !overdriveError.get("errorCode").equals("NoIssuesAvailable")) {
							String errorMessage = overdriveError.getString("message");
							logger.error(errorMessage + " " + magazineParentId);
							overdriveHasError = true;
						}
						continue;
					}
					jsonObject = overdriveCall.getResponse();
					if (jsonObject.getInt("totalItems") != 0) {
						// Once we found issues from an Advantage account break out of the advantage account loop
						issuesFound = true;
						break;
					}
				}
			}


			if (!issuesFound) {
				processLog.incErrors();
				processLog.addNote("No magazines issues found for " + magazineParentId);
				processLog.saveToDatabase(pikaConn, logger);
				return;
			}

			int     x         = 0;
			boolean lastBatch = false;
			do {
				int       updates         = 0;
				int       returnedRecords = jsonObject.getInt("totalItems");
				JSONArray products        = jsonObject.getJSONArray("products");
				if (returnedRecords < issuesPerQuery) {
					lastBatch = true;
				}
				for (int i = 0; i < products.length(); i++) {
					JSONObject         issue                    = (JSONObject) products.get(i);
					JSONObject         images                   = issue.getJSONObject("images");
					JSONObject         image                    = images.getJSONObject("cover");
					String             coverUrl                 = image.getString("href");
					String             magazineIssueId          = issue.getString("id");
					String             magazineTitle            = issue.getString("sortTitle").replace("'", "&apos;");
					String             magazineCrossRef         = issue.getString("crossRefId");
					String             magazineEdition          = issue.getString("edition").replace("'", "&apos;");
					JSONObject         links                    = issue.getJSONObject("links");
					JSONObject         metadata                 = links.getJSONObject("metadata");
					String             magazineIssueMetadataURL = metadata.getString("href");
					WebServiceResponse metadataCall             = callOverDriveURL(magazineIssueMetadataURL, logger);
					JSONObject         magazineMetaCall         = metadataCall.getResponse();
					String             magazineDescription;
					if (magazineMetaCall.has("shortDescription")) {
						magazineDescription = magazineMetaCall.getString("shortDescription").replace("'", "&apos;");
					} else {
						magazineDescription = magazineTitle + " " + magazineEdition;
					}
					JSONArray formatsArray  = magazineMetaCall.getJSONArray("formats");
					String    pubDateString = null;
					for (int n = 0; n < formatsArray.length(); n++) {
						JSONObject formatsObject = formatsArray.getJSONObject(n);
						pubDateString = formatsObject.getString("onSaleDate");
					}
					Date pubDate   = new SimpleDateFormat("MM/dd/yyyy").parse(pubDateString);
					long published = pubDate.getTime() / 1000;
					long dateTime  = System.currentTimeMillis() / 1000;
					doesIdExist.setString(1, magazineIssueId);
					ResultSet idResult = doesIdExist.executeQuery();
					if (!idResult.first()) {
						insertIssues.setString(1, magazineIssueId);
						insertIssues.setString(2, magazineCrossRef);
						insertIssues.setString(3, magazineTitle);
						insertIssues.setString(4, magazineEdition);
						insertIssues.setLong(5, published);
						insertIssues.setString(6, coverUrl);
						insertIssues.setString(7, magazineParentId);
						insertIssues.setString(8, magazineDescription);
						insertIssues.setLong(9, dateTime);
						insertIssues.setLong(10, dateTime);
						updates = updates + insertIssues.executeUpdate();
					} else {
						updateIssues.setString(1, magazineCrossRef);
						updateIssues.setString(2, magazineTitle);
						updateIssues.setString(3, magazineEdition);
						updateIssues.setLong(4, published);
						updateIssues.setString(5, coverUrl);
						updateIssues.setString(6, magazineParentId);
						updateIssues.setString(7, magazineDescription);
						updateIssues.setLong(8, dateTime);
						updateIssues.setString(9, magazineIssueId);
						updates = updates + updateIssues.executeUpdate();
					}
				}
				processLog.addUpdates(updates);
				processLog.saveToDatabase(pikaConn, logger);

				if (!lastBatch) {
					x = x + issuesPerQuery;
					String nextBatchUrl = overDriveUrl + "&offset=" + x;
					WebServiceResponse overdriveCall     = callOverDriveURL(nextBatchUrl, logger);
					if (overdriveCall.getResponseCode() != 200) {
						JSONObject overdriveError = new JSONObject(overdriveCall.getError());
						String     errorMessage   = overdriveError.getString("message");
						logger.error(errorMessage + " " + magazineParentId);
						// TODO: break out of while loop on error??
					} else {
						jsonObject = overdriveCall.getResponse();
					}
				}


			} while (!lastBatch);
		} catch (Exception e) {
			logger.error("Fetch all issues - " +e.getMessage() + ": " + magazineParentId);
		}
	}

	public void getNewMagazineIssuesById(String magazineParentId, Logger logger, CronProcessLogEntry processLog, Connection econtentConn, Connection pikaConn) {
		try {
			final int  issuesPerQuery = 6; // Most of the new updates will be single issues; so keep the number of issues we fetch small
			JSONObject jsonObject     = null;
			String     overDriveUrl   = "";
			boolean    issuesFound    = false;
			for (Map.Entry<Long, String> sharedCollections : libToOverDriveAPIKeyMap.entrySet()) {
				final String sharedCollectionsProductKey = sharedCollections.getValue();
				// From the API documentation :
				// Sort based on the date an issue became available for sale.
				// By default, issues are sorted in descending order (newest issue to oldest).
				// To sort by oldest to newest, enter sort=saledate:asc.
				//
				// So we will use the default sort, and populate till we get a magazine we've already processed

				overDriveUrl = "https://api.overdrive.com/v1/collections/" + sharedCollectionsProductKey + "/products/" + magazineParentId + "/issues?limit=" + issuesPerQuery;
				WebServiceResponse overdriveCall     = callOverDriveURL(overDriveUrl, logger);
				boolean            overdriveHasError = false;
				if (overdriveCall.getResponseCode() != 200) {
					JSONObject overdriveError = new JSONObject(overdriveCall.getError());
					if (overdriveError.has("errorCode") && !overdriveError.get("errorCode").equals("NoIssuesAvailable")) {
						String errorMessage = overdriveError.getString("message");
						logger.error(errorMessage + " " + magazineParentId);
						overdriveHasError = true;
					}
				} else {
					jsonObject  = overdriveCall.getResponse();
					issuesFound = jsonObject.getInt("totalItems") != 0;
				}
				if (issuesFound) {
					break;
				}
			}

			if (!issuesFound) {
				// Try Advantage accounts for Issues if we still haven't found them
				boolean overdriveHasError = false;
				if (logger.isInfoEnabled()) {
					logger.info("No issues found for " + magazineParentId + " trying advantage accounts");
				}
				for (String advantageCollections : advantageCollectionToLibMap.keySet()) {
					overDriveUrl = "https://api.overdrive.com/v1/collections/" + advantageCollections + "/products/" + magazineParentId + "/issues?limit=" + issuesPerQuery;
					WebServiceResponse overdriveCall = callOverDriveURL(overDriveUrl, logger);

					if (overdriveCall.getResponseCode() != 200) {
						JSONObject overdriveError = new JSONObject(overdriveCall.getError());
						if (overdriveError.has("errorCode") && !overdriveError.get("errorCode").equals("NoIssuesAvailable")) {
							String errorMessage = overdriveError.getString("message");
							logger.error(errorMessage + " " + magazineParentId);
							overdriveHasError = true;
						}
						continue;
					}
					jsonObject = overdriveCall.getResponse();
					if (jsonObject.getInt("totalItems") != 0) {
						// Once we found issues from an Advantage account break out of the advantage account loop
						issuesFound = true;
						break;
					}
				}
			}


			if (!issuesFound) {
				processLog.incErrors();
				processLog.addNote("No magazines issues found for " + magazineParentId);
				processLog.saveToDatabase(pikaConn, logger);
				return;
			}

			int     x          = 0;
			boolean lastBatch  = false;
			boolean hadUpdates = false;
			do {
				int       updates         = 0;
				int       returnedRecords = jsonObject.getInt("totalItems");
				JSONArray products        = jsonObject.getJSONArray("products");
				if (returnedRecords < issuesPerQuery) {
					lastBatch = true;
				}
				for (int i = 0; i < products.length(); i++) {
					JSONObject issue           = (JSONObject) products.get(i);
					String     magazineIssueId = issue.getString("id");
					doesIdExist.setString(1, magazineIssueId);
					ResultSet idResult = doesIdExist.executeQuery();
					// Check if we have fetched this magazine before
					if (!idResult.first()) {
						JSONObject         images                   = issue.getJSONObject("images");
						JSONObject         image                    = images.getJSONObject("cover");
						String             coverUrl                 = image.getString("href");
						String             magazineTitle            = issue.getString("sortTitle").replace("'", "&apos;");
						String             magazineCrossRef         = issue.getString("crossRefId");
						String             magazineEdition          = issue.getString("edition").replace("'", "&apos;");
						JSONObject         links                    = issue.getJSONObject("links");
						JSONObject         metadata                 = links.getJSONObject("metadata");
						String             magazineIssueMetadataURL = metadata.getString("href");
						WebServiceResponse metadataCall             = callOverDriveURL(magazineIssueMetadataURL, logger);
						JSONObject         magazineMetaCall         = metadataCall.getResponse();
						String             magazineDescription;
						if (magazineMetaCall.has("shortDescription")) {
							magazineDescription = magazineMetaCall.getString("shortDescription").replace("'", "&apos;");
						} else {
							magazineDescription = magazineTitle + " " + magazineEdition;
						}
						JSONArray formatsArray  = magazineMetaCall.getJSONArray("formats");
						String    pubDateString = null;
						for (int n = 0; n < formatsArray.length(); n++) {
							JSONObject formatsObject = formatsArray.getJSONObject(n);
							pubDateString = formatsObject.getString("onSaleDate");
						}
						Date pubDate   = new SimpleDateFormat("MM/dd/yyyy").parse(pubDateString);
						long published = pubDate.getTime() / 1000;
						long dateTime  = System.currentTimeMillis() / 1000;
						insertIssues.setString(1, magazineIssueId);
						insertIssues.setString(2, magazineCrossRef);
						insertIssues.setString(3, magazineTitle);
						insertIssues.setString(4, magazineEdition);
						insertIssues.setLong(5, published);
						insertIssues.setString(6, coverUrl);
						insertIssues.setString(7, magazineParentId);
						insertIssues.setString(8, magazineDescription);
						insertIssues.setLong(9, dateTime);
						insertIssues.setLong(10, dateTime);
						updates = updates + insertIssues.executeUpdate();
					} else {
						// We found an issue we've processed before, so let's break out of our batch loop
						lastBatch = true;
						//TODO: break out of for loop as well?
					}
				}
				if (updates > 0) hadUpdates = true;
				processLog.addUpdates(updates);
				processLog.saveToDatabase(pikaConn, logger);

				if (!lastBatch) {
					x = x + issuesPerQuery;
					String             nextBatchUrl  = overDriveUrl + "&offset=" + x;
					WebServiceResponse overdriveCall = callOverDriveURL(nextBatchUrl, logger);
					if (overdriveCall.getResponseCode() != 200) {
						JSONObject overdriveError = new JSONObject(overdriveCall.getError());
						String     errorMessage   = overdriveError.getString("message");
						logger.error(errorMessage + " " + magazineParentId);
						// TODO: break out of while loop on error??
					} else {
						jsonObject = overdriveCall.getResponse();
					}
				}


			} while (!lastBatch);
			if (hadUpdates){
				try {
					// Update Cover URL
					HttpURLConnection conn;
					URL emptyIndexURL = new URL(bookCoverUrl + magazineParentId);
					conn = (HttpURLConnection) emptyIndexURL.openConnection();
					if (conn instanceof HttpsURLConnection){
						HttpsURLConnection sslConn = (HttpsURLConnection)conn;
						sslConn.setHostnameVerifier((hostname, session) -> {
							//Do not verify host names
							return true;
						});
					}
					conn.setConnectTimeout(3000);
					conn.setReadTimeout(5000);
					if (conn.getResponseCode() != 200) {
						if (logger.isDebugEnabled()){
							logger.debug("Failed to update OverDrive Magazine cover for " + magazineParentId);
						}
					}
				} catch (IOException e) {
					//We can likely ignore all the time outs. As long as the Pika server received the cover url call, it should reload the cover for us.
					if (logger.isDebugEnabled()){
						logger.debug("Error while updating Pika cover for OverDrive Magazine " + magazineParentId, e);
					}
				}

			}
		} catch (Exception e) {
			logger.error("Fetch new issues - " +e.getMessage() + " : " + magazineParentId);
		}
	}

	private WebServiceResponse callOverDriveURL(String overdriveUrl, Logger logger) throws SocketTimeoutException {
		WebServiceResponse webServiceResponse = new WebServiceResponse();
		if (connectToOverDriveAPI(false, logger)) {
			//Connect to the API to get our token
			HttpURLConnection conn;
			StringBuilder     response = new StringBuilder();
			boolean           hadTimeoutsFromOverDrive;
			try {
				URL emptyIndexURL = new URL(overdriveUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				if (conn instanceof HttpsURLConnection) {
					HttpsURLConnection sslConn = (HttpsURLConnection) conn;
					sslConn.setHostnameVerifier((hostname, session) -> {
						//Do not verify host names
						return true;
					});
				}
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Authorization", overDriveAPITokenType + " " + overDriveAPIToken);
				conn.setReadTimeout(30000);
				conn.setConnectTimeout(30000);
				webServiceResponse.setResponseCode(conn.getResponseCode());
				if (conn.getResponseCode() == 200) {
					// Get the response
					try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						//logger.debug("  Finished reading response");
					}
					String responseString = response.toString();
					if (responseString.equals("null")) {
						webServiceResponse.setResponse(null);
					} else {
						webServiceResponse.setResponse(new JSONObject(response.toString()));
					}
				} else {
					// Get any errors
					try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						//logger.info("Received error " + conn.getResponseCode() + " connecting to overdrive API " + response.toString());
						//logger.debug("  Finished reading response");
						//logger.debug(response.toString());
						webServiceResponse.setError(response.toString());
					}
					hadTimeoutsFromOverDrive = true;
				}
			} catch (SocketTimeoutException toe) {
				throw toe;
			} catch (Exception e) {
				logger.debug("Error loading data from overdrive API ", e);
				hadTimeoutsFromOverDrive = true;
			}
		} else {
			logger.error("Unable to connect to API");
		}
		return webServiceResponse;
	}

	private boolean connectToOverDriveAPI(boolean getNewToken, Logger logger) throws SocketTimeoutException {
		//Check to see if we already have a valid token
		if (overDriveAPIToken != null && !getNewToken) {
			if (overDriveAPIExpiration - new Date().getTime() > 0) {
				//logger.debug("token is still valid");
				return true;
			} else {
				logger.debug("Token has expired");
			}
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL emptyIndexURL = new URL("https://oauth.overdrive.com/token");
			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			if (conn instanceof HttpsURLConnection) {
				HttpsURLConnection sslConn = (HttpsURLConnection) conn;
				sslConn.setHostnameVerifier((hostname, session) -> {
					//Do not verify host names
					return true;
				});
			}
			conn.setRequestMethod("POST");
			conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			//logger.debug("Client Key is " + clientSecret);
			String encoded = Base64.getEncoder().encodeToString((clientKey + ":" + clientSecret).getBytes());
			conn.setRequestProperty("Authorization", "Basic " + encoded);
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setDoOutput(true);
			try (OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), "UTF8")) {
				wr.write("grant_type=client_credentials");
				wr.flush();
			}
			StringBuilder response = new StringBuilder();
			if (conn.getResponseCode() == 200) {
				// Get the response
				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
				}
				JSONObject parser = new JSONObject(response.toString());
				overDriveAPIToken     = parser.getString("access_token");
				overDriveAPITokenType = parser.getString("token_type");
				//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
				overDriveAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
				//logger.debug("OverDrive token is " + overDriveAPIToken);
			} else {
				logger.error("Received error " + conn.getResponseCode() + " connecting to overdrive authentication service");
				// Get any errors
				try (BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
					if (logger.isDebugEnabled()) {
						logger.debug("  Finished reading response\r\n" + response);
					}
				}
				return false;
			}
		} catch (SocketTimeoutException toe) {
			throw toe;
		} catch (Exception e) {
			logger.error("Error connecting to overdrive API", e);
			return false;
		}
		return true;
	}

	private String getProductsKeyForSharedCollection(Long sharedCollectionId, Logger logger) {
		int i = (int) (Math.abs(sharedCollectionId) - 1);
		if (i < accountIds.size()) {
			String accountId = accountIds.get(i);
			return overDriveProductsKeys.get(accountId);
		} else if (logger.isDebugEnabled()) {
			logger.debug("Shared Collection ID '" + sharedCollectionId.toString() + "' doesn't have a matching Overdrive Account Id. Failed to get corresponding Products key.");
		}
		return "";
	}
}