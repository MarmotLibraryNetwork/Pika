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

import org.apache.logging.log4j.Logger;
import org.json.JSONException;
import org.json.JSONObject;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 12/9/13
 * Time: 9:14 AM
 */
public class OverDriveProcessor {
	private GroupedWorkIndexer indexer;
	private Logger             logger;
	private boolean            fullReindex;
	private boolean            hasSharedAdvantageAccount = false;
	private PreparedStatement  getProductInfoStmt;
	private PreparedStatement  getNumCopiesStmt;
	private PreparedStatement  getProductMetadataStmt;
	private PreparedStatement  getProductAvailabilityStmt;
	private PreparedStatement  getProductFormatsStmt;
	private PreparedStatement  getProductLanguagesStmt;
	private PreparedStatement  getProductSubjectsStmt;
	private PreparedStatement  getProductIdentifiersStmt;
	private PreparedStatement  getMagazineIssueIdentifiersStmt;

	public OverDriveProcessor(GroupedWorkIndexer groupedWorkIndexer, Connection econtentConn, Logger logger, boolean fullReindex, String serverName) {
		this.indexer = groupedWorkIndexer;
		this.logger = logger;
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);
		String sharedAdvantageAccounts = PikaConfigIni.getIniValue("OverDrive", "sharedAdvantageAccountKey");
		if (sharedAdvantageAccounts != null && !sharedAdvantageAccounts.isEmpty()){
			hasSharedAdvantageAccount = true;
		}

