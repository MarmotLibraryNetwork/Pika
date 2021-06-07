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

import org.apache.log4j.Logger;

import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   3/9/2020
 */
public class OverDriveRecordGrouper extends RecordGroupingProcessor {

	private Connection eContentConnection;
	PreparedStatement overDriveSubjectsStmt;

	OverDriveRecordGrouper(Connection pikaConn, Connection eContentConnection, Logger logger) {
		super(pikaConn, logger);
		super.setupDatabaseStatements(pikaConn);

		this.eContentConnection = eContentConnection;
		setupOverDriveDatabaseStatements();

		File   curFile = new File("../../sites/default/translation_maps/iso639-1TOiso639-2B_map.properties");
		if (curFile.exists()) {
			String mapName                        = curFile.getName().replace(".properties", "").replace("_map", "");
			translationMaps.put(mapName, loadTranslationMap(curFile, mapName));
		} else {
			logger.error("Language code converting map for OverDrive grouping not found");
		}

	}


	void setupOverDriveDatabaseStatements() {
		try {
			overDriveSubjectsStmt = eContentConnection.prepareStatement("SELECT * FROM overdrive_api_product_subjects INNER JOIN overdrive_api_product_subjects_ref ON overdrive_api_product_subjects.id = subjectId WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

		} catch (Exception e) {
			logger.error("Error setting up prepared statements", e);
		}
	}

	void processOverDriveRecord(RecordIdentifier primaryIdentifier, ResultSet overDriveRecordRS, boolean primaryDataChanged) {
		GroupedWorkBase groupedWork = GroupedWorkFactory.getInstance(-1, pikaConn);
		try {

//		String title, String subtitle, String author, String format, String language,

		long   id                  = overDriveRecordRS.getLong("id");
		String mediaType           = overDriveRecordRS.getString("mediaType");
		String title               = overDriveRecordRS.getString("title");
		String subtitle            = overDriveRecordRS.getString("subtitle");
		String author              = overDriveRecordRS.getString("primaryCreatorName");
		String productLanguageCode = overDriveRecordRS.getString("code");
		//primary creator in overdrive is always first name, last name.

		String groupingFormat;
		if (mediaType.equalsIgnoreCase("ebook")) {
			groupingFormat = "book";
			//Overdrive Graphic Novels can be derived from having a specific subject in the metadata
			overDriveSubjectsStmt.setLong(1, id);
			try (ResultSet overDriveSubjectRS = overDriveSubjectsStmt.executeQuery()) {
				while (overDriveSubjectRS.next()) {
					String subject = overDriveSubjectRS.getString("name");
					if (subject.equals("Comic and Graphic Books")) {
						groupingFormat = "comic";
						break;
					}
				}
			} catch (Exception e) {
				logger.error("Error looking for overdrive graphic novel info", e);
			}
		} else {
			groupingFormat = mediaType;
		}

		if (mediaType.equalsIgnoreCase("magazine")){
			// OverDrive Magazine don't populate the primaryCreatorName, so we will use the publisher as the grouping author
			// (Unfortunately, the publisher doesn't usually correspond to publisher of the print version)

			author = overDriveRecordRS.getString("publisher");
		}

		// Set Grouping Language (use ISO 639-2 Bibliographic code)
		String groupingLanguage = "";
			if (productLanguageCode != null) {
				groupingLanguage = translationMaps.get("iso639-1TOiso639-2B").translateValue(productLanguageCode, primaryIdentifier.toString());
			}

			//Replace & with and for better matching
		groupedWork.setTitle(title, subtitle);

		if (author != null) {
			groupedWork.setAuthor(author);
		}

		switch (groupingFormat.toLowerCase()) {
			case "music":
				groupedWork.setGroupingCategory("music", primaryIdentifier);
				break;
			case "video":
				groupedWork.setGroupingCategory("movie", primaryIdentifier);
				break;
			case "comic":
				groupedWork.setGroupingCategory("comic", primaryIdentifier);
				break;
			default:
				logger.warn("Unrecognized OverDrive mediaType (using book at grouping category) for " + primaryIdentifier + " : " + groupingFormat);
			case "external service":
			case "magazine":
			case "audiobook":
			case "ebook":
			case "book":
				groupedWork.setGroupingCategory("book", primaryIdentifier);
		}

		// Language
		if (groupedWork.getGroupedWorkVersion() >= 5) {
			((GroupedWork5) groupedWork).setGroupingLanguage(groupingLanguage);
		}

		addGroupedWorkToDatabase(primaryIdentifier, groupedWork, primaryDataChanged);
		}  catch (Exception e) {
			logger.error("Error processing OverDrive metadata for " + primaryIdentifier, e);
		}

	}

}
