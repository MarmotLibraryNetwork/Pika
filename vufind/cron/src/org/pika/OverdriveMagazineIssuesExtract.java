package org.pika;

import com.sun.mail.imap.Rights;
import com.sun.org.apache.xpath.internal.operations.Bool;
import org.apache.log4j.Logger;
import org.ini4j.Profile;
import org.json.*;

import javax.net.ssl.HttpsURLConnection;
import java.io.BufferedReader;
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
		private final List<String>            accountIds                 = new ArrayList<>();
		private       String                  overDriveAPIToken;
		private       String                  overDriveAPITokenType;
		private       long                    overDriveAPIExpiration;
		private       boolean                 forceMetaDataUpdate;
		private final TreeMap<String, String> overDriveProductsKeys      = new TreeMap<>(); // specifically <AccountId, overDriveProductsKey>
		private final TreeMap<Long, String>   libToOverDriveAPIKeyMap    = new TreeMap<>();
		private final TreeMap<Long, Long>     libToSharedCollectionIdMap = new TreeMap<>(); // specifically <libraryId, sharedCollectionId>
		private final HashMap<String, Long>                advantageCollectionToLibMap = new HashMap<>();
		private final HashMap<String, Long>                existingLanguageIds         = new HashMap<>();
		private final HashMap<String, Long>                existingSubjectIds          = new HashMap<>();
		@Override
		public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
			CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Overdrive Magazine Issues Extract");
			processLog.saveToDatabase(pikaConn, logger);
		try{

			PreparedStatement magazineIdStatement = econtentConn.prepareStatement("SELECT overdriveId, crossRefId from overdrive_api_products WHERE mediaType='magazine'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);

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
				String[] tempsharedAdvantageName       = PikaConfigIni.getIniValue("OverDrive", "sharedAdvantageAccountKey").split(",");
				i = 0;
				for (String sharedAdvantageKey : tempSharedAdvantageAccountKey) {
					long pikaLibraryId = i + 1;
					advantageCollectionToLibMap.put(tempsharedAdvantageName[i++].trim(), pikaLibraryId);
					libToOverDriveAPIKeyMap.put(pikaLibraryId, sharedAdvantageKey);
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

						final long   pikaLibraryId          = advantageCollectionMapRS.getLong(1);
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
			clientSecret        = PikaConfigIni.getIniValue("OverDrive", "clientSecret");
			clientKey           = PikaConfigIni.getIniValue("OverDrive", "clientKey");

			if(clientSecret == null || clientKey == null || clientSecret.length() == 0 || clientKey.length() == 0 || accountIds.isEmpty())
			{
				logger.info("Did not find correct configuration in config.ini, not loading overdrive magazines");
			}else{

				  ResultSet magazines = magazineIdStatement.executeQuery();
				  if(!magazines.wasNull()) {
				  	while (magazines.next()) {

						  if (processSettings.get("fullReindex") != null){
						  	processLog.addNote("Adding/Updating all magazine issues");
						  	getAllMagazineIssuesById(magazines.getString("overdriveId"), magazines.getString("crossRefId"),logger, processLog, econtentConn, pikaConn);
						  }else{
						  	processLog.addNote("Adding New Magazine Issues");
						  	getNewMagazineIssuesById(magazines.getString("overdriveId"), magazines.getString("crossRefId"), logger, processLog, econtentConn, pikaConn);
						  }



				  		processLog.addNote("Processed " + magazines.getString("overdriveId"));

				  	}
				  }else{
				  	logger.info("Zero magazines found in database");
				  }

			}
		} catch (Exception e) {
			e.printStackTrace();
		}
			processLog.incUpdated();
			processLog.saveToDatabase(pikaConn, logger);

	}
	public void getAllMagazineIssuesById(String overdriveId, String crossRefId, Logger logger, CronProcessLogEntry processLog, Connection econtentConn, Connection pikaConn)
	{

		try {
			boolean small = false;
			int x = 0;
			for (Map.Entry<String, String> entry : overDriveProductsKeys.entrySet()) {
				String accountId          = entry.getKey();
				String productKey         = entry.getValue();
				Long   sharedCollectionId = (accountIds.indexOf(accountId) + 1) * -1L;
				libToOverDriveAPIKeyMap.put(sharedCollectionId, productKey);
			}
			while (small == false) {
				int issuesPerQuery = 2000;
				String overDriveUrl = "https://api.overdrive.com/v1/collections/" + getProductsKeyForSharedCollection(-1L, logger) + "/products/" + overdriveId + "/issues?limit=" + issuesPerQuery + "&sort=saledate:asc&offset=" + x;
				WebServiceResponse overdriveCall = callOverDriveURL(overDriveUrl, logger);
				JSONObject jsonObject = overdriveCall.getResponse();
				if (jsonObject.getInt("totalItems") == 0) {
					processLog.addNote("No magazines found for " + overdriveId + " trying advantage");
					for (String advantageCollections : advantageCollectionToLibMap.keySet()) {
						overDriveUrl = "https://api.overdrive.com/v1/collections/" + advantageCollections + "/products/" + overdriveId + "/issues?limit=" + issuesPerQuery + "&sort=saledate:asc&offset=" + x;
						overdriveCall = callOverDriveURL(overDriveUrl, logger);
						jsonObject = overdriveCall.getResponse();

					if(jsonObject.getInt("totalItems") ==0) {
						processLog.incErrors();
						processLog.addNote("No magazines found for " + overdriveId);
						processLog.saveToDatabase(pikaConn, logger);
						break;
					}
				}
		}
			JSONArray products = jsonObject.getJSONArray("products");
			int returnedRecords = jsonObject.getInt("totalItems");
			if (returnedRecords < issuesPerQuery)
			{
				small = true;
			}
			String magazineParentId = overdriveId;
			int updates = 0;
			for(int i = 0; i< products.length(); i++) {
				JSONObject issue = (JSONObject) products.get(i);
				JSONObject images = issue.getJSONObject("images");
				JSONObject image = images.getJSONObject("cover");
				String coverUrl = image.getString("href");
				String magazineIssueId = issue.getString("id");
				String magazineTitle = issue.getString("sortTitle").replace("'", "&apos;");
				String magazineCrossRef = issue.getString("crossRefId");
				String magazineEdition = issue.getString("edition").replace("'", "&apos;");
				JSONObject links = issue.getJSONObject("links");
				JSONObject metadata = links.getJSONObject("metadata");
				String magazineIssueMetadataURL = metadata.getString("href");

				WebServiceResponse metadataCall = callOverDriveURL(magazineIssueMetadataURL, logger);
				JSONObject magazineMetaCall = metadataCall.getResponse();
				String magazineDescription = magazineMetaCall.getString("shortDescription").replace("'", "&apos;");
				JSONArray formatsArray = magazineMetaCall.getJSONArray("formats");
				String pubDateString = null;
				for(int n = 0; n<formatsArray.length(); n++)
				{
					JSONObject formatsObject = formatsArray.getJSONObject(n);
					pubDateString = formatsObject.getString("onSaleDate");
				}

				Date pubDate = new SimpleDateFormat("MM/dd/yyyy").parse(pubDateString);
				Long published = pubDate.getTime()/1000;

				long dateTime = System.currentTimeMillis() / 1000;

				PreparedStatement doesIdExist = econtentConn.prepareStatement("SELECT id from `overdrive_api_magazine_issues` WHERE overdriveId ='" + magazineIssueId + "'");
				ResultSet idResult = doesIdExist.executeQuery();
				if (!idResult.first()) {
					String insert = ("'" + magazineIssueId + "','" + magazineCrossRef + "','" + magazineTitle + "','" + magazineEdition + "','" + published + "','" + coverUrl + "','" + magazineParentId + "','" + magazineDescription + "', " + dateTime + "," + dateTime);
					PreparedStatement updateDatabase = econtentConn.prepareStatement("INSERT INTO `overdrive_api_magazine_issues` " +
									"(overdriveId, crossRefId, title, edition, pubDate, coverUrl, parentId, description, dateAdded, dateUpdated) " +
									"VALUES(" + insert + ")");
					updates = updates +  updateDatabase.executeUpdate();
					logger.info("Added " + updates + "magazine issues to database for magazine id: " + magazineParentId);
				} else {
					PreparedStatement updateDatabase = econtentConn.prepareStatement(
									"UPDATE `overdrive_api_magazine_issues` SET " +
													"crossRefId ='" + magazineCrossRef + "', " +
													"title ='" + magazineTitle + "', " +
													"edition ='" + magazineEdition + "', " +
													"pubDate ='" + published + "', "+
													"coverUrl ='" + coverUrl + "', " +
													"description ='" + magazineDescription + "', " +
													"dateUpdated =" + dateTime +
													" WHERE overdriveId='" + magazineIssueId + "'");
					updates = updates + updateDatabase.executeUpdate();

				}
			}
				x = x+issuesPerQuery;
				processLog.addNote("Added or Updated " + updates + " magazine issues to database for magazine id: " + magazineParentId);
				processLog.addUpdates(updates);
				processLog.incUpdated();
				processLog.saveToDatabase(pikaConn,logger);
			}
		}catch(Exception e)
		{
			e.printStackTrace();

		}
	}

	public void getNewMagazineIssuesById(String overdriveId, String crossRefId, Logger logger, CronProcessLogEntry processLog, Connection econtentConn, Connection pikaConn)
	{
		try {
			boolean small = false;
			int x = 0;
			int issuesPerQuery = 25;
			while (small == false){


				String overDriveUrl = "https://api.overdrive.com/v1/collections/"+ overDriveProductsKeys.firstEntry().getValue() + "/products/" + overdriveId + "/issues?limit="+ issuesPerQuery +"&offset="+x;
				WebServiceResponse overdriveCall = callOverDriveURL(overDriveUrl, logger);
				JSONObject jsonObject = overdriveCall.getResponse();
				if (jsonObject.getInt("totalItems") == 0) {
					processLog.addNote("No magazines found for " + overdriveId + " trying advantage");
					for (String advantageCollections : advantageCollectionToLibMap.keySet()) {
						overDriveUrl = "https://api.overdrive.com/v1/collections/" + advantageCollections + "/products/" + overdriveId + "/issues?limit=" + issuesPerQuery + "&sort=saledate:asc&offset=" + x;
						overdriveCall = callOverDriveURL(overDriveUrl, logger);
						jsonObject = overdriveCall.getResponse();

						if(jsonObject.getInt("totalItems") ==0) {
							processLog.incErrors();
							processLog.addNote("No magazines found for " + overdriveId);
							processLog.saveToDatabase(pikaConn, logger);
							break;
						}
					}
				}
				JSONArray products = jsonObject.getJSONArray("products");
				int returnedRecords = jsonObject.getInt("totalItems");
				if (returnedRecords < issuesPerQuery)
				{
					small = true;
				}
				String magazineParentId = overdriveId;
				int updates = 0;
				for(int i = 0; i< products.length(); i++) {
					JSONObject issue = (JSONObject) products.get(i);
					JSONObject images = issue.getJSONObject("images");
					JSONObject image = images.getJSONObject("cover");
					String coverUrl = image.getString("href");
					String magazineIssueId = issue.getString("id");
					String magazineTitle = issue.getString("sortTitle").replace("'", "&apos;");
					String magazineCrossRef = issue.getString("crossRefId");
					String magazineEdition = issue.getString("edition").replace("'", "&apos;");
					JSONObject links = issue.getJSONObject("links");
					JSONObject metadata = links.getJSONObject("metadata");
					String magazineIssueMetadataURL = metadata.getString("href");

					WebServiceResponse metadataCall = callOverDriveURL(magazineIssueMetadataURL, logger);
					JSONObject magazineMetaCall = metadataCall.getResponse();
					String magazineDescription = magazineMetaCall.getString("shortDescription").replace("'", "&apos;");
					JSONArray formatsArray = magazineMetaCall.getJSONArray("formats");
					String pubDateString = null;
					for(int n = 0; n<formatsArray.length(); n++)
					{
						JSONObject formatsObject = formatsArray.getJSONObject(n);
						pubDateString = formatsObject.getString("onSaleDate");
					}

					Date pubDate = new SimpleDateFormat("MM/dd/yyyy").parse(pubDateString);
					Long published = pubDate.getTime()/1000;

					long dateTime = System.currentTimeMillis() / 1000;

					PreparedStatement doesIdExist = econtentConn.prepareStatement("SELECT id from `overdrive_api_magazine_issues` WHERE overdriveId ='" + magazineIssueId + "'");
					ResultSet idResult = doesIdExist.executeQuery();
					if (!idResult.first()) {
						String insert = ("'" + magazineIssueId + "','" + magazineCrossRef + "','" + magazineTitle + "','" + magazineEdition + "', '"+ published +"','" + coverUrl + "','" + magazineParentId + "','" + magazineDescription + "', " + dateTime + "," + dateTime);
						PreparedStatement updateDatabase = econtentConn.prepareStatement("INSERT INTO `overdrive_api_magazine_issues` " +
										"(overdriveId, crossRefId, title, edition, pubDate, coverUrl, parentId, description, dateAdded, dateUpdated) " +
										"VALUES(" + insert + ")");
						updates = updates + updateDatabase.executeUpdate();

					}

			}
				processLog.addNote("Added " + updates + " magazine issues to database for magazine id: " + magazineParentId);
				processLog.addUpdates(updates);
				processLog.incUpdated();
				processLog.saveToDatabase(pikaConn, logger);
				x = x+issuesPerQuery;
			}
		}catch(Exception e)
		{
			e.printStackTrace();

		}
	}

		private WebServiceResponse callOverDriveURL(String overdriveUrl, Logger logger) throws SocketTimeoutException {
			WebServiceResponse webServiceResponse = new WebServiceResponse();
			if (connectToOverDriveAPI(false, logger)) {
				//Connect to the API to get our token
				HttpURLConnection conn;
				StringBuilder     response = new StringBuilder();
				boolean hadTimeoutsFromOverDrive;
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
				String accountId   = accountIds.get(i);
				return overDriveProductsKeys.get(accountId);
			} else if (logger.isDebugEnabled()) {
				logger.debug("Shared Collection ID '" + sharedCollectionId.toString() + "' doesn't have a matching Overdrive Account Id. Failed to get corresponding Products key.");
			}
			return "";
		}
 }
