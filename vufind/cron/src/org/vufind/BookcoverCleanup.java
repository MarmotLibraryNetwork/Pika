package org.vufind;

import java.io.File;
import java.io.FilenameFilter;
import java.sql.Connection;
import java.util.Date;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;

public class BookcoverCleanup implements IProcessHandler {
	private static final int DEFAULTAGE = 7;

	public void doCronProcess(String serverName, Ini configIni, Section processSettings, Connection pikaConn, Connection eContentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Bookcover Cleanup");
		processLog.saveToDatabase(pikaConn, logger);

		String   coverPath              = configIni.get("Site", "coverPath");
		String[] coverPaths             = new String[]{"/small", "/medium", "/large"};
		long     currentTime            = new Date().getTime();
		String   coverAgeInDaysToDelete = configIni.get("Site", "coverAgeInDaysToDelete");
		int      coverAge               = DEFAULTAGE;
		try {
			if (coverAgeInDaysToDelete != null) {
				coverAge = Integer.parseInt(coverAgeInDaysToDelete);
			}
		} catch (NumberFormatException e) {
			logger.warn("Failed to parse coverAgeInDaysToDelete : " + coverAgeInDaysToDelete, e);
		} finally {
			if (coverAge <= 0) {
				logger.warn("Invalid value for coverAgeInDaysToDelete : " + coverAge);
				coverAge = DEFAULTAGE;
			}
		}

		try {
			for (String path : coverPaths) {
				int    numFilesDeleted    = 0;
				String fullPath           = coverPath + path;
				File   coverDirectoryFile = new File(fullPath);
				if (!coverDirectoryFile.exists()) {
					processLog.incErrors();
					processLog.addNote("Directory " + coverDirectoryFile.getAbsolutePath() + " does not exist.  Please check configuration file.");
					processLog.saveToDatabase(pikaConn, logger);
				} else {
					processLog.addNote("Cleaning up covers in " + coverDirectoryFile.getAbsolutePath());
					processLog.saveToDatabase(pikaConn, logger);
					File[] filesToCheck = coverDirectoryFile.listFiles((dir, name) -> name.toLowerCase().endsWith("jpg") || name.toLowerCase().endsWith("png"));
					if (filesToCheck != null) {
						for (File curFile : filesToCheck) {
							//Remove any files created more than 2 weeks ago.
							if (curFile.lastModified() < (currentTime - coverAge * 24 * 3600 * 1000)) {
								if (curFile.delete()) {
									numFilesDeleted++;
									processLog.incUpdated();
								} else {
									processLog.incErrors();
									processLog.addNote("Unable to delete file " + curFile.toString());
								}
							}
						}
					}
					if (numFilesDeleted > 0) {
						processLog.addNote("\tRemoved " + numFilesDeleted + " files from " + fullPath + ".");
					}
				}
			}
		} catch (Exception e) {
			logger.error("Unknown Error while cleaning covers.", e);
		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}
}
