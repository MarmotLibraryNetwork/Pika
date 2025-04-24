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
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.*;
import java.util.regex.Pattern;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   2/12/2020
 */
public class FormatDetermination {
	protected Logger logger;

	HashMap<String, TranslationMap> translationMaps;

	String profileType;
	String formatSource;
	String specifiedFormat;
	String specifiedFormatCategory;
	int    specifiedFormatBoost;
	String formatDeterminationMethod;
	char   formatSubfield;

	String  itemTag;
	char    iTypeSubfield;
	char    collectionSubfield;
	char    statusSubfieldIndicator;
	char    locationSubfieldIndicator;
	boolean useICode2Suppression;
	char    iCode2Subfield;
	Pattern statusesToSuppressPattern    = null;
	Pattern locationsToSuppressPattern   = null;
	Pattern collectionsToSuppressPattern = null;
	Pattern iTypesToSuppressPattern      = null;
	Pattern iCode2sToSuppressPattern     = null;

	String sierraRecordFixedFieldsTag;
	String materialTypeSubField;
	String matTypesToIgnore;

	private Character typeOfRecordLeaderChar = null;

	FormatDetermination(ResultSet indexingProfileRS, HashMap<String, TranslationMap> translationMaps, Logger logger) throws SQLException {
		this.logger = logger;
		this.translationMaps = translationMaps;

		profileType               = indexingProfileRS.getString("name");
		formatSource              = indexingProfileRS.getString("formatSource");
		formatDeterminationMethod = indexingProfileRS.getString("formatDeterminationMethod");
		if (formatDeterminationMethod == null) {
			formatDeterminationMethod = "bib";
		}
		matTypesToIgnore = indexingProfileRS.getString("materialTypesToIgnore");
		if (matTypesToIgnore == null) {
			matTypesToIgnore = "";
		}
		specifiedFormat         = indexingProfileRS.getString("specifiedFormat");
		specifiedFormatCategory = indexingProfileRS.getString("specifiedFormatCategory");
		specifiedFormatBoost    = indexingProfileRS.getInt("specifiedFormatBoost");
		formatSubfield          = getSubfieldIndicatorFromConfig(indexingProfileRS, "format");

		sierraRecordFixedFieldsTag = indexingProfileRS.getString("sierraRecordFixedFieldsTag");
		materialTypeSubField       = indexingProfileRS.getString("materialTypeField");

		itemTag                   = indexingProfileRS.getString("itemTag");
		iTypeSubfield             = getSubfieldIndicatorFromConfig(indexingProfileRS, "iType");
		statusSubfieldIndicator   = getSubfieldIndicatorFromConfig(indexingProfileRS, "status");
		iCode2Subfield            = getSubfieldIndicatorFromConfig(indexingProfileRS, "iCode2");
		useICode2Suppression      = indexingProfileRS.getBoolean("useICode2Suppression");
		collectionSubfield        = getSubfieldIndicatorFromConfig(indexingProfileRS, "collection");
		locationSubfieldIndicator = getSubfieldIndicatorFromConfig(indexingProfileRS, "location");

		String locationsToSuppress = indexingProfileRS.getString("locationsToSuppress");
		if (locationsToSuppress != null && !locationsToSuppress.isEmpty()) {
			locationsToSuppressPattern = Pattern.compile(locationsToSuppress);
		}
		String collectionsToSuppress = indexingProfileRS.getString("collectionsToSuppress");
		if (collectionsToSuppress != null && !collectionsToSuppress.isEmpty()) {
			collectionsToSuppressPattern = Pattern.compile(collectionsToSuppress);
		}
		String statusesToSuppress = indexingProfileRS.getString("statusesToSuppress");
		if (statusesToSuppress != null && !statusesToSuppress.isEmpty()) {
			statusesToSuppressPattern = Pattern.compile(statusesToSuppress);
		}
		String iTypesToSuppress = indexingProfileRS.getString("iTypesToSuppress");
		if (iTypesToSuppress != null && !iTypesToSuppress.isEmpty()) {
			iTypesToSuppressPattern = Pattern.compile(iTypesToSuppress);
		}
		String iCode2sToSuppress = indexingProfileRS.getString("iCode2sToSuppress");
		if (iCode2sToSuppress != null && !iCode2sToSuppress.isEmpty()) {
			iCode2sToSuppressPattern = Pattern.compile(iCode2sToSuppress);
		}

	}

	private char getSubfieldIndicatorFromConfig(ResultSet indexingProfileRS, String subfieldName) throws SQLException{
		String subfieldString = indexingProfileRS.getString(subfieldName);
		char subfield = ' ';
		if (!indexingProfileRS.wasNull() && !subfieldString.isEmpty())  {
			subfield = subfieldString.charAt(0);
		}
		return subfield;
	}

	/**
	 * Determine Record Format(s)
	 */
	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
		//We should already have formats based on the items

