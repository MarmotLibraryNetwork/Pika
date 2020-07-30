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

import java.io.File;
import java.sql.Connection;
import java.util.Date;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;

public class BookcoverCleanup implements IProcessHandler {
	private static final int DEFAULTAGE = 7;

	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection eContentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Bookcover Cleanup");
		processLog.saveToDatabase(pikaConn, logger);

		String   coverPath              = PikaConfigIni.getIniValue("Site", "coverPath");
		String[] coverPaths             = new String[]{"/small", "/medium", "/large"};
		long     currentTime            = new Date().getTime();
		String   coverAgeInDaysToDelete = PikaConfigIni.getIniValue("Site", "coverAgeInDaysToDelete");
		int      coverAge               = DEFAULTAGE;
		try {
			// Config Ini setting
			if (coverAgeInDaysToDelete != null && !coverAgeInDaysToDelete.isEmpty()) {
				coverAge = Integer.parseInt(coverAgeInDaysToDelete);
			}
			// command line setting should override config ini setting
			if (processSettings.containsKey("coverAgeInDaysToDelete")){
				coverAgeInDaysToDelete = processSettings.get("coverAgeInDaysToDelete");
				if (coverAgeInDaysToDelete != null && !coverAgeInDaysToDelete.isEmpty()){
					coverAge = Integer.parseInt(coverAgeInDaysToDelete);
				}
			}
		} catch (NumberFormatException e) {
			logger.warn("Failed to parse coverAgeInDaysToDelete : " + coverAgeInDaysToDelete, e);
		} finally {
			if (coverAge < 0) {
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
					File[] filesToCheck = coverDirectoryFile.listFiles((dir, name) -> name.toLowerCase().endsWith("png") || name.toLowerCase().endsWith("jpg"));
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
