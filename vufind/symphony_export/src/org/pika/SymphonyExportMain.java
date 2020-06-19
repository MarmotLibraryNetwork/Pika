package org.pika;

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.marc4j.*;
import org.marc4j.marc.*;
import org.pika.MarcRecordGrouper;

import java.io.*;
import java.sql.*;
import java.util.*;
import java.util.Date;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.zip.CRC32;

/**
 * Extracts information from Symphony server
 * Created by mnoble on 7/25/2017.
 */
public class SymphonyExportMain {
	private static Logger              logger    = Logger.getLogger(SymphonyExportMain.class);
	private static String              serverName;
	private static IndexingProfile     indexingProfile;
	private static PikaSystemVariables systemVariables;
	private static boolean             hadErrors = false;
	private static Long                exportStartTime;

	private static MarcRecordGrouper recordGroupingProcessor;

	public static void main(String[] args) {
		serverName = args[0];

		// Set-up Logging //
		Date startTime = new Date();
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.symphony_extract.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.toString());
		}

		if (logger.isInfoEnabled()) {
			logger.info(startTime.toString() + ": Starting Symphony Extract");
		}

		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Connect to the Pika database
		Connection pikaConn = null;
		try {
			String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (Exception e) {
			System.out.println("Error connecting to pika database " + e.toString());
			System.exit(1);
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		// The time this export started
		exportStartTime = startTime.getTime() / 1000;

		// The time the last export started
		long lastExportTime = getLastExtractTime();

		String profileToLoad = "ils";
		if (args.length > 1) {
			profileToLoad = args[1];
		}
		indexingProfile = IndexingProfile.loadIndexingProfile(pikaConn, profileToLoad, logger);

		//Setup other systems we will use
		initializeRecordGrouper(pikaConn);

		//Check for new marc out
		processNewMarcExports(lastExportTime, pikaConn);

		//Check for a new holds file
		processNewHoldsFile(lastExportTime, pikaConn);

		//Check for new orders file(lastExportTime, pikaConn);
		processOrdersFile(lastExportTime, pikaConn);

		//update the last export start time
		if (!hadErrors) {
			//Update the last extract time
			systemVariables.setVariable("last_symphony_extract_time", exportStartTime);
		} else {
			logger.error("There was an error updating during the extract, not setting last extract time.");
		}

		try {
			//Close the connection
			pikaConn.close();
		} catch (Exception e) {
			System.out.println("Error closing connection: " + e.toString());
		}
	}

	private static void processOrdersFile(long lastExportTime, Connection pikaConn) {
		File            mainFile      = new File(indexingProfile.marcPath + "/fullexport.mrc");
		HashSet<String> idsInMainFile = new HashSet<>();
		if (mainFile.exists()) {
			try {
				MarcReader reader         = new MarcPermissiveStreamReader(new FileInputStream(mainFile), true, true);
				int        numRecordsRead = 0;
				while (reader.hasNext()) {
					try {
						Record marcRecord = reader.next();
						numRecordsRead++;
						String id = getPrimaryIdentifierFromMarcRecord(marcRecord);
						idsInMainFile.add(id);
					} catch (MarcException me) {
						logger.warn("Error processing individual record  on record " + numRecordsRead + " of " + mainFile.getAbsolutePath(), me);
					}
				}
			} catch (Exception e) {
				logger.error("Error loading existing marc ids", e);
			}
		}

		//We have gotten 2 different exports a single export as CSV and a second daily version as XLSX.  If the XLSX exists, we will
		//process that and ignore the CSV version.
		File ordersFileMarc = new File(indexingProfile.marcPath + "/Pika_orders.mrc"); // The output of convertOrdersFileToMarc
		File ordersFile     = new File(indexingProfile.marcPath + "/PIKA-onorderfile.txt"); // The input of convertOrdersFileToMarc
		convertOrdersFileToMarc(ordersFile, ordersFileMarc, idsInMainFile);

	}

	private static void convertOrdersFileToMarc(File ordersFile, File ordersFileMarc, HashSet<String> idsInMainFile) {
		if (ordersFile.exists()) {
			long now                    = new Date().getTime();
			long ordersFileLastModified = ordersFile.lastModified();
			if (now - ordersFileLastModified > 7 * 24 * 60 * 60 * 1000) {
				logger.warn("Orders File was last written more than 7 days ago");
			}
			//Always process since we only received one export and we are gradually removing records as they appear in the full export.
			try (BufferedReader ordersReader = new BufferedReader(new InputStreamReader(new FileInputStream(ordersFile)))) {
				MarcWriter writer                 = new MarcStreamWriter(new FileOutputStream(ordersFileMarc, false));
				String     line                   = ordersReader.readLine();
				int        numOrderRecordsWritten = 0;
				int        numOrderRecordsSkipped = 0;
				Pattern    isbnPattern            = Pattern.compile("(^\\d{13}|^\\d{10}).*$");
				while (line != null) {
					int firstPipePos = line.indexOf('|');
					if (firstPipePos != -1) {
						String recordNumber = line.substring(0, firstPipePos);
						line = line.substring(firstPipePos + 1);
						if (recordNumber.matches("^\\d+$")) {
							if (!idsInMainFile.contains("a" + recordNumber)) { // Don't add order records for anything in the ILS's main export file (ie. the regular record for the order)
								if (line.endsWith("|")) {
									line = line.substring(0, line.length() - 1);
								}
								int     lastPipePosition = line.lastIndexOf('|');
								String  isbn             = "";
								String  isbnString       = line.substring(lastPipePosition + 1);
								Matcher isbnMatcher      = isbnPattern.matcher(isbnString);
								if (isbnMatcher.matches()) {
									isbn = isbnMatcher.group(1);
//									if (logger.isDebugEnabled()) {
//										logger.debug("Got " + isbn + " from raw string " + isbnString);
//									}
								}
								line             = line.substring(0, lastPipePosition);
								lastPipePosition = line.lastIndexOf('|');
								if (lastPipePosition != -1) {
									String title = line.substring(lastPipePosition + 1);
									line             = line.substring(0, lastPipePosition);
									lastPipePosition = line.lastIndexOf('|');
									if (lastPipePosition != -1) {
										String author = line.substring(lastPipePosition + 1);
										line = line.substring(0, lastPipePosition);
										String ohohseven = line.replace("|", " ");
										//The marc record does not exist, create a temporary bib in the orders file which will get processed by record grouping
										MarcFactory factory    = MarcFactory.newInstance();
										Record      marcRecord = factory.newRecord();
										marcRecord.addVariableField(factory.newControlField("001", "a" + recordNumber));
										if (!ohohseven.equals("-")) {
											marcRecord.addVariableField(factory.newControlField("007", ohohseven));
										}
										if (!author.equals("-")) {
											marcRecord.addVariableField(factory.newDataField("100", '0', '0', "a", author));
										}
										marcRecord.addVariableField(factory.newDataField("245", '0', '0', "a", title));
										if (!isbn.isEmpty()) {
											marcRecord.addVariableField(factory.newDataField("020", '0', '0', "a", isbn));
										}
										writer.write(marcRecord);
										numOrderRecordsWritten++;
									} else {
										logger.warn("Failed to parse author on order items file with line :" + line);
									}
								} else {
									logger.warn("Failed to parse title on order items file with line :" + line);
								}
							} else {
								logger.info("Marc record already exists for a" + recordNumber);
								numOrderRecordsSkipped++;
							}
						}
					}
					line = ordersReader.readLine();
				}
				writer.close();
				if (logger.isInfoEnabled()) {
					logger.info("Finished writing Orders to MARC record");
					logger.info("Wrote " + numOrderRecordsWritten + " order records.");
					logger.info("Skipped " + numOrderRecordsSkipped + " order records because they are in the main export");
				}
			} catch (Exception e) {
				logger.error("Error reading orders file ", e);
			}
		} else {
			logger.warn("Could not find orders file at " + ordersFile.getAbsolutePath());
		}
	}

	/**
	 * Check the marc folder to see if the holds files have been updated since the last export time.
	 *
	 * If so, load a count of holds per bib and then update the database.
	 *
	 * @param lastExportTime the last time the export was run
	 * @param pikaConn       the connection to the database
	 */
	private static void processNewHoldsFile(long lastExportTime, Connection pikaConn) {
		HashMap<String, Integer> holdsByBib = new HashMap<>();
		boolean                  writeHolds = false;
		File                     holdFile   = new File(indexingProfile.marcPath + "/Pika_Holds.csv");
		if (holdFile.exists()) {
			long now                  = new Date().getTime();
			long holdFileLastModified = holdFile.lastModified();
			if (now - holdFileLastModified > 2 * 24 * 60 * 60 * 1000) {
				logger.warn("Holds File was last written more than 2 days ago");
			} else {
				writeHolds = true;
				String lastCatalogIdRead = "";
				try (
					BufferedReader reader = new BufferedReader(new FileReader(holdFile));
				){
					String         line   = reader.readLine();
					while (line != null) {
						int firstComma = line.indexOf(',');
						if (firstComma > 0) {
							String catalogId = line.substring(0, firstComma);
							catalogId         = catalogId.replaceAll("\\D", "");
							lastCatalogIdRead = catalogId;
							//Make sure the catalog is numeric
							if (catalogId.length() > 0 && catalogId.matches("^\\d+$")) {
								if (holdsByBib.containsKey(catalogId)) {
									holdsByBib.put(catalogId, holdsByBib.get(catalogId) + 1);
								} else {
									holdsByBib.put(catalogId, 1);
								}
							}
						}
						line = reader.readLine();
					}
				} catch (Exception e) {
					logger.error("Error reading holds file ", e);
					hadErrors = true;
				}
				if (logger.isInfoEnabled()) {
					logger.info("Read " + holdsByBib.size() + " bibs with holds, lastCatalogIdRead = " + lastCatalogIdRead);
				}
			}
		} else {
			logger.warn("No holds file found at " + indexingProfile.marcPath + "/Pika_Holds.csv");
			hadErrors = true;
		}

		File periodicalsHoldFile = new File(indexingProfile.marcPath + "/Pika_Hold_Periodicals.csv");
		if (periodicalsHoldFile.exists()) {
			long now                  = new Date().getTime();
			long holdFileLastModified = periodicalsHoldFile.lastModified();
			if (now - holdFileLastModified > 2 * 24 * 60 * 60 * 1000) {
				logger.warn("Periodicals Holds File was last written more than 2 days ago");
			} else {
				writeHolds = true;
				try (
					BufferedReader reader            = new BufferedReader(new FileReader(periodicalsHoldFile));
				){
					String         line              = reader.readLine();
					String         lastCatalogIdRead = "";
					while (line != null) {
						int firstComma = line.indexOf(',');
						if (firstComma > 0) {
							String catalogId = line.substring(0, firstComma);
							catalogId         = catalogId.replaceAll("\\D", "");
							lastCatalogIdRead = catalogId;
							//Make sure the catalog is numeric
							if (catalogId.length() > 0 && catalogId.matches("^\\d+$")) {
								if (holdsByBib.containsKey(catalogId)) {
									holdsByBib.put(catalogId, holdsByBib.get(catalogId) + 1);
								} else {
									holdsByBib.put(catalogId, 1);
								}
							}
						}
						line = reader.readLine();
					}
					if (logger.isInfoEnabled()) {
						logger.info(holdsByBib.size() + " bibs with holds (including periodicals) lastCatalogIdRead for periodicals = " + lastCatalogIdRead);
					}
				} catch (Exception e) {
					logger.error("Error reading periodicals holds file ", e);
					hadErrors = true;
				}
			}
		} else {
			logger.warn("No periodicals holds file found at " + indexingProfile.marcPath + "/Pika_Hold_Periodicals.csv");
			hadErrors = true;
		}

		//Now that we've counted all the holds, update the database
		if (!hadErrors && writeHolds) {
			try {
				pikaConn.setAutoCommit(false);
				pikaConn.prepareCall("TRUNCATE ils_hold_summary").executeUpdate();  // Truncate so that id value doesn't grow beyond column size
				if (logger.isInfoEnabled()) {
					logger.info("Removed existing holds");
				}
				PreparedStatement updateHoldsStmt = pikaConn.prepareStatement("INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)");
				for (String ilsId : holdsByBib.keySet()) {
					if (ilsId.length() < 20) {
						updateHoldsStmt.setString(1, "a" + ilsId);
						updateHoldsStmt.setInt(2, holdsByBib.get(ilsId));
						int numUpdates = updateHoldsStmt.executeUpdate();
						if (numUpdates != 1) {
							logger.warn("Hold was not inserted " + "a" + ilsId + " " + holdsByBib.get(ilsId));
						}
					} else {
						logger.warn("ILS id for hold summary longer that database column varchar(20) : a" + ilsId);
					}
				}
				pikaConn.commit();
				pikaConn.setAutoCommit(true);
				if (logger.isInfoEnabled()) {
					logger.info("Finished adding new holds to the database");
				}
			} catch (Exception e) {
				logger.error("Error updating holds database", e);
				hadErrors = true;
			}
		}
	}

	/**
	 * Check the updates folder for any files that have arrived since our last export, but after the
	 * last full export.
	 *
	 * If we get new files, load the MARC records from the file and compare what we have on disk.
	 * If the checksum has changed, we should mark the records as updated in the database and replace
	 * the current MARC with the new record.
	 *
	 * @param lastExportTime the last time the export was run
	 * @param pikaConn       the connection to the database
	 */
	private static void processNewMarcExports(long lastExportTime, Connection pikaConn) {
		File fullExportFile      = new File(indexingProfile.marcPath + "/fullexport.mrc");
		File fullExportDirectory = fullExportFile.getParentFile();
		File sitesDirectory      = fullExportDirectory.getParentFile();
		File updatesDirectory    = new File(sitesDirectory.getAbsolutePath() + "/marc_updates");
		File updatesFile         = new File(updatesDirectory.getAbsolutePath() + "/Pika-hourly.mrc");
		int recordsUpdated       = 0;
		if (!fullExportFile.exists()) {
			logger.error("Full export file did not exist");
			hadErrors = true;
			return;
		}
		if (!updatesFile.exists()) {
			logger.warn("Updates file did not exist");
			hadErrors = true;
			return;
		}
		if (updatesFile.lastModified() < fullExportFile.lastModified()) {
			logger.debug("Updates File was written before the full export, ignoring");
			return;
		}
		if (updatesFile.lastModified() < lastExportTime) {
			// The extract may get called multiple times for the same marc file from Symphony
			if (logger.isInfoEnabled()) {
				logger.info("Not processing updates file because it hasn't changed since the last run of the export process.");
			}
			return;
		}

		//If we got this far we have a good updates file to process.
		try (
				PreparedStatement getChecksumStmt = pikaConn.prepareStatement("SELECT checksum FROM ils_marc_checksums WHERE source = ? AND ilsId = ?");
				PreparedStatement updateChecksumStmt = pikaConn.prepareStatement("UPDATE ils_marc_checksums SET checksum = ? WHERE source = ? AND ilsId = ?");
				PreparedStatement updateExtractInfoStatement = pikaConn.prepareStatement("INSERT INTO ils_extract_info (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined
				PreparedStatement getGroupedWorkIdStmt = pikaConn.prepareStatement("SELECT grouped_work_id FROM grouped_work_primary_identifiers WHERE type = ? AND identifier = ?");
				PreparedStatement updateGroupedWorkStmt = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?");
		){

			MarcReader updatedMarcReader = new MarcStreamReader(new FileInputStream(updatesFile));
			while (updatedMarcReader.hasNext()) {
				Record marcRecord = updatedMarcReader.next();
				//Get the id of the record
				String recordNumber = getPrimaryIdentifierFromMarcRecord(marcRecord);
				if (logger.isInfoEnabled()){
					logger.info("Marc update file has a record for " + recordNumber);
				}
				//Check to see if the checksum has changed
				getChecksumStmt.setString(1, indexingProfile.sourceName);
				getChecksumStmt.setString(2, recordNumber);
				ResultSet getChecksumRS = getChecksumStmt.executeQuery();
				if (getChecksumRS.next()) {
					//If it has, write the file to disk and update the database
					Long oldChecksum = getChecksumRS.getLong(1);
					Long newChecksum = getChecksum(marcRecord);
					if (!oldChecksum.equals(newChecksum)) {
						getGroupedWorkIdStmt.setString(1, indexingProfile.sourceName);
						getGroupedWorkIdStmt.setString(2, recordNumber);
						ResultSet getGroupedWorkIdRS = getGroupedWorkIdStmt.executeQuery();
						if (getGroupedWorkIdRS.next()) {
							Long groupedWorkId = getGroupedWorkIdRS.getLong(1);

							//Save the marc record
							File             ilsFile = indexingProfile.getFileForIlsRecord(recordNumber);
							MarcStreamWriter writer2 = new MarcStreamWriter(new FileOutputStream(ilsFile, false), "UTF-8");
							writer2.setAllowOversizeEntry(true);
							writer2.write(marcRecord);
							writer2.close();
							if (logger.isInfoEnabled()){
								logger.info("Updated individual marc record for " + recordNumber);
								recordsUpdated++;
							}

							//Setup the grouped work for the record.  This will take care of either adding it to the proper grouped work
							//or creating a new grouped work
							if (!recordGroupingProcessor.processMarcRecord(marcRecord, true)) {
								logger.warn(recordNumber + " was suppressed");
							} else {
								logger.debug("Finished record grouping for " + recordNumber);
							}

							//Mark the work as changed
//							updateGroupedWorkStmt.setLong(1, new Date().getTime() / 1000);
//							updateGroupedWorkStmt.setLong(2, groupedWorkId);
//							updateGroupedWorkStmt.executeUpdate();

							//TODO: does grouping do this for us
							//Save the new checksum so we don't reprocess
							updateChecksumStmt.setLong(1, newChecksum);
							updateChecksumStmt.setString(2, indexingProfile.sourceName);
							updateChecksumStmt.setString(3, recordNumber);
							updateChecksumStmt.executeUpdate();

							//Update last extract info
							updateExtractInfoStatement.setLong(1, indexingProfile.id);
							updateExtractInfoStatement.setString(2, recordNumber);
							updateExtractInfoStatement.setLong(3, exportStartTime);
							updateExtractInfoStatement.executeUpdate();
						} else {
							logger.warn("Could not find grouped work for MARC " + recordNumber);
						}
					} else if (logger.isInfoEnabled()) {
						logger.info("Skipping MARC " + recordNumber + " because it hasn't changed");
						if (logger.isDebugEnabled()) {
							logger.debug("old checksum: " + oldChecksum + ", new checksum: " + newChecksum);
						}
					}
				} else if (logger.isInfoEnabled()){
					logger.info("MARC Record " + recordNumber + " is new since the last full export");
				}

			}
			if (logger.isInfoEnabled()){
				logger.info(recordsUpdated + " records were updated.");
			}
		} catch (Exception e) {
			logger.error("Error loading updated marcs", e);
			hadErrors = true;
		}
	}

	//TODO: update to indexing profile setting for the record number subfield
	private static String getPrimaryIdentifierFromMarcRecord(Record marcRecord) {
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(indexingProfile.recordNumberTag);
		String              recordNumber       = null;
		//Make sure we only get one ils identifier
		for (VariableField curVariableField : recordNumberFields) {
			if (curVariableField instanceof DataField) {
				DataField curRecordNumberField = (DataField) curVariableField;
				Subfield  subfieldA            = curRecordNumberField.getSubfield('a');
				if (subfieldA != null && (indexingProfile.recordNumberPrefix.length() == 0 || subfieldA.getData().length() > indexingProfile.recordNumberPrefix.length())) {
					if (curRecordNumberField.getSubfield('a').getData().substring(0, indexingProfile.recordNumberPrefix.length()).equals(indexingProfile.recordNumberPrefix)) {
						recordNumber = curRecordNumberField.getSubfield('a').getData().trim();
						break;
					}
				}
			} else {
				//It's a control field
				ControlField curRecordNumberField = (ControlField) curVariableField;
				recordNumber = curRecordNumberField.getData().trim();
				break;
			}
		}
		return recordNumber;
	}

	private static Long getLastExtractTime() {
		Long lastSymphonyExtractTime = systemVariables.getLongValuedVariable("last_symphony_extract_time");

		//Last Update in UTC
		Date now       = new Date();
		Date yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
		// Add a small buffer (2 minutes) to the last extract time
		Date lastExtractDate = (lastSymphonyExtractTime != null) ? new Date((lastSymphonyExtractTime * 1000) - (120 * 1000)) : yesterday;

		if (lastExtractDate.before(yesterday)) {
			logger.warn("Last Extract date was more than 24 hours ago.  Just getting the last 24 hours since we should have a full extract.");
			lastSymphonyExtractTime = yesterday.getTime();
		} else {
			lastSymphonyExtractTime = lastExtractDate.getTime();
		}
		return lastSymphonyExtractTime;
	}

	private static long getChecksum(Record marcRecord) {
		CRC32  crc32              = new CRC32();
		String marcRecordContents = marcRecord.toString();
		//There can be slight differences in how the record length gets calculated between ILS export and what is written
		//by MARC4J since there can be differences in whitespace and encoding.
		// Remove the text LEADER
		// Remove the length of the record
		// Remove characters in position 12-16 (position of data)
		marcRecordContents = marcRecordContents.substring(12, 19) + marcRecordContents.substring(24).trim();
		marcRecordContents = marcRecordContents.replaceAll("\\p{C}", "?");
		crc32.update(marcRecordContents.getBytes());
		return crc32.getValue();
	}

	private static void initializeRecordGrouper(Connection pikaConn) {
		recordGroupingProcessor = new MarcRecordGrouper(pikaConn, indexingProfile, logger, false);
	}

}
