package org.pika;

import org.apache.log4j.Logger;
import org.ini4j.Profile.Section;

import java.net.MalformedURLException;
import java.net.URL;
import org.json.*;
import java.sql.Connection;
import java.util.Scanner;

public class NYTList implements IProcessHandler {
	private PikaSystemVariables systemVariables;

	@Override
	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		CronProcessLogEntry processEntry = new CronProcessLogEntry(cronEntry.getLogEntryId(), "NYT Updates");
		processEntry.saveToDatabase(pikaConn, logger);
		this.systemVariables = systemVariables;
		try {
			final Boolean fullReindexRunning = systemVariables.getBooleanValuedVariable("full_reindex_running");
			if (fullReindexRunning != null && !fullReindexRunning) {
				String url = PikaConfigIni.getIniValue("Index", "url") + "/admin/cores?wt=json";

				if (isSolrRunning(url, logger, processEntry)) {
					addNYTItemsToList(PikaConfigIni.getIniValue("Site", "url"), logger, processEntry, pikaConn);
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
				// Now check that index size is close to the expected size we have in solr_grouped_minimum_number_records
				// If not, that indicates an incomplete index that will likely not have all the NY Time titles we want in it.
				String documentCount = groupedObject.getJSONObject("index").get("numDocs").toString();
				long documentsInIndex = Long.parseLong(documentCount);
				Long indexCountLevel  = systemVariables.getLongValuedVariable("solr_grouped_minimum_number_records");
				if (indexCountLevel == null){
					logger.error("No system variable 'solr_grouped_minimum_number_records' found.");
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
			logger.error("Cannot reach Solr server or server down");
			processEntry.incErrors();
		}
		return false;
	}

	public void addNYTItemsToList(String pikaSiteURL, Logger logger, CronProcessLogEntry processEntry, Connection pikaConn ) throws MalformedURLException {
		String url         = pikaSiteURL + "/API/ListAPI?method=getAvailableListsFromNYT";
		URL    apiLocation = new URL(url);
		try {
			StringBuilder str;
			try (Scanner scan = new Scanner(apiLocation.openStream())) {
				str = new StringBuilder();
				while (scan.hasNext()) {
					str.append(scan.nextLine());
				}
			}
			JSONObject obj     = new JSONObject(str.toString());
			JSONObject result  = obj.getJSONObject("result");
			JSONArray  results = result.getJSONArray("results");
			for (int i = 0; i < results.length(); i++) {
				JSONObject    newResult         = (JSONObject) results.get(i);
				String        encoded_list_name = newResult.get("list_name_encoded").toString();
				String        updateUrl         = pikaSiteURL + "/API/ListAPI?method=createUserListFromNYT&listToUpdate=" + encoded_list_name;
				URL           updateLocation    = new URL(updateUrl);
				try (Scanner updateScan = new Scanner(updateLocation.openStream())) {
					StringBuilder updateStr = new StringBuilder();
					while (updateScan.hasNext()) {
						updateStr.append(updateScan.nextLine());
					}
					JSONObject updateStatus = new JSONObject(updateStr.toString());
					JSONObject resultJSON   = updateStatus.getJSONObject("result");
					if (resultJSON.getBoolean("success")) {
						processEntry.addNote("Updated List: " + encoded_list_name);
					} else {
						processEntry.addNote("Could not update list: " + encoded_list_name);
					}
					processEntry.saveToDatabase(pikaConn, logger);
				} catch (Exception e){
					logger.error("Error trying to update NY Times list " + encoded_list_name, e);
					// Caught exception, now try to build other lists
				}
			}
		} catch (Exception e) {
			logger.error("Cannot reach Solr server or server down");
		}
	}
}

class NYTListOptions
{
	String list_name;
	String display_name;
	String list_name_encoded;
	String oldest_published_date;
	String newest_published_date;
}
