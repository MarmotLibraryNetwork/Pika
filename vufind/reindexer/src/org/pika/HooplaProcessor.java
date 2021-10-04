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
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Extracts data from Hoopla Marc records to fill out information within the work to be indexed.
 *
 * Pika
 * User: Mark Noble
 * Date: 12/17/2014
 * Time: 10:30 AM
 */
class HooplaProcessor extends MarcRecordProcessor {
	protected     boolean                      fullReindex;
	private       String                       sourceDisplayName;
	private       String                       source;
	private       String                       individualMarcPath;
	private       int                          numCharsToCreateFolderFrom;
	private       boolean                      createFolderFromLeadingCharacters;
	private       PreparedStatement            hooplaExtractInfoStatement;
	private       HooplaExtractInfo            hooplaExtractInfo;
	private final HashSet<HooplaInclusionRule> libraryHooplaInclusionRules  = new HashSet<>();
	private final HashSet<HooplaInclusionRule> locationHooplaInclusionRules = new HashSet<>();

	HooplaProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, logger, fullReindex);
		this.fullReindex = fullReindex;

		try {
			sourceDisplayName                 = indexingProfileRS.getString("name");
			source                            = indexingProfileRS.getString("sourceName");
			individualMarcPath                = indexingProfileRS.getString("individualMarcPath");
			numCharsToCreateFolderFrom        = indexingProfileRS.getInt("numCharsToCreateFolderFrom");
			createFolderFromLeadingCharacters = indexingProfileRS.getBoolean("createFolderFromLeadingCharacters");

		} catch (Exception e) {
			logger.error("Error loading indexing profile information from database", e);
		}
		try {
			hooplaExtractInfoStatement = pikaConn.prepareStatement("SELECT * FROM hoopla_export WHERE hooplaId = ? LIMIT 1", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);

			//Load Hoopla Inclusion Rules
			try (
			PreparedStatement libraryHooplaInclusionRulesStatement  = pikaConn.prepareStatement("SELECT * FROM library_hoopla_setting");
			ResultSet         libraryRulesResultSet                 = libraryHooplaInclusionRulesStatement.executeQuery()
			) {
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
			}

			try (
			PreparedStatement locationHooplaInclusionRulesStatement = pikaConn.prepareStatement("SELECT * FROM location_hoopla_setting");
			ResultSet         locationRulesResultSet                = locationHooplaInclusionRulesStatement.executeQuery()
			) {
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
			}

		} catch (SQLException e) {
			logger.error("Failed to set SQL statement to fetch Hoopla Extract data", e);
		}
	}

	@Override
	public void processRecord(GroupedWorkSolr groupedWork, RecordIdentifier identifier, boolean loadedNovelistSeries) {
		Record record = loadMarcRecordFromDisk(identifier.getIdentifier());

		if (record != null) {
			try {
				if (getHooplaExtractInfo(identifier.getIdentifier(), record)) {
					if (hooplaExtractInfo != null) {
						if (hooplaExtractInfo.isActive()) {
							// Only Include titles that are active according to the Hoopla Extract

							updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier, loadedNovelistSeries);

						} else  if (logger.isInfoEnabled()){
							logger.info("Excluding due to title inactive for everyone hoopla id# " + hooplaExtractInfo.getTitleId() + " :" + hooplaExtractInfo.getTitle());
						}
					}
				}
			} catch (Exception e) {
				logger.error("Error updating solr based on hoopla marc record", e);
			}
		}
	}

	private final Pattern hooplaIdInAccessUrl = Pattern.compile("title/(\\d*)");

	/**
	 * @param identifier identifier of the bib to fetch extract info from
	 * @param record Marc Data
	 * @return whether or not extract information was found
	 */
	private boolean getHooplaExtractInfo(String identifier, Record record) {
		if (getHooplaExtractInfo(identifier)) {
			return true;
		} else {
			// Parse url for alternate Id
			String url = MarcUtil.getFirstFieldVal(record, "856u");
			if (url != null) {
				Matcher idInUrl = hooplaIdInAccessUrl.matcher(url);
				if (idInUrl.find()){
					String newId = idInUrl.group(1);
					if (fullReindex && logger.isInfoEnabled()) {
						logger.info("For " + identifier + ", trying Hoopla Id from Url : " + newId);
					}
					if (getHooplaExtractInfo(newId)){
						GroupedReindexMain.hooplaRecordWithOutExtractInfo.remove(identifier);
						if (!GroupedReindexMain.hooplaRecordUsingUrlIdExtractInfo.contains(identifier)) {
							GroupedReindexMain.hooplaRecordUsingUrlIdExtractInfo.add(identifier);
						}
						return true;
					}
				}
			}
		}
		return false;
	}

	private boolean getHooplaExtractInfo(String identifier) {
		try {
			hooplaExtractInfo = new HooplaExtractInfo(); // Make sure the class variable get reset for each record processed
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
//					logger.info("Did not find Hoopla Extract information for " + identifier);
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
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, RecordIdentifier identifier, boolean loadedNovelistSeries) {
		//First get format
		String format = MarcUtil.getFirstFieldVal(record, "099a");
		if (format != null) {
			format = format.replace(" hoopla", "");
		} else {
			logger.warn("No format found in 099a for Hoopla record " + identifier);
			//TODO: We can fall back to the Hoopla Extract 'kind' now.
		}

		//Do updates based on the overall bib (shared regardless of scoping)
		updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, null, identifier.getIdentifier(), format, loadedNovelistSeries);

		//Do special processing for Hoopla which does not have individual items within the record
		//Instead, each record has essentially unlimited items that can be used at one time.
		//There are also not multiple formats within a record that we would need to split out.

		//Setup the per Record information
		RecordInfo recordInfo = groupedWork.addRelatedRecord(source, identifier.getIdentifier());

		String formatCategory = indexer.translateSystemValue("format_category_hoopla", format, identifier.getIdentifier());
		long   formatBoost    = 8L; // Reasonable default value
		String formatBoostStr = indexer.translateSystemValue("format_boost_hoopla", format, identifier.getIdentifier());
		if (formatBoostStr != null && !formatBoostStr.isEmpty()) {
			formatBoost = Long.parseLong(formatBoostStr);
		} else {
			logger.warn("Did not find format boost for Hoopla format : " + format);
		}

		String fullDescription = Util.getCRSeparatedString(MarcUtil.getFieldList(record, "520a"));
		groupedWork.addDescription(fullDescription, format);

		//Load editions
		Set<String> editions       = MarcUtil.getFieldList(record, "250a");
		if (editions.size() > 0) {
			groupedWork.addEditions(editions);
			String primaryEdition = editions.iterator().next();
			recordInfo.setEdition(primaryEdition);
		}

		//Load publication details
		//Load publishers
		Set<String> publishers = this.getPublishers(record);
		if (publishers.size() > 0) {
			groupedWork.addPublishers(publishers);
			String publisher = publishers.iterator().next();
			recordInfo.setPublisher(publisher);
		}

		//Load publication dates
		Set<String> publicationDates = this.getPublicationDates(record);
		if (publicationDates.size() > 0) {
			groupedWork.addPublicationDates(publicationDates);
			String publicationDate = publicationDates.iterator().next();
			recordInfo.setPublicationDate(publicationDate);
		}

		//Load physical description
		Set<String> physicalDescriptions = MarcUtil.getFieldList(record, "300abcefg:530abcd");
		if (physicalDescriptions.size() > 0) {
			groupedWork.addPhysical(physicalDescriptions);
			String physicalDescription = physicalDescriptions.iterator().next();
			recordInfo.setPhysicalDescription(physicalDescription);
		}


		recordInfo.setFormatBoost(formatBoost);

		if (hooplaExtractInfo.abridged){
			recordInfo.setAbridged(true);
		}
//		// Is this an abridged record? (check all edition statements)
//		for (String editionCheck : editions) {
//			if (editionCheck.matches("(?i)(?<!un)abridged[\\s.\\]]")){
//				// Matches "abridged" but not "unabridged" case-insensitive, followed by word-break, period, or right bracket
//				recordInfo.setAbridged(true);
//				break;
//			}
//		}

		//Load Languages
		//For ILS Records, we can create multiple different records, one for print and order items,
		//and one or more for ILS eContent items.
		//For Hoopla Econtent there will only be one related record
		HashSet<RecordInfo> relatedRecords = new HashSet<>();
		relatedRecords.add(recordInfo);
		loadLanguageDetails(groupedWork, record, relatedRecords, identifier);

		//For Hoopla, we just have a single item always
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);
		itemInfo.setNumCopies(1);
		itemInfo.setFormat(format);
		itemInfo.setFormatCategory(formatCategory);
		itemInfo.seteContentSource(sourceDisplayName);
		itemInfo.setShelfLocation("Online Hoopla Collection");
		itemInfo.setCallNumber("Online Hoopla");
		itemInfo.setSortableCallNumber("Online Hoopla");
		itemInfo.setDetailedStatus("Available Online");
		loadEContentUrl(record, itemInfo, identifier);
		Date dateAdded = indexer.getDateFirstDetected(source, identifier.getIdentifier());
		itemInfo.setDateAdded(dateAdded);

		recordInfo.addItem(itemInfo);

		loadScopeInfoForEContentItem(groupedWork, recordInfo, itemInfo, record);


		//TODO: Determine how to find popularity for Hoopla titles.
		//Right now the information is not exported from Hoopla.  We could load based on clicks
		//From Pika to Hoopla, but that wouldn't count plays directly within the app
		//(which may be ok).
		groupedWork.addPopularity(1);