		try {
			getProductInfoStmt = econtentConn.prepareStatement("SELECT overdrive_api_products.*, fileAs FROM overdrive_api_products LEFT JOIN overdrive_api_product_creators ON (overdrive_api_product_creators.productId = overdrive_api_products.id AND primaryCreatorName = name) WHERE overdriveId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getNumCopiesStmt = econtentConn.prepareStatement("SELECT sum(copiesOwned) AS totalOwned FROM overdrive_api_product_availability WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//TODO filter by libraries belonging to an overdrive account??
			getProductMetadataStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_metadata WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getProductAvailabilityStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_availability WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//getProductCreatorsStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_creators where productId = ?");
			getProductFormatsStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_formats WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getProductLanguagesStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_languages INNER JOIN overdrive_api_product_languages_ref ON overdrive_api_product_languages.id = languageId WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getProductSubjectsStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_subjects INNER JOIN overdrive_api_product_subjects_ref ON overdrive_api_product_subjects.id = subjectId WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getProductIdentifiersStmt = econtentConn.prepareStatement("SELECT * FROM overdrive_api_product_identifiers WHERE productId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getMagazineIssueIdentifiersStmt = econtentConn.prepareStatement("SELECT overdriveId, crossRefId FROM overdrive_api_magazine_issues  WHERE parentId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		} catch (SQLException e) {
			logger.error("Error setting up overdrive processor", e);
		}
	}

	private SimpleDateFormat dateAddedParser = new SimpleDateFormat("yyyy-MM-dd");
	public void processRecord(GroupedWorkSolr groupedWork, String identifier, boolean loadedNovelistSeries) {
		try {
			indexer.overDriveRecordsIndexed.add(identifier);
			getProductInfoStmt.setString(1, identifier);
			try (ResultSet productRS = getProductInfoStmt.executeQuery()) {
				if (productRS.next()) {
					//Make sure the record isn't deleted
					Long   productId = productRS.getLong("id");
					String title     = productRS.getString("title");

					if (productRS.getInt("deleted") == 1) {
						if (logger.isInfoEnabled()) {
							logger.info("Not processing deleted overdrive product " + title + " - " + identifier);
						}
						indexer.overDriveRecordsSkipped.add(identifier);

					} else {
						getNumCopiesStmt.setLong(1, productId);
						try (ResultSet numCopiesRS = getNumCopiesStmt.executeQuery()) {
							numCopiesRS.next();
							if (numCopiesRS.getInt("totalOwned") == 0) {
								if (logger.isDebugEnabled()) {
									logger.debug("Not processing overdrive product with no copies owned " + title + " - " + identifier);
								}
								indexer.overDriveRecordsSkipped.add(identifier);
							} else {

								RecordInfo overDriveRecord = groupedWork.addRelatedRecord("overdrive", identifier);
								groupedWork.addAlternateId(productRS.getString("crossRefId"));

								HashMap<String, String> metadata;
								String                  formatCategory;
								String                  primaryFormat;
								String                  fullTitle = title;
								String                  series    = null;

								Object[] theReturn      = loadOverDriveSubjects(groupedWork, productId);
								// Load the subjects first, so we can determine when an eBook is actually eComic

								String mediaType = productRS.getString("mediaType");
								switch (mediaType) {
									case "Audiobook":
										formatCategory = "Audio Books";
										primaryFormat = "eAudiobook";
										break;
									case "Video":
										formatCategory = "Movies";
										primaryFormat = "eVideo";
										break;
									case "eBook":
										boolean  isComic        = (boolean) theReturn[3];
										if (isComic){
											primaryFormat = "eComic";
											formatCategory = "eBook";
										} else {
											formatCategory = mediaType;
											primaryFormat = mediaType;
										}
										break;
									case "Magazine":
										formatCategory = "eBook";
										primaryFormat = "eMagazine";
										break;
									default:
										formatCategory = mediaType;
										primaryFormat = mediaType;
										break;
								}

								metadata = loadOverDriveMetadata(groupedWork, productId, primaryFormat);

								try {
									series = productRS.getString("series");
									String sortTitle         = metadata.get("sortTitle");
									String subTitle          = productRS.getString("subtitle");
									if (subTitle != null && !subTitle.isEmpty()) {
										String titleLowerCase    = title.toLowerCase();
										String subTitleLowerCase = subTitle.toLowerCase();
										if (titleLowerCase.equals(subTitleLowerCase)) {
											if (fullReindex) {
												logger.warn(identifier + " overdrive title '" + title + "' is the same as the subtitle :" + subTitle );
											}
											subTitle = "";
										} else if (titleLowerCase.endsWith(subTitleLowerCase)) {
											// Pika should treat the title as not including the subtitle, so remove subtitle from title if it is there
											if (fullReindex) {
												logger.warn(identifier + " Overdrive title '" + title + "' ends with the subtitle :" + subTitle);
											}
											title = title.substring(0, titleLowerCase.lastIndexOf(subTitleLowerCase));
											title = title.replaceAll("[\\s:]+$", ""); // remove ending white space and any ending colon characters. //TODO: remove trailing "--"
										} else if (subTitleLowerCase.contains(" series, book")){
											// If the overdrive subtitle is just a series statement, do not add to the index as the subtitle
											// In these cases, the subtitle takes the form "{series title} Series, Book {Series Number}
											subTitle = "";

											if (series == null || series.isEmpty()) {
												sortTitle = title; // The sort title will likely just contain the series statement as well
												//TODO: Remove beginning The, A, An
												if (fullReindex) {
													logger.warn("OverDrive title had series styled subtitle but is missing series info, subtitle '" + subTitle + "' for " + identifier);
												}
											} else {
												// Remove Series statement from sort title
												String seriesNameInSortTitle = series.replaceAll("&", "and").replaceAll("'", "");
												//TODO: probably need a remove all punctuation regex
												if (sortTitle.contains(seriesNameInSortTitle)) {
													sortTitle = sortTitle.replaceAll(seriesNameInSortTitle + " Series Book (?:.*)$", "");
													// The Series Number at the end of the series statement is usually digits but not always
													// eg. Book One, Book 14.5, Book I
													if (fullReindex && sortTitle.contains("Series Book")) {
														logger.warn(identifier + " : Failed to remove series info from Overdrive sort title '" + sortTitle + "'");
													}
												} else {
													sortTitle = title; // The sort title will likely just contain the series statement as well
													//TODO: Remove beginning The, A, An
												}
											}
										}
										if (!subTitle.isEmpty()){
											fullTitle = title + " " + subTitle;
										}
									}

									fullTitle = fullTitle.trim();
									groupedWork.setTitle(title, subTitle, fullTitle, sortTitle, primaryFormat);
								} catch (Exception e) {
									logger.error("Error processing Overdrive title info for " + identifier, e);
								}
								groupedWork.addFullTitle(fullTitle);
								if (!loadedNovelistSeries) {
									groupedWork.addSeries(series, "");
									//TODO: add volume info?, from either subtitle or sort title with phrase "book X"
								}
								final String author = productRS.getString("fileAs");  // using the fileAs name for the primaryCreatorName in the products table
								groupedWork.setAuthor(author, primaryFormat);
								groupedWork.setAuthAuthor(author);
								groupedWork.setAuthorDisplay(author, primaryFormat);

								Date dateAdded = null;
								try {
									String productDataRaw = productRS.getString("rawData");
									if (productDataRaw != null) {
										JSONObject productDataJSON = new JSONObject(productDataRaw);
										if (productDataJSON.has("dateAdded")) {
											String dateAddedString = productDataJSON.getString("dateAdded");
											if (dateAddedString.length() > 10) {
												dateAddedString = dateAddedString.substring(0, 10);
											}

											dateAdded = dateAddedParser.parse(dateAddedString);
										}
									}
								} catch (ParseException e) {
									logger.warn("Error parsing date added for Overdrive " + productId, e);
								} catch (JSONException e) {
									logger.warn("Error loading date added for Overdrive " + productId, e);
								}
								if (dateAdded == null) {
									dateAdded = new Date(productRS.getLong("dateAdded") * 1000);
								}

								productRS.close();

								String primaryLanguage = loadOverDriveLanguages(groupedWork, overDriveRecord, productId);

								//Load the formats for the record.  For OverDrive, we will create a separate item for each format.
								HashSet<String> validFormats    = loadOverDriveFormats(groupedWork, overDriveRecord, productId);
								//overDriveRecord.addFormats(validFormats);

								loadOverDriveIdentifiers(groupedWork, productId, primaryFormat);

								if (primaryFormat.equals("eMagazine")){
									loadMagazineIssueIdentifiers(groupedWork, identifier);
								}


								//Load availability & determine which scopes are valid for the record
								getProductAvailabilityStmt.setLong(1, productId);
								int totalCopiesOwned;
								try (ResultSet availabilityRS = getProductAvailabilityStmt.executeQuery()) {


									String edition = metadata.get("edition");
									if (edition != null && !edition.isEmpty()) {
										if (primaryFormat.equals("eMagazine")) {
											overDriveRecord.setEdition(edition);
										}
										if (edition.equals("Abridged")) {
											overDriveRecord.setAbridged(true);
										}
									}

									overDriveRecord.setPrimaryLanguage(primaryLanguage);
									overDriveRecord.setPublisher(metadata.get("publisher"));
									overDriveRecord.setPublicationDate(primaryFormat.equals("eMagazine") ? metadata.get("publishDateMagazine") : metadata.get("publicationDate"));
//									overDriveRecord.setPhysicalDescription("");

									boolean isKids  = (boolean) theReturn[0];
									boolean isTeen  = (boolean) theReturn[1];
									boolean isAdult = (boolean) theReturn[2];

									totalCopiesOwned = 0; //Since totalCopiedOwned only contributes to the grouped work's holdings count,
									// this doesn't need to have special handling for multiple overdrive accounts.
									while (availabilityRS.next()) {
										//Just create one item for each with a list of sub formats.
										ItemInfo itemInfo = new ItemInfo();
										itemInfo.seteContentSource("OverDrive");
										itemInfo.setIsEContent(true);
										itemInfo.setShelfLocation("Online OverDrive Collection");
										itemInfo.setCallNumber("Online OverDrive");
										itemInfo.setSortableCallNumber("Online OverDrive");
										itemInfo.setDateAdded(dateAdded);
										itemInfo.setFormat(primaryFormat);
										itemInfo.setFormatCategory(formatCategory);

										overDriveRecord.addItem(itemInfo);

										long    libraryId = availabilityRS.getLong("libraryId");
										boolean available = availabilityRS.getBoolean("available");

										//Need to set an identifier based on the scope so we can filter later.
										itemInfo.setItemIdentifier(Long.toString(libraryId));

										//TODO: Check to see if this is a pre-release title.  If not, suppress if the record has 0 copies owned
										int copiesOwned = availabilityRS.getInt("copiesOwned");
										itemInfo.setNumCopies(copiesOwned);
										totalCopiesOwned = Math.max(copiesOwned, totalCopiesOwned);

										if (available) {
											itemInfo.setDetailedStatus("Available Online");
										} else {
											itemInfo.setDetailedStatus("Checked Out");
										}

										if (libraryId < 0) {
											for (Scope curScope : indexer.getScopes()) {
												if (curScope.isIncludeOverDriveCollection() && curScope.getSharedOverdriveCollectionId() == libraryId) {
													//Check based on the audience as well
													boolean okToInclude = (
																	(isAdult && curScope.isIncludeOverDriveAdultCollection()) ||
																	(isKids && curScope.isIncludeOverDriveKidsCollection()) ||
																	(isTeen && curScope.isIncludeOverDriveTeenCollection())
													);
													if (okToInclude) {
														ScopingInfo scopingInfo = itemInfo.addScope(curScope);
														scopingInfo.setAvailable(available);
														scopingInfo.setHoldable(true);

														if (available) {
															scopingInfo.setStatus("Available Online");
															scopingInfo.setGroupedStatus("Available Online");
														} else {
															scopingInfo.setStatus("Checked Out");
															scopingInfo.setGroupedStatus("Checked Out");
														}
													}
												}
											}
										} else {
											// Handle Advantage copies
											for (Scope curScope : indexer.getScopes()) {
												if (curScope.isIncludeOverDriveCollection() && (hasSharedAdvantageAccount || curScope.getLibraryId().equals(libraryId))) {
													//TODO: would need more complicated logic when there are multiple shared accounts plus sharedAdvantageAccount
													boolean okToInclude = (
																	(isAdult && curScope.isIncludeOverDriveAdultCollection()) ||
																	(isKids && curScope.isIncludeOverDriveKidsCollection()) ||
																	(isTeen && curScope.isIncludeOverDriveTeenCollection())
													);
													if (okToInclude) {
														ScopingInfo scopingInfo = itemInfo.addScope(curScope);
														scopingInfo.setAvailable(available);
														scopingInfo.setHoldable(true);
														if (curScope.isLocationScope()) {
															scopingInfo.setLocallyOwned(true);
															scopingInfo.setLibraryOwned(true);
														}
														if (curScope.isLibraryScope()) {
															scopingInfo.setLibraryOwned(true);
														}
														if (available) {
															scopingInfo.setStatus("Available Online");
															scopingInfo.setGroupedStatus("Available Online");
														} else {
															scopingInfo.setStatus("Checked Out");
															scopingInfo.setGroupedStatus("Checked Out");
														}
													}
												}
											}

										}//End processing availability
									}
								}
							}
						}
					}
				}
			}
		} catch (SQLException e) {
			logger.error("Error loading information from Database for overdrive title", e);
		}
	}

	private void loadOverDriveIdentifiers(GroupedWorkSolr groupedWork, Long productId, String primaryFormat) {
		try {
			getProductIdentifiersStmt.setLong(1, productId);
			ResultSet identifiersRS = getProductIdentifiersStmt.executeQuery();
			while (identifiersRS.next()){
				String type = identifiersRS.getString("type");
				String value = identifiersRS.getString("value");
				//For now, ignore anything that isn't an ISBN
				if (type.equals("ISBN")){
					groupedWork.addIsbn(value, primaryFormat);
				}
			}
		} catch (SQLException e) {
			logger.error("Error adding ISBNs to index for OverDrive product {}", productId, e);
		}
	}

	private void loadMagazineIssueIdentifiers(GroupedWorkSolr groupedWork, String identifier) {
		try {
			getMagazineIssueIdentifiersStmt.setString(1, identifier);
			ResultSet identifiersRS = getMagazineIssueIdentifiersStmt.executeQuery();
			while (identifiersRS.next()){
				String overdriveId = identifiersRS.getString("overdriveId").toLowerCase();
				String crossRefId  = identifiersRS.getString("crossRefId");
				groupedWork.addAlternateId(overdriveId); // TODO: currently stored in database with uppercase hashes
				groupedWork.addAlternateId(crossRefId);
			}
		} catch (SQLException e) {
			logger.error("Error adding magazine issue ids to index for parent id {}", identifier, e);
		}
	}


	/**
	 * Load information based on subjects for the record
	 *
	 * @param groupedWork Solr Document to update
	 * @param productId   The id of the OverDrive record
	 * @return isKids, isTeen, isAdult for use later in scoping
	 * and isComic, which indicates whether this record is an eComic
	 */
	private Object[] loadOverDriveSubjects(GroupedWorkSolr groupedWork, Long productId) {
		//Load subject data
		boolean isKids  = false;
		boolean isTeen  = false;
		boolean isAdult = false;
		boolean isComic = false;
		try {
			getProductSubjectsStmt.setLong(1, productId);
			try (ResultSet subjectsRS = getProductSubjectsStmt.executeQuery()) {
				HashSet<String>          topics             = new HashSet<>();
				HashSet<String>          genres             = new HashSet<>();
				HashMap<String, Integer> literaryForm       = new HashMap<>();
				HashMap<String, Integer> literaryFormFull   = new HashMap<>();
				while (subjectsRS.next()) {
					String curSubject = subjectsRS.getString("name");
					if (curSubject.contains("Nonfiction")) {
						addToMapWithCount(literaryForm, "Non Fiction");
						addToMapWithCount(literaryFormFull, "Non Fiction");
						genres.add("Non Fiction");
					} else if (curSubject.contains("Fiction")) {
						addToMapWithCount(literaryForm, "Fiction");
						addToMapWithCount(literaryFormFull, "Fiction");
						genres.add("Fiction");
					}

					if (curSubject.contains("Poetry")) {
						addToMapWithCount(literaryForm, "Fiction");
						addToMapWithCount(literaryFormFull, "Poetry");
					} else if (curSubject.contains("Essays")) {
						addToMapWithCount(literaryForm, "Non Fiction");
						addToMapWithCount(literaryFormFull, curSubject);
					} else if (curSubject.contains("Short Stories") || curSubject.contains("Drama")) {
						addToMapWithCount(literaryForm, "Fiction");
						addToMapWithCount(literaryFormFull, curSubject);
					}

					if (curSubject.contains("Juvenile") || curSubject.equals("Children's Video") || curSubject.equals("Children's Music")) {
						isKids = true;
						groupedWork.addTargetAudience("Juvenile");
						groupedWork.addTargetAudienceFull("Juvenile");
					} else if (curSubject.contains("Young Adult")) {
						isTeen = true;
						groupedWork.addTargetAudience("Young Adult");
						groupedWork.addTargetAudienceFull("Adolescent (14-17)");
					} else if (curSubject.contains("Picture Book")) {
						isKids = true;
						groupedWork.addTargetAudience("Juvenile");
						groupedWork.addTargetAudienceFull("Preschool (0-5)");
					} else if (curSubject.contains("Beginning Reader")) {
						isKids = true;
						groupedWork.addTargetAudience("Juvenile");
						groupedWork.addTargetAudienceFull("Primary (6-8)");
					} else if (curSubject.contains("Kids & Teens")) {
						isKids = true;
						isTeen = true;
						groupedWork.addTargetAudience("Juvenile");
						groupedWork.addTargetAudienceFull("Primary (6-8)");
						groupedWork.addTargetAudience("Young Adult");
						groupedWork.addTargetAudienceFull("Adolescent (14-17)");
					}

					if (!isComic && curSubject.equalsIgnoreCase("Comic and Graphic Books")) {
						isComic = true;
					}
					topics.add(curSubject);
				}
				if (!isKids && !isTeen){
					isAdult = true;
					groupedWork.addTargetAudience("Adult");
					groupedWork.addTargetAudienceFull("Adult");
				}
				groupedWork.addTopic(topics);
				groupedWork.addTopicFacet(topics);
				groupedWork.addGenre(genres);
				groupedWork.addGenreFacet(genres);
				if (!literaryForm.isEmpty()) {
					groupedWork.addLiteraryForms(literaryForm);
				}
				if (!literaryFormFull.isEmpty()) {
					groupedWork.addLiteraryFormsFull(literaryFormFull);
				}
			}
		} catch (Exception e) {
			logger.error("Error fetching Overdrive Subjects for product " + productId, e);
		}

		return new Object[] { isKids, isTeen, isAdult, isComic };
	}

	private void addToMapWithCount(HashMap<String, Integer> map, String elementToAdd){
		if (map.containsKey(elementToAdd)){
			map.put(elementToAdd, map.get(elementToAdd) + 1);
		}else{
			map.put(elementToAdd, 1);
		}
	}

	private String loadOverDriveLanguages(GroupedWorkSolr groupedWork, RecordInfo overDriveRecord, Long productId) throws SQLException {
		String primaryLanguage = null;
		//Load languages
		getProductLanguagesStmt.setLong(1, productId);
		try (ResultSet languagesRS = getProductLanguagesStmt.executeQuery()) {
			HashSet<String> languages = new HashSet<>();
			while (languagesRS.next()) {
				String overdriveLanguageCode = languagesRS.getString("code");
				String iso3LanguageCode;
				String language;
				try {
					// Use language labels from translation map if possible
					iso3LanguageCode = indexer.translateSystemValue("iso639-1TOiso639-2B", overdriveLanguageCode, overDriveRecord.getRecordIdentifier());
					language         = indexer.translateSystemValue("language", iso3LanguageCode, overDriveRecord.getRecordIdentifier());
				} catch (MissingResourceException e) {
					logger.warn("Can not convert Overdrive language code :" + overdriveLanguageCode);
					language = languagesRS.getString("name");
				}
				languages.add(language);
				if (primaryLanguage == null) {
					primaryLanguage = language;
				}
			}
			overDriveRecord.setLanguages(languages);
		}
		if (primaryLanguage == null) {
			if  (logger.isInfoEnabled()){
				logger.info("Using English, because no language found for overdrive record "+ overDriveRecord.getRecordIdentifier());
			}
			primaryLanguage = "English";
		}
		return primaryLanguage;
	}

	private HashSet<String> loadOverDriveFormats(GroupedWorkSolr groupedWork, RecordInfo overDriveRecord, Long productId) throws SQLException {
		//Load formats
		getProductFormatsStmt.setLong(1, productId);
		HashSet<String> formats;
		try (ResultSet formatsRS = getProductFormatsStmt.executeQuery()) {
			formats = new HashSet<>();
			HashSet<String> eContentDevices = new HashSet<>();
			long maxFormatBoost = 1;
			while (formatsRS.next()) {
				String format = formatsRS.getString("name");
				formats.add(format);
				String   deviceString = indexer.translateSystemValue("device_compatibility", format.replace(' ', '_'), overDriveRecord.getRecordIdentifier());
				String[] devices      = deviceString.split("\\|");
				for (String device : devices) {
					eContentDevices.add(device.trim());
				}
				long formatBoost = 1;
				try {
					formatBoost = Long.parseLong(indexer.translateSystemValue("format_boost_overdrive", format.replace(' ', '_'), overDriveRecord.getRecordIdentifier()));
				} catch (Exception e) {
					logger.warn("Could not translate format boost for " + overDriveRecord.getFullIdentifier());
				}
				if (formatBoost > maxFormatBoost) {
					maxFormatBoost = formatBoost;
				}
			}

			overDriveRecord.setFormatBoost(maxFormatBoost);


			//By default, formats are good for all locations
			groupedWork.addEContentDevices(eContentDevices);
		}

		return formats;
	}

	private HashMap<String, String> loadOverDriveMetadata(GroupedWorkSolr groupedWork, Long productId, String format) throws SQLException {
		HashMap<String, String> returnMetadata = new HashMap<>();
		//Load metadata
		getProductMetadataStmt.setLong(1, productId);
		try (ResultSet metadataRS = getProductMetadataStmt.executeQuery()) {
			if (metadataRS.next()) {
				returnMetadata.put("sortTitle", metadataRS.getString("sortTitle"));
				String publisher = metadataRS.getString("publisher");
				groupedWork.addPublisher(publisher);
				returnMetadata.put("publisher", publisher);
				//Currently the overdrive extract only saves years; otherwise the date is left blank.
				//This will be an problem for magazines
				String publicationDate = metadataRS.getString("publishDate");
				groupedWork.addPublicationDate(publicationDate);
				returnMetadata.put("publicationDate", publicationDate);
				//Need to divide this because it seems to be all time checkouts for all libraries, not just our libraries
				//Hopefully OverDrive will give us better stats in the near future that we can use.
				float popularity = metadataRS.getFloat("popularity");
				groupedWork.addPopularity(popularity > 500F ? popularity / 500f : 1);
				String shortDescription = metadataRS.getString("shortDescription");
				groupedWork.addDescription(shortDescription, format);
				String fullDescription = metadataRS.getString("fullDescription");
				groupedWork.addDescription(fullDescription, format);

				//Decode JSON data to get a little more information
				try {
					String rawMetadata = metadataRS.getString("rawData");
					if (rawMetadata != null) {
						JSONObject jsonData = new JSONObject(rawMetadata);
						if (jsonData.has("edition")){
							String edition = jsonData.getString("edition");
							returnMetadata.put("edition", edition);

							// Need to include the publication date of magazines in order to sort them
							if (jsonData.has("publishDateText")){
								String publishDate = jsonData.getString("publishDateText").replace(" 12:00AM", "");
								returnMetadata.put("publishDateMagazine", publishDate);
							}
						}
//						if (jsonData.has("ATOS")) {
//							groupedWork.setAcceleratedReaderReadingLevel(jsonData.getString("ATOS"));
//						}
					}else{
						logger.warn("Overdrive product " + productId + " did not have raw metadata");
					}
				} catch (JSONException e) {
					logger.error("Error loading raw data for OverDrive MetaData", e);
				}
			}
		}
		return returnMetadata;
	}
}