		switch (formatSource) {
			case "specified":
				if (!specifiedFormat.isEmpty()) {
					HashSet<String> translatedFormats = new HashSet<>();
					translatedFormats.add(specifiedFormat);
					HashSet<String> translatedFormatCategories = new HashSet<>();
					translatedFormatCategories.add(specifiedFormatCategory);
					recordInfo.addFormats(translatedFormats);
					recordInfo.addFormatCategories(translatedFormatCategories);
					recordInfo.setFormatBoost(specifiedFormatBoost);
				} else {
					logger.error("Specified Format is not set in indexing profile. Can not use specified format for format determination. Fall back to bib format determination.");
					loadPrintFormatFromBib(recordInfo, record);
				}
				break;
			case "item":
				loadPrintFormatFromITypes(recordInfo, record);
				break;
			default:
				if (formatDeterminationMethod.equalsIgnoreCase("matType")) {
					loadPrintFormatFromMatType(recordInfo, record);
				} else {
					// Format source presumed to be "bib" by default
					loadPrintFormatFromBib(recordInfo, record);
				}
				break;
		}
	}

	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		if (formatSource.equals("specified")){
			HashSet<String> translatedFormats          = new HashSet<>();
			HashSet<String> translatedFormatCategories = new HashSet<>();
			translatedFormats.add(specifiedFormat);
			translatedFormatCategories.add(specifiedFormatCategory);
			econtentRecord.addFormats(translatedFormats);
			econtentRecord.addFormatCategories(translatedFormatCategories);
			econtentRecord.setFormatBoost(specifiedFormatBoost);
		} else {
			LinkedHashSet<String> printFormats = getFormatsFromBib(record, econtentRecord);
			if (!this.translationMaps.isEmpty()){
				String firstFormat    = printFormats.iterator().next();
				String formatBoostStr = translateValue("format_boost", firstFormat, econtentRecord.getRecordIdentifier());
				econtentItem.setFormat(translateValue("format", firstFormat, econtentRecord.getRecordIdentifier()));
				econtentItem.setFormatCategory(translateValue("format_category", firstFormat, econtentRecord.getRecordIdentifier()));
				try {
					long formatBoost = Long.parseLong(formatBoostStr);
					econtentRecord.setFormatBoost(formatBoost);
				}catch (Exception e){
					logger.warn("Unable to parse format boost {} for format {} {}", formatBoostStr, firstFormat, econtentRecord.getFullIdentifier());
					econtentRecord.setFormatBoost(1);
				}
			} else {
				//Convert formats from print to eContent version
				for (String format : printFormats) {
					switch (format.toLowerCase()) {
						case "adultliteracybook":
							econtentItem.setFormat("Adult Literacy eBook");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(10);
							break;
						case "graphicnovel":
						case "ecomic":
							econtentItem.setFormat("eComic");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(10);
							break;
						case "ebook":
						case "book":
						case "bookwithcdrom":
						case "bookwithdvd":
						case "bookwithaudiocd":
						case "bookwithvideodisc":
						case "largeprint":
						case "illustratededition":
						case "manuscript":
						case "thesis":
						case "print":
						case "microfilm":
						case "kit":
						case "boardbook":
							econtentItem.setFormat("eBook");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(10);
							break;
						case "journal":
						case "serial":
							econtentItem.setFormat("eMagazine");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(3);
							break;
						case "soundrecording":
						case "sounddisc":
						case "sounddiscwithcdrom":
						case "playaway":
						case "cdrom":
						case "soundcassette":
						case "compactdisc":
						//case "chipcartridge":
						case "mp3disc":
						case "mp3":
						case "eaudio":
							econtentItem.setFormat("eAudiobook");
							econtentItem.setFormatCategory("Audio Books");
							econtentRecord.setFormatBoost(8);
							break;
						case "musiccd":
						case "musicrecording":
							econtentItem.setFormat("eMusic");
							econtentItem.setFormatCategory("Music");
							econtentRecord.setFormatBoost(5);
							break;
						case "musicalscore":
							econtentItem.setFormat("Musical Score");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(5);
							break;
						case "movies":
						case "video":
						case "dvd":
						case "videodisc":
						case "4kultrablu-ray" :
						case "dvdblu-raycombo":
						case "blu-ray4kcombo":
						case "playawayview":
							econtentItem.setFormat("eVideo");
							econtentItem.setFormatCategory("Movies");
							econtentRecord.setFormatBoost(10);
							break;
						case "photo":
							econtentItem.setFormat("Photo");
							//econtentItem.setFormatCategory("Other"); // Have no format category rather than other
							econtentRecord.setFormatBoost(2);
							break;
						case "atlas":
						case "map":
							econtentItem.setFormat("Map");
							//econtentItem.setFormatCategory("Other"); // Have no format category rather than other
							econtentRecord.setFormatBoost(2);
							break;
						case "newspaper":
							econtentItem.setFormat("Newspaper");
							econtentItem.setFormatCategory("eBook");
							econtentRecord.setFormatBoost(2);
							break;
						default:
							logger.warn("Could not find appropriate eContent format for {} while side loading eContent {}", format, econtentRecord.getFullIdentifier());
							// Use the generic format determination for the cases below
						case "electronic":
						case "software":
						case "mixedmaterials":
							// bad electronic format determinations, likely from 007 codes
						case "tapereel":
						case "tapecassette":
						case "tapecartridge":
						case "disccartridge":
						case "chipcartridge":
						case "floppydisk":
							// Mis-determined game formats of econtent
						case "playstation":
						//case "xbox":
						case "xbox360":
							econtentItem.setFormat("Online Materials");
							//econtentItem.setFormatCategory("Other"); // Have no format category rather than other
							econtentRecord.setFormatBoost(2);
					}
				}
			}
		}
	}

	void loadPrintFormatFromBib(RecordInfo recordInfo, Record record) {
		LinkedHashSet<String> printFormats = getFormatsFromBib(record, recordInfo);

		/*for(String format: printFormats){
			logger.debug("Print formats from bib:");
			logger.debug("    " + format);
		}*/
		HashSet<String> translatedFormats = translateCollection("format", printFormats, recordInfo.getRecordIdentifier());
		if (translatedFormats.isEmpty()){
			logger.warn("Did not find a format for {} using standard format method {}", recordInfo.getRecordIdentifier(), printFormats.toString());
		}
		HashSet<String> translatedFormatCategories = translateCollection("format_category", printFormats, recordInfo.getRecordIdentifier());
		recordInfo.addFormats(translatedFormats);
		recordInfo.addFormatCategories(translatedFormatCategories);
		long            formatBoost  = 0L;
		HashSet<String> formatBoosts = translateCollection("format_boost", printFormats, recordInfo.getRecordIdentifier());
		for (String tmpFormatBoost : formatBoosts) {
			if (Util.isNumeric(tmpFormatBoost)) {
				formatBoost = Math.max(formatBoost, Long.parseLong(tmpFormatBoost));
			} else {
				logger.warn("Format boost invalid for format {} profile {} for {}", tmpFormatBoost, profileType, recordInfo.getRecordIdentifier());
			}
		}
		recordInfo.setFormatBoost(formatBoost);
	}

	private void loadPrintFormatFromMatType(RecordInfo recordInfo, Record record) {
		if (sierraRecordFixedFieldsTag != null && !sierraRecordFixedFieldsTag.isEmpty()) {
			if (materialTypeSubField != null && !materialTypeSubField.isEmpty()) {
				String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
				if (matType != null) {
					if (!isMatTypeToIgnore(matType)) {
						String translatedFormat = translateValue("format", matType, recordInfo.getRecordIdentifier());
						if (translatedFormat != null && !translatedFormat.equals(matType)) {
							recordInfo.addFormat(translatedFormat);
							String translatedFormatCategory = translateValue("format_category", matType, recordInfo.getRecordIdentifier());
							if (translatedFormatCategory != null && !translatedFormatCategory.equals(matType)) {
								recordInfo.addFormatCategory(translatedFormatCategory);
							}
							// use translated value
							String formatBoost = translateValue("format_boost", matType, recordInfo.getRecordIdentifier());
							try {
								long tmpFormatBoostLong = Long.parseLong(formatBoost);
								recordInfo.setFormatBoost(tmpFormatBoostLong);
								return;
							} catch (NumberFormatException e) {
								logger.warn("Could not load format boost for format {} profile {} for {}", formatBoost, profileType, recordInfo.getRecordIdentifier() + "; Falling back to default format determination process");
							}
						} else {
							logger.info("Material Type {} had no translation, falling back to default format determination.", matType);
						}
					} else {
						logger.info("Material Type for {} has ignored value '{}', falling back to default format determination.", recordInfo.getRecordIdentifier(), matType);
					}
				} else {
					logger.info("{} did not have a material type, falling back to default format determination.", recordInfo.getRecordIdentifier());
				}
			} else {
				logger.error("The materialTypeSubField is not set. Material Type format determination skipped.");
			}
		} else {
			logger.error("The sierraRecordFixedFieldsTag is not set. Material Type format determination skipped.");
		}

		// Fall back to the format determination based on the Bib when the Material Type format determination failed
		loadPrintFormatFromBib(recordInfo, record);
	}

	private boolean isMatTypeToIgnore(String matType) {
		return matType.isEmpty() || matType.equals("-") || matType.equals(" ") || matTypesToIgnore.indexOf(matType.charAt(0)) >= 0;
	}

	public void loadPrintFormatFromITypes(RecordInfo recordInfo, Record record) {
		HashMap<String, Integer> itemCountsByItype = new HashMap<>();
		HashMap<String, String>  itemTypeToFormat  = new HashMap<>();
		int                      mostUsedCount     = 0;
		String                   mostPopularIType  = "";  //Get a list of all the formats based on the items
		RecordIdentifier         recordIdentifier  = recordInfo.getRecordIdentifier();
		List<DataField> items = MarcUtil.getDataFields(record, itemTag);
		for(DataField item : items){
			if (!isItemSuppressed(item)) {
				Subfield iTypeSubField = item.getSubfield(iTypeSubfield);
				if (iTypeSubField != null) {
					String iType = iTypeSubField.getData().toLowerCase();
					if (itemCountsByItype.containsKey(iType)) {
						itemCountsByItype.put(iType, itemCountsByItype.get(iType) + 1);
					} else {
						itemCountsByItype.put(iType, 1);
						//Translate the iType to see what formats we get.  Some item types do not have a format by default and use the default translation
						//We still will want to record those counts.
						String translatedFormat = translateValue("format", iType, recordIdentifier);
						//If the format is book, ignore it for now.  We will use the default method later.
						if (translatedFormat == null || translatedFormat.equalsIgnoreCase("book")) {
							translatedFormat = "";
						}
						itemTypeToFormat.put(iType, translatedFormat);
					}

					if (itemCountsByItype.get(iType) > mostUsedCount) {
						mostPopularIType = iType;
						mostUsedCount = itemCountsByItype.get(iType);
					}
				}
			}
		}

		if (itemTypeToFormat.isEmpty() || itemTypeToFormat.get(mostPopularIType) == null || itemTypeToFormat.get(mostPopularIType).isEmpty()){
			//We didn't get any formats from the collections, get formats from the base method (007, 008, etc).
			//logger.debug("All formats are books or there were no formats found, loading format information from the bib");
			loadPrintFormatFromBib(recordInfo, record);
		} else{
			//logger.debug("Using default method of loading formats from iType");
			recordInfo.addFormat(itemTypeToFormat.get(mostPopularIType));
			String translatedFormatCategory = translateValue("format_category", mostPopularIType, recordIdentifier);
			if (translatedFormatCategory == null){
				translatedFormatCategory = translateValue("format_category", itemTypeToFormat.get(mostPopularIType), recordIdentifier);
				if (translatedFormatCategory == null){
					translatedFormatCategory = mostPopularIType;
				}
			}
			recordInfo.addFormatCategory(translatedFormatCategory);
			long   formatBoost    = 1L;
			String formatBoostStr = translateValue("format_boost", mostPopularIType, recordIdentifier);
//			if (formatBoostStr == null){
//				formatBoostStr = translateValue("format_boost", itemTypeToFormat.get(mostPopularIType), recordIdentifier);
//			}
			if (Util.isNumeric(formatBoostStr)) {
				formatBoost = Long.parseLong(formatBoostStr);
			}
			recordInfo.setFormatBoost(formatBoost);
		}
	}

	protected boolean isItemSuppressed(DataField curItem) {
		if (statusSubfieldIndicator != ' ') {
			Subfield statusSubfield = curItem.getSubfield(statusSubfieldIndicator);
			if (statusSubfield == null) { // suppress if subfield is missing
				return true;
			} else {
				String status = statusSubfield.getData().trim();
				if (statusesToSuppressPattern != null && statusesToSuppressPattern.matcher(status).matches()) {
					return true;
				}
			}
		}
		if (locationSubfieldIndicator != ' ') {
			Subfield locationSubfield = curItem.getSubfield(locationSubfieldIndicator);
			if (locationSubfield == null){ // suppress if subfield is missing
				return true;
			}else{
				if (locationsToSuppressPattern != null && locationsToSuppressPattern.matcher(locationSubfield.getData().trim()).matches()){
					return true;
				}
			}
		}
		if (collectionSubfield != ' '){
			Subfield collectionSubfieldValue = curItem.getSubfield(collectionSubfield);
			if (collectionSubfieldValue == null){ // suppress if subfield is missing
				return true;
			}else{
				if (collectionsToSuppressPattern != null && collectionsToSuppressPattern.matcher(collectionSubfieldValue.getData().trim()).matches()){
					return true;
				}
			}
		}
		if (iTypeSubfield != ' '){
			Subfield iTypeSubfieldValue = curItem.getSubfield(iTypeSubfield);
			if (iTypeSubfieldValue == null){ // suppress if subfield is missing
				return true;
			}else{
				String iType = iTypeSubfieldValue.getData().trim();
				if (iTypesToSuppressPattern != null && iTypesToSuppressPattern.matcher(iType).matches()) {
					logger.debug("Item record is suppressed due to Itype {}", iType);
					return true;
				}
			}
		}
		if (useICode2Suppression && iCode2Subfield != ' ') {
			Subfield icode2Subfield = curItem.getSubfield(iCode2Subfield);
			if (icode2Subfield != null) {
				String iCode2 = icode2Subfield.getData().toLowerCase().trim();

				//Suppress iCode2 codes
				if (iCode2sToSuppressPattern != null && iCode2sToSuppressPattern.matcher(iCode2).matches()) {
					logger.debug("Item record is suppressed due to ICode2 {}", iCode2);
					return true;
				}
			}
		}

		return false;
	}

	LinkedHashSet<String> getFormatsFromBib(Record record, RecordInfo recordInfo){
		LinkedHashSet<String> printFormats = new LinkedHashSet<>();
		RecordIdentifier      identifier   = recordInfo.getRecordIdentifier();
		String                leader       = record.getLeader().toString();

		typeOfRecordLeaderChar = leader.length() >= 6 ? Character.toLowerCase(leader.charAt(6)) : null;

		// check for music recordings quickly so we can figure out if it is music
		// for category (need to do here since checking what is on the Compact
		// Disc/Phonograph, etc. is difficult).
		if (typeOfRecordLeaderChar != null) {
			if (typeOfRecordLeaderChar.equals('j')) {
				printFormats.add("MusicRecording");
			}
			else if (typeOfRecordLeaderChar.equals('r')) {
				printFormats.add("PhysicalObject");
			} else if (typeOfRecordLeaderChar.equals('o')) {
				printFormats.add("Kit");
				//skipOtherDeterminations = true;
				// Book Club Kit should supersede Kit
			}
		}
		getFormatFromPublicationInfo(record, printFormats);
		getFormatFromNotes(record, printFormats);
		getFormatFromEdition(record, printFormats, identifier);
		getFormatFromPhysicalDescription(record, printFormats, identifier);
		getFormatFromSubjects(record, printFormats);
		getFormatFromTitle(record, printFormats);
		getFormatFromDigitalFileCharacteristics(record, printFormats);
		getGameFormatFrom753(record, printFormats);

		if (printFormats.isEmpty()) {
			//Only get from fixed field information if we don't have anything yet since the cataloging of
			//fixed fields is not kept up to date reliably.  #D-87
			getFormatFrom007(record, printFormats);
			if (printFormats.isEmpty()) {
				ControlField fixedField008 = (ControlField) record.getVariableField("008");
				getFormatFrom008(fixedField008, printFormats);
				getFormatFromLeader(printFormats, leader, fixedField008);
				if (printFormats.size() > 1){
					if (logger.isInfoEnabled()) {
						logger.info("Found more than 1 format for {} looking at just the leader: {}", recordInfo.getFullIdentifier(), String.join(",", printFormats));
					}
				}
			} else if (printFormats.size() > 1){
				logger.info("Found more than 1 format for {} looking at just 007", recordInfo.getFullIdentifier());
			}
		}

		if (printFormats.isEmpty()){
			//if (fullReindex) {
			//	logger.info("Did not get any formats for record {}, assuming it is a book ", recordInfo.getFullIdentifier());
			//}
			printFormats.add("Book");
		}else if (logger.isDebugEnabled()){
			logger.debug("Pre-filtering found formats " + String.join(",", printFormats));
		}
		accompanyingMaterialCheck(record, printFormats); //TODO: can this go before printFormats.isEmpty check?
		filterPrintFormats(printFormats, record, identifier);

		if (printFormats.size() > 1){
			String formatsString = String.join(",", printFormats);
			if (!formatsToFilter.contains(formatsString)){
				formatsToFilter.add(formatsString);
				if (logger.isInfoEnabled()) {
					logger.info("Found more than 1 format for {} - {}", recordInfo.getFullIdentifier(), formatsString);
				}
			}
		}
		return printFormats;
	}

	private final HashSet<String> formatsToFilter = new HashSet<>();

	private void getFormatFromDigitalFileCharacteristics(Record record, LinkedHashSet<String> printFormats) {
		Set<String> fields = MarcUtil.getFieldList(record, "347b");
		for (String curField : fields){
			if (find4KUltraBluRayPhrases(curField))
				printFormats.add("4KUltraBlu-Ray");
			if (curField.equalsIgnoreCase("Blu-Ray")){
				printFormats.add("Blu-ray");
			}else if (curField.equalsIgnoreCase("DVD-ROM") || curField.equalsIgnoreCase("DVDROM")){
				printFormats.add("CDROM"); //TODO: should be determined as format dvd-rom (wouldn't work in cd-rom player) TODO: add exclusion check for CD ROM eg. "CD-ROM or DVD-ROM drive"
			}else if (curField.equalsIgnoreCase("DVD video")){
				printFormats.add("DVD");
			}
		}
	}

	private void accompanyingMaterialCheck(Record record, LinkedHashSet<String> printFormats){
		if (typeOfRecordLeaderChar != null){
			if (typeOfRecordLeaderChar == 'a') {
				// Language material  (text/books generally)
				if (printFormats.contains("CDROM")) {
					printFormats.clear();
					printFormats.add("BookWithCDROM");
					return;
				}
				if (printFormats.contains("CompactDisc")){
					// Likely coming from an 007 "sd f"
					printFormats.clear();
					printFormats.add("BookWithAudioCD");
					return;
				}
//				else if (typeOfRecordLeaderChar == 'm') {
//					// Computer file : classes of electronic resources: computer software (including programs, games, fonts)
//					// Games is of concern here
//					if (printFormats.contains("XboxOne")) {
//						if (printFormats.contains("SoundDisc")) {
//							printFormats.remove("SoundDisc");
//						}
//					}
//				}
			}
		}

		List<DataField> physicalDescriptions = record.getDataFields("300");
		for (DataField physicalDescription : physicalDescriptions) {
			if (physicalDescription != null) {
				if (physicalDescription.getSubfield('e') != null) {
					String accompanying = physicalDescription.getSubfield('e').getData().toLowerCase();
					if (accompanying.contains("dvd-rom")) {
						if (printFormats.contains("Book") || printFormats.contains("BookWithCDROM")) {
							printFormats.clear();
							printFormats.add("BookWithDVDROM");
						}
					} else if (accompanying.contains("dvd") || accompanying.contains("videodisc")) {
						if (printFormats.contains("Book")) {
							printFormats.clear();
							printFormats.add("BookWithDVD");
						}else if (printFormats.contains("MusicCD")) {
							printFormats.clear();
							printFormats.add("MusicCDWithDVD");
						} else if (printFormats.contains("DVD")) //TODO: test
						{
							if (physicalDescription.getSubfield('a') != null) {
								String mainPhysical = physicalDescription.getSubfield('a').getData().toLowerCase();
								if (mainPhysical.contains("pages") || mainPhysical.contains("p.") || mainPhysical.contains("pgs")) {
									printFormats.clear();
									printFormats.add("BookWithDVD");
								}
							}
						}
						//printFormats.add("DVD"); This appears to be an unneeded determination.
						// If it's needed an explanation needs to provided here
					} else if (accompanying.contains("book") && !accompanying.contains("booklet") && !accompanying.contains("ebook") && !accompanying.contains("e-book")) {
						if (printFormats.contains("SoundDisc")) {
							printFormats.clear();
							printFormats.add("BookWithAudioCD");
						}
					} else if (accompanying.contains("audio disc")) {
						if (printFormats.contains("Book")) {
							printFormats.clear();
							printFormats.add("BookWithAudioCD");
						}
					}
				}
			}
		}
	}

	/**
	 * @param overridingFormat If this format is present, printFormats will be cleared and set
	 *                         to the overriding format
	 * @param printFormats All the format determinations to filter through
	 * @return Whether the overriding format was found
	 */
	private boolean hasOverRidingFormat(String overridingFormat, Set<String> printFormats) {
		if (printFormats.contains(overridingFormat)) {
			printFormats.clear();
			printFormats.add(overridingFormat);
			return true;
		}
		return false;
	}

	private void filterPrintFormats(Set<String> printFormats, Record record, RecordIdentifier identifier) {
		if (printFormats.size() == 1) {
			return;
		}

		if (printFormats.contains("BookClubKit")){
			if (logger.isDebugEnabled() && printFormats.contains("LargePrint")){
				logger.debug("Book club bib {} also had large print determination", identifier);
			}
			// BookClubKit needs to trump Kit
			printFormats.clear();
			printFormats.add("BookClubKit");
			return;
		}
		if (hasOverRidingFormat("Kit", printFormats)) {
				return;
		}
		if (hasOverRidingFormat("Archival Materials", printFormats)) {
				return;
		}
		if (hasOverRidingFormat("Thesis", printFormats)) {
				return;
		}
		if (hasOverRidingFormat("Braille", printFormats)) {
				return;
		}
		if (hasOverRidingFormat("Phonograph", printFormats)) {
				return;
		}

		// Read-Along things
		if (hasOverRidingFormat("VoxBooks", printFormats)) {
			return;
		}

		if (printFormats.contains("WonderBook")){
			// This should come before Play Away because wonderbooks will get mis-determined as playaway
			if (printFormats.contains("PlayStation3")) {
				// There is PS3 game with a "wonderbook" controller. The game is called "Wonderbook. Book of potions"
				printFormats.remove("WonderBook");
			} else {
				printFormats.clear();
				printFormats.add("WonderBook");
				return;
			}
		}

		// Playaway Launchpad
		if (hasOverRidingFormat("PlayawayLaunchpad", printFormats)) {
			return;
		}

		// AudioBook Devices
		if (hasOverRidingFormat("PlayawayView", printFormats)) {
			return;
		}
		if (hasOverRidingFormat("Playaway", printFormats)) {
			return;
		}
		if (hasOverRidingFormat("GoReader", printFormats)) {
			return;
		}
		if (printFormats.contains("YotoStory")){
			if (hasOverRidingFormat("YotoMusic", printFormats)) {
				// If we have both Yoto formats, assume music is better.
				return;
			}

			// This should filter out PhysicalObject, YotoStory
			// Note: General need to be careful with Yoto Player records that should have determination of Physical Object
			printFormats.clear();
			printFormats.add("YotoStory");
			return;
		}

		// Video Things
		if (hasOverRidingFormat("Blu-ray4KCombo", printFormats)) {
			// Check this before DVD/Blu-ray Combo checking because this combo can be confused for the other combo
			return;
		}
		if (printFormats.contains("DVD") || printFormats.contains("Blu-ray")) {
			if (isComboPack(record)) {
				printFormats.clear();
				printFormats.add("DVDBlu-rayCombo");
				return;
			}
			// possible DVD with CD
			//300  |a 1 videodisc (60 min.) : |b sd., col. ; |c 4 3/4 in. + 1 compact disc (4 3/4 in.) (51 min.)
			printFormats.remove("CompactDisc");
		}
		if (printFormats.contains("Video")){
			if (printFormats.contains("DVD")
					|| printFormats.contains("VideoDisc")
					|| printFormats.contains("VideoCassette")
			) {
				printFormats.remove("Video");
			}
		}
		if (printFormats.contains("CDROM") && (
						printFormats.contains("DVD")
						|| printFormats.contains("VideoDisc")
						|| printFormats.contains("MusicCD")  // Result of Enhanced music CDs
		)){
			printFormats.remove("CDROM");
		}
		if (printFormats.contains("WindowsGame") && printFormats.contains("VideoDisc")){
			printFormats.remove("WindowsGame");
		}
		if (printFormats.contains("VideoDisc")){
			if (printFormats.contains("Blu-ray")
					|| printFormats.contains("DVD")
					|| printFormats.contains("4KUltraBlu-Ray")
			) {
				printFormats.remove("VideoDisc");
			}
		}
		if (printFormats.contains("VideoCassette") && printFormats.contains("DVD")){
			printFormats.remove("VideoCassette");
		}
		if (printFormats.contains("DVD") && printFormats.contains("Blu-ray")){
			printFormats.remove("DVD");
		}
		if (printFormats.contains("Blu-ray") && printFormats.contains("4KUltraBlu-Ray")){
			printFormats.remove("Blu-ray");
		}
		if (printFormats.contains("GraphicNovel") && printFormats.contains("DVD")){
			printFormats.remove("GraphicNovel");
		}

		// Sound Things
		if (printFormats.contains("SoundCassette") && printFormats.contains("MusicRecording")){
			printFormats.clear();
			printFormats.add("MusicCassette");
			return;
		}
		if (printFormats.contains("SoundDisc") && printFormats.contains("MusicRecording")) {
			// This is likely music phonographs, which get determined as music recordings
			printFormats.remove("SoundDisc");
		}
		if (printFormats.contains("CompactDisc") && printFormats.contains("MusicCD")){
			printFormats.remove("CompactDisc");
		}
		if (printFormats.contains("MusicRecording") && (printFormats.contains("CD") || printFormats.contains("CompactDisc"))){
			if (printFormats.contains("DVD")) {
				// Probable Accompanying Material
				printFormats.clear();
				printFormats.add("MusicCDWithDVD");
			} else if (printFormats.contains("Blu-ray")) {
				//Probable Accompanying Material
				printFormats.clear();
				printFormats.add("MusicCDWithBluRay");
			} else {
				printFormats.clear();
				printFormats.add("MusicCD");
			}
			return;
		}
		if (printFormats.contains("SoundRecording") && printFormats.contains("CDROM")){
			printFormats.clear();
			printFormats.add("SoundDisc");
		}
		if (printFormats.contains("SoundRecording")) {
			if (printFormats.contains("SoundDisc")
					|| printFormats.contains("CompactDisc")
					|| printFormats.contains("SoundCassette")
			) {
				printFormats.remove("SoundRecording");
			}
		}
		if (printFormats.contains("SoundDisc")) {
			if (printFormats.contains("MP3")) {
				printFormats.clear();
				printFormats.add("MP3Disc");
				return;
			}
			if (printFormats.contains("CDROM") || printFormats.contains("WindowsGame")) {
				printFormats.remove("CDROM");
				printFormats.remove("WindowsGame");
				printFormats.add("SoundDiscWithCDROM");
			}
		}
		if (printFormats.contains("CompactDisc")) {
			if (printFormats.contains("SoundCassette")
					|| printFormats.contains("SoundDisc")
			) {
				printFormats.remove("CompactDisc");
			}
		}
		if (printFormats.contains("CD") && printFormats.contains("SoundDisc")){
			//TODO: Likely obsolete - no determinations of CD
			printFormats.remove("CD");
		}
		if (printFormats.contains("MP3") && printFormats.contains("CompactDisc")){
			printFormats.remove("MP3");
		}
		if (printFormats.contains("DVD") && printFormats.contains("SoundDisc")){
			printFormats.remove("DVD");
		}
		if (printFormats.contains("GraphicNovel") && printFormats.contains("SoundDisc")){
			printFormats.remove("GraphicNovel");
		}
		if (printFormats.contains("MusicRecording") && printFormats.contains("YotoMusic")){
			printFormats.remove("MusicRecording");
		}

		// Book Things
		if (printFormats.contains("Book")){
			if (printFormats.contains("LargePrint")
					|| printFormats.contains("Manuscript")
					|| printFormats.contains("GraphicNovel")
					|| printFormats.contains("MusicalScore")
					|| printFormats.contains("BookClubKit")
			//		|| printFormats.contains("Kit") // Kit filtering should happen before this now
					|| printFormats.contains("BoardBook")
			){
				printFormats.remove("Book");
			}
		}

		if (printFormats.contains("Serial") && printFormats.contains("GraphicNovel")){
			printFormats.remove("Serial");
		}
		if (printFormats.contains("Atlas") && printFormats.contains("Map")){
			printFormats.remove("Atlas");
		}
		if (printFormats.contains("Manuscript") && printFormats.contains("LargePrint")){
			printFormats.remove("Manuscript");
		}
		if (printFormats.contains("Photo") && printFormats.contains("Painting")){
			printFormats.remove("Photo");
		}

		// Video Game Things
		if (printFormats.contains("Wii") && printFormats.contains("WiiU")){
			printFormats.remove("Wii");
		}
		if (printFormats.contains("NintendoDS") && printFormats.contains("3DS")){
			printFormats.remove("NintendoDS");
		}
		if (printFormats.contains("PlayStation4") && printFormats.contains("PlayStation5")){
			printFormats.remove("PlayStation4");
		}
		if (printFormats.contains("PlayStation") && printFormats.contains("PlayStation5")){
			printFormats.remove("PlayStation");
		}
		if (printFormats.contains("PlayStation3") && printFormats.contains("PlayStation4")){
			printFormats.remove("PlayStation3");
		}
		if (printFormats.contains("PlayStation") && printFormats.contains("PlayStation4")){
			printFormats.remove("PlayStation");
		}
		if (printFormats.contains("PlayStation") && printFormats.contains("PlayStation3")){
			printFormats.remove("PlayStation");
		}
		if (printFormats.contains("GraphicNovel") && printFormats.contains("PlayStation3")){
			printFormats.remove("GraphicNovel");
		}

		if (printFormats.contains("XboxSeriesX") && printFormats.contains("XboxOne")){
			printFormats.remove("XboxSeriesX");  // a lot of xbox one games will say mention the XboxSeriesX in system requirements as well
		}
		if (printFormats.contains("Xbox360") && printFormats.contains("XboxOne")){
			printFormats.remove("Xbox360");
		}
		if (printFormats.contains("Xbox360") && printFormats.contains("Kinect")){
			printFormats.remove("Xbox360");
		}
		if (printFormats.contains("XboxOne") && printFormats.contains("Kinect")){
			printFormats.remove("XboxOne");
		}
		if (printFormats.contains("Kinect") || printFormats.contains("Xbox360")
				|| printFormats.contains("XboxOne") || printFormats.contains("XboxSeriesX")
				|| printFormats.contains("PlayStation") || printFormats.contains("PlayStation3")
				|| printFormats.contains("PlayStation4") || printFormats.contains("PlayStation5")
				|| printFormats.contains("Wii") || printFormats.contains("WiiU")
				|| printFormats.contains("NintendoSwitch")
				|| printFormats.contains("NintendoDS") || printFormats.contains("3DS")
				|| printFormats.contains("WindowsGame")){
			printFormats.remove("Software");
			printFormats.remove("Electronic");
			printFormats.remove("CDROM");  // Game systems with CD-ROM physical description
			printFormats.remove("DVD");
			printFormats.remove("Blu-ray");
			printFormats.remove("4KUltraBlu-Ray");
			printFormats.remove("PhysicalObject");
			printFormats.remove("VideoDisc");
			printFormats.remove("SoundDisc"); // example: xbox one disc with sound disc "1 computer optical disc : sound, color ; 4 3/4 in. + 1 sound disc (digital ; 4 3/4 in.)"
		}

		// Physical Object Things
		if (hasOverRidingFormat("SeedPacket", printFormats)) {
			return;
		}
		if (printFormats.contains("PhysicalObject")) {
			// Probable DVD players
			printFormats.remove("DVD");
			// Probable Blu-ray players
			printFormats.remove("Blu-ray");
			// record player?
			printFormats.remove("SoundDisc");
			// Possibly MP3 player; probably obsolete
			printFormats.remove("MP3");
		}
	}

	private boolean isComboPack(Record record) {
		List<DataField> marc250 = MarcUtil.getDataFields(record, "250");
		for (DataField field : marc250) {
			if (field != null) {
				if (field.getSubfield('a') != null) {
					String fieldData = field.getSubfield('a').getData().toLowerCase();
					if (fieldData.contains("combo")) {
						return true;
					}
				}
			}
		}
		List<DataField> marc300 = MarcUtil.getDataFields(record, "300");
		for (DataField field300 : marc300) {
			if (field300 != null) {
				if (field300.getSubfield('a') != null) {
					String fieldData = field300.getSubfield('a').getData().toLowerCase();
					if (fieldData.contains("combo")) {
						return true;
					}
				}
			}
		}
		return false;
	}

	private void getFormatFromTitle(Record record, Set<String> printFormats) {
		String titleMedium = MarcUtil.getFirstFieldVal(record, "245h");
		if (titleMedium != null){
			titleMedium = titleMedium.toLowerCase();
			if (titleMedium.contains("sound recording-cass")){
				printFormats.add("SoundCassette");
			}else if (titleMedium.contains("braille")){
				printFormats.add("Braille");
			}else if (titleMedium.contains("large print")){
				printFormats.add("LargePrint");
			}else if (findBookClubKitPhrasesLowerCased(titleMedium)){
				printFormats.add("BookClubKit");
			}else if (titleMedium.contains("ebook")){
				printFormats.add("eBook");
			}else if (titleMedium.contains("eaudio")){
				printFormats.add("eAudio");
			}else if (titleMedium.contains("emusic")){
				printFormats.add("eMusic");
			}else if (titleMedium.contains("evideo")){
				printFormats.add("eVideo");
			}else if (titleMedium.contains("ejournal")){
				printFormats.add("eJournal");
			}else if (titleMedium.contains("playaway view")){
				printFormats.add("PlayawayView");
			}else if (titleMedium.contains("playaway")){
				printFormats.add("Playaway");
			}else if (titleMedium.contains("periodical")){
				printFormats.add("Serial");
			}else if (titleMedium.contains("vhs")){
				printFormats.add("VideoCassette");
			}else if (titleMedium.contains("blu-ray")){
				printFormats.add("Blu-ray");
			}else if (titleMedium.contains("dvd-rom") || titleMedium.contains("dvdrom")){
				printFormats.add("CDROM"); //TODO: should be determined as format dvd-rom (wouldn't work in cd-rom player) TODO: add exclusion check for CD ROM eg. "CD-ROM or DVD-ROM drive"
			}else if (titleMedium.contains("dvd")){
				printFormats.add("DVD");
			}
			else if (titleMedium.contains("mp3"))
			{
				printFormats.add("MP3");
			}

		}
		String titleForm = MarcUtil.getFirstFieldVal(record, "245k");
		if (titleForm != null){
			titleForm = titleForm.toLowerCase();
			if (titleForm.contains("sound recording-cass")){
				printFormats.add("SoundCassette");
			}else if (titleForm.contains("large print")){
				printFormats.add("LargePrint");
			}else if (findBookClubKitPhrasesLowerCased(titleForm)){
				printFormats.add("BookClubKit");
			}
		}
		String titlePart = MarcUtil.getFirstFieldVal(record, "245p");
		if (titlePart != null){
			titlePart = titlePart.toLowerCase();
			if (titlePart.contains("sound recording-cass")){
				printFormats.add("SoundCassette");
			}else if (titlePart.contains("large print")){
				printFormats.add("LargePrint");
			}
		}
		String title = MarcUtil.getFirstFieldVal(record, "245a");
		if (title != null){
			if (findBookClubKitPhrases(title)){
				printFormats.add("BookClubKit");
			}
		}
	}

	private void getFormatFromPublicationInfo(Record record, Set<String> result) {
		// check for playaway in 260|b
		DataField sysDetailsNote = record.getDataField("260");
		if (sysDetailsNote != null) {
			if (sysDetailsNote.getSubfield('b') != null) {
				String sysDetailsValue = sysDetailsNote.getSubfield('b').getData()
						.toLowerCase();
				if (sysDetailsValue.contains("playaway view")) {
					result.add("PlayawayView");
				}else if (sysDetailsValue.contains("playaway")) {
					result.add("Playaway");
				}else if (sysDetailsValue.matches(".*[^a-z]go reader.*")) {
					result.add("GoReader");
				}
			}
		}
	}

	private void getFormatFromEdition(Record record, Set<String> result, RecordIdentifier identifier) {
		List<DataField> editions = record.getDataFields("250");
		for (DataField edition : editions) {
			if (edition != null) {
				if (edition.getSubfield('a') != null) {
					String editionData = edition.getSubfield('a').getData().trim().toLowerCase();
					if (findBookClubKitPhrasesLowerCased(editionData)) {
						// Has to come before large print, because some kits are large print book club kits
						result.add("BookClubKit");
						if (logger.isDebugEnabled()){
							if (editionData.contains("large type") || editionData.contains("large print")) {
								logger.debug("Book Club kit also has large print in edition in bib {}", identifier);
							}
						}
					} else if (editionData.contains("large type") || editionData.contains("large print")) {
						result.add("LargePrint");
					} else if (editionData.equals("go reader") || editionData.matches(".*[^a-z]go reader.*")) {
						result.add("GoReader");
					}	 else if (editionData.contains("wonderbook")) {
						result.add("WonderBook");
					} else if (editionData.contains("board book")) {
						result.add("BoardBook");
					} else if (editionData.contains("illustrated ed")) {
						result.add("IllustratedEdition");
					} else if (findBluRay4KUltraBluRayComboPhrasesLowerCased(editionData)){
						//Do combo check before single format check
						result.add("Blu-ray4KCombo");
						// TODO: return immediately?, since this is definitely format if we get here
				  } else if (find4KUltraBluRayPhrasesLowerCased(editionData)) {
						result.add("4KUltraBlu-Ray");
						// not sure if this is a good idea yet. see D-2432
						// enabled with D-5071
					} else {
						String gameFormat = getGameFormatFromValue(editionData);
						if (gameFormat != null) {
							result.add(gameFormat);
							if (typeOfRecordLeaderChar != null) {
								// if the leader isn't "Language material", check for video game format phrases
								// This helps to avoid books about game systems; especially books with CD-ROMs
								//TODO: determine if the leader character check should only be for windows game
								logger.info("Game format determination from 250 (editions) {} has Type of Record Leader Character {}", gameFormat, typeOfRecordLeaderChar);
							}
						}
					}
				}
			}
		}
	}


	private void getFormatFromPhysicalDescription(Record record, Set<String> result, RecordIdentifier recordIdentifier) {
		//List<DataField> physicalDescription = MarcUtil.getDataFields(record, "300");
		List<DataField> physicalDescription = record.getDataFields("300");
		if (physicalDescription != null) {
			Iterator<DataField> fieldsIter = physicalDescription.iterator();
			DataField           field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				boolean        hasDigital        = false;
				boolean        hasSoundDisc      = false;
				boolean        hasMusicRecording = result.contains("MusicRecording");
				List<Subfield> subFields         = field.getSubfields();
				for (Subfield subfield : subFields) {
					if (subfield.getCode() != 'e') { // Exclude accompanying material subfield e
						String physicalDescriptionData = subfield.getData().toLowerCase();
						if (physicalDescriptionData.contains("digital")){
							hasDigital = true;
						}
						if (physicalDescriptionData.contains("large type") || physicalDescriptionData.contains("large print")) {
							result.add("LargePrint");
						} else if (physicalDescriptionData.contains("braille")){
							result.add("Braille");
						} else if (find4KUltraBluRayPhrasesLowerCased(physicalDescriptionData)) {
							result.add("4KUltraBlu-Ray");
						} else if (physicalDescriptionData.contains("bluray") || physicalDescriptionData.contains("blu-ray")) {
							result.add("Blu-ray");
						} else if (physicalDescriptionData.contains("videodisc")){
							result.add("VideoDisc");
						}	else if (physicalDescriptionData.contains("cd-rom") || physicalDescriptionData.contains("cdrom") || physicalDescriptionData.contains("computer optical disc")) {
							if (!result.contains("PlayStation3") && !result.contains("PlayStation4") && !result.contains("XboxSeriesX") && !result.contains("XboxOne")) {
								// Prevent this determination for PlayStations, so that playstation/xbox records with a bad typeOfRecordLeaderChar of 'a' do not trigger accompanyingMaterialCheck determination of BookWithCD
								result.add("CDROM");
							}
						} else if (physicalDescriptionData.contains("sound cassettes")) {
							result.add("SoundCassette");
						} else if (physicalDescriptionData.contains("compact disc")){
							result.add("CompactDisc");
							logger.info("Got format determination 'CompactDisc' on {}", recordIdentifier);
							//TODO: likely need to additional logic to set to something more specific
						} else if (physicalDescriptionData.contains("sound disc") || physicalDescriptionData.contains("audio disc")) {
							hasSoundDisc = true;
							result.add("SoundDisc");
//						} else if (physicalDescriptionData.contains("sound disc") || physicalDescriptionData.contains("audio disc") || physicalDescriptionData.contains("compact disc")) {
//							//TODO "compact disc" should be it's own entry to CD
//							result.add("SoundDisc");
						} else if (physicalDescriptionData.contains("wonderbook")) {
							result.add("WonderBook");
						}else if (physicalDescriptionData.contains("vox book")){
							result.add("VoxBooks");
						}else if (physicalDescriptionData.contains("hotspot device") || physicalDescriptionData.contains("mobile hotspot") || physicalDescriptionData.contains("hot spot") || physicalDescriptionData.contains("hotspot")){
							result.add("PhysicalObject");
						} else if (hasMusicRecording && (physicalDescriptionData.contains(" cd :") || physicalDescriptionData.contains(" cds :"))) {
							// If we know the record is Music (due to typeOfRecordLeaderChar MusicRecording determination),
							// allow phrases like "CD : digital" or "CDs : digital" to get us to MusicCD
							// e.g. 300			 |a 1 CD : |b digital, stereophonic ; |c 4 3/4 inches.
							// e.g. 300			 |a 2 CDs : |b digital, stereo, mono ;
							hasSoundDisc = true;
						}
						//Since this is fairly generic, only use it if we have no other formats yet
						if (result.isEmpty() && subfield.getCode() == 'f' && physicalDescriptionData.matches("^.*?\\d+\\s+(p\\.|pages).*$")) {
							result.add("Book");
						}
					}
				}
				if (hasSoundDisc) {
					if (hasDigital) {
						if (hasMusicRecording) {
							// Since MusicRecording is determined by leader at beginning of format determination,
							// it should be reliably already determined at this point
							result.add("MusicCD");
							result.remove("SoundDisc");
							result.remove("MusicRecording");
						} else {
							// Otherwise it should be an audio CD (the translation for SoundDisc).
							// (I might be wrong here, remove or refine if you determine so.)
							result.add("SoundDisc");
						}
					} else if (hasMusicRecording) {
						List<DataField> soundCharacteristics = record.getDataFields("344");
						for (DataField field1 : soundCharacteristics) {
							String typeOfRecording = String.valueOf(MarcUtil.getSpecifiedSubfieldsAsString(field1, "a", " ")).trim();
							if (typeOfRecording.equalsIgnoreCase("digital")) {
								result.add("MusicCD");
								result.remove("SoundDisc");
								result.remove("MusicRecording");
								break;
							}
						}
					}
				}
			}
		}
	}

	private void getFormatFromNotes(Record record, Set<String> result) {
		// Check for formats in the 538 field (System Details Note)
		List<DataField> systemDetailsNotes = record.getDataFields("538");
		for (DataField sysDetailsNote2 : systemDetailsNotes) {
			if (sysDetailsNote2 != null) {
				if (sysDetailsNote2.getSubfield('a') != null) {
					String sysDetailsValue = sysDetailsNote2.getSubfield('a').getData().toLowerCase();
					String gameFormat      = getGameFormatFromValue(sysDetailsValue);
					if (gameFormat != null) {
						result.add(gameFormat);
						if (typeOfRecordLeaderChar != null) {
							// if the leader isn't "Language material", check for video game format phrases
							// This helps to avoid books about game systems; especially books with CD-ROMs
							//TODO: determine if the leader character check should only be for windows game
							logger.info("Game format determination from 538 {} has Type of Record Leader Character {}", gameFormat, typeOfRecordLeaderChar);
						}
					} else {
						if (sysDetailsValue.contains("playaway view")) {
							result.add("PlayawayView");
						} else if (sysDetailsValue.contains("playaway")) {
							result.add("Playaway");
						} else if (find4KUltraBluRayPhrasesLowerCased(sysDetailsValue)) {
							result.add("4KUltraBlu-Ray");
						} else if (sysDetailsValue.contains("bluray") || sysDetailsValue.contains("blu-ray")) {
							result.add("Blu-ray");
						} else if (sysDetailsValue.contains("dvd-rom") || sysDetailsValue.contains("dvdrom")) {
							result.add("CDROM"); //TODO: should be determined as format dvd-rom (wouldn't work in cd-rom player)
						} else if (sysDetailsValue.contains("dvd")) {
							result.add("DVD");
						} else if (sysDetailsValue.contains("vertical file")) {
							result.add("VerticalFile");
						} else if (sysDetailsValue.contains("for use in yoto player")){
							if (typeOfRecordLeaderChar != null && typeOfRecordLeaderChar.equals('j')){
								result.remove("MusicRecording");
								result.add("YotoMusic");
							} else {
								result.add("YotoStory");
							}
						}
					}
				}
			}
		}

		// Check for formats in the 500 tag  (General Note)
		List<DataField> noteFields = record.getDataFields("500");
		for (DataField noteField : noteFields) {
			if (noteField != null) {
				if (noteField.getSubfield('a') != null) {
					String noteValue = noteField.getSubfield('a').getData().toLowerCase();
					if (noteValue.contains("vox book") || noteValue.contains("vox audio")) {
						result.add("VoxBooks");
					} else if (noteValue.contains("wonderbook")) {
						result.add("WonderBook");
					} else if (noteValue.contains("playaway launchpad")) {
						result.add("PlayawayLaunchpad");
					} else if (noteValue.contains("playaway view")) {
						result.add("PlayawayView");
					} else if (noteValue.contains("playaway")) {
						result.add("Playaway");
					} else if (noteValue.contains("vertical file")) {
						result.add("VerticalFile");
					}else if (noteValue.matches(".*[^a-z]go reader.*")){
						result.add("GoReader");
					} else if (noteValue.contains("board pages")){
						result.add("BoardBook");
					} else if (noteValue.contains("mp3")){
						result.add("MP3");
					}

				}
			}
		}

		// Check for formats in the 502 tag (Dissertation Note)
		// 502a Dissertation Note -- Designation of an academic dissertation or thesis and the institution to which it was presented.
		DataField dissertationNoteField = record.getDataField("502");
		if (dissertationNoteField != null) {
			if (dissertationNoteField.getSubfield('a') != null) {
				String noteValue = dissertationNoteField.getSubfield('a').getData().toLowerCase();
				if (noteValue.contains("thesis (m.a.)")) {
					result.add("Thesis");
				}
			}
		}

		// Check for formats in the 590 tag  (Local Note)
		List<DataField> noteField = record.getDataFields("590");
		for(DataField localNoteField : noteField) {
			if (localNoteField != null) {
				if (localNoteField.getSubfield('a') != null) {
					String noteValue = localNoteField.getSubfield('a').getData().toLowerCase();
					if (noteValue.contains("archival material")) {
						result.add("Archival Materials");
					}
				}
			}
		}
	}

	private void getGameFormatFrom753(Record record, Set<String> result) {
		// Check for formats in the 753 field "System Details Access to Computer Files"
		// 753|a is Make and model of machine
		DataField sysDetailsTag = record.getDataField("753");
		if (sysDetailsTag != null) {
			if (sysDetailsTag.getSubfield('a') != null) {
				String sysDetailsValue = sysDetailsTag.getSubfield('a').getData().toLowerCase();
				String gameFormat = getGameFormatFromValue(sysDetailsValue);
				if (gameFormat != null){
					result.add(gameFormat);
					if (typeOfRecordLeaderChar != null) {
						// if the leader isn't "Language material", check for video game format phrases
						// This helps to avoid books about game systems; especially books with CD-ROMs
						//TODO: determine if the leader character check should only be for windows game
						logger.info("Game format determination from 753 {} has Type of Record Leader Character {}", gameFormat, typeOfRecordLeaderChar);
					}
				}
			}
		}
	}

	private String getGameFormatFromValue(String value) {
		if (value.contains("kinect sensor")) {
			return "Kinect";
			//TODO: Go through playstation checks first to help avoid playstation games
			// that mention use of "Xbox Live"
		} else if (value.contains("system requirements: xbox series x")){
			// for case that includes "optimized for xbox series x" but we know is in fact the 'xbox series x'
			//TODO; what to do with phrase "System requirements: : Xbox Series X or Xbox One consoles"
			return "XboxSeriesX";
		} else if (value.contains("xbox series x") && !value.contains("compatible") && !value.contains("optimized for xbox series x")) {
			//xbox one games can contain the phrase "optimized for Xbox Series X"
			return "XboxSeriesX";
		} else if (value.contains("xbox one") && !value.contains("compatible")) {
			return "XboxOne";
		} else if ((value.contains("xbox 360") || value.contains("xbox360")) && !value.contains("compatible")) {
			if (logger.isInfoEnabled() && value.contains("xbox live")){
				logger.info("Potential xbox game format string contains 'xbox live': " + value);
			}
			return "Xbox360";
		} else if (value.contains("playstation vita") /*&& !value.contains("compatible")*/) {
			return "PlayStationVita";
		} else if ((value.contains("playstation 5") ||value.contains("playstation5") || value.equals("ps5") || value.matches(".*[^a-z]ps5.*")) && isNotBluRayPlayerDescription(value)) {
			// If the entire value is "PS5", good reason to call this a playstation
			if (value.contains("xbox live")){
				logger.info("PS string mentioning xbox live :" + value);
			}
			return "PlayStation5";
		} else if ((value.contains("playstation 4") ||value.contains("playstation4") || value.equals("ps4") || value.matches(".*[^a-z]ps4.*")) && isNotBluRayPlayerDescription(value)) {
			if (value.contains("xbox live")){
				logger.info("PS string mentioning xbox live :" + value);
			}
			return "PlayStation4";
		} else if ((value.contains("playstation 3") ||value.contains("playstation3") || value.equals("ps3") || value.matches(".*[^a-z]ps3.*")) && isNotBluRayPlayerDescription(value)) {
			if (value.contains("xbox live")){
				logger.info("PS string mentioning xbox live :" + value);
			}
			return "PlayStation3";
		} else if (value.replaceAll("playstation (plus|network)", "").contains("playstation") && isNotBluRayPlayerDescription(value)) {
			return "PlayStation";
		} else if (value.contains("wii u")) {
			return "WiiU";
		} else if (value.contains("nintendo wii")) {
			return "Wii";
		} else if (value.contains("wii")) { // make sure this check comes after checks for "wii u"
			return "Wii";
		} else if (value.contains("nintendo 3ds")) {
			return "3DS";
		} else if (value.contains("nintendo switch")) {
			return "NintendoSwitch";
		} else if (value.contains("nintendo ds")) {
			return "NintendoDS";
		} else if (value.contains("directx")) {
			return "WindowsGame";
		}else{
			return null;
		}
	}

		/**
	 *  Avoid play station format determinations for descriptions of Blu-ray player that note
	 *  that a play station is compatible with Blu-ray players.
	 *
	 * @param value text of a subfield
	 * @return whether string is describing being Blu-ray compatible
	 */
	private boolean isNotBluRayPlayerDescription(String value) {
		if (logger.isInfoEnabled() && value.contains("compatible")){
			logger.info("Blu-ray description test string w/ 'compatible' : {}", value);
			//  keying off only "compatible" is likely a false positive
			//  since descriptions may be referring to other things.
		}
		return !value.contains("blu-ray player") && !value.contains("blu-ray disc player") && !value.contains("blu-ray disc computer") && !value.contains("compatible");
		// Order by the more specific phrases first, and save phrase compatible for last to reduce potential false positives here
	}

	private Boolean find4KUltraBluRayPhrases(String subject) {
		subject = subject.toLowerCase();
		return find4KUltraBluRayPhrasesLowerCased(subject);
	}

	private Boolean find4KUltraBluRayPhrasesLowerCased(String subject) {
		return subject.contains("4k ultra hd blu-ray") ||
						subject.contains("4k ultra hd bluray") ||
						subject.contains("4k ultrahd blu-ray") ||
						subject.contains("4k ultrahd bluray") ||
						subject.contains("4k uh blu-ray") ||
						subject.contains("4k uh bluray") ||
						subject.contains("4k ultra high-definition blu-ray") ||
						subject.contains("4k ultra high-definition bluray") ||
						subject.contains("4k ultra high definition blu-ray") ||
						subject.contains("4k ultra high definition bluray") ||
						subject.contains("4k ultra hd")
						;
	}

	private Boolean findBluRay4KUltraBluRayComboPhrasesLowerCased(String subject) {
		return subject.contains("4k ultra hd + blu-ray") ||
						subject.contains("blu-ray + 4k ultra hd") ||
						subject.contains("4k ultra hd/blu-ray combo") ||
						subject.contains("4k ultra hd blu-ray + blu-ray")
						;
	}


	/**
	 * @param subject A string that could contain upper case text
	 * @return contains one of the phrases, or not
	 */
	private Boolean findBookClubKitPhrases(String subject){
		subject = subject.toLowerCase();
		return findBookClubKitPhrasesLowerCased(subject);
	}

	/**
	 * @param subject A string that has already been made lower case
	 * @return contains one of the phrases, or not
	 */
	private Boolean findBookClubKitPhrasesLowerCased(String subject){
		return subject.contains("book club kit") || subject.contains("bookclub kit");
	}

	private void getFormatFromSubjects(Record record, Set<String> result) {
		List<DataField> topicalTerm = MarcUtil.getDataFields(record, "650");
		if (topicalTerm != null) {
			Iterator<DataField> fieldsIter = topicalTerm.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					char subfieldCode = subfield.getCode();
					if (subfieldCode == 'a'){
						String subfieldData = subfield.getData().toLowerCase();
						if (subfieldData.contains("large type") || subfieldData.contains("large print")) {
							result.add("LargePrint");
						}else if (subfieldData.contains("playaway view")) {
							result.add("PlayawayView");
						}else if (subfieldData.contains("playaway")) {
							result.add("Playaway");
						}else if (subfieldData.contains("readers for new literates")/* || subfieldData.contains("high interest-low vocabulary books")*/) {
							result.add("AdultLiteracyBook");
						} else if (subfieldData.contains("yoto card") || subfieldData.contains("yoto story card")) {
							result.add("YotoStory");
						}else if (subfieldData.contains("graphic novel")
										|| subfieldData.contains("comic and graphic books")  // OverDrive Marc
						) {
							boolean okToAdd = false;
							if (field.getSubfield('v') != null){
								String subfieldVData = field.getSubfield('v').getData().toLowerCase();
								if (!subfieldVData.contains("television adaptation")){
									okToAdd = true;
									//}else{
									//System.out.println("Not including graphic novel format");
								}
							}else{
								okToAdd = true;
							}
							if (okToAdd){
								result.add("GraphicNovel");
							}
						} else if (subfieldData.contains("board books")) {
							result.add("BoardBook");
						}
					} else if (subfieldCode == 'v') {
						String subfieldData = subfield.getData().toLowerCase();
						if (subfieldData.contains("comic books, strips, etc") || subfieldData.contains("comic books,strips, etc")) {
							result.add("GraphicNovel");
						}
					}
				}
			}
		}

		List<DataField> genreFormTerm = MarcUtil.getDataFields(record, "655");
		if (genreFormTerm != null) {
			Iterator<DataField> fieldsIter = genreFormTerm.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();

				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					if (subfield.getCode() == 'a'){
						String subfieldData = subfield.getData().toLowerCase();
						if (subfieldData.contains("large type")) {
							result.add("LargePrint");
						}else if (subfieldData.contains("playaway view")) {
							result.add("PlayawayView");
						}else if (subfieldData.contains("playaway")) {
							result.add("Playaway");
						}else if (subfieldData.contains("readers for new literates")/* || subfieldData.contains("high interest-low vocabulary books")*/) {
							result.add("AdultLiteracyBook");
						}else if (subfieldData.contains("graphic novel")
										|| subfieldData.contains("comic books, strips, etc") // Library of Congress authorized term
						) {
							boolean okToAdd = false;
							if (field.getSubfield('v') != null){
								String subfieldVData = field.getSubfield('v').getData().toLowerCase();
								if (!subfieldVData.contains("television adaptation")){
									okToAdd = true;
									//}else{
									//System.out.println("Not including graphic novel format");
								}
							}else{
								okToAdd = true;
							}
							if (okToAdd){
								result.add("GraphicNovel");
							}
						}else if (subfieldData.contains("board books"))
						{
							result.add("BoardBook");
						}
					}
				}
			}
		}


		List<DataField> localTopicalTerm = MarcUtil.getDataFields(record, "690");
		if (localTopicalTerm != null) {
			Iterator<DataField> fieldsIterator = localTopicalTerm.iterator();
			DataField field;
			while (fieldsIterator.hasNext()) {
				field = fieldsIterator.next();
				Subfield subfieldA = field.getSubfield('a');
				if (subfieldA != null) {
					if (subfieldA.getData().toLowerCase().contains("seed library")) {
						result.add("SeedPacket");
					}
				}
			}
		}


		List<DataField> addedEntryFields = MarcUtil.getDataFields(record, "710");
		if (localTopicalTerm != null) {
			Iterator<DataField> addedEntryFieldIterator = addedEntryFields.iterator();
			DataField field;
			while (addedEntryFieldIterator.hasNext()) {
				field = addedEntryFieldIterator.next();
				Subfield subfieldA = field.getSubfield('a');
				if (subfieldA != null && subfieldA.getData() != null) {
					String fieldData = subfieldA.getData().toLowerCase();
					if (fieldData.contains("playaway launchpad")) {
						result.add("PlayawayLaunchpad");
					}else if (fieldData.contains("playaway view")) {
						result.add("PlayawayView");
					}else if (fieldData.contains("playaway digital audio") || fieldData.contains("findaway world")) {
						result.add("Playaway");
					}
				}
			}
		}
	}

	private void getFormatFrom008(ControlField formatField, Set<String> result) {
		if (formatField != null) {
			String formatFieldData = formatField.getData();
			if (formatFieldData != null && formatFieldData.length() >= 24) {
				char formatCode = Character.toLowerCase(formatFieldData.charAt(23));
				if (formatCode == 'd') {
					result.add("LargePrint");
				}
			}
		}
	}

	private void getFormatFrom007(Record record, Set<String> result) {
		ControlField formatField = (ControlField) record.getVariableField( "007");
		if (formatField != null){
			if (formatField.getData() == null || formatField.getData().length() < 2) {
				return;
			}
			// Check for blu-ray (s in position 4)
			// This logic does not appear correct.
			/*
			 * if (formatField.getData() != null && formatField.getData().length()
			 * >= 4){ if (formatField.getData().toUpperCase().charAt(4) == 'S'){
			 * result.add("Blu-ray"); break; } }
			 */
			// check the 007 - this is a repeating field
			char formatCode       = formatField.getData().toUpperCase().charAt(0);
			char specificMaterial = formatField.getData().toUpperCase().charAt(1);
			switch (formatCode) {
				case 'A':
					if (specificMaterial == 'D') {
						result.add("Atlas");
					} else {
						result.add("Map");
					}
					break;
				case 'C':
					// https://www.loc.gov/marc/bibliographic/bd007c.html
					switch (specificMaterial) {
						case 'A':
							result.add("TapeCartridge");
							break;
						case 'B':
							result.add("ChipCartridge");
							break;
						case 'C':
							result.add("DiscCartridge");
							break;
						case 'F':
							result.add("TapeCassette");
							break;
						case 'H':
							result.add("TapeReel");
							break;
						case 'J':
							result.add("FloppyDisk");
							break;
						case 'M':
						case 'O':
							result.add("CDROM");
							break;
						case 'R':
							// Do not return - this will cause anything with an
							// 856 field to be labeled as "Electronic"
							break;
						case 'Z':
//						default: // disabling Catch All option here
							result.add("Software");
							break;
					}
					break;
				case 'D':
					result.add("Globe");
					break;
				case 'F':
					result.add("Braille");
					break;
				case 'G':
					switch (specificMaterial) {
						case 'C':
						case 'D':
							result.add("Filmstrip");
							break;
						case 'T':
							result.add("Transparency");
							break;
						default:
							result.add("Slide");
							break;
					}
					break;
				case 'H':
					result.add("Microfilm");
					break;
				case 'K':
					switch (specificMaterial) {
						case 'C':
							result.add("Collage");
							break;
						case 'D':
						case 'L':
							result.add("Drawing");
							break;
						case 'E':
							result.add("Painting");
							break;
						case 'F':
						case 'J':
							result.add("Print");
							break;
						case 'G':
							result.add("Photonegative");
							break;
						case 'O':
							result.add("FlashCard");
							break;
						case 'N':
							result.add("Chart");
							break;
						default:
							result.add("Photo");
							break;
					}
					break;
				case 'M':
					switch (specificMaterial) {
						case 'F':
							result.add("VideoCassette");
							break;
						case 'R':
							result.add("Filmstrip");
							break;
						default:
							result.add("MotionPicture");
							break;
					}
					break;
				case 'O':
					result.add("Kit");
					break;
				case 'Q':
					result.add("MusicalScore");
					break;
				case 'R':
					result.add("SensorImage");
					break;
				case 'S':
					switch (specificMaterial) {
						case 'D':
							if (formatField.getData().length() >= 4) {
								char speed = formatField.getData().toUpperCase().charAt(3);
								if (speed >= 'A' && speed <= 'E') {
									result.add("Phonograph");
								} else if (speed == 'F') {
									result.add("CompactDisc");
								} else if (speed >= 'K' && speed <= 'R') {
									result.add("TapeRecording");
								} else {
									result.add("SoundDisc");
								}
							} else {
								result.add("SoundDisc");
							}
							break;
						case 'S':
							result.add("SoundCassette");
							break;
						default:
							result.add("SoundRecording");
							break;
					}
					break;
				case 'T':
					switch (specificMaterial) {
						case 'A':
							result.add("Book");
							break;
						case 'B':
							result.add("LargePrint");
							break;
					}
					break;
				case 'V':
					switch (specificMaterial) {
						case 'C':
							result.add("VideoCartridge");
							break;
						case 'D':
							result.add("VideoDisc");
							break;
						case 'F':
							result.add("VideoCassette");
							break;
						case 'R':
							result.add("VideoReel");
							break;
						default:
							result.add("Video");
							break;
					}
					break;
			}
		}
	}


	private void getFormatFromLeader(Set<String> result, String leader, ControlField fixedField008) {
		if (typeOfRecordLeaderChar != null) {
			switch (typeOfRecordLeaderChar) {
				//TODO: create case 'a' = book at this point? does it require checking the leader 07 below?
				case 'c':
				case 'd':
					result.add("MusicalScore");
					break;
				case 'e':
				case 'f':
					result.add("Map");
					break;
				case 'g':
					// We appear to have a number of items without 007 tags marked as G's.
					// These seem to be Videos rather than Slides.
					// result.add("Slide");
					result.add("Video");
					break;
				case 'i':
					result.add("SoundRecording");
					break;
				case 'j':
					result.add("MusicRecording");
					break;
				case 'k':
					result.add("Photo");
					break;
				case 'm':
					result.add("Electronic");
					break;
				case 'o':
					result.add("Kit");
					break;
				case 'p':
					result.add("MixedMaterials");
					break;
				case 'r':
					result.add("PhysicalObject");
					break;
				case 't':
					result.add("Manuscript");
					break;
			}
		}

		if (leader.length() >= 7) {

			// check the Leader at position 7
			char leaderBit = leader.charAt(7);
			switch (Character.toUpperCase(leaderBit)) {
				// Monograph
				case 'M':
					if (result.isEmpty()) {
						result.add("Book");
					}
					break;
				// Serial
				case 'S':
					// Look in 008 to determine what type of Continuing Resource
					if (fixedField008 != null && fixedField008.getData().length() >= 22) {
						char formatCode = Character.toUpperCase(fixedField008.getData().charAt(21));
						switch (formatCode) {
							case 'N':
								result.add("Newspaper");
								break;
							case 'P':
								result.add("Journal");
								break;
							default:
								result.add("Serial");
								break;
						}
					}
			}
		}
	}

	HashSet<String> translateCollection(String mapName, Set<String> values, RecordIdentifier identifier) {
		TranslationMap translationMap = translationMaps.get(mapName);
		HashSet<String> translatedValues;
		if (translationMap == null){
			logger.error("Unable to find translation map for " + mapName + " in profile " + profileType);
			if (values instanceof HashSet){
				translatedValues = (HashSet<String>)values;
			}else{
				translatedValues = new HashSet<>(values);
			}
		}else{
			translatedValues = translationMap.translateCollection(values, identifier);
		}
		return translatedValues;
	}

	public String translateValue(String mapName, String value, RecordIdentifier identifier){
		return translateValue(mapName, value, identifier, true);
	}

	public String translateValue(String mapName, String value, RecordIdentifier identifier, boolean reportErrors){
		if (value == null){
			return null;
		}
		TranslationMap translationMap = translationMaps.get(mapName);
		String translatedValue;
		if (translationMap == null){
			logger.error("Unable to find translation map for " + mapName + " in profile " + profileType);
			translatedValue = value;
		}else{
			translatedValue = translationMap.translateValue(value, identifier, reportErrors);
		}
		return translatedValue;
	}

}
