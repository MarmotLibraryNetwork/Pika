package org.pika;

import org.apache.log4j.Logger;
import org.ini4j.Profile;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.MarcReader;
import org.marc4j.marc.Record;

import java.io.File;
import java.io.FileInputStream;
import java.sql.Connection;
import java.util.ArrayList;
import java.util.Set;

/**
 * Splits a MARC export based on location codes
 *
 * Pika
 * User: Mark Noble
 * Date: 11/21/2014
 * Time: 5:25 PM
 */
public class SplitMarcExport implements IProcessHandler {

	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection eContentConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Split Marc Records");
		processLog.saveToDatabase(pikaConn, logger);
		ArrayList<MarcSplitOption> splitOptions = new ArrayList<>();
		try {
			String marcPath         = Util.cleanIniValue(processSettings.get("marcPath"));
			String itemTag          = Util.cleanIniValue(processSettings.get("itemTag"));
			String marcEncoding     = Util.cleanIniValue(processSettings.get("marcEncoding"));
			char   locationSubfield = Util.cleanIniValue(processSettings.get("locationSubfield")).charAt(0);
			String splitMarcPath    = Util.cleanIniValue(processSettings.get("splitMarcPath"));
			if (splitMarcPath == null || splitMarcPath.isEmpty()) {
				logger.error("Did not find path to store the split marc files, please add splitMarcPath to the configuration file.");
				processLog.incErrors();
				return;
			}

			//Determine what splits to do
			for (String key : processSettings.keySet()) {
				if (key.startsWith("split_") && key.endsWith("_filename")) {
					try {
						int             curSplit    = Integer.parseInt(key.replace("split_", "").replace("_filename", ""));
						MarcSplitOption splitOption = new MarcSplitOption();
						splitOption.setFilename(splitMarcPath, Util.cleanIniValue(processSettings.get("split_" + curSplit + "_filename")));
						splitOption.setLocationsToInclude(Util.cleanIniValue(processSettings.get("split_" + curSplit + "_locations")));
						splitOption.setItemTag(itemTag);
						splitOption.setLocationSubfield(locationSubfield);
						splitOptions.add(splitOption);
					} catch (NumberFormatException e) {
						logger.error("Failed to parse split setting number for " + key);
					}
				}
			}

			File[] catalogBibFiles = new File(marcPath).listFiles();
			int    numRecordsRead  = 0;
			if (catalogBibFiles != null) {
				String lastRecordProcessed = "";
				for (File curBibFile : catalogBibFiles) {
					if (curBibFile.getName().endsWith(".mrc") || curBibFile.getName().endsWith(".marc")) {
						try (FileInputStream marcFileStream = new FileInputStream(curBibFile)) {
							MarcReader catalogReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);
							while (catalogReader.hasNext()) {
								Record curBib = catalogReader.next();

								//Check the items within the marc record to see if they should be kept or discarded
								for (MarcSplitOption splitter : splitOptions) {
									splitter.processRecord(curBib);
								}
							}
						} catch (Exception e) {
							logger.error("Error loading catalog bibs on record " + numRecordsRead + " the last record processed was " + lastRecordProcessed, e);
							processLog.incErrors();
						}
					}
					processLog.saveToDatabase(pikaConn, logger);
				}
				processLog.addNote("Completed splitting " + catalogBibFiles.length + "source MARC files.");
				processLog.addNote("Read " + numRecordsRead + " records.");
			}
		} catch (Exception e) {
			logger.error("Error splitting marc records", e);
			processLog.incErrors();
			processLog.addNote("Error splitting marc records " + e.toString());
		} finally {
			for (MarcSplitOption splitter : splitOptions) {
				processLog.incUpdated();
				splitter.close();
			}
			processLog.setFinished();
			processLog.saveToDatabase(pikaConn, logger);
		}
	}
}
