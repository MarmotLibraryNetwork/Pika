package org.pika;

import org.apache.logging.log4j.Logger;
import org.ini4j.Profile.Section;

import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import org.json.*;
import java.sql.Connection;
import java.util.ArrayList;
import java.util.Scanner;

public class NYTList implements IProcessHandler {
	// The NY Times API allows 5 requests per minute, so wait between calls that reach it.
	private static final long NYT_API_CALL_DELAY = 14000L;
	// The delay above is not always enough, so back off for longer again before asking for the same list a second or
	// third time.  Each retry waits this much longer than the one before it.
	private static final long NYT_API_RETRY_DELAY = 60000L;
	// How many times to ask for a single list before giving up on it and counting a process error.
	private static final int NYT_API_MAX_ATTEMPTS = 3;
	// The rate limit fault the NY Times sends back, as it reaches us through the Pika List API.  The error code is only
	// present when the NY Times sends the detail along with it, so fall back to the text of the fault itself.
	private static final String NYT_API_QUOTA_ERROR_CODE = "policies.ratelimit.QuotaViolation";
	private static final String NYT_API_QUOTA_ERROR_TEXT = "Rate limit quota violation";

	private PikaSystemVariables systemVariables;
	private String userAgent;

	@Override
	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		CronProcessLogEntry processEntry = new CronProcessLogEntry(cronEntry.getLogEntryId(), "NYT Updates");
		processEntry.saveToDatabase(pikaConn, logger);
		this.systemVariables = systemVariables;
		try {
			final Boolean fullReindexRunning = systemVariables.getBooleanValuedVariable("full_reindex_running");
			if (fullReindexRunning != null && !fullReindexRunning) {
				userAgent = PikaConfigIni.getIniValue("Site", "internalUserAgent");
				if (userAgent == null || userAgent.isEmpty()) {
					logger.warn( "No internal user agent set in config.ini. Proxy may interfere with these calls.  Using default user agent.");
					userAgent = "Pika";
				}
				String url = PikaConfigIni.getIniValue("Index", "url") + "/admin/cores?wt=json";
				if (isSolrRunning(url, logger, processEntry)) {
					updateAllNYTLists(PikaConfigIni.getIniValue("Site", "url"), logger, processEntry, pikaConn);
				} else {
					final String message = "Solr Down or index incomplete; Not Updating NY Times User Lists";
					logger.error(message);
					processEntry.addNote(message);
				}
			} else {
				final String message = "Full Reindex Running; Not Updating NY Times User Lists";
				logger.error(message);
				processEntry.addNote(message);
			}
		} catch (Exception e) {
			logger.error(e);
			processEntry.incErrors();
		}
		processEntry.setFinished();
		processEntry.saveToDatabase(pikaConn, logger);
	}

	public boolean isSolrRunning(String url, Logger logger, CronProcessLogEntry processEntry) throws MalformedURLException {

			URL solrLocation = new URL(url);
		try {
			StringBuilder str;
			try (Scanner scan = new Scanner(solrLocation.openStream())) {
				str = new StringBuilder();
				while (scan.hasNext())
					str.append(scan.nextLine());
			}

			JSONObject obj           = new JSONObject(str.toString());
		  JSONObject statusObject  = obj.getJSONObject("status");
			JSONObject groupedObject = statusObject.getJSONObject("grouped");
			int        uptime        = Integer.parseInt(groupedObject.get("uptime").toString());
			if (uptime > 0 && uptime < 999000000) {
				// Now check that the index size is close to the expected size we have in solr_grouped_minimum_number_records
				// If not, that indicates an incomplete index that will likely not have all the NY Time titles we want in it.
				String documentCount = groupedObject.getJSONObject("index").get("numDocs").toString();
				long documentsInIndex = Long.parseLong(documentCount);
				Long indexCountLevel  = systemVariables.getLongValuedVariable("solr_grouped_minimum_number_records");
				if (indexCountLevel == null){
					String message = "No system variable 'solr_grouped_minimum_number_records' found.";
					logger.error(message);
					processEntry.addNote(message);
					processEntry.incErrors();
				} else if ((indexCountLevel - documentsInIndex) > 10000) {
					final String message = "Index document count is more than 10,000 below solr_grouped_minimum_number_records : " + documentsInIndex;
					logger.error(message);
					processEntry.addNote(message);
					processEntry.incErrors();
				} else {
					return true;
				}
			}
		} catch (Exception e) {
			String message = "Cannot reach Solr server or server down";
			logger.error(message, e);
			processEntry.addNote(message);
			processEntry.incErrors();
		}
		return false;
	}

	/**
	 * Builds the NY Times lists one at a time, asking the Pika List API for the list of lists and then for each list in
	 * turn.  updateAllNYTLists() does the same work with a single call to the NY Times, and is what doCronProcess()
	 * calls; this is kept so that we can go back to building the lists one at a time if the NY Times API changes and
	 * the single call no longer returns everything each list needs.
	 */
	public void addNYTItemsToList(String pikaSiteURL, Logger logger, CronProcessLogEntry processEntry, Connection pikaConn ) throws MalformedURLException {
		// The NY Times API has call limits. This is taken from: https://developer.nytimes.com/faq#a11
		// there are two rate limits per API: 500 requests per day and 5 requests per minute.
		// You should sleep 12 seconds between calls to avoid hitting the per minute rate limit. If you need a higher rate limit, please contact us at code@nytimes.com.
		String        url         = pikaSiteURL + "/API/ListAPI?method=getAvailableListsFromNYT";
		URL           apiLocation = new URL(url);
		StringBuilder str         = new StringBuilder();
		try {
			HttpURLConnection conn = (HttpURLConnection) apiLocation.openConnection();
			conn.setRequestProperty("User-Agent", userAgent);
			try (Scanner scan = new Scanner(conn.getInputStream())) {
				while (scan.hasNext()) {
					str.append(scan.nextLine());
				}
			}
			JSONObject obj    = new JSONObject(stripPHPNoticeFromJSONResponse(str, logger));
			JSONObject result = obj.getJSONObject("result");
			// A successful response is the New York Times overview itself, which carries no success flag, so there is
			// only one to check when the List API has a problem to report, e.g., a rate limit violation.
			if (!result.optBoolean("success", true)) {
				final String message = "Could not get the list of NY Times lists : " + result.optString("message", "Unknown error");
				logger.error(message);
				processEntry.addNote(message);
				processEntry.incErrors();
				return;
			}
			logger.info("Got NY Times list of lists: {}", result);
			JSONArray results = result.getJSONObject("results").getJSONArray("lists");
			for (int i = 0; i < results.length(); i++) {
				JSONObject newResult         = (JSONObject) results.get(i);
				String     encoded_list_name = newResult.get("list_name_encoded").toString();
				if (!updateOneNYTList(pikaSiteURL, encoded_list_name, logger, processEntry, pikaConn)) {
					// The wait between lists was interrupted, so stop rather than run the rest of them back to back
					break;
				}
			}
		} catch (Exception e) {
			logger.error("Cannot reach Pika server or server down", e);
			logger.error("Pika Response: {}", str);
			processEntry.incErrors();
		}
	}

	/**
	 * Asks the Pika List API to rebuild every NY Times list, then gives any list it could not build a second chance on
	 * its own.
	 *
	 * The List API builds all of the lists from a single call to the NY Times, so a whole run costs one NY Times
	 * request instead of one per list and stays well inside their rate limit.
	 *
	 * @param pikaSiteURL  the Pika site to ask
	 * @param logger       where to log
	 * @param processEntry the cron process entry to note the outcome of each list on
	 * @param pikaConn     connection used to save the process entry as the lists are built
	 */
	public void updateAllNYTLists(String pikaSiteURL, Logger logger, CronProcessLogEntry processEntry, Connection pikaConn) throws MalformedURLException {
		String        url         = pikaSiteURL + "/API/ListAPI?method=updateAllUserListsFromNYT";
		URL           apiLocation = new URL(url);
		StringBuilder str         = new StringBuilder();
		try {
			HttpURLConnection conn = (HttpURLConnection) apiLocation.openConnection();
			conn.setRequestProperty("User-Agent", userAgent);
			try (Scanner scan = new Scanner(conn.getInputStream())) {
				while (scan.hasNext()) {
					str.append(scan.nextLine());
				}
			}
			JSONObject obj    = new JSONObject(stripPHPNoticeFromJSONResponse(str, logger));
			JSONObject result = obj.getJSONObject("result");
			logger.debug("NY Times List Update Status: {}", result);
			if (!result.optBoolean("success", false)) {
				final String message = "Could not update the NY Times lists : " + result.optString("message", "Unknown error");
				logger.error(message);
				processEntry.addNote(message);
				processEntry.incErrors();
				return;
			}

			JSONArray lists = result.optJSONArray("lists");
			if (lists == null || lists.length() == 0) {
				final String message = "The List API did not report on any NY Times lists";
				logger.error(message);
				processEntry.addNote(message);
				processEntry.incErrors();
				return;
			}

			// A list that could not be built from the single call is worth asking for on its own, in case the trouble
			// was with that one list rather than with the call as a whole.
			ArrayList<String> listsToRetry = new ArrayList<>();
			for (int i = 0; i < lists.length(); i++) {
				JSONObject listResult = lists.getJSONObject(i);
				String     listName   = listResult.optString("listName", "");
				if (listResult.optBoolean("success", false)) {
					processEntry.addNote("Updated List: " + listName + " Added " + listResult.optInt("titlesAdded") + " Titles to the list");
					processEntry.incUpdated();
				} else {
					String message = listResult.optString("message", "Unknown error");
					logger.warn("Could not update NY Times list {} : {}", listName, message);
					if (listName.isEmpty()) {
						// With no name to ask for there is nothing to retry
						processEntry.addNote("Could not update list: " + message);
						processEntry.incErrors();
					} else {
						listsToRetry.add(listName);
					}
				}
			}
			processEntry.saveToDatabase(pikaConn, logger);

			for (String listName : listsToRetry) {
				processEntry.addNote("Asking for list on its own: " + listName);
				if (!updateOneNYTList(pikaSiteURL, listName, logger, processEntry, pikaConn)) {
					// The wait before the retry was interrupted, so stop rather than run the rest of them back to back
					break;
				}
			}
		} catch (Exception e) {
			logger.error("Cannot reach Pika server or server down", e);
			logger.error("Pika Response: {}", str);
			processEntry.incErrors();
		}
	}

	/**
	 * Asks the Pika List API to build a single NY Times list, waiting first so that the call stays under the NY Times
	 * rate limit, and asking again after a longer wait when they turn us away for asking too often.
	 *
	 * @param pikaSiteURL       the Pika site to ask
	 * @param encoded_list_name the list to build, as list_name_encoded
	 * @param logger            where to log
	 * @param processEntry      the cron process entry to note the outcome on
	 * @param pikaConn          connection used to save the process entry
	 * @return whether to carry on with any further lists; false when the wait was interrupted
	 */
	private boolean updateOneNYTList(String pikaSiteURL, String encoded_list_name, Logger logger, CronProcessLogEntry processEntry, Connection pikaConn) throws MalformedURLException {
		String updateUrl = pikaSiteURL + "/API/ListAPI?method=createUserListFromNYT&listToUpdate=" + encoded_list_name;

		int     attempt       = 0;
		boolean quotaExceeded = false;
		do {
			// This call to the Pika List API triggers a call to the NY Times API, so pause before it to stay under the
			// per minute rate limit.  A retry waits longer again, to give the rate limit the time it needs to recover.
			long delay = attempt == 0 ? NYT_API_CALL_DELAY : attempt * NYT_API_RETRY_DELAY;
			try {
				Thread.sleep(delay);
			} catch (InterruptedException e) {
				logger.warn("Sleep was interrupted while waiting to update NY Times list {}.", encoded_list_name);
				Thread.currentThread().interrupt();
				return false;
			}
			attempt++;
			quotaExceeded = false;

			try {
				URL               updateLocation = new URL(updateUrl);
				HttpURLConnection updateConn     = (HttpURLConnection) updateLocation.openConnection();
				updateConn.setRequestProperty("User-Agent", userAgent);

				StringBuilder updateStr = new StringBuilder();
				try (Scanner updateScan = new Scanner(updateConn.getInputStream())) {
					while (updateScan.hasNext()) {
						updateStr.append(updateScan.nextLine());
					}
				}
				String     resultStr    = stripPHPNoticeFromJSONResponse(updateStr, logger);
				JSONObject updateStatus = new JSONObject(resultStr);
				JSONObject resultJSON   = updateStatus.getJSONObject("result");
				logger.debug("NY Times List Update Status: {}", resultJSON);
				if (resultJSON.getBoolean("success")) {
					String message = resultJSON.getString("message").split("<br>")[1];
					processEntry.addNote("Updated List: " + encoded_list_name + " " + message);
					processEntry.incUpdated();
				} else {
					String message = resultJSON.optString("message", "Unknown error");
					// The rate limit is worth waiting out; any other failure will come back just the same.
					if (isQuotaViolation(message) && attempt < NYT_API_MAX_ATTEMPTS) {
						quotaExceeded = true;
						logger.warn("Rate limit reached updating NY Times list {} on attempt {} of {}, will try again", encoded_list_name, attempt, NYT_API_MAX_ATTEMPTS);
					} else {
						processEntry.addNote("Could not update list: " + encoded_list_name + " " + message);
						logger.error("Could not update list: {} {}",  encoded_list_name, resultJSON);
						logger.debug("Request URL was: {}", updateUrl);
						processEntry.incErrors();
					}
				}
				processEntry.saveToDatabase(pikaConn, logger);
			} catch (Exception e){
				logger.error("Error trying to update NY Times list {}", encoded_list_name, e);
				processEntry.incErrors();
				// Caught exception, now try to build other lists
			}
		} while (quotaExceeded);
		return true;
	}

	/**
	 * Was this failure the NY Times turning us away for asking too often, rather than a problem with the list itself?
	 *
	 * @param message the message the Pika List API sent back with the failure
	 * @return whether the message reports a rate limit violation
	 */
	private static boolean isQuotaViolation(String message) {
		return message != null && (message.contains(NYT_API_QUOTA_ERROR_CODE) || message.contains(NYT_API_QUOTA_ERROR_TEXT));
	}

	public String stripPHPNoticeFromJSONResponse(StringBuilder updateStr, Logger logger){
		String resultStr = updateStr.toString();
		if (!resultStr.isEmpty() && updateStr.charAt(0) != '{'){
			String[] split     = resultStr.split("\\{", 2);
			if (split.length < 2) {
				logger.info("Response did not begin with { and did not contain { : '{}'", resultStr);
			} else {
				String phpNotice = split[0];
				resultStr = "{" + split[1];
				logger.info("PHP notice from API call: {}", phpNotice);
			}
		}
		return resultStr;
	}
}

