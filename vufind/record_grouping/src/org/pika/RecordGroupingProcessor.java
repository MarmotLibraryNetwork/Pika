package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.marc.*;

import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.sql.*;
import java.util.*;
import java.util.Date;
import java.util.regex.Pattern;

/**
 * User: Mark Noble
 * Date: 10/17/13
 * Time: 9:26 AM
 */
class RecordGroupingProcessor {
	protected Logger logger;
	String  recordNumberTag     = "";
	char    recordNumberField   = 'a';
	String  recordNumberPrefix  = "";
	boolean useEContentSubfield = false;
	char    eContentDescriptor  = ' ';
	String  itemTag;
	private PreparedStatement insertGroupedWorkStmt;
	private PreparedStatement checkHistoricalGroupedWorkStmt;
	private PreparedStatement insertHistoricalGroupedWorkStmt;
	private PreparedStatement groupedWorkForIdentifierStmt;
	private PreparedStatement updateDateUpdatedForGroupedWorkStmt;
	private PreparedStatement addPrimaryIdentifierForWorkStmt;
	private PreparedStatement removePrimaryIdentifiersForMergedWorkStmt;
	private PreparedStatement loadExistingGroupedWorksStmt;

	private int numRecordsProcessed  = 0;
	private int numGroupedWorksAdded = 0;

	private boolean fullRegrouping;
	private long    startTime = new Date().getTime();

	HashMap<String, TranslationMap> translationMaps = new HashMap<>();
//	HashMap<String, HashMap<String, String>> translationMaps = new HashMap<>();

	//TODO: Determine if we can avoid this by simply using the ON DUPLICATE KEY UPDATE FUNCTIONALITY
	//Would also want to mark merged works as changed (at least once) to make sure they get reindexed.
//	private HashMap<String, Long> existingGroupedWorks = new HashMap<>();

	//A list of grouped works that have been manually merged.
	private HashMap<String, String> mergedGroupedWorks = new HashMap<>();
	private HashSet<String>         recordsToNotGroup  = new HashSet<>();
	private Long                    updateTime         = new Date().getTime() / 1000;

	/**
	 * Default constructor for use by subclasses
	 */
	RecordGroupingProcessor(Logger logger, boolean fullRegrouping) {
		this.logger         = logger;
		this.fullRegrouping = fullRegrouping;

//		String mapName = "formatsToGroupingCategory";
//		translationMaps.put(mapName, new TranslationMap("grouping", mapName, false, false, this.logger, RecordGroupingProcessor.formatsToGroupingCategory));
//		mapName = "category";
//		translationMaps.put(mapName, new TranslationMap("grouping", mapName, false, false, this.logger, categoryMap));
	}

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param dbConnection   - The Connection to the Pika database
	 * @param serverName     - The server we are grouping data for
	 * @param logger         - A logger to store debug and error messages to.
	 * @param fullRegrouping - Whether or not we are doing full regrouping or if we are only grouping changes.
	 *                       Determines if old works are loaded at the beginning.
	 */
	RecordGroupingProcessor(Connection dbConnection, String serverName, Logger logger, boolean fullRegrouping) {
		this(logger, fullRegrouping);

		setupDatabaseStatements(dbConnection);
		loadTranslationMaps(serverName);
	}

