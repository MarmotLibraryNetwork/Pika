package org.pika;

import org.apache.log4j.Logger;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.marc.Record;

import java.io.ByteArrayInputStream;
import java.io.FileNotFoundException;
import java.io.InputStream;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Date;
import java.util.HashSet;
import java.util.Set;

/**
 * Extracts data from Hoopla Marc records to fill out information within the work to be indexed.
 *
 * Pika
 * User: Mark Noble
 * Date: 12/17/2014
 * Time: 10:30 AM
 */
class HooplaProcessor extends MarcRecordProcessor {
	protected boolean                      fullReindex;
	private   String                       individualMarcPath;
	private   int                          numCharsToCreateFolderFrom;
	private   boolean                      createFolderFromLeadingCharacters;
	private   PreparedStatement            hooplaExtractInfoStatement;
	private   HooplaExtractInfo            hooplaExtractInfo;
	private   HashSet<HooplaInclusionRule> libraryHooplaInclusionRules  = new HashSet<>();
	private   HashSet<HooplaInclusionRule> locationHooplaInclusionRules = new HashSet<>();

	HooplaProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, logger);
		this.fullReindex = fullReindex;

		try {
			individualMarcPath                = indexingProfileRS.getString("individualMarcPath");
			numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
			createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");

		} catch (Exception e) {
			logger.error("Error loading indexing profile information from database", e);
		}
		try {
			hooplaExtractInfoStatement = pikaConn.prepareStatement("SELECT * FROM hoopla_export WHERE hooplaId = ? LIMIT 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);

			//Load Hoopla Inclusion Rules
			PreparedStatement libraryHooplaInclusionRulesStatement  = pikaConn.prepareStatement("SELECT * FROM library_hoopla_setting");
			PreparedStatement locationHooplaInclusionRulesStatement = pikaConn.prepareStatement("SELECT * FROM location_hoopla_setting");
			ResultSet         libraryRulesResultSet                 = libraryHooplaInclusionRulesStatement.executeQuery();
			while ((libraryRulesResultSet.next())) {
				HooplaInclusionRule rule = new HooplaInclusionRule(logger);

				rule.setLibraryId(libraryRulesResultSet.getLong("libraryId"));
				rule.setKind(libraryRulesResultSet.getString("kind"));
				rule.setMaxPrice(libraryRulesResultSet.getFloat("maxPrice"));
				rule.setExcludeParentalAdvisory(libraryRulesResultSet.getBoolean("excludeParentalAdvisory"));
				rule.setExcludeProfanity(libraryRulesResultSet.getBoolean("excludeProfanity"));
				rule.setIncludeChildrenTitlesOnly(libraryRulesResultSet.getBoolean("includeChildrenTitlesOnly"));

				libraryHooplaInclusionRules.add(rule);
			}
			libraryRulesResultSet.close();

			ResultSet locationRulesResultSet = locationHooplaInclusionRulesStatement.executeQuery();
			while ((locationRulesResultSet.next())) {
				HooplaInclusionRule rule = new HooplaInclusionRule(logger);

				rule.setLocationId(locationRulesResultSet.getLong("locationId"));
				rule.setKind(locationRulesResultSet.getString("kind"));
				rule.setMaxPrice(locationRulesResultSet.getFloat("maxPrice"));
				rule.setExcludeParentalAdvisory(locationRulesResultSet.getBoolean("excludeParentalAdvisory"));
				rule.setExcludeProfanity(locationRulesResultSet.getBoolean("excludeProfanity"));
				rule.setIncludeChildrenTitlesOnly(locationRulesResultSet.getBoolean("includeChildrenTitlesOnly"));

				locationHooplaInclusionRules.add(rule);
			}
			locationRulesResultSet.close();

		} catch (SQLException e) {
			logger.error("Failed to set SQL statement to fetch Hoopla Extract data", e);
		}
	}

	@Override
	public void processRecord(GroupedWorkSolr groupedWork, String identifier) {
		Record record = loadMarcRecordFromDisk(identifier);

		if (record != null) {
			try {
				if (getHooplaExtractInfo(identifier)) {
					updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier);
					updateGroupedWorkSolrDataBasedOnHooplaExtract(groupedWork, identifier);
				}
			} catch (Exception e) {
				logger.error("Error updating solr based on hoopla marc record", e);
			}
		}
	}

	private boolean getHooplaExtractInfo(String identifier) {
		try {
			long hooplaId = Long.parseLong(identifier.replaceAll("^MWT", ""));
			hooplaExtractInfoStatement.setLong(1, hooplaId);
			try (ResultSet hooplaExtractInfoRS = hooplaExtractInfoStatement.executeQuery()) {
				if (hooplaExtractInfoRS.next()) {
					float hooplaPrice = hooplaExtractInfoRS.getFloat("price");

					//Fetch other data for inclusion rules
					String  kind             = hooplaExtractInfoRS.getString("kind");
					boolean active           = hooplaExtractInfoRS.getBoolean("active");
					boolean parentalAdvisory = hooplaExtractInfoRS.getBoolean("pa");
					boolean profanity        = hooplaExtractInfoRS.getBoolean("profanity");
					boolean abridged         = hooplaExtractInfoRS.getBoolean("abridged");
					boolean children         = hooplaExtractInfoRS.getBoolean("children");

					//For debugging, logging
					String title   = hooplaExtractInfoRS.getString("title");
					Long   titleId = hooplaExtractInfoRS.getLong("hooplaId");

					hooplaExtractInfo = new HooplaExtractInfo();
					hooplaExtractInfo.setTitleId(titleId);
					hooplaExtractInfo.setTitle(title);

					hooplaExtractInfo.setKind(kind);
					hooplaExtractInfo.setPrice(hooplaPrice);
					hooplaExtractInfo.setActive(active);
					hooplaExtractInfo.setParentalAdvisory(parentalAdvisory);
					hooplaExtractInfo.setProfanity(profanity);
					hooplaExtractInfo.setAbridged(abridged);
					hooplaExtractInfo.setChildren(children);
					return true;
				} else if (fullReindex) {
					logger.info("Did not find Hoopla Extract information for " + identifier);
					if (!GroupedReindexMain.hooplaRecordWithOutExtractInfo.contains(identifier)) {
						GroupedReindexMain.hooplaRecordWithOutExtractInfo.add(identifier);
					}
				}
			}
		} catch (NumberFormatException e) {
			logger.error("Error parsing identifier : " + identifier + " to a hoopla id number", e);
		} catch (SQLException e) {
			logger.error("Error adding hoopla extract data to solr document for hoopla record : " + identifier, e);
		}
		return false;
	}

	/**
	 * @param groupedWork Solr Document to update
	 * @param identifier  Record Identifier, used to get hoopla extract information
	 */
	private void updateGroupedWorkSolrDataBasedOnHooplaExtract(GroupedWorkSolr groupedWork, String identifier) {
		float hooplaPrice = (float) hooplaExtractInfo.getPrice();
		groupedWork.setHooplaPrice(hooplaPrice); //TODO: is adding the price to the index really needed?
		//TODO: this can't be a grouped work level value.  Another reason to remove it.
	}

	private Record loadMarcRecordFromDisk(String identifier) {
		Record record = null;
		//Load the marc record from disc
		String individualFilename = getFileForIlsRecord(identifier);
		try {
			byte[]      fileContents = Util.readFileBytes(individualFilename);
			InputStream inputStream  = new ByteArrayInputStream(fileContents);
			//FileInputStream inputStream = new FileInputStream(individualFile);
			MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
			if (marcReader.hasNext()) {
				record = marcReader.next();
			}
			inputStream.close();
		} catch (FileNotFoundException fnfe) {
			logger.error("Hoopla file " + individualFilename + " did not exist");
		} catch (Exception e) {
			logger.error("Error reading data from hoopla file " + individualFilename, e);
		}
		return record;
	}

	private String getFileForIlsRecord(String recordNumber) {
		StringBuilder shortId = new StringBuilder(recordNumber.replace(".", ""));
		while (shortId.length() < 9) {
			shortId.insert(0, "0");
		}

		String subFolderName;
		if (createFolderFromLeadingCharacters) {
			subFolderName = shortId.substring(0, numCharsToCreateFolderFrom);
		} else {
			subFolderName = shortId.substring(0, shortId.length() - numCharsToCreateFolderFrom);
		}

		String basePath = individualMarcPath + "/" + subFolderName;
		return basePath + "/" + shortId + ".mrc";
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		//First get format
		String format = MarcUtil.getFirstFieldVal(record, "099a");
		if (format != null) {
			format = format.replace(" hoopla", "");
		} else {
			logger.warn("No format found in 099a for Hoopla record " + identifier);
			//TODO: We can fall back to the Hoopla Extract 'kind' now.
		}

		//Do updates based on the overall bib (shared regardless of scoping)
		updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, null, identifier, format);

		//Do special processing for Hoopla which does not have individual items within the record
		//Instead, each record has essentially unlimited items that can be used at one time.
		//There are also not multiple formats within a record that we would need to split out.

		String formatCategory = indexer.translateSystemValue("format_category_hoopla", format, identifier);
		long   formatBoost    = 8L; // Reasonable default value
		String formatBoostStr = indexer.translateSystemValue("format_boost_hoopla", format, identifier);
		if (formatBoostStr != null && !formatBoostStr.isEmpty()) {
			formatBoost = Long.parseLong(formatBoostStr);
		} else {
			logger.warn("Did not find format boost for Hoopla format : " + format);
		}

		String fullDescription = Util.getCRSeparatedString(MarcUtil.getFieldList(record, "520a"));
		groupedWork.addDescription(fullDescription, format);

		//Load editions
		Set<String> editions       = MarcUtil.getFieldList(record, "250a");
		String      primaryEdition = null;
		if (editions.size() > 0) {
			primaryEdition = editions.iterator().next();
		}
		groupedWork.addEditions(editions);

		//Load publication details
		//Load publishers
		Set<String> publishers = this.getPublishers(record);
		groupedWork.addPublishers(publishers);
		String publisher = null;
		if (publishers.size() > 0) {
			publisher = publishers.iterator().next();
		}

		//Load publication dates
		Set<String> publicationDates = this.getPublicationDates(record);
		groupedWork.addPublicationDates(publicationDates);
		String publicationDate = null;
		if (publicationDates.size() > 0) {
			publicationDate = publicationDates.iterator().next();
		}

		//Load physical description
		Set<String> physicalDescriptions = MarcUtil.getFieldList(record, "300abcefg:530abcd");
		String      physicalDescription  = null;
		if (physicalDescriptions.size() > 0) {
			physicalDescription = physicalDescriptions.iterator().next();
		}
		groupedWork.addPhysical(physicalDescriptions);

		//Setup the per Record information
		RecordInfo recordInfo = groupedWork.addRelatedRecord("hoopla", identifier);

		recordInfo.setFormatBoost(formatBoost);
		recordInfo.setEdition(primaryEdition);
		recordInfo.setPhysicalDescription(physicalDescription);
		recordInfo.setPublicationDate(publicationDate);
		recordInfo.setPublisher(publisher);

		//Load Languages
		HashSet<RecordInfo> records = new HashSet<>();
		records.add(recordInfo);
		loadLanguageDetails(groupedWork, record, records, identifier);

		//For Hoopla, we just have a single item always
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);
		itemInfo.setNumCopies(1);
		itemInfo.setFormat(format);
		itemInfo.setFormatCategory(formatCategory);
		itemInfo.seteContentSource("Hoopla");
		itemInfo.seteContentProtectionType("Always Available");
		itemInfo.setShelfLocation("Online Hoopla Collection");
		itemInfo.setCallNumber("Online Hoopla");
		itemInfo.setSortableCallNumber("Online Hoopla");
		itemInfo.seteContentSource("Hoopla");
		itemInfo.seteContentProtectionType("Always Available");
		itemInfo.setDetailedStatus("Available Online");
		loadEContentUrl(record, itemInfo);
		Date dateAdded = indexer.getDateFirstDetected("hoopla", identifier);
		itemInfo.setDateAdded(dateAdded);

		recordInfo.addItem(itemInfo);

		loadScopeInfoForEContentItem(groupedWork, recordInfo, itemInfo, record);


		//TODO: Determine how to find popularity for Hoopla titles.
		//Right now the information is not exported from Hoopla.  We could load based on clicks
		//From Pika to Hoopla, but that wouldn't count plays directly within the app
		//(which may be ok).
		groupedWork.addPopularity(1);
	}

	private void loadScopeInfoForEContentItem(GroupedWorkSolr groupedWork, RecordInfo recordInfo, ItemInfo itemInfo, Record record) {
		if (hooplaExtractInfo != null) {
			if (hooplaExtractInfo.isActive()) { // Only Include titles that are active according to the Hoopla Extract
				//Figure out ownership information
				for (Scope curScope : indexer.getScopes()) {
					String                originalUrl = itemInfo.geteContentUrl();
					Scope.InclusionResult result      = curScope.isItemPartOfScope("hoopla", "", "", null, groupedWork.getTargetAudiences(), recordInfo.getPrimaryFormat(), false, false, true, record, originalUrl);
					if (result.isIncluded) {

						boolean isHooplaIncluded = true;
						boolean hadLocationRules = false;
						if (curScope.isLocationScope()) {
							Long locationId = curScope.getLocationId();
							for (HooplaInclusionRule curHooplaRule : locationHooplaInclusionRules) {
								if (curHooplaRule.doesLocationRuleApply(hooplaExtractInfo, locationId)) {
									hadLocationRules = true;
									if (!curHooplaRule.isHooplaTitleIncluded(hooplaExtractInfo)) {
										isHooplaIncluded = false;
										break;
									}
								}
							}
						}

						if (curScope.isLibraryScope() || curScope.isLocationScope() && !hadLocationRules) {
							// For Location Scopes, apply the Library settings if the locations didn't have any settings of its own
							Long libraryId = curScope.getLibraryId();
							for (HooplaInclusionRule curRule : libraryHooplaInclusionRules) {
								if (curRule.doesLibraryRuleApply(hooplaExtractInfo, libraryId)) {
									if (!curRule.isHooplaTitleIncluded(hooplaExtractInfo)) {
										isHooplaIncluded = false;
										break;
									}
								}
							}
						}

						// Add to scope after all applicable rules are tested
						if (isHooplaIncluded) {
							addScopeToItem(itemInfo, curScope, originalUrl, result);
						}
					}
				}
			} else {
				logger.info("Excluding due to title inactive for everyone hoopla id# " + hooplaExtractInfo.getTitleId() + " :" + hooplaExtractInfo.getTitle());
			}
		} else {
			//Exclude Records that don't have extract info for now.
			groupedWork.removeRelatedRecord(recordInfo);

			if (fullReindex) {
				logger.info("There was no extract information for Hoopla record " + recordInfo.getRecordIdentifier());
				if (!GroupedReindexMain.hooplaRecordWithOutExtractInfo.contains(recordInfo.getRecordIdentifier())) {
					GroupedReindexMain.hooplaRecordWithOutExtractInfo.add(recordInfo.getRecordIdentifier());
				}
			}
		}
	}

	private void addScopeToItem(ItemInfo itemInfo, Scope curScope, String originalUrl, Scope.InclusionResult result) {
		ScopingInfo scopingInfo = itemInfo.addScope(curScope);
		scopingInfo.setAvailable(true);
		scopingInfo.setStatus("Available Online");
		scopingInfo.setGroupedStatus("Available Online");
		scopingInfo.setHoldable(false);
		if (curScope.isLocationScope()) {
			scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope("hoopla", "", ""));
			if (curScope.getLibraryScope() != null) {
				scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope("hoopla", "", ""));
			}
		}
		if (curScope.isLibraryScope()) {
			scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope("hoopla", "", ""));
		}
		//Check to see if we need to do url rewriting
		if (originalUrl != null && !originalUrl.equals(result.localUrl)) {
			scopingInfo.setLocalUrl(result.localUrl);
		}
	}

	protected void loadTitles(GroupedWorkSolr groupedWork, Record record, String format, String identifier) {
		//title (full title done by index process by concatenating short and subtitle
		Set<String> titleTags = MarcUtil.getFieldList(record, "245a");
		if (titleTags.size() > 1) {
			logger.info("More than 1 245a title tag for Hoopla record : " + identifier);
		}
		super.loadTitles(groupedWork, record, format, identifier);
	}


}
