package org.pika;

import org.apache.log4j.Logger;

import java.sql.Connection;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   3/9/2020
 */
public class OverDriveRecordGrouper extends RecordGroupingProcessor {

	OverDriveRecordGrouper(Connection dbConnection, String serverName, Logger logger, boolean fullRegrouping) {
		super(dbConnection, serverName, logger, fullRegrouping);
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
				groupedWork.setGroupingCategory("music");
				break;
			case "video":
				groupedWork.setGroupingCategory("movie");
				break;
			case "comic":
				groupedWork.setGroupingCategory("comic");
				break;
			default:
			case "audiobook":
			case "ebook":
				groupedWork.setGroupingCategory("book");
		}

		// Language
		if (groupedWork.getGroupedWorkVersion() >= 5) {
			((GroupedWork5) groupedWork).setGroupingLanguage(language);
		}

		addGroupedWorkToDatabase(primaryIdentifier, groupedWork, primaryDataChanged);
	}

}
