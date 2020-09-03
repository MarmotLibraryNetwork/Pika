/*
 * Copyright (C) 2020  Marmot Library Network
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

import au.com.bytecode.opencsv.CSVReader;
import org.apache.log4j.Logger;

import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.math.BigInteger;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.HashMap;
import java.util.HashSet;

/**
 * Superclass for all Grouped Works which have different normalization rules.
 *
 * Pika
 * User: Mark Noble
 * Date: 1/26/2015
 * Time: 8:57 AM
 */
public abstract class GroupedWorkBase {
	private static Logger logger = Logger.getLogger(GroupedWorkBase.class);

	//The id of the work within the database.
	String permanentId;

	String fullTitle          = "";  // Up to 400 chars
	String originalAuthorName = "";
	String groupingCategory   = "";  // Up to 5 chars
	protected        String author           = "";   // Up to 100 chars
	protected        String uniqueIdentifier = null; // Used with records that should not be grouped
	protected static int    version          = 0;    // The grouped work version number

	//Load authorities
	private static boolean authoritiesLoaded = false;
	private static HashMap<String, String> authorAuthorities = new HashMap<>();
	private static HashMap<String, String> titleAuthorities  = new HashMap<>();

	GroupedWorkBase(){
		if (!authoritiesLoaded){
			loadAuthorities();
		}
	}

	GroupedWorkBase(Connection pikaConn){
		if (!authoritiesLoaded){
			loadAuthorities(pikaConn);
		}
	}

	String getPermanentId() {
		if (this.permanentId == null) {
			StringBuilder permanentId;
			try {
				MessageDigest idGenerator = MessageDigest.getInstance("MD5");
				String        fullTitle   = getAuthoritativeTitle();
				if (fullTitle.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(fullTitle.getBytes());
				}

				String author = getAuthoritativeAuthor();
				if (author.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(author.getBytes());
				}
				if (groupingCategory.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(groupingCategory.getBytes());
				}
				if (uniqueIdentifier != null) {
					// This will cause records marked for not grouping to have their own grouped work Id
					idGenerator.update(uniqueIdentifier.getBytes());
				}
				permanentId = new StringBuilder(new BigInteger(1, idGenerator.digest()).toString(16));
				while (permanentId.length() < 32) {
					permanentId.insert(0, "0");
				}
				//Insert -'s for formatting
				this.permanentId = permanentId.substring(0, 8) + "-" + permanentId.substring(8, 12) + "-" + permanentId.substring(12, 16) + "-" + permanentId.substring(16, 20) + "-" + permanentId.substring(20);
			} catch (NoSuchAlgorithmException e) {
				System.out.println("Error generating permanent id" + e.toString());
			}
		}
		return this.permanentId;
	}

	abstract String getTitle();

	private String authoritativeTitle;

	String getAuthoritativeTitle() {
		if (authoritativeTitle == null) {
			if (titleAuthorities.containsKey(fullTitle)) {
				authoritativeTitle = titleAuthorities.get(fullTitle);
				fullTitle = authoritativeTitle; // We want to see the authoritative title saved in the db as the grouping title so that this process isn't invisible
//				if (logger.isDebugEnabled()){
//					logger.debug("Using authoritative title '" + authoritativeTitle + "' for normalized title '" + fullTitle + "'");
//				}
			} else {
				authoritativeTitle = fullTitle;
			}
		}
		return authoritativeTitle;
	}

	abstract void setTitle(String title, String subtitle, int numNonFilingCharacters);

	void setTitle(String title, String subtitle){
		setTitle(title, subtitle, 0);
	};

	abstract String getAuthor();

	private String authoritativeAuthor = null;

	String getAuthoritativeAuthor() {
		if (authoritativeAuthor == null) {
			if (authorAuthorities.containsKey(author)) {
				authoritativeAuthor = authorAuthorities.get(author);
				author = authoritativeAuthor; // We want to see the authoritative author saved in the db as the grouping author so that this process isn't invisible
				if (logger.isDebugEnabled()){
					logger.debug("Using authoritative author '" + authoritativeAuthor + "' for normalized author '" + author + "'");
				}
			} else {
				authoritativeAuthor = author;
			}
		}
		return authoritativeAuthor;
	}

	abstract void setAuthor(String author);

	abstract void overridePermanentId(String groupedWorkPermanentId);

	abstract void setGroupingCategory(String groupingCategory, RecordIdentifier identifier);

	abstract String getGroupingCategory();

	protected static void loadAuthorities(Connection pikaConn) {
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT sourceGroupingAuthor, preferredGroupingAuthor FROM grouping_authors_preferred", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet resultSet=preparedStatement.executeQuery()
		){
			while (resultSet.next()) {
				authorAuthorities.put(resultSet.getString("sourceGroupingAuthor"), resultSet.getString("preferredGroupingAuthor"));
			}
		} catch (Exception e) {
			logger.error("Error loading preferred grouping authors", e);
		}
		try (
				PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT sourceGroupingTitle, preferredGroupingTitle FROM grouping_titles_preferred", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet resultSet=preparedStatement.executeQuery()
		){
			while (resultSet.next()) {
				titleAuthorities.put(resultSet.getString("sourceGroupingTitle"), resultSet.getString("preferredGroupingTitle"));
			}
		} catch (Exception e) {
			logger.error("Error loading preferred grouping titles", e);
		}
		authoritiesLoaded = true;
	}

	private static void loadAuthorities() {
		try (CSVReader csvReader = new CSVReader(new FileReader(new File("../record_grouping/author_authorities.properties")))) {
			String[] curLine = csvReader.readNext();
			while (curLine != null) {
				if (curLine.length >= 2) {
					authorAuthorities.put(curLine[0], curLine[1]);
				}
				curLine = csvReader.readNext();
			}
		} catch (IOException e) {
			logger.error("Unable to load author authorities", e);
		}
		try (CSVReader csvReader = new CSVReader(new FileReader(new File("../record_grouping/title_authorities.properties")))) {
			String[] curLine = csvReader.readNext();
			while (curLine != null) {
				if (curLine.length >= 2) {
					titleAuthorities.put(curLine[0], curLine[1]);
				}
				curLine = csvReader.readNext();
			}
		} catch (IOException e) {
			logger.error("Unable to load title authorities", e);
		}
		authoritiesLoaded = true;
	}

	String getOriginalAuthor() {
		return originalAuthorName;
	}

	void makeUnique(String primaryIdentifier) {
		uniqueIdentifier = primaryIdentifier;
		// Update the GroupedWork permanent Id now
		permanentId = null;
		getPermanentId();
	}

	public int getGroupedWorkVersion() {
		return this.version;  // Return the version number of whichever grouper class this work belongs to
	}


}
