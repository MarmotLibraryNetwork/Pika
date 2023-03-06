/*
 * Copyright (C) 2023  Marmot Library Network
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

import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;

public class BookcoverCleanup implements IProcessHandler {
	private static final int DEFAULTAGE = 7;

	public void doCronProcess(String serverName, Section processSettings, Connection pikaConn, Connection eContentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
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
			if (processSettings.containsKey("coverAgeInDaysToDelete")) {
				coverAgeInDaysToDelete = processSettings.get("coverAgeInDaysToDelete");
				if (coverAgeInDaysToDelete != null && !coverAgeInDaysToDelete.isEmpty()) {
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
			final String note = "Deleting covers older than " + coverAge + " days";
			processLog.addNote(note);
			if (logger.isInfoEnabled()) {
				logger.info(note);
			}
		}

		// Delete Cover Images
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
							//Remove any files created more than coverAge days ago.
							if (curFile.lastModified() < (currentTime - (long) coverAge * 24 * 3600 * 1000)) {
								if (curFile.delete()) {
									numFilesDeleted++;
									processLog.incUpdated();
								} else {
									processLog.incErrors();
									processLog.addNote("Unable to delete file " + curFile);
								}
							}
						}
					}
					if (numFilesDeleted > 0) {
						final String note = "Removed " + numFilesDeleted + " files from " + fullPath + ".";
						processLog.addNote("\t" + note);
						if (logger.isInfoEnabled()) {
							logger.info(note);
						}
					}
				}
			}
		} catch (Exception e) {
			logger.error("Unknown Error while cleaning covers.", e);
		}

		// Delete QR Code Images
		String qrCodePath = PikaConfigIni.getIniValue("Site", "qrcodePath");
		if (qrCodePath != null && !qrCodePath.isEmpty()) {
			try {
				final String note = "Deleting qrcode images older than " + coverAge + " days";
				processLog.addNote(note);
				if (logger.isInfoEnabled()) {
					logger.info(note);
				}
				int  numFilesDeleted    = 0;
				File coverDirectoryFile = new File(qrCodePath);
				if (!coverDirectoryFile.exists()) {
					processLog.incErrors();
					processLog.addNote("Directory " + coverDirectoryFile.getAbsolutePath() + " does not exist.  Please check configuration file.");
					processLog.saveToDatabase(pikaConn, logger);
				} else {
					processLog.addNote("Cleaning up qrcode images in " + coverDirectoryFile.getAbsolutePath());
					processLog.saveToDatabase(pikaConn, logger);
					File[] filesToCheck = coverDirectoryFile.listFiles((dir, name) -> name.toLowerCase().endsWith("png") || name.toLowerCase().endsWith("jpg"));
					if (filesToCheck != null) {
						for (File curFile : filesToCheck) {
							//Remove any files created more than coverAge days ago.
							if (curFile.lastModified() < (currentTime - (long) coverAge * 24 * 3600 * 1000)) {
								if (curFile.delete()) {
									numFilesDeleted++;
									processLog.incUpdated();
								} else {
									processLog.incErrors();
									processLog.addNote("Unable to delete file " + curFile);
								}
							}
						}
					}
					if (numFilesDeleted > 0) {
						final String aNote = "Removed " + numFilesDeleted + " files from " + qrCodePath + ".";
						processLog.addNote("\t" + aNote);
						if (logger.isInfoEnabled()) {
							logger.info(aNote);
						}
					}
				}

			} catch (Exception e) {
				logger.error("Unknown Error while cleaning qrcode images.", e);
			}
		}

		// Delete Materials Request Summary Chart Images
		String materialsRequestSummaryCharts = PikaConfigIni.getIniValue("Site", "local");
		if (materialsRequestSummaryCharts != null && !materialsRequestSummaryCharts.isEmpty()) {
			materialsRequestSummaryCharts += "/images/charts/";
			try {
				final String note = "Deleting Materials Request Summary Chart images";
				processLog.addNote(note);
				if (logger.isInfoEnabled()) {
					logger.info(note);
				}
				int  numFilesDeleted    = 0;
				File coverDirectoryFile = new File(materialsRequestSummaryCharts);
				if (!coverDirectoryFile.exists()) {
					processLog.incErrors();
					processLog.addNote("Directory " + coverDirectoryFile.getAbsolutePath() + " does not exist.  Please check configuration file.");
					processLog.saveToDatabase(pikaConn, logger);
				} else {
					processLog.addNote("Cleaning up qrcode images in " + coverDirectoryFile.getAbsolutePath());
					processLog.saveToDatabase(pikaConn, logger);
					File[] filesToCheck = coverDirectoryFile.listFiles((dir, name) -> name.toLowerCase().endsWith("png") || name.toLowerCase().endsWith("jpg"));
					if (filesToCheck != null) {
						for (File curFile : filesToCheck) {
								if (curFile.delete()) {
									numFilesDeleted++;
									processLog.incUpdated();
								} else {
									processLog.incErrors();
									processLog.addNote("Unable to delete file " + curFile);
								}
						}
					}
					if (numFilesDeleted > 0) {
						final String aNote = "Removed " + numFilesDeleted + " files from " + materialsRequestSummaryCharts + ".";
						processLog.addNote("\t" + aNote);
						if (logger.isInfoEnabled()) {
							logger.info(aNote);
						}
					}
				}

			} catch (Exception e) {
				logger.error("Unknown Error while cleaning materials request summary chart images.", e);
			}
		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}
}