	void setupDatabaseStatements(Connection pikaConn) {
		try {
			insertGroupedWorkStmt                     = pikaConn.prepareStatement("INSERT INTO " + RecordGrouperMain.groupedWorkTableName + " (full_title, author, grouping_category, grouping_language, permanent_id, date_updated) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_updated = VALUES(date_updated), id=LAST_INSERT_ID(id) ", Statement.RETURN_GENERATED_KEYS);
			insertHistoricalGroupedWorkStmt           = pikaConn.prepareStatement("INSERT INTO grouped_work_historical (permanent_id, grouping_title, grouping_author, grouping_category, grouping_language, grouping_version) VALUES (?, ?, ?, ?, ?, ?) ");
			checkHistoricalGroupedWorkStmt            = pikaConn.prepareStatement("SELECT COUNT(*) FROM grouped_work_historical WHERE permanent_id = ? AND grouping_title = ? AND grouping_author = ? AND grouping_category = ? AND grouping_version = ?", ResultSet.CONCUR_READ_ONLY);
			updateDateUpdatedForGroupedWorkStmt       = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?");
			addPrimaryIdentifierForWorkStmt           = pikaConn.prepareStatement("INSERT INTO grouped_work_primary_identifiers (grouped_work_id, type, identifier) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), grouped_work_id = VALUES(grouped_work_id)", Statement.RETURN_GENERATED_KEYS);
			removePrimaryIdentifiersForMergedWorkStmt = pikaConn.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE grouped_work_id = ?");
			groupedWorkForIdentifierStmt              = pikaConn.prepareStatement("SELECT grouped_work.id, grouped_work.permanent_id FROM grouped_work INNER JOIN grouped_work_primary_identifiers ON grouped_work_primary_identifiers.grouped_work_id = grouped_work.id where type = ? AND identifier = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			loadExistingGroupedWorksStmt              = pikaConn.prepareStatement("SELECT id FROM grouped_work WHERE permanent_id = ?");

//			if (!fullRegrouping) {
//				try (
//						PreparedStatement loadExistingGroupedWorksStmt = pikaConn.prepareStatement("SELECT id, permanent_id FROM grouped_work");
//						ResultSet loadExistingGroupedWorksRS = loadExistingGroupedWorksStmt.executeQuery()
//				) {
//					while (loadExistingGroupedWorksRS.next()) {
//						existingGroupedWorks.put(loadExistingGroupedWorksRS.getString("permanent_id"), loadExistingGroupedWorksRS.getLong("id"));
//					}
//				} catch (Exception e) {
//					logger.error("Error loading all existing Works", e);
//				}
//			}

			try (
					PreparedStatement loadMergedWorksStmt = pikaConn.prepareStatement("SELECT * FROM merged_grouped_works");
					ResultSet mergedWorksRS = loadMergedWorksStmt.executeQuery()
			) {
				while (mergedWorksRS.next()) {
					mergedGroupedWorks.put(mergedWorksRS.getString("sourceGroupedWorkId"), mergedWorksRS.getString("destinationGroupedWorkId"));
				}
			} catch (Exception e) {
				logger.error("Error loading all merged grouped works", e);
			}

			try (
					PreparedStatement recordsToNotGroupStmt = pikaConn.prepareStatement("SELECT * FROM nongrouped_records");
					ResultSet nonGroupedRecordsRS = recordsToNotGroupStmt.executeQuery()
			) {
				while (nonGroupedRecordsRS.next()) {
					String identifier = nonGroupedRecordsRS.getString("source") + ":" + nonGroupedRecordsRS.getString("recordId");
					recordsToNotGroup.add(identifier.toLowerCase());
				}
			} catch (Exception e) {
				logger.error("Error loading all non grouped records", e);
			}
		} catch (Exception e) {
			logger.error("Error setting up prepared statements", e);
		}
	}

	//	private static Pattern overdrivePattern = Pattern.compile("(?i)^http://.*?lib\\.overdrive\\.com/ContentDetails\\.htm\\?id=[\\da-f]{8}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{12}$");
	//above pattern not strictly valid because urls don't have to contain the lib.overdrive.com
	private static Pattern overdrivePattern = Pattern.compile("(?i)^http://.*?/ContentDetails\\.htm\\?id=[\\da-f]{8}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{12}$|^http://link\\.overdrive\\.com");

	RecordIdentifier getPrimaryIdentifierFromMarcRecord(Record marcRecord, String recordType, boolean doAutomaticEcontentSuppression) {
		RecordIdentifier    identifier         = null;
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(recordNumberTag);
		for (VariableField recordNumberFieldValue : recordNumberFields) {
			//Make sure we only get one ils identifier
//			logger.debug("getPrimaryIdentifierFromMarcRecord - Got record number field");
			if (recordNumberFieldValue != null) {
				if (recordNumberFieldValue instanceof DataField) {
//					logger.debug("getPrimaryIdentifierFromMarcRecord - Record number field is a data field");

					DataField curRecordNumberField = (DataField) recordNumberFieldValue;
					Subfield  recordNumberSubfield = curRecordNumberField.getSubfield(recordNumberField);
					if (recordNumberSubfield != null && (recordNumberPrefix.length() == 0 || recordNumberSubfield.getData().length() > recordNumberPrefix.length())) {
						if (recordNumberSubfield.getData().substring(0, recordNumberPrefix.length()).equals(recordNumberPrefix)) {
							String recordNumber = recordNumberSubfield.getData().trim();
							identifier = new RecordIdentifier(recordType, recordNumber);
							break;
						}
					}
				} else {
					//It's a control field
					logger.debug("getPrimaryIdentifierFromMarcRecord - Record number field is a control field");
					ControlField curRecordNumberField = (ControlField) recordNumberFieldValue;
					String       recordNumber         = curRecordNumberField.getData().trim();
					identifier = new RecordIdentifier(recordType, recordNumber);
					break;
				}
			}
		}

		if (doAutomaticEcontentSuppression) {
			logger.debug("getPrimaryIdentifierFromMarcRecord - Doing automatic Econtent Suppression");

			//Check to see if the record is an overdrive record
			//TODO: is this needed at the grouping level
			if (useEContentSubfield) {
				boolean allItemsSuppressed = true;

				List<DataField> itemFields = getDataFields(marcRecord, itemTag);
				int             numItems   = itemFields.size();
				if (numItems == 0) {
					allItemsSuppressed = false;
				} else {
					for (DataField itemField : itemFields) {
						if (itemField.getSubfield(eContentDescriptor) != null) {
							//Check the protection types and sources
							String   eContentData   = itemField.getSubfield(eContentDescriptor).getData();
							String[] eContentFields = eContentData.split(":");
							String   sourceType     = eContentFields[0].toLowerCase().trim();
							if (!sourceType.equals("overdrive") && !sourceType.equals("hoopla")) {
								allItemsSuppressed = false;
								break;
							}
						} else {
							allItemsSuppressed = false;
							break;
						}
					}
				}
				if (allItemsSuppressed && identifier != null) {
					//Don't return a primary identifier for this record (we will suppress the bib and just use OverDrive APIs)
					identifier.setSuppressed(true);
				}
			} else {
				//Check the 856 for an overdrive url
				if (identifier != null) {
					List<DataField> linkFields = getDataFields(marcRecord, "856");
					for (DataField linkField : linkFields) {
						if (linkField.getSubfield('u') != null) {
							//Check the url to see if it is from OverDrive or Hoopla
							//TODO: no actual hoopla suppression here?
							//TODO: Would this block sideloaded hoopla?
							String linkData = linkField.getSubfield('u').getData().trim();
							if (overdrivePattern.matcher(linkData).matches()) {
								identifier.setSuppressed(true);
							}
						}
					}
				}
			}
		}

		if (identifier != null && identifier.isValid()) {
			return identifier;
		} else {
			return null;
		}
	}


	List<DataField> getDataFields(Record marcRecord, String tag) {
		return marcRecord.getDataFields(tag);
	}

	GroupedWorkBase setupBasicWorkForIlsRecord(RecordIdentifier identifier, Record marcRecord, IndexingProfile profile) {
		GroupedWorkBase workForTitle = GroupedWorkFactory.getInstance(-1);

		// Title
		setWorkTitleBasedOnMarcRecord(marcRecord, workForTitle);

		// Category
		String    groupingCategory = setGroupingCategoryForWork(identifier, marcRecord, profile, workForTitle);

		//TODO: use grouping category to set special author for movies

		// Author
		setWorkAuthorBasedOnMarcRecord(marcRecord, workForTitle, identifier);

		// Language
		if (workForTitle.getGroupedWorkVersion() >= 5) {
			setGroupingLanguageBasedOnMarc(marcRecord, (GroupedWork5) workForTitle, identifier);
		}

		return workForTitle;
	}

	protected String setGroupingCategoryForWork(RecordIdentifier identifier, Record marcRecord, IndexingProfile profile, GroupedWorkBase workForTitle) {
		String groupingCategory;
		HashSet<String> groupingCategories = new FormatDetermination(profile, translationMaps, logger).loadPrintFormatInformation(identifier, marcRecord);
		if (groupingCategories.size() > 1){
			groupingCategory = "book"; // fall back option for now
			logger.warn("More than one grouping category for " + identifier + " : " + String.join(",", groupingCategories));
		} else if (groupingCategories.size() == 0){
			logger.warn("No grouping category for " + identifier);
			groupingCategory = "book"; // fall back option for now
		} else {
			groupingCategory = groupingCategories.iterator().next(); //First Format
		}

		workForTitle.setGroupingCategory(groupingCategory, identifier);
		return groupingCategory;
	}

	private void setWorkAuthorBasedOnMarcRecord(Record marcRecord, GroupedWorkBase workForTitle, RecordIdentifier identifier) {
		String    author   = null;
		DataField field100 = marcRecord.getDataField("100"); // Heading - Personal Name
		if (field100 != null && field100.getSubfield('a') != null) {
			author = field100.getSubfield('a').getData();
		} else {
			//110	2		|a Mixed By Yogitunes (Musical Group)

			DataField field110 = marcRecord.getDataField("110"); // Heading - Corporate Name
			if (field110 != null && field110.getSubfield('a') != null) {
				author = field110.getSubfield('a').getData();
				if (field110.getSubfield('b') != null) {
					author += " " + field110.getSubfield('b').getData();
				}
			} else {
				DataField field111 = marcRecord.getDataField("111"); // Meeting Name
				if (field111 != null && field111.getSubfield('a') != null) {
					author = field111.getSubfield('a').getData();
				} else {
						DataField field700 = marcRecord.getDataField("700"); // Added Entry Personal Name
							//TODO: combine multiple 700s ?
						if (field700 != null && field700.getSubfield('a') != null) {
							author = field700.getSubfield('a').getData();
						} else {
							DataField field711 = marcRecord.getDataField("711"); // // Added Entry Corporate Name
							if (field711 != null && field711.getSubfield('a') != null) {
								// Check the 711 before the 710
								author = field711.getSubfield('a').getData();
							} else {
								DataField field710 = marcRecord.getDataField("710"); // Added Entry-Corporate Name
								if (field710 != null && field710.getSubfield('a') != null) {
									author = field710.getSubfield('a').getData();
								} else {
									// 264		1	|a Washington :|b Printed by P. Force,|c 1837.
									// 264		1	|a Alexandria, VA :|b distributed by Time Life,|c [2004, 1992]
									DataField field264 = marcRecord.getDataField("264"); // Production, Publication, Distribution, Manufacture, and Copyright Notice.
									if (field264 != null && field264.getIndicator2() == '1' && field264.getSubfield('b') != null) {
										author = field264.getSubfield('b').getData();
									} else {
										//260			|a Addison, IL :|b compiled by the Addison Public Library,|c 2004.
										DataField field260 = marcRecord.getDataField("260"); // Publication, Distribution, etc.
										if (field260 != null && field260.getSubfield('b') != null) {
											author = field260.getSubfield('b').getData();
										} else {
											DataField field245 = marcRecord.getDataField("245");
											if (field245 != null && field245.getSubfield('c') != null) {
											//TODO: if we are using this, we should do the clean up here, trimming out prefix phrases like "editor", etc
											author = field245.getSubfield('c').getData();
											if (author.indexOf(';') > 0) {
												//For Example:
												//245	1	0	|a Pop Corn & Ma Goodness /|c Edna Mitchell Preston ; illustrated by Robert Andrew Parker.
												author = author.substring(0, author.indexOf(';') - 1);
											}
											if (logger.isInfoEnabled()) {
												logger.info("Resorting to 245c for grouping author for " + identifier + " : " + author);
											}
										}
									}
									}
								}
							}
						}
//					}
				}
			}
		}
		if (author != null) {
			workForTitle.setAuthor(author);
		}
	}

	protected void setGroupingLanguageBasedOnMarc(Record marcRecord, GroupedWork5 workForTitle, RecordIdentifier identifier){
		ControlField fixedField     = (ControlField) marcRecord.getVariableField("008");
		String       languageCode = null;
		if (fixedField != null) {
			String       oo8Data         = fixedField.getData();
			if (oo8Data.length() > 37) {
				String oo8languageCode = oo8Data.substring(35, 38).toLowerCase().trim(); // (trim because some bad values will have spaces)
				if (!oo8languageCode.equals("") && !oo8languageCode.equals("|||")){
					//"   " (trimmed to "" & "|||" are equivalent to no language value being set
					languageCode = oo8languageCode;
				}
			}
		} else {
				logger.warn("Missing 008 : " + identifier.toString());
		}
		if (languageCode == null) {
			// If we still don't have a language, try using the first 041a if present
			DataField languageField = marcRecord.getDataField("041");
			if (languageField != null){
				Subfield languageSubField = languageField.getSubfield('a');
				if (languageSubField != null && languageField.getIndicator1() != '1' && languageField.getIndicator2() != '7'){
					// First indicator of 1 is for translations; 2nd indicator of 2 is for other language code schemes
					languageCode = languageSubField.getData().trim().toLowerCase().substring(0, 3);
					//substring(0,3) because some 041 tags will have multiple language codes within a single subfield.
					// We will just use the very first one.
				}
			}
		}
		if (languageCode == null) languageCode = "";
		workForTitle.setGroupingLanguage(languageCode);
	}

	private void setWorkTitleBasedOnMarcRecord(Record marcRecord, GroupedWorkBase workForTitle) {
		DataField field245 = marcRecord.getDataField("245");
		if (field245 != null && field245.getSubfield('a') != null) {
			String basicTitle = field245.getSubfield('a').getData();

			char nonFilingCharacters = field245.getIndicator2();
			if (nonFilingCharacters == ' ') nonFilingCharacters = '0';
			int numNonFilingCharacters = 0;
			if (nonFilingCharacters > '0' && nonFilingCharacters <= '9') {
				numNonFilingCharacters = Integer.parseInt(Character.toString(nonFilingCharacters));
			}

			//Add in subtitle (subfield b as well to avoid problems with gov docs, etc)
			StringBuilder groupingSubtitle = new StringBuilder();
			if (field245.getSubfield('b') != null) {
				groupingSubtitle.append(field245.getSubfield('b').getData());
			}

			//Group volumes, seasons, etc. independently
			if (field245.getSubfield('n') != null) {
				if (groupingSubtitle.length() > 0) groupingSubtitle.append(" ");
				groupingSubtitle.append(field245.getSubfield('n').getData());
			}
			if (field245.getSubfield('p') != null) {
				if (groupingSubtitle.length() > 0) groupingSubtitle.append(" ");
				groupingSubtitle.append(field245.getSubfield('p').getData());
			}

			workForTitle.setTitle(basicTitle, groupingSubtitle.toString(), numNonFilingCharacters);
		}
	}

	private long getExistingWork(String permanentId){
		try {
			loadExistingGroupedWorksStmt.setString(1, permanentId);
			ResultSet loadExistingGroupedWorksRS = loadExistingGroupedWorksStmt.executeQuery();
			if (loadExistingGroupedWorksRS.next()){
				long groupedWorkId = loadExistingGroupedWorksRS.getLong(1);
				return groupedWorkId;
			}
		} catch (SQLException e) {
			logger.warn("Error looking up work id from permanent id: " + permanentId, e);
		}
		return 0L;
	}

	private boolean workNotInHistoricalTable(GroupedWorkBase groupedWork){
		try {
			checkHistoricalGroupedWorkStmt.setString( 1, groupedWork.getPermanentId());
			checkHistoricalGroupedWorkStmt.setString( 2, groupedWork.fullTitle);
			checkHistoricalGroupedWorkStmt.setString( 3, groupedWork.author);
			checkHistoricalGroupedWorkStmt.setString( 4, groupedWork.groupingCategory);
			checkHistoricalGroupedWorkStmt.setInt( 5, groupedWork.getGroupedWorkVersion());

			try (ResultSet existingHistoricalEntryRS = checkHistoricalGroupedWorkStmt.executeQuery()){
				existingHistoricalEntryRS.next();
				int count = existingHistoricalEntryRS.getInt(1);
				return count == 0;
			}
		} catch (SQLException e){
			logger.warn("Error looking up work in historical table", e);
		}
		return true;  // When things go awry, say work is not in table.  If it is, the follow-up INSERT statement will fail on unique check anyway.
	}

	private void addToHistoricalTable(GroupedWorkBase groupedWork){
		try {
			insertHistoricalGroupedWorkStmt.setString( 1, groupedWork.permanentId);
			insertHistoricalGroupedWorkStmt.setString( 2, groupedWork.fullTitle);
			insertHistoricalGroupedWorkStmt.setString( 3, groupedWork.author);
			insertHistoricalGroupedWorkStmt.setString( 4, groupedWork.groupingCategory);
			insertHistoricalGroupedWorkStmt.setString( 5, ((GroupedWork5)groupedWork).groupingLanguage);
			insertHistoricalGroupedWorkStmt.setInt( 6, groupedWork.getGroupedWorkVersion());

			int success = insertHistoricalGroupedWorkStmt.executeUpdate();
			if (success != 1){
				logger.error("Error adding to historical grouping table: " + groupedWork.permanentId + " with title '" + groupedWork.fullTitle + "' and author '" + groupedWork.author + "'");
			}

		} catch (SQLException e){
			logger.warn("Error adding entry to historical table, query: " + insertGroupedWorkStmt, e);
		}

	}

	/**
	 * Add a work to the database
	 *
	 * @param primaryIdentifier The primary identifier we are updating the work for
	 * @param groupedWork       Information about the work itself
	 */
	void addGroupedWorkToDatabase(RecordIdentifier primaryIdentifier, GroupedWorkBase groupedWork, boolean primaryDataChanged) {
//		if (workNotInHistoricalTable(groupedWork)){
//			// Add grouping factors to historical table in order to track permanent Ids across grouping versions
//			// Do this before unmerging or merging because we want to track the original factors and id
//
//			addToHistoricalTable(groupedWork);
//		}
		//TODO: undo above

		//Check to see if we need to ungroup this
		if (recordsToNotGroup.contains(primaryIdentifier.toString().toLowerCase())) {
			groupedWork.makeUnique(primaryIdentifier.toString());
		}

		String groupedWorkPermanentId = groupedWork.getPermanentId();

		//Check to see if we are doing a manual merge of the work
		if (mergedGroupedWorks.containsKey(groupedWorkPermanentId)) {
			groupedWorkPermanentId = handleMergedWork(groupedWork, groupedWorkPermanentId);
		}

		//Check to see if the record is already on an existing work.  If so, remove from the old work.
		try {
			groupedWorkForIdentifierStmt.setString(1, primaryIdentifier.getSource());
			groupedWorkForIdentifierStmt.setString(2, primaryIdentifier.getIdentifier());

			try (ResultSet groupedWorkForIdentifierRS = groupedWorkForIdentifierStmt.executeQuery()) {
				if (groupedWorkForIdentifierRS.next()) {
					//We have an existing grouped work
					String existingGroupedWorkPermanentId = groupedWorkForIdentifierRS.getString("permanent_id");
					Long   existingGroupedWorkId          = groupedWorkForIdentifierRS.getLong("id");
					if (!existingGroupedWorkPermanentId.equals(groupedWorkPermanentId)) {
						markWorkUpdated(existingGroupedWorkId);
					}
				}
			}
		} catch (SQLException e) {
			logger.error("Error determining existing grouped work for identifier", e);
		}

		//Add the work to the database
		numRecordsProcessed++;
		long groupedWorkId = -1;
		try {
			groupedWorkId = getExistingWork(groupedWorkPermanentId); // returns the grouped work id or 0
			if (groupedWorkId > 0) {
				//There is an existing grouped record

				//Mark that the work has been updated
				//Only mark it as updated if the data for the primary identifier has changed
				if (primaryDataChanged) {
					markWorkUpdated(groupedWorkId);
				}

			} else {
				//Need to insert a new grouped record
				insertGroupedWorkStmt.setString(1, groupedWork.getTitle());
				insertGroupedWorkStmt.setString(2, groupedWork.getAuthor());
				insertGroupedWorkStmt.setString(3, groupedWork.getGroupingCategory());
				if (groupedWork.getGroupedWorkVersion() >= 5) {
					insertGroupedWorkStmt.setString(4, ((GroupedWork5)groupedWork).getGroupingLanguage());
					insertGroupedWorkStmt.setString(5, groupedWorkPermanentId);
					insertGroupedWorkStmt.setLong(6, updateTime);
				} else {
					//TODO: meh??
					insertGroupedWorkStmt.setString(5, groupedWorkPermanentId);
					insertGroupedWorkStmt.setLong(6, updateTime);
				}

				insertGroupedWorkStmt.executeUpdate();
				try (ResultSet generatedKeysRS = insertGroupedWorkStmt.getGeneratedKeys()) {
					if (generatedKeysRS.next()) {
						groupedWorkId = generatedKeysRS.getLong(1);
					}
				}
				numGroupedWorksAdded++;

				//Add to the existing works so we can optimize performance later
//				existingGroupedWorks.put(groupedWorkPermanentId, groupedWorkId);
				updatedAndInsertedWorksThisRun.add(groupedWorkId);
			}

			//Update identifiers
			addPrimaryIdentifierForWorkToDB(groupedWorkId, primaryIdentifier);
		} catch (Exception e) {
			logger.error("Error adding grouped record to grouped work ", e);
		}

	}

	private String handleMergedWork(GroupedWorkBase groupedWork, String sourceGroupedWorkPermanentId) {
		//Handle the merge
		//Override the work id
		String targetGroupedWorkPermanentId = mergedGroupedWorks.get(sourceGroupedWorkPermanentId);
		groupedWork.overridePermanentId(targetGroupedWorkPermanentId);

		logger.debug("Overriding grouped work " + sourceGroupedWorkPermanentId + " with " + targetGroupedWorkPermanentId);

		//Mark that the original was updated
		long originalGroupedWorkId = getExistingWork(sourceGroupedWorkPermanentId);
		if (originalGroupedWorkId > 0) {
			//There is an existing grouped record

			//Make sure we mark the original work as updated so it can be removed from the index next time around
			markWorkUpdated(originalGroupedWorkId);

			//Remove the identifiers for the work.
			//TODO: If we have multiple identifiers for this work, we'll call the delete once for each work.
			//Should we optimize to just call it once and remember that we removed it already?
			try {
				removePrimaryIdentifiersForMergedWorkStmt.setLong(1, originalGroupedWorkId);
				removePrimaryIdentifiersForMergedWorkStmt.executeUpdate();
			} catch (SQLException e) {
				logger.error("Error removing primary identifiers for merged work " + sourceGroupedWorkPermanentId + " (" + originalGroupedWorkId + ")");
			}
		}
		return targetGroupedWorkPermanentId;
	}

	private HashSet<Long> updatedAndInsertedWorksThisRun = new HashSet<>();

	private void markWorkUpdated(long groupedWorkId) {
		//Optimize to not continually mark the same works as updated
		if (!updatedAndInsertedWorksThisRun.contains(groupedWorkId)) {
			try {
				updateDateUpdatedForGroupedWorkStmt.setLong(1, updateTime);
				updateDateUpdatedForGroupedWorkStmt.setLong(2, groupedWorkId);
				updateDateUpdatedForGroupedWorkStmt.executeUpdate();
				updatedAndInsertedWorksThisRun.add(groupedWorkId);
			} catch (Exception e) {
				logger.error("Error updating date updated for grouped work ", e);
			}
		}
	}

	private void addPrimaryIdentifierForWorkToDB(long groupedWorkId, RecordIdentifier primaryIdentifier) {
		//Optimized to not delete and remove the primary identifier if it hasn't changed.  Just updates the grouped_work_id.
		try {
			//This statement will either add the primary key or update the work id if it already exists
			//Note, we can not lower case this because we depend on the actual identifier later
			addPrimaryIdentifierForWorkStmt.setLong(1, groupedWorkId);
			addPrimaryIdentifierForWorkStmt.setString(2, primaryIdentifier.getSource());
			addPrimaryIdentifierForWorkStmt.setString(3, primaryIdentifier.getIdentifier());
			addPrimaryIdentifierForWorkStmt.executeUpdate();
			/*ResultSet primaryIdentifierRS = addPrimaryIdentifierForWorkStmt.getGeneratedKeys();
			primaryIdentifierRS.next();
			primaryIdentifier.setIdentifierId(primaryIdentifierRS.getLong(1));
			primaryIdentifierRS.close();*/
		} catch (SQLException e) {
			logger.error("Error adding primary identifier to grouped work " + groupedWorkId + " " + primaryIdentifier.toString(), e);
		}
	}

/*	private static HashMap<String, String> formatsToGroupingCategory = new HashMap<>();

	static {
		// Keep entries in lower case
		formatsToGroupingCategory.put("atlas", "other");
		formatsToGroupingCategory.put("archival materials", "other");
		formatsToGroupingCategory.put("map", "other");
		formatsToGroupingCategory.put("tapecartridge", "other");
		formatsToGroupingCategory.put("chipcartridge", "other");
		formatsToGroupingCategory.put("disccartridge", "other");
		formatsToGroupingCategory.put("tapecassette", "other");
		formatsToGroupingCategory.put("tapereel", "other");
		formatsToGroupingCategory.put("floppydisk", "other");
		formatsToGroupingCategory.put("cdrom", "other");
		formatsToGroupingCategory.put("software", "other");
		formatsToGroupingCategory.put("globe", "other");
		formatsToGroupingCategory.put("braille", "book");
		formatsToGroupingCategory.put("filmstrip", "movie");
		formatsToGroupingCategory.put("transparency", "other");
		formatsToGroupingCategory.put("slide", "other");
		formatsToGroupingCategory.put("microfilm", "other");
		formatsToGroupingCategory.put("collage", "other");
		formatsToGroupingCategory.put("drawing", "other");
		formatsToGroupingCategory.put("painting", "other");
		formatsToGroupingCategory.put("print", "other");
		formatsToGroupingCategory.put("photonegative", "other");
		formatsToGroupingCategory.put("flashcard", "other");
		formatsToGroupingCategory.put("chart", "other");
		formatsToGroupingCategory.put("photo", "other");
		formatsToGroupingCategory.put("motionpicture", "movie");
		formatsToGroupingCategory.put("kit", "other");
		formatsToGroupingCategory.put("bookclubkit", "other");
		formatsToGroupingCategory.put("sensorimage", "other");
		formatsToGroupingCategory.put("sounddisc", "audio");
		formatsToGroupingCategory.put("soundcassette", "audio");
		formatsToGroupingCategory.put("soundrecording", "audio");
		formatsToGroupingCategory.put("videocartridge", "movie");
		formatsToGroupingCategory.put("videodisc", "movie");
		formatsToGroupingCategory.put("videocassette", "movie");
		formatsToGroupingCategory.put("videoreel", "movie");
		formatsToGroupingCategory.put("video", "movie");
		formatsToGroupingCategory.put("musicalscore", "book");
		formatsToGroupingCategory.put("musicrecording", "music");
		formatsToGroupingCategory.put("musiccd", "music");
		formatsToGroupingCategory.put("electronic", "other");
		formatsToGroupingCategory.put("physicalobject", "other");
		formatsToGroupingCategory.put("manuscript", "book");
		formatsToGroupingCategory.put("ebook", "ebook");
		formatsToGroupingCategory.put("book", "book");
		formatsToGroupingCategory.put("newspaper", "book");
		formatsToGroupingCategory.put("journal", "book");
		formatsToGroupingCategory.put("serial", "book");
		formatsToGroupingCategory.put("unknown", "other");
		formatsToGroupingCategory.put("playaway", "audio");
		formatsToGroupingCategory.put("playawayview", "movie");
		formatsToGroupingCategory.put("largeprint", "book");
		formatsToGroupingCategory.put("blu-ray", "movie");
		formatsToGroupingCategory.put("4kultrablu-ray", "movie");
		formatsToGroupingCategory.put("dvd", "movie");
		formatsToGroupingCategory.put("verticalfile", "other");
		formatsToGroupingCategory.put("compactdisc", "audio");
		formatsToGroupingCategory.put("taperecording", "audio");
		formatsToGroupingCategory.put("phonograph", "audio");
//		formatsToGroupingCategory.put("pdf", "ebook");
//		formatsToGroupingCategory.put("epub", "ebook");
//		formatsToGroupingCategory.put("jpg", "other");
//		formatsToGroupingCategory.put("gif", "other");
//		formatsToGroupingCategory.put("mp3", "audio");
		formatsToGroupingCategory.put("plucker", "ebook");
		formatsToGroupingCategory.put("kindle", "ebook");
//		formatsToGroupingCategory.put("externallink", "ebook");
//		formatsToGroupingCategory.put("externalmp3", "audio");
//		formatsToGroupingCategory.put("interactivebook", "ebook");
		formatsToGroupingCategory.put("overdrive", "ebook");
//		formatsToGroupingCategory.put("external_web", "ebook"); //TODO: external_ values obsolete
//		formatsToGroupingCategory.put("external_ebook", "ebook"); //TODO: external_ values obsolete
//		formatsToGroupingCategory.put("external_eaudio", "audio"); //TODO: external_ values obsolete
//		formatsToGroupingCategory.put("external_emusic", "music"); //TODO: external_ values obsolete
//		formatsToGroupingCategory.put("external_evideo", "movie"); //TODO: external_ values obsolete
		formatsToGroupingCategory.put("text", "ebook");
//		formatsToGroupingCategory.put("gifs", "other");
//		formatsToGroupingCategory.put("itunes", "audio");
//		formatsToGroupingCategory.put("adobe_epub_ebook", "ebook");
//		formatsToGroupingCategory.put("kindle_book", "ebook");
//		formatsToGroupingCategory.put("microsoft_ebook", "ebook");
//		formatsToGroupingCategory.put("overdrive_wma_audiobook", "audio");
//		formatsToGroupingCategory.put("overdrive_mp3_audiobook", "audio");
//		formatsToGroupingCategory.put("overdrive_music", "music");
//		formatsToGroupingCategory.put("overdrive_video", "movie");
//		formatsToGroupingCategory.put("overdrive_read", "ebook");
//		formatsToGroupingCategory.put("overdrive_listen", "audio");
//		formatsToGroupingCategory.put("adobe_pdf_ebook", "ebook");
//		formatsToGroupingCategory.put("palm", "ebook");
//		formatsToGroupingCategory.put("mobipocket_ebook", "ebook");
//		formatsToGroupingCategory.put("disney_online_book", "ebook");
//		formatsToGroupingCategory.put("open_pdf_ebook", "ebook");
//		formatsToGroupingCategory.put("open_epub_ebook", "ebook");
//		formatsToGroupingCategory.put("nook_periodicals", "ebook");
		formatsToGroupingCategory.put("econtent", "ebook");
		formatsToGroupingCategory.put("seedpacket", "other");
		formatsToGroupingCategory.put("graphicnovel", "comic");
		formatsToGroupingCategory.put("thesis", "book");
		formatsToGroupingCategory.put("wonderbook", "other");
		formatsToGroupingCategory.put("voxbooks", "other");
		formatsToGroupingCategory.put("playstation", "other");
		formatsToGroupingCategory.put("playstation3", "other");
		formatsToGroupingCategory.put("playstation4", "other");
		formatsToGroupingCategory.put("playstationvita", "other");
		formatsToGroupingCategory.put("nintendods", "other");
		formatsToGroupingCategory.put("3ds", "other");
		formatsToGroupingCategory.put("nintendoswitch", "other");
		formatsToGroupingCategory.put("wii", "other");
		formatsToGroupingCategory.put("wiiu", "other");
		formatsToGroupingCategory.put("xbox360", "other");
		formatsToGroupingCategory.put("xboxone", "other");
		formatsToGroupingCategory.put("kinect", "other");
		formatsToGroupingCategory.put("windowsgame", "other");
		formatsToGroupingCategory.put("online materials", "other");
	}

	private static HashMap<String, String> categoryMap = new HashMap<>();

	static {
		categoryMap.put("audio", "book");
		categoryMap.put("book", "book");
		categoryMap.put("ebook", "book");
		categoryMap.put("other", "book"); //TODO: keep as other??
		categoryMap.put("comic", "comic");
		categoryMap.put("music", "music");
		categoryMap.put("movie", "movie");
		categoryMap.put("movies", "movie");
	}
*/

	void dumpStats() {
		if (logger.isDebugEnabled()) {
			long totalElapsedTime    = new Date().getTime() - startTime;
			long totalElapsedMinutes = totalElapsedTime / (60 * 1000);
			logger.debug("-----------------------------------------------------------");
			logger.debug("Processed " + numRecordsProcessed + " records in " + totalElapsedMinutes + " minutes");
			logger.debug("Created a total of " + numGroupedWorksAdded + " grouped works");
		}
	}

	//TODO: This only gets used by the generate Author authorities, maybe the overdrive grouper
	private void loadTranslationMaps(String serverName) {
		//Load all translationMaps, first from default, then from the site specific configuration
		File   defaultTranslationMapDirectory = new File("../../sites/default/translation_maps");
		File[] defaultTranslationMapFiles     = defaultTranslationMapDirectory.listFiles((dir, name) -> name.endsWith("properties"));

		File   serverTranslationMapDirectory = new File("../../sites/" + serverName + "/translation_maps");
		File[] serverTranslationMapFiles     = serverTranslationMapDirectory.listFiles((dir, name) -> name.endsWith("properties"));

		if (defaultTranslationMapFiles != null) {
			for (File curFile : defaultTranslationMapFiles) {
				String mapName = curFile.getName().replace(".properties", "");
				mapName = mapName.replace("_map", "");
				translationMaps.put(mapName, loadTranslationMap(curFile, mapName));
			}
			if (serverTranslationMapFiles != null) {
				for (File curFile : serverTranslationMapFiles) {
					String mapName = curFile.getName().replace(".properties", "");
					mapName = mapName.replace("_map", "");
					translationMaps.put(mapName, loadTranslationMap(curFile, mapName));
				}
			}
		}
	}

	protected TranslationMap loadTranslationMap(File translationMapFile, String mapName) {
		Properties props = new Properties();
		try {
			props.load(new FileReader(translationMapFile));
		} catch (IOException e) {
			logger.error("Could not read file translation map, " + translationMapFile.getAbsolutePath(), e);
		}
		TranslationMap translationMap = new TranslationMap("grouping", mapName, false, false, logger);
		//TODO: profile name
		//TODO: use regular expression
		//TODO: what file maps can be moved to the indexing profile
		for (Object keyObj : props.keySet()) {
			String key = (String) keyObj;
			translationMap.addValue(key.toLowerCase(), props.getProperty(key));
		}
		return translationMap;
	}

	private HashSet<String> unableToTranslateWarnings = new HashSet<>();

//	private String translateValue(String mapName, String value) {
//		value = value.toLowerCase();
//		HashMap<String, String> translationMap = translationMaps.get(mapName);
//		String                  translatedValue;
//		if (translationMap == null) {
//			if (!unableToTranslateWarnings.contains("unable_to_find_" + mapName)) {
//				logger.error("Unable to find translation map for " + mapName);
//				unableToTranslateWarnings.add("unable_to_find_" + mapName);
//			}
//
//			translatedValue = value;
//		} else {
//			if (translationMap.containsKey(value)) {
//				translatedValue = translationMap.get(value);
//			} else {
//				if (translationMap.containsKey("*")) {
//					translatedValue = translationMap.get("*");
//				} else {
//					String concatenatedValue = mapName + ":" + value;
//					if (!unableToTranslateWarnings.contains(concatenatedValue)) {
//						logger.warn("Could not translate '" + concatenatedValue + "'");
//						unableToTranslateWarnings.add(concatenatedValue);
//					}
//					translatedValue = value;
//				}
//			}
//		}
//		if (translatedValue != null) {
//			translatedValue = translatedValue.trim();
//			if (translatedValue.length() == 0) {
//				translatedValue = null;
//			}
//		}
//		return translatedValue;
//	}
}
