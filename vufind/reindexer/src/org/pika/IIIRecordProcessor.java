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
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.sql.*;
import java.text.SimpleDateFormat;
import java.time.temporal.ChronoUnit;
import java.util.*;
import java.util.Date;

/**
 * Record Processor to handle processing records from iii's Sierra ILS
 * Pika
 * User: Mark Noble
 * Date: 7/9/2015
 * Time: 11:39 PM
 */
abstract class IIIRecordProcessor extends IlsRecordProcessor{
	private boolean                               loanRuleDataLoaded  = false;
	private HashMap<Long, LoanRule>               loanRules           = new HashMap<>();
	private ArrayList<LoanRuleDeterminer>         loanRuleDeterminers = new ArrayList<>();
	// A list of status codes that are eligible to show items as checked out.
	//protected String  availableStatus          = "-";
	protected  HashSet<String> availableStatusCodes  = new HashSet<String>() {{
		add("-");
	}};
	//protected Pattern availableStatusesPattern = null;
	protected  HashSet<String> libraryUseOnlyStatusCodes = new HashSet<String>() {{
		add("o");
	}};
	protected String validOnOrderRecordStatus = "o1";
	HashSet<String> validCheckedOutStatusCodes = new HashSet<String>() {{
		add("-");
	}};

	private PreparedStatement updateExtractInfoStatement;
	private int indexingProfileId;

	private boolean hasSierraLanguageFixedField;

	//Fields for loading order information
//	private String orderTag;
//	private char orderLocationSubfield;
//	private char singleOrderLocationSubfield;
//	private char orderCopiesSubfield;
//	private char orderStatusSubfield;
//	private char orderCode3Subfield;
//
//	private boolean addOnOrderShelfLocations = false;

	IIIRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);

		try {
			String availableStatusString = indexingProfileRS.getString("availableStatuses");
			String checkedOutStatuses     = indexingProfileRS.getString("checkedOutStatuses");
			String libraryUseOnlyStatuses = indexingProfileRS.getString("libraryUseOnlyStatuses");
			if (availableStatusString != null && !availableStatusString.isEmpty()){
				availableStatusCodes.addAll(Arrays.asList(availableStatusString.split("\\|")));
				//availableStatusesPattern = Pattern.compile("^(" + availableStatusString + ")$");
			}// else {
				// Compile default available status otherwise into regex
				//availableStatusesPattern = Pattern.compile("^(" + availableStatus + ")$");
			//}
			if (checkedOutStatuses != null && !checkedOutStatuses.isEmpty()){
				validCheckedOutStatusCodes.addAll(Arrays.asList(checkedOutStatuses.split("\\|")));
			}
			if (libraryUseOnlyStatuses != null && !libraryUseOnlyStatuses.isEmpty()){
				libraryUseOnlyStatusCodes.addAll(Arrays.asList(libraryUseOnlyStatuses.split("\\|")));
			}

//			orderTag                    = indexingProfileRS.getString("orderTag");
//			orderLocationSubfield       = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderLocation");
//			singleOrderLocationSubfield = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderLocationSingle");
//			orderCopiesSubfield         = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderCopies");
//			orderStatusSubfield         = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderStatus");
//			orderCode3Subfield          = getSubfieldIndicatorFromConfig(indexingProfileRS, "orderCode3");

			indexingProfileId = indexingProfileRS.getInt("id");
			updateExtractInfoStatement = pikaConn.prepareStatement("INSERT INTO `ils_extract_info` (indexingProfileId, ilsId, lastExtracted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lastExtracted=VALUES(lastExtracted)"); // unique key is indexingProfileId and ilsId combined

		} catch (SQLException e) {
			logger.error("Error loading indexing profile information from database for IIIRecordProcessor", e);
		}


		String orderStatusesToExport = PikaConfigIni.getIniValue("Reindex", "orderStatusesToExport");
		if (orderStatusesToExport != null && !orderStatusesToExport.isEmpty())  {
			// In the configuration file, statuses are delimited by the pipe character
			validOnOrderRecordStatus = orderStatusesToExport.replaceAll("\\|", "");
		}

		loadLoanRuleInformation(pikaConn, logger);

		hasSierraLanguageFixedField = sierraRecordFixedFieldsTag != null && !sierraRecordFixedFieldsTag.isEmpty() && sierraFixedFieldLanguageSubField != ' ';
	}

	protected boolean isLibraryUseOnly(ItemInfo itemInfo) {
		String status = itemInfo.getStatusCode();
		return status != null && !status.isEmpty() && libraryUseOnlyStatusCodes.contains(status);
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		boolean available = false;
		String  status    = itemInfo.getStatusCode();

		//if (status != null && !status.isEmpty() && availableStatusesPattern.matcher(status.toLowerCase()).matches()) {
		if (status != null && !status.isEmpty() && availableStatusCodes.contains(status.toLowerCase())) {
			if (isEmptyDueDate(itemInfo.getDueDate())) {
				available = true;
			}
		}
		return available;
	}

	protected boolean isOrderItemValid(String status) {
		return !status.isEmpty() && validOnOrderRecordStatus.indexOf(status.charAt(0)) >= 0;
	}


	private void loadLoanRuleInformation(Connection pikaConn, Logger logger) {
		if (!loanRuleDataLoaded) {
			//Load loan rules
			try (
					PreparedStatement loanRuleStmt = pikaConn.prepareStatement("SELECT * FROM loan_rules", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
					ResultSet loanRulesRS = loanRuleStmt.executeQuery()
			) {
				while (loanRulesRS.next()) {
					try {
						LoanRule loanRule = new LoanRule();
						loanRule.setLoanRuleId(loanRulesRS.getLong("loanRuleId"));
						loanRule.setName(loanRulesRS.getString("name"));
						loanRule.setHoldable(loanRulesRS.getBoolean("holdable"));
						loanRule.setBookable(loanRulesRS.getBoolean("bookable"));
						loanRule.setIsHomePickup(loanRulesRS.getBoolean("homePickup"));

						loanRules.put(loanRule.getLoanRuleId(), loanRule);
					} catch (SQLException e) {
						logger.error("Error while loading loan rules", e);
					}
				}
				if (logger.isDebugEnabled()) {
					logger.debug("Loaded {} loan rules", loanRules.size());
				}

				try (
						PreparedStatement loanRuleDeterminersStmt = pikaConn.prepareStatement("SELECT * FROM loan_rule_determiners WHERE active = 1 ORDER BY rowNumber DESC", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
						ResultSet loanRuleDeterminersRS = loanRuleDeterminersStmt.executeQuery()
				) {
					while (loanRuleDeterminersRS.next()) {
						try {
							boolean isActive = loanRuleDeterminersRS.getBoolean("active");
							if (isActive) { // Only load active determiners
								LoanRuleDeterminer loanRuleDeterminer = new LoanRuleDeterminer(logger);
								loanRuleDeterminer.setRowNumber(loanRuleDeterminersRS.getLong("rowNumber"));
								loanRuleDeterminer.setLocation(loanRuleDeterminersRS.getString("location"));
								loanRuleDeterminer.setPatronType(loanRuleDeterminersRS.getString("patronType"));
								loanRuleDeterminer.setItemType(loanRuleDeterminersRS.getString("itemType"));
								loanRuleDeterminer.setLoanRuleId(loanRuleDeterminersRS.getLong("loanRuleId"));
								loanRuleDeterminer.setActive(isActive);

								loanRuleDeterminers.add(loanRuleDeterminer);
							}
						} catch (SQLException e) {
							logger.error("Error while loading loan rule determiners", e);
						}
					}

					if (logger.isDebugEnabled()) {
						logger.debug("Loaded {} loan rule determiner", loanRuleDeterminers.size());
					}
				}
			} catch (SQLException e) {
				logger.error("Unable to load loan rules", e);
			}
			loanRuleDataLoaded = true;
		}
	}

	/**
	 * Legacy method to load order records from Order Record MARC data.
	 * Several pieces of logic and fields are specific to Sierra (probably Millennium in the past)
	 *
	 */
//	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record){
//		List<DataField> orderFields = MarcUtil.getDataFields(record, orderTag);
//		for (DataField curOrderField : orderFields){
//			//Check here to make sure the order item is valid before doing further processing.
//			String status = "";
//			if (curOrderField.getSubfield(orderStatusSubfield) != null) {
//				status = curOrderField.getSubfield(orderStatusSubfield).getData();
//			}
//			String code3 = null;
//			if (orderCode3Subfield != ' ' && curOrderField.getSubfield(orderCode3Subfield) != null){
//				code3 = curOrderField.getSubfield(orderCode3Subfield).getData();
//			}
//
//			if (isOrderItemValid(status, code3)){
//				int copies = 0;
//				//If the location is multi, we actually have several records that should be processed separately
//				List<Subfield> detailedLocationSubfield = curOrderField.getSubfields(orderLocationSubfield);
//				if (detailedLocationSubfield.size() == 0){
//					//Didn't get detailed locations
//					if (curOrderField.getSubfield(orderCopiesSubfield) != null){
//						copies = Integer.parseInt(curOrderField.getSubfield(orderCopiesSubfield).getData());
//					}
//					String locationCode = "multi";
//					if (curOrderField.getSubfield(singleOrderLocationSubfield) != null){
//						locationCode = curOrderField.getSubfield(singleOrderLocationSubfield).getData().trim();
//					}
//					createAndAddOrderItem(recordInfo, curOrderField, locationCode, copies);
//				} else {
//					for (Subfield curLocationSubfield : detailedLocationSubfield) {
//						String curLocation = curLocationSubfield.getData();
//						if (curLocation.startsWith("(")) {
//							//There are multiple copies for this location
//							String tmpLocation = curLocation;
//							try {
//								copies = Integer.parseInt(tmpLocation.substring(1, tmpLocation.indexOf(")")));
//								curLocation = tmpLocation.substring(tmpLocation.indexOf(")") + 1).trim();
//							} catch (StringIndexOutOfBoundsException e) {
//								logger.error("Error parsing copies and location for order item " + tmpLocation);
//							}
//						} else {
//							//If we only get one location in the detailed copies, we need to read the copies subfield rather than
//							//hard coding to 1
//							copies = 1;
//							if (orderCopiesSubfield != ' ') {
//								if (detailedLocationSubfield.size() == 1 && curOrderField.getSubfield(orderCopiesSubfield) != null) {
//									String copiesData = curOrderField.getSubfield(orderCopiesSubfield).getData().trim();
//									try {
//										copies = Integer.parseInt(copiesData);
//									} catch (StringIndexOutOfBoundsException e) {
//										logger.error("StringIndexOutOfBoundsException loading number of copies " + copiesData, e);
//									} catch (Exception e) {
//										logger.error("Exception loading number of copies " + copiesData, e);
//									} catch (Error e) {
//										logger.error("Error loading number of copies " + copiesData, e);
//									}
//								}
//							}
//						}
//						if (createAndAddOrderItem(recordInfo, curOrderField, curLocation, copies)) {
//							//For On Order Items, increment popularity based on number of copies that are being purchased.
//							groupedWork.addPopularity(copies);
//						}
//					}
//				}
//			}
//		}
//		if (!recordInfo.getNumPrintCopies() > 0 && recordInfo.getNumCopiesOnOrder() > 0){
//			groupedWork.addKeywords("On Order");
//			groupedWork.addKeywords("Coming Soon");
//			/*//Don't do this anymore, see D-1893
//			HashSet<String> additionalOrderSubjects = new HashSet<>();
//			additionalOrderSubjects.add("On Order");
//			additionalOrderSubjects.add("Coming Soon");
//			groupedWork.addTopic(additionalOrderSubjects);
//			groupedWork.addTopicFacet(additionalOrderSubjects);*/
//		}
//	}

	private boolean isWildCardValue(String value) {
		return value.equals("9999");
	}

	private final HashMap<String, HashMap<RelevantLoanRule, LoanRuleDeterminer>> cachedRelevantLoanRules = new HashMap<>();
	private HashMap<RelevantLoanRule, LoanRuleDeterminer> getRelevantLoanRules(String iType, String locationCode, HashSet<Long> pTypesToCheck) {
		return getRelevantLoanRules(iType, locationCode, pTypesToCheck, null);
	}
	private HashMap<RelevantLoanRule, LoanRuleDeterminer> getRelevantLoanRules(String iType, String locationCode, HashSet<Long> pTypesToCheck, ItemInfo itemInfo) {
		//Look for ac cached value
		String                                        key               = iType + locationCode + pTypesToCheck.toString();
		HashMap<RelevantLoanRule, LoanRuleDeterminer> relevantLoanRules = cachedRelevantLoanRules.get(key);
		if (relevantLoanRules == null) {
			relevantLoanRules = new HashMap<>();
		} else {
			return relevantLoanRules;
		}

		HashSet<Long> pTypesNotAccountedFor = new HashSet<>(pTypesToCheck);
//		pTypesNotAccountedFor.addAll(pTypesToCheck);
		Long iTypeLong;
		if (iType == null) {
			iTypeLong = 9999L;
		} else {
			iTypeLong = Long.parseLong(iType);
		}

		boolean hasDefaultPType = pTypesToCheck.contains(-1L);
		for (LoanRuleDeterminer curDeterminer : loanRuleDeterminers) {
			if (curDeterminer.isActive()) {
				if (isWildCardValue(curDeterminer.getItemType()) || curDeterminer.getItemTypes().contains(iTypeLong)) {
					//logger.debug("    " + curDeterminer.getRowNumber() + " matches iType");

					if (hasDefaultPType || isWildCardValue(curDeterminer.getPatronType()) || isPTypeValid(curDeterminer.getPatronTypes(), pTypesNotAccountedFor)) {
						//logger.debug("    " + curDeterminer.getRowNumber() + " matches pType");


						//Make sure the location matches
//					if (curDeterminer.matchesLocation(locationCode)) {
						if (curDeterminer.matchesLocation(locationCode, itemInfo)) {
							// Matching location last so that we can debug lrd logic when the other factors are already a match.
							//logger.debug("    " + curDeterminer.getRowNumber() + " matches location");


							LoanRule loanRule = loanRules.get(curDeterminer.getLoanRuleId());
							relevantLoanRules.put(new RelevantLoanRule(loanRule, curDeterminer.getPatronTypes()), curDeterminer);

							//Stop once we have accounted for all ptypes
							if (isWildCardValue(curDeterminer.getPatronType())) {
								// 9999 accounts for all pTypes
								break;
							} else {
								pTypesNotAccountedFor.removeAll(curDeterminer.getPatronTypes());
								if (pTypesNotAccountedFor.isEmpty()) {
									break;
								}
							}

							//We want all relevant loan rules, do not break
							//break;
						}
					}
				}
			}
		}
		cachedRelevantLoanRules.put(key, relevantLoanRules);
		return relevantLoanRules;
	}

	private boolean isPTypeValid(HashSet<Long> determinerPatronTypes, HashSet<Long> pTypesToCheck) {
		//For our case,
		if (pTypesToCheck.isEmpty()){
			return true;
		}
		for (Long determinerPType : determinerPatronTypes){
			for (Long pTypeToCheck : pTypesToCheck){
				if (pTypeToCheck.equals(determinerPType)) {
					return true;
				}
			}
		}
		return false;
	}

	private final HashMap<String, HoldabilityInformation> holdabilityCache = new HashMap<>();
	@Override
	protected HoldabilityInformation isItemHoldable(ItemInfo itemInfo, Scope curScope, HoldabilityInformation isHoldableUnscoped) {
		//Check to make sure this isn't an unscoped record
		if (curScope.isUnscoped()){
			//This is an unscoped scope (everything should be holdable unless the location/itype/status is not holdable
			return isHoldableUnscoped;
		}else{
			//First check to see if the overall record is not holdable based on suppression rules
			if (isHoldableUnscoped.isHoldable()) {
				String                 locationCode = itemInfo.getLocationCode();
				String                 itemIType    = itemInfo.getITypeCode();
				String                 key          = curScope.getScopeName() + locationCode + itemIType;
				HoldabilityInformation cachedInfo   = holdabilityCache.get(key);
				if (cachedInfo == null){
					//HashMap<RelevantLoanRule, LoanRuleDeterminer> relevantLoanRules = getRelevantLoanRules(itemIType, locationCode, curScope.getRelatedNumericPTypes());
					HashMap<RelevantLoanRule, LoanRuleDeterminer> relevantLoanRules = getRelevantLoanRules(itemIType, locationCode, curScope.getRelatedNumericPTypes(), itemInfo);
					HashSet<Long> holdablePTypes = new HashSet<>();

					//Set back to false and then prove true
					boolean holdable = false;
					for (RelevantLoanRule loanRule : relevantLoanRules.keySet()) {
						if (loanRule.getLoanRule().getHoldable()) {
							holdablePTypes.addAll(loanRule.getPatronTypes());
							holdable = true;
						}
					}
					cachedInfo = new HoldabilityInformation(holdable, holdablePTypes);
					holdabilityCache.put(key, cachedInfo);
				}
				return cachedInfo;
			}else{
				return isHoldableUnscoped;
			}
		}
	}

	private final HashMap<String, BookabilityInformation> bookabilityCache = new HashMap<>();

	@Override
	protected BookabilityInformation isItemBookable(ItemInfo itemInfo, Scope curScope, BookabilityInformation isBookableUnscoped) {
		String locationCode = itemInfo.getLocationCode();
		String itemIType    = itemInfo.getITypeCode();
		String key          = curScope.getScopeName() + "-" + locationCode + "-" + itemIType;
		if (!bookabilityCache.containsKey(key)) {
			HashMap<RelevantLoanRule, LoanRuleDeterminer> relevantLoanRules = getRelevantLoanRules(itemIType, locationCode, curScope.getRelatedNumericPTypes());
			HashSet<Long> bookablePTypes = new HashSet<>();
			boolean isBookable = false;
			for (RelevantLoanRule loanRule : relevantLoanRules.keySet()) {
				if (loanRule.getLoanRule().getBookable()) {
					bookablePTypes.addAll(loanRule.getPatronTypes());
					isBookable = true;
				}
			}
			bookabilityCache.put(key, new BookabilityInformation(isBookable, bookablePTypes));
		}
		return bookabilityCache.get(key);
	}

	private final HashMap<String, HomePickUpInformation> homePickupCache = new HashMap<>();

	@Override
	protected HomePickUpInformation isItemHomePickUp(ItemInfo itemInfo, Scope curScope, HomePickUpInformation isHomePickUpUnscoped) {
		String locationCode = itemInfo.getLocationCode();
		String itemIType    = itemInfo.getITypeCode();
		String key          = curScope.getScopeName() + "-" + locationCode + "-" + itemIType;
		if (!homePickupCache.containsKey(key)) {
			HashMap<RelevantLoanRule, LoanRuleDeterminer> relevantLoanRules = getRelevantLoanRules(itemIType, locationCode, curScope.getRelatedNumericPTypes());
			HashSet<Long> homePickUpPTypes = new HashSet<>();
			boolean isHomePickup = false;
			for (RelevantLoanRule loanRule : relevantLoanRules.keySet()) {
				if (loanRule.getLoanRule().getIsHomePickup()) {
					homePickUpPTypes.addAll(loanRule.getPatronTypes());
					isHomePickup = true;
				}
			}
			homePickupCache.put(key, new HomePickUpInformation(isHomePickup, homePickUpPTypes));
		}
		return homePickupCache.get(key);
	}

	protected String getDisplayGroupedStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, true);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			String statusCode = itemInfo.getStatusCode();
			if (validCheckedOutStatusCodes.contains(statusCode)) {
				//We need to override based on due date
				if (isEmptyDueDate(itemInfo.getDueDate())) {
					return translateValue("item_grouped_status", statusCode, identifier);
				} else {
					return "Checked Out";
				}
			} else {
				return translateValue("item_grouped_status", statusCode, identifier);
			}
		}
	}

	protected String getDisplayStatus(ItemInfo itemInfo, RecordIdentifier identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			String statusCode = itemInfo.getStatusCode();
			if (validCheckedOutStatusCodes.contains(statusCode)) {
				//We need to override based on due date
				if (isEmptyDueDate(itemInfo.getDueDate())) {
					return translateValue("item_status", statusCode, identifier);
				} else {
					return "Checked Out";
				}
			} else {
				return translateValue("item_status", statusCode, identifier);
			}
		}
	}

	protected void setDetailedStatus(ItemInfo itemInfo, DataField itemField, String itemStatus, RecordIdentifier identifier) {
		//See if we need to override based on the last check in date
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			itemInfo.setDetailedStatus(overriddenStatus);
		}else {
			if (validCheckedOutStatusCodes.contains(itemStatus)) {
				if (isEmptyDueDate(itemInfo.getDueDate())) {
					itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
				}else{
					itemInfo.setDetailedStatus("Due " + getDisplayDueDate(itemInfo.getDueDate(), identifier));
				}
			} else {
				itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
			}
		}
	}

	public boolean isEmptyDueDate(String dueDate) {
		return dueDate == null || dueDate.isEmpty() || dueDate.trim().equals("-  -");
	}

	private SimpleDateFormat displayDateFormatter = new SimpleDateFormat("MMM d, yyyy");
	private String getDisplayDueDate(String dueDate, RecordIdentifier identifier){
		try {
			Date dateAdded = dueDateFormatter.parse(dueDate);
			return displayDateFormatter.format(dateAdded);
		}catch (Exception e){
			logger.warn("Could not load display due date for dueDate {} for identifier {}", dueDate, identifier, e);
		}
		return "Unknown";
	}

	protected void createAndAddOrderItem(GroupedWorkSolr groupedWork, RecordInfo recordInfo, OrderInfo orderItem, Record record) {
		ItemInfo itemInfo    = new ItemInfo();
		String   orderNumber = orderItem.getOrderRecordId();
		String   location    = orderItem.getLocationCode();
		if (location == null) {
			logger.warn("No location set for order {} skipping", orderNumber);
			return;
		}
		itemInfo.setLocationCode(location);
		itemInfo.setItemIdentifier(orderNumber);
		itemInfo.setNumCopies(orderItem.getNumCopies());
		itemInfo.setIsEContent(false);
		itemInfo.setIsOrderItem();
		itemInfo.setCallNumber("ON ORDER");
		itemInfo.setSortableCallNumber("ON ORDER");
		itemInfo.setDetailedStatus("On Order");
		itemInfo.setCollection("On Order");
		//Since we don't know when the item will arrive, assume it will come tomorrow.
		Date tomorrow = Date.from(new Date().toInstant().plus(1, ChronoUnit.DAYS));
		itemInfo.setDateAdded(tomorrow);

		//Format and Format Category should be set at the record level, so we don't need to set them here.

		//Add the library this is on order for
		itemInfo.setShelfLocation("On Order");

		String status = orderItem.getStatus();

		if (isOrderItemValid(status)) {
			recordInfo.addItem(itemInfo);
			if (logger.isDebugEnabled()) {
				logger.debug("Add order " + orderNumber + " to " + recordInfo.getFullIdentifier());
			}

			//For On Order Items, increment popularity based on number of copies that are being purchased.
			groupedWork.addPopularity(orderItem.getNumCopies());
		}
	}

	void loadLanguageDetails(GroupedWorkSolr groupedWork, Record record, HashSet<RecordInfo> ilsRecords, RecordIdentifier identifier) {
		// Note: ilsRecords are alternate manifestations for the same record, like for an order record or ILS econtent items

		HashSet<String> languageNames        = new HashSet<>();
		HashSet<String> translationsNames    = new HashSet<>();
		String          primaryLanguage      = null;

		String languageCode = MarcUtil.getFirstFieldVal(record, "008[35-37]");
		String languageName = languageCode == null ? null : indexer.translateSystemValue("language", languageCode, "008: " + identifier);

		if (hasSierraLanguageFixedField && (languageName == null || languageName.equals("Unknown") || languageName.equals(languageCode.trim()))) {
			// If we didn't have a translation for the 008 language field,
			// and we have settings for the sierra language fixed field,
			// use that instead
			languageCode = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + sierraFixedFieldLanguageSubField);
			languageName = languageCode == null ? null : indexer.translateSystemValue("language", languageCode, sierraRecordFixedFieldsTag + sierraFixedFieldLanguageSubField + ": " + identifier);
		}

		if (languageName != null && !languageName.equals(languageCode.trim())) {
			// The trim() is for some bad language codes that will have spaces on the ends
			languageNames.add(languageName);
			primaryLanguage = languageName;
		}

		List<DataField> languageDataFields = record.getDataFields("041");
		for (DataField languageData : languageDataFields) {
			if (languageData.getIndicator2() != '7') {
				// 041 with 2nd indicator of 7 denotes language codes other that the standard iso-639-2 (B) are used.
				// (We'll only look at standard codes)

				char firstIndicator = languageData.getIndicator1();
				if (firstIndicator == '0') {
					// Languages in title
					for (char subfield : Arrays.asList('a', 'b', 'd')) {
						// 041a Language code of text/sound track or separate title
						// 041b Language code of summary or abstract
						// 041d Language code of sung or spoken text

						for (Subfield languageSubfield : languageData.getSubfields(subfield)) {
							languageCode = languageSubfield.getData().trim();
							// Some end of tag subfields has trailing spaces
							int round = 1;
							do {
								// Multiple language codes can be smashed together in a single subfield,
								// so we need to parse each three letter code and process it.
								// (Note: this loop also has to handle for single entry language codes that
								// are less than three letters.[probably incorrect codes])

								// eg. 041	0		|d latger|e engfregerlat|h gerlat
								// eg. 041	0		|d engyidfrespaapaund|e engyidfrespaapa|g eng

								final int length = languageCode.length();
								String    code   = length > 3 ? languageCode.substring(0, 3) : languageCode;
								languageName = indexer.translateSystemValue("language", code, "041" + subfield + " " + identifier);
								if (languageName != null && !languageName.equals(code.trim())) {
									// Don't allow untranslated language codes into the facet but do allow codes
									// that have been translated to "Unknown",etc
									languageNames.add(languageName);

									if (primaryLanguage == null && subfield == 'a' && round == 1) {
										// Set primary Language and language boosts if we haven't found a good value yet
										// Only use the first 041a language code for the primary language and boosts
										primaryLanguage = languageName;
									}
								}
								if (length >= 3) {
									languageCode = languageCode.substring(3);
									// truncate the subfield data for the next round
									// the last round with multiple language codes will be exactly 3 long,
									// so need to cut to 0-length to break out of loop.
								}
								round++;
							} while (languageCode.length() >= 3);
						}
					}
				} else if (firstIndicator == '1') {
					// Translations
					for (char subfield : Arrays.asList('b', 'd', 'j')) {
						// 041b Language code of summary or abstract
						// 041d Language code of sung or spoken text
						// 041j Language code of subtitles

						for (Subfield languageSubfield : languageData.getSubfields(subfield)) {
							languageCode = languageSubfield.getData().trim();
							// Some end of tag subfields has trailing spaces
							do {
								// Multiple language codes can be smashed together in a single subfield,
								// so we need to parse each three letter code and process it.
								// (Note: this loop also has to handle for single entry language codes that
								// are less than three letters.[probably incorrect codes])

								final int length = languageCode.length();
								String    code   = length > 3 ? languageCode.substring(0, 3) : languageCode;
								languageName = indexer.translateSystemValue("language", code, "041" + subfield + " " + identifier);
								if (languageName != null && !languageName.equals(code.trim())) {
									translationsNames.add(languageName);
								}
								if (length >= 3) {
									languageCode = languageCode.substring(3);
									// truncate the subfield data for the next round
									// the last round with multiple language codes will be exactly 3 long,
									// so need to cut to 0-length to break out of loop.
								}
							} while (languageCode.length() >= 3);
						}
					}
				}
			}
		}

		//Check to see if we have Unknown plus a valid value
		if (languageNames.size() > 1) {
			languageNames.remove("Unknown");
		}

		for (RecordInfo ilsRecord : ilsRecords) {
			if (primaryLanguage != null) {
				ilsRecord.setPrimaryLanguage(primaryLanguage);
			}
			ilsRecord.setLanguages(languageNames);
			ilsRecord.setTranslations(translationsNames);
		}
	}

	@Override
	protected void updateLastExtractTimeForRecord(String identifier) {
		if (identifier != null && !identifier.isEmpty()) {
			try {
				updateExtractInfoStatement.setInt(1, indexingProfileId);
				updateExtractInfoStatement.setString(2, identifier);
				updateExtractInfoStatement.setNull(3, Types.INTEGER);
				int result = updateExtractInfoStatement.executeUpdate();
			} catch (SQLException e) {
				logger.error("Unable to update ils_extract_info table for {}", identifier, e);
			}
		}
	}

}
