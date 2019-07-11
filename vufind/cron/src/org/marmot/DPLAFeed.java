package org.marmot;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.json.JSONException;
import org.json.JSONObject;
import org.json.JSONArray;
import org.vufind.CronLogEntry;
import org.vufind.CronProcessLogEntry;
import org.vufind.IProcessHandler;
import org.vufind.Util;

import java.io.BufferedWriter;
import java.io.FileWriter;
import java.io.IOException;
import java.io.InputStream;
import java.net.MalformedURLException;
import java.sql.Connection;
import java.net.URL;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   5/15/2019
 */
public class DPLAFeed implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private String              pikaUrl;
	private Logger              logger;

	@Override
	public void doCronProcess(String serverName, Ini configIni, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger) {
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "DPLA Feed");
		processLog.saveToDatabase(pikaConn, logger);

		this.logger = logger;
		logger.info("Building DPLA Feed File");
		processLog.addNote("Building DPLA Feed File");

		pikaUrl = configIni.get("Site", "url");
		if (pikaUrl == null || pikaUrl.length() == 0) {
			logger.error("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			processLog.incErrors();
			processLog.addNote("Unable to get URL for Pika in ConfigIni settings.  Please add a url key to the Site section.");
			return;
		}
		boolean fatal            = false;
		String  DPLAFeedFilePath = configIni.get("Site", "local");
		try (FileWriter fileWriter = new FileWriter(DPLAFeedFilePath + "/dplaFeed.json");
			 BufferedWriter bufferedWriter = new BufferedWriter(fileWriter)) {
			String DLPAFeedUrlString = pikaUrl + "/API/ArchiveAPI?method=getDPLAFeed";
			int    numPages          = 0;
			int    currentPage       = 1;
			int    pageSize          = 100;
			if (processSettings.get("pageSize") != null) {
				pageSize = Integer.parseInt(processSettings.get("pageSize"));
			}
			if (pageSize != 0 && pageSize != 100) {
				// pageSize 0 is a bad value and 100 is the default value
				DLPAFeedUrlString += "&pageSize=" + pageSize;
			}

			boolean tryAgain = false;
			int     tries    = 0;
			do {
				if (tryAgain && tries < 3) {
					tryAgain = false;
					currentPage--;
					tries++;
				} else if (tryAgain && tries == 3) {
					processLog.incErrors();
					tryAgain = false;
					continue;
				} else {
					tries = 0;
				}
				try {
					String urlStringThisRound = DLPAFeedUrlString + (currentPage > 1 ? "&page=" + currentPage : "");
					URL    DPLAFeedUrl        = new URL(urlStringThisRound);
					Object dplaFeedRaw = DPLAFeedUrl.getContent();
					if (dplaFeedRaw instanceof InputStream) {
						String jsonData = "";
						jsonData = Util.convertStreamToString((InputStream) dplaFeedRaw);
						if (jsonData != null && jsonData.length() > 0) {
							logger.debug("Fetched page " + currentPage + " of " + numPages);
							try {
								JSONObject dplaFeedData = new JSONObject(jsonData);
								JSONObject result       = dplaFeedData.getJSONObject("result");
								if (numPages == 0 && result.has("numPages")) {
									numPages = result.getInt("numPages");
								}
								if (result.has("docs")) {
									String docs = null;
									try {
										docs = result.getString("docs");
										StringBuilder pageOfEntries = new StringBuilder(docs);
										pageOfEntries.deleteCharAt(0) // remove the beginning [
												.deleteCharAt(pageOfEntries.length() - 1); // remove the ending ]
										if (currentPage == 1) {
											pageOfEntries.insert(0, '[');
										}
										if (currentPage != numPages) {
											pageOfEntries.append(','); // add a comma between json object to concatenate the list
										} else {
											pageOfEntries.append(']'); // add closing ] at end of content
										}

										bufferedWriter.write(pageOfEntries.toString());
										bufferedWriter.newLine();
									} catch (JSONException e) {
										logger.error("Error retrieving feed entries", e);
										tryAgain = true;
									}
								} else {
									logger.error("DPLA Feed Call did not return any archive objects : " + DPLAFeedUrl + " response : " + jsonData);
									tryAgain = true;
								}
							} catch (JSONException e) {
								logger.error("DPLA Feed JSON Error for call : " + DPLAFeedUrl, e);
								tryAgain = true;
							}
						} else {
							logger.error("DPLA Feed Call had an empty json response : " + DPLAFeedUrl + " response : " + jsonData);
							tryAgain = true;
						}
					} else {
						logger.error("DPLA Feed Call was not an InputStream " + DPLAFeedUrl);
						tryAgain = true;
					}
				} catch (MalformedURLException e) {
					logger.error("Bad URL error : ", e);
					fatal = true;
				} catch (IOException e) {
					logger.error("DPLA Feed IO Exception error.", e);
//					fatal = true;
				} catch (Exception e) {
					logger.error("Unknown error.", e);
					fatal = true;
				}
			} while (!fatal && currentPage++ < numPages || tryAgain); // tryAgain check is for problems in very first call or very last call
		} catch (IOException e) {
			logger.error("Error with writing file", e);
		}
		if (fatal) {
			processLog.incErrors();
			//TODO: rework file to be valid JSON even with an error
		}

		logger.info("Finished building DPLA Feed File");
		processLog.addNote("Finished building DPLA Feed File");
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

}
