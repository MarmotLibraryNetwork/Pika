package org.pika;

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
		try{
			if(!systemVariables.getBooleanValuedVariable("full_reindex_running"))
			{
				  String url = serverName + ":8080/solr/admin/cores?wt=json";
					if(isSolrRunning(url))
					{
							addNYTItemsToList("https://" + serverName);
					}
					else{
						System.out.println("Solr Down");
					}
			}
			else{
				System.out.print("Full Reindex Running");
			}
		}
		catch(Exception e )
		{
				e.printStackTrace();
		}
	}

	public boolean isSolrRunning(String url) throws MalformedURLException {

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
			if (uptime > 0 && uptime < 99000000)
			{
				return true;
			}
		} catch (Exception e) {
			System.out.println("Cannot reach Solr server or server down");
		}
		return false;
	}

	public void addNYTItemsToList(String serverName) throws MalformedURLException {
		System.out.print("You have arrived");

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
				String updateStr = new String();
				while (updateScan.hasNext())
					 updateStr += updateScan.nextLine();
				updateScan.close();

			}




		}catch(Exception e)
		{
			e.printStackTrace();
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
