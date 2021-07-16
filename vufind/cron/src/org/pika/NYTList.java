package org.pika;

import com.sun.tools.internal.xjc.model.CNonElement;
import org.apache.log4j.Logger;
import org.ini4j.Profile.Section;
import org.w3c.dom.*;

import java.net.MalformedURLException;
import java.net.URL;
import org.json.*;
import java.sql.Connection;
import java.util.Scanner;

public class NYTList implements IProcessHandler {

	@Override
	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
			CronProcessLogEntry processEntry = new CronProcessLogEntry(cronEntry.getLogEntryId(), "NYT Updates");
			processEntry.saveToDatabase(pikaConn, logger);
		try{
			if(!systemVariables.getBooleanValuedVariable("full_reindex_running"))
			{
				  String url = "http://" + serverName + ":8080/solr/admin/cores?wt=json";

					if(isSolrRunning(url, logger, processEntry))
					{
						 addNYTItemsToList("https://" + serverName, logger, processEntry, pikaConn);
					}
					else{
						logger.error("Solr Down");
					}
			}
			else{
				logger.error("Full Reindex Running");
			}
		}
		catch(Exception e )
		{
				logger.error(e);
		}
	}

	public boolean isSolrRunning(String url, Logger logger, CronProcessLogEntry processEntry) throws MalformedURLException {

			URL solrLocation = new URL(url);
		try {
			Scanner scan = new Scanner(solrLocation.openStream());
			String str = new String();
			while (scan.hasNext())
				str += scan.nextLine();
			scan.close();

			JSONObject obj = new JSONObject(str);
		  JSONObject statusObject = obj.getJSONObject("status");
			JSONObject groupedObject = statusObject.getJSONObject("grouped");
			Integer uptime = Integer.parseInt(groupedObject.get("uptime").toString());
			if (uptime > 0 && uptime < 999000000)
			{
				return true;
			}
		} catch (Exception e) {
			logger.error("Cannot reach Solr server or server down");
			processEntry.incErrors();
		}
		return false;
	}

	public void addNYTItemsToList(String serverName, Logger logger, CronProcessLogEntry processEntry, Connection pikaConn ) throws MalformedURLException {
		String url = serverName + "/API/ListAPI?method=getAvailableListsFromNYT";
		URL apiLocation = new URL(url);
		try{
			Scanner scan = new Scanner(apiLocation.openStream());
			String str = new String();
			while (scan.hasNext())
			  str += scan.nextLine();
			scan.close();
			JSONObject obj = new JSONObject(str);
			JSONObject result = obj.getJSONObject("result");
			JSONArray results = new JSONArray();
			results = result.getJSONArray("results");
			for(int i = 0; i < results.length(); i++)
			{
				JSONObject newresult = (JSONObject) results.get(i);

				String encoded_list_name = newresult.get("list_name_encoded").toString();
				String updateUrl = serverName + "/API/ListAPI?method=createUserListFromNYT&listToUpdate=" + encoded_list_name;
				URL updateLocation = new URL(updateUrl);
				Scanner updateScan = new Scanner(updateLocation.openStream());
				String updateStr = "";
				while (updateScan.hasNext()) {
					updateStr += updateScan.nextLine();
				}
				JSONObject updateStatus = new JSONObject(updateStr);

				JSONObject resultJSON =	updateStatus.getJSONObject("result");
				if(resultJSON.getBoolean("success"))
				{
						processEntry.addNote("Updated List: " + encoded_list_name);
						processEntry.saveToDatabase(pikaConn, logger);

				}
				else {
					processEntry.addNote("Could not update list: " + encoded_list_name);
				}
				updateScan.close();
			}
		}catch(Exception e)
		{
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
