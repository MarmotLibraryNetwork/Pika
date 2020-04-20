package org.pika;

import org.apache.log4j.Logger;

import java.io.File;
import java.sql.Connection;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   3/9/2020
 */
public class OverDriveRecordGrouper extends RecordGroupingProcessor {

	OverDriveRecordGrouper(Connection pikaConn, String serverName, Logger logger, boolean fullRegrouping) {
//		super(pikaConn, serverName, logger, fullRegrouping);
		super(logger, fullRegrouping);
		super.setupDatabaseStatements(pikaConn);
		File   curFile = new File("../../sites/default/translation_maps/iso639-1TOiso639-2B_map.properties");
		if (curFile.exists()) {
			String mapName                        = curFile.getName().replace(".properties", "").replace("_map", "");
			translationMaps.put(mapName, loadTranslationMap(curFile, mapName));
		} else {
			logger.error("Language code converting map for OverDrive grouping not found");
		}

	}


	void processOverDriveRecord(RecordIdentifier primaryIdentifier, String title, String subtitle, String author, String format, String language, boolean primaryDataChanged) {
		GroupedWorkBase groupedWork = GroupedWorkFactory.getInstance(-1);

		//Replace & with and for better matching
		groupedWork.setTitle(title, subtitle);

		if (author != null) {
			groupedWork.setAuthor(author);
		}

		switch (format.toLowerCase()) {
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
				logger.warn("Unrecognized OverDrive mediaType (using book at grouping category) for " + primaryIdentifier + " : " + format);
			case "magazine":
			case "audiobook":
			case "ebook":
			case "book":
				groupedWork.setGroupingCategory("book", primaryIdentifier);
		}

		// Language
		if (groupedWork.getGroupedWorkVersion() >= 5) {
			((GroupedWork5) groupedWork).setGroupingLanguage(language);
		}

		addGroupedWorkToDatabase(primaryIdentifier, groupedWork, primaryDataChanged);
	}

}