//		groupedWork.addHoldings(1);
	}

	private void loadScopeInfoForEContentItem(GroupedWorkSolr groupedWork, RecordInfo recordInfo, ItemInfo itemInfo, Record record) {
		//Figure out ownership information
		for (Scope curScope : indexer.getScopes()) {
			String                originalUrl = itemInfo.geteContentUrl();
			Scope.InclusionResult result      = curScope.isItemPartOfScope(source, "", null, groupedWork.getTargetAudiences(), recordInfo.getPrimaryFormat(), false, false, true, record, originalUrl);
			if (result.isIncluded) {

				boolean isHooplaIncluded = true;
				boolean hadLocationRules = false;
				if (curScope.isLocationScope()) {
					Long locationId = curScope.getLocationId();
					for (HooplaInclusionRule curHooplaRule : locationHooplaInclusionRules) {
						if (curHooplaRule.doesLocationRuleApply(hooplaExtractInfo, locationId)) {
							hadLocationRules = true;
							if (curHooplaRule.isHooplaTitleExcluded(hooplaExtractInfo)) {
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
							if (curRule.isHooplaTitleExcluded(hooplaExtractInfo)) {
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
	}

	private void addScopeToItem(ItemInfo itemInfo, Scope curScope, String originalUrl, Scope.InclusionResult result) {
		ScopingInfo scopingInfo = itemInfo.addScope(curScope);
		scopingInfo.setAvailable(true);
		scopingInfo.setStatus("Available Online");
		scopingInfo.setGroupedStatus("Available Online");
		scopingInfo.setHoldable(false);
		if (curScope.isLocationScope()) {
			scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(source, ""));
			if (curScope.getLibraryScope() != null) {
				scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(source, ""));
			}
		}
		if (curScope.isLibraryScope()) {
			scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(source, ""));
		}
		//Check to see if we need to do url rewriting
		if (originalUrl != null && !originalUrl.equals(result.localUrl)) {
			scopingInfo.setLocalUrl(result.localUrl);
		}
	}

	protected void loadTitles(GroupedWorkSolr groupedWork, Record record, String format, String identifier) {
		//title (full title done by index process by concatenating short and subtitle
		if (logger.isInfoEnabled()) {
			Set<String> titleTags = MarcUtil.getFieldList(record, "245a");
			if (titleTags.size() > 1) {
				logger.info("More than 1 245a title tag for Hoopla record : " + identifier);
			}
		}
		super.loadTitles(groupedWork, record, format, identifier);
	}


}
