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
 * Format Determinations should match the indexing format determinations.
 * Here with an additional step of using that format determination to make a grouping category determination.
 *
 * @author pbrammeier
 * 		Date:   2/12/2020
 */
public class GroupingFormatDetermination {
	protected Logger logger;

	HashMap<String, TranslationMap> translationMaps;

	String profileType;
	String formatSource;
//	String specifiedFormat;
	String specifiedGroupingCategory;
//	String specifiedFormatCategory;
//	int    specifiedFormatBoost;
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
	String materialTypeSubField = "";
	String matTypesToIgnore;

	//For Record Grouping Category
	HashSet<String> groupingCategories = new HashSet<>();

	GroupingFormatDetermination(IndexingProfile indexingProfile, HashMap<String, TranslationMap> translationMaps, Logger logger){
		this.logger = logger;
		this.translationMaps = translationMaps;

		profileType               = indexingProfile.sourceName;
		formatSource              = indexingProfile.formatSource;
		formatDeterminationMethod = indexingProfile.formatDeterminationMethod;
		if (formatDeterminationMethod == null) {
			formatDeterminationMethod = "bib";
		}
		matTypesToIgnore = indexingProfile.materialTypesToIgnore;
		if (matTypesToIgnore == null) {
			matTypesToIgnore = "";
		}
//		specifiedFormat         = indexingProfile.specifiedFormat;
//		specifiedFormatCategory = indexingProfile.specifiedFormatCategory;
		specifiedGroupingCategory = indexingProfile.specifiedGroupingCategory;
//		specifiedFormatBoost    = indexingProfile.specifiedFormatBoost;
		formatSubfield          = indexingProfile.format;

		sierraRecordFixedFieldsTag = indexingProfile.sierraRecordFixedFieldsTag;
		if (indexingProfile.materialTypeSubField != ' ') {
			materialTypeSubField       = String.valueOf(indexingProfile.materialTypeSubField);
		}

		itemTag                   = indexingProfile.itemTag;
		iTypeSubfield             = indexingProfile.iTypeSubfield;
		statusSubfieldIndicator   = indexingProfile.itemStatusSubfield;
		iCode2Subfield            = indexingProfile.iCode2Subfield;
		useICode2Suppression      = indexingProfile.useICode2Suppression;
		collectionSubfield        = indexingProfile.collectionSubfield;
		locationSubfieldIndicator = indexingProfile.locationSubfield;


		locationsToSuppressPattern   = indexingProfile.locationsToSuppressPattern;
		collectionsToSuppressPattern = indexingProfile.collectionsToSuppressPattern;
		statusesToSuppressPattern    = indexingProfile.statusesToSuppressPattern;
		iTypesToSuppressPattern      = indexingProfile.iTypesToSuppressPattern;
		iCode2sToSuppressPattern     = indexingProfile.iCode2sToSuppressPattern;
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
	public HashSet<String> loadPrintFormatInformation(RecordIdentifier identifier, Record record) {
		switch (formatSource) {
			case "specified":
				if (!specifiedGroupingCategory.isEmpty()) {
					groupingCategories.add(specifiedGroupingCategory);
				} else {
					logger.error("Specified Format is not set in indexing profile. Can not use specified format for format determination. Fall back to bib format determination.");
					loadPrintFormatFromBib(identifier, record);
				}
				break;
			case "item":
				loadPrintFormatFromITypes(identifier, record);
				break;
			default:
				if (formatDeterminationMethod.equalsIgnoreCase("matType")) {
					loadPrintFormatFromMatType(identifier, record);
				} else {
					// Format source presumed to be "bib" by default
					loadPrintFormatFromBib(identifier, record);
				}
				break;
		}
		return groupingCategories;
	}

	public HashSet<String> loadEContentFormatInformation(RecordIdentifier identifier, Record record) {
		if (formatSource.equals("specified") && !specifiedGroupingCategory.isEmpty()){
			groupingCategories.add(specifiedGroupingCategory);
		} else {
			LinkedHashSet<String> printFormats = getFormatsFromBib(record, identifier);
			if (!translationMaps.isEmpty() && translationMaps.containsKey("grouping_categories")){
				groupingCategories.addAll(translateCollection("grouping_categories", printFormats, identifier));
			} else {
				//Set grouping category from default bib determinations.
				for (String format : printFormats) {
					switch (format.toLowerCase()) {
						case "graphicnovel":
						case "ecomic":
							groupingCategories.add("comic");
							break;
						case "ebook":
						case "book":
						case "bookwithcdrom":
						case "bookwithdvd":
						case "bookwithvideodisc":
						case "largeprint":
						case "manuscript":
						case "thesis":
						case "print":
						case "microfilm":
						case "kit":
						case "journal":
						case "serial":
						case "soundrecording":
						case "sounddisc":
						case "playaway":
						case "cdrom":
						case "soundcassette":
						case "compactdisc":
						case "eaudio":
						case "electronic":
						case "software":
						case "photo":
						case "map":
						case "newspaper":
						// Below are likely bad marc data for econtent records
						case "tapereel":
						case "tapecassette":
						case "tapecartridge":
						case "disccartridge":
						case "chipcartridge":
						case "floppydisk":
							groupingCategories.add("book");
							break;
						case "musicrecording":
						case "musicalscore":
						case "musiccd":
							groupingCategories.add("music");
							break;
						case "movies":
						case "video":
						case "dvd":
						case "videodisc":
							groupingCategories.add("movie");
							break;
						case "young reader":
							groupingCategories.add("young");
							break;
						default:
							groupingCategories.add("book");
							logger.warn("Could not find appropriate grouping category for " + format + " while side loading eContent " + identifier);
					}
				}
			}
		}
		return groupingCategories;
	}

	private void loadPrintFormatFromBib(RecordIdentifier identifier, Record record) {
		LinkedHashSet<String> printFormats = getFormatsFromBib(record, identifier);
		if (printFormats.isEmpty()){
			logger.warn("Did not find a format for " + identifier + " using standard format method " + printFormats);
		}
		groupingCategories.addAll(translateCollection("grouping_categories", printFormats, identifier));
	}

	private void loadPrintFormatFromMatType(RecordIdentifier identifier, Record record) {
		if (sierraRecordFixedFieldsTag != null && !sierraRecordFixedFieldsTag.isEmpty()) {
			if (materialTypeSubField != null && !materialTypeSubField.isEmpty()) {
				String matType = MarcUtil.getFirstFieldVal(record, sierraRecordFixedFieldsTag + materialTypeSubField);
				if (matType != null) {
					if (!isMatTypeToIgnore(matType)) {
						String translatedFormat = translateValue("format", matType, identifier);
						if (translatedFormat != null && !translatedFormat.equals(matType)) {
							groupingCategories.add(translateValue("grouping_categories", matType, identifier));
							return;
						} else if (logger.isInfoEnabled()) {
							logger.info("Material Type " + matType + " had no translation, falling back to default format determination.");
						}
					} else if (logger.isDebugEnabled()) {
						logger.debug("Material Type for " + identifier + " has ignored value '" + matType + "', falling back to default format determination.");
					}
				} else if (logger.isInfoEnabled()) {
					logger.info(identifier + " did not have a material type, falling back to default format determination.");
				}
			} else {
				logger.error("The materialTypeSubField is not set. Material Type format determination skipped.");
			}
		} else {
			logger.error("The sierraRecordFixedFieldsTag is not set. Material Type format determination skipped.");
		}

		// Fall back to the format determination based on the Bib when the Material Type format determination failed
		loadPrintFormatFromBib(identifier, record);
	}

	private boolean isMatTypeToIgnore(String matType) {
		return matType.isEmpty() || matType.equals("-") || matType.equals(" ") || matTypesToIgnore.indexOf(matType.charAt(0)) >= 0;
	}

	private void loadPrintFormatFromITypes(RecordIdentifier identifier, Record record) {
		HashMap<String, Integer> itemCountsByItype = new HashMap<>();
		HashMap<String, String>  itemTypeToFormat  = new HashMap<>();
		int                      mostUsedCount     = 0;
		String                   mostPopularIType  = "";  //Get a list of all the formats based on the items
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
						String translatedFormat = translateValue("format", iType, identifier);
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
			loadPrintFormatFromBib(identifier, record);
		} else{
			//logger.debug("Using default method of loading formats from iType");
			groupingCategories.add(translateValue("grouping_categories", itemTypeToFormat.get(mostPopularIType), identifier));
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
				if (iTypesToSuppressPattern != null && iTypesToSuppressPattern.matcher(iType).matches()){
					if (logger.isDebugEnabled()) {
						logger.debug("Item record is suppressed due to Itype " + iType);
					}
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
					if (logger.isDebugEnabled()) {
						logger.debug("Item record is suppressed due to ICode2 " + iCode2);
					}
					return true;
				}
			}
		}

		return false;
	}

	LinkedHashSet<String> getFormatsFromBib(Record record, RecordIdentifier identifier){
		LinkedHashSet<String> printFormats = new LinkedHashSet<>();
		String                leader       = record.getLeader().toString();
		Character             leaderBit    = leader.length() >= 6 ? Character.toLowerCase(leader.charAt(6)) : null;

		// check for music recordings quickly so we can figure out if it is music
		// for category (need to do here since checking what is on the Compact
		// Disc/Phonograph, etc. is difficult).
		if (leaderBit != null && leaderBit.equals('j')) {
			printFormats.add("MusicRecording");
			//TODO: finish early?
		}
		getFormatFromPublicationInfo(record, printFormats);
		getFormatFromNotes(record, printFormats);
		getFormatFromEdition(record, printFormats);
		getFormatFromPhysicalDescription(record, printFormats);
		getFormatFromSubjects(record, printFormats);
		getFormatFromTitle(record, printFormats);
		getFormatFromDigitalFileCharacteristics(record, printFormats);
		getGameFormatFrom753(record, printFormats);
		if (printFormats.isEmpty()) {
			//Only get from fixed field information if we don't have anything yet since the cataloging of
			//fixed fields is not kept up to date reliably.  #D-87
			getFormatFrom007(record, printFormats);
			if (printFormats.isEmpty()) {
				ControlField fixedField = (ControlField) record.getVariableField("008");
				getFormatFromLeader(printFormats, leader, fixedField);
				if (printFormats.size() > 1){
					if (logger.isDebugEnabled()) {
						logger.debug("Found more than 1 format for {} looking at just the leader: {}", identifier, String.join(",", printFormats));
					}
				}
			} else if (printFormats.size() > 1){
				if (logger.isDebugEnabled()) {
					logger.debug("Found more than 1 format for " + identifier + " looking at just 007");
				}
			}
		}

//		if (leaderBit != null) {
//			accompanyingMaterialCheck(leaderBit, printFormats);
//		}

		if (printFormats.isEmpty()){
//			if (fullReindex) {
//				logger.warn("Did not get any formats for record " + recordInfo.getFullIdentifier() + ", assuming it is a book ");
//			}
			printFormats.add("Book");
		}
//		else if (logger.isDebugEnabled()){
//			for(String format: printFormats){
//				logger.debug("    found format " + format);
//			}
//		}

		filterPrintFormats(printFormats);

		if (printFormats.size() > 1){
			String formatsString = String.join(",", printFormats);
			if (!formatsToFilter.contains(formatsString)){
				formatsToFilter.add(formatsString);
				if (logger.isDebugEnabled()) {
					logger.debug("Found more than 1 format for " + identifier + " - " + formatsString);
				}
			}
		}
		return printFormats;
	}

	private HashSet<String> formatsToFilter = new HashSet<>();

	private void getFormatFromDigitalFileCharacteristics(Record record, LinkedHashSet<String> printFormats) {
		Set<String> fields = MarcUtil.getFieldList(record, "347b");
		for (String curField : fields){
			if (find4KUltraBluRayPhrases(curField))
				printFormats.add("4KUltraBlu-Ray");
			if (curField.equalsIgnoreCase("Blu-Ray")){
				printFormats.add("Blu-ray");
			}else if (curField.equalsIgnoreCase("DVD-ROM") || curField.equalsIgnoreCase("DVDROM")){
				printFormats.add("CDROM");
			}else if (curField.equalsIgnoreCase("DVD video")){
				printFormats.add("DVD");
			}
		}
	}

	private void accompanyingMaterialCheck(char recordTypefromLeader, LinkedHashSet<String> printFormats){
		switch (recordTypefromLeader){
			case 'a' :
				// Language material  (text/books generally)
				if (printFormats.contains("CDROM")){
					printFormats.clear();
					printFormats.add("BookWithCDROM");
					break;
				}
				if (printFormats.contains("DVD")){
					printFormats.clear();
					printFormats.add("BookWithDVD");
					break;
				}
				if (printFormats.contains("VideoDisc")){
					printFormats.clear();
					printFormats.add("BookWithVideoDisc");
				}
		}
	}

	private void filterPrintFormats(Set<String> printFormats) {
		if (printFormats.size() == 1) {
			return;
		}
		if (printFormats.contains("Young Reader")) {
			printFormats.clear();
			printFormats.add("Young Reader");
			return;
		}
		if (printFormats.contains("Archival Materials")) {
			printFormats.clear();
			printFormats.add("Archival Materials");
			return;
		}
		if (printFormats.contains("Thesis")) {
			printFormats.clear();
			printFormats.add("Thesis");
		}
		if (printFormats.contains("Braille")) {
			printFormats.clear();
			printFormats.add("Braille");
			return;
		}
		if (printFormats.contains("Phonograph")){
			printFormats.clear();
			printFormats.add("Phonograph");
			return;
		}

		// Read-Along things
		if (printFormats.contains("VoxBooks")){
			printFormats.clear();
			printFormats.add("VoxBooks");
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

		// AudioBook Devices
		if (printFormats.contains("PlayawayView")){
			printFormats.clear();
			printFormats.add("PlayawayView");
			return;
		}
		if (printFormats.contains("Playaway")){
			printFormats.clear();
			printFormats.add("Playaway");
			return;
		}
		if (printFormats.contains("GoReader")){
			printFormats.clear();
			printFormats.add("GoReader");
			return;
		}

		// Video Things
		if (printFormats.contains("Video")){
			if (printFormats.contains("DVD")
					|| printFormats.contains("VideoDisc")
					|| printFormats.contains("VideoCassette")
			) {
				printFormats.remove("Video");
			}
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
		if (printFormats.contains("Blu-ray") && printFormats.contains("4KUltraBlu-Ray")){
			printFormats.remove("Blu-ray");
		}

		// Sound Things
		if (printFormats.contains("SoundCassette") && printFormats.contains("MusicRecording")){
			printFormats.clear();
			printFormats.add("MusicCassette");
		}
		if (printFormats.contains("MusicRecording") && (printFormats.contains("CD") || printFormats.contains("CompactDisc")
				|| printFormats.contains("SoundDisc")
				|| printFormats.contains("DVD") || printFormats.contains("Blu-ray") /* likely accompanying material */
		)){
			printFormats.clear();
			printFormats.add("MusicCD");
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
		if (printFormats.contains("CompactDisc")) {
			if (printFormats.contains("SoundCassette")
					|| printFormats.contains("SoundDisc")
			) {
				printFormats.remove("CompactDisc");
			}
		}
		if (printFormats.contains("CD") && printFormats.contains("SoundDisc")){
			printFormats.remove("CD");
		}
		if (printFormats.contains("AudioCD") && printFormats.contains("CD")){
			printFormats.remove("AudioCD");
		}
		if (printFormats.contains("DVD") && printFormats.contains("SoundDisc")){
			printFormats.remove("DVD");
		}
		if (printFormats.contains("GraphicNovel") && printFormats.contains("SoundDisc")){
			printFormats.remove("GraphicNovel");
		}
		if (printFormats.contains("GraphicNovel") && printFormats.contains("DVD")){
			printFormats.remove("GraphicNovel");
		}
		if (printFormats.contains("WindowsGame") && printFormats.contains("SoundDisc")){
			printFormats.remove("WindowsGame");
		}

		// Book Things
		if (printFormats.contains("Book")){
			if (printFormats.contains("LargePrint")
					|| printFormats.contains("Manuscript")
					|| printFormats.contains("GraphicNovel")
					|| printFormats.contains("MusicalScore")
					|| printFormats.contains("BookClubKit")
					|| printFormats.contains("Kit")
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
				|| printFormats.contains("NintendoDS") || printFormats.contains("3DS")
				|| printFormats.contains("WindowsGame")){
			printFormats.remove("Software");
			printFormats.remove("Electronic");
			printFormats.remove("CDROM");
			printFormats.remove("DVD");
			printFormats.remove("Blu-ray");
			printFormats.remove("4KUltraBlu-Ray");
		}
	}

	private void getFormatFromTitle(Record record, Set<String> printFormats) {
		String titleMedium = MarcUtil.getFirstFieldVal(record, "245h");
		if (titleMedium != null){
			titleMedium = titleMedium.toLowerCase();
			if (titleMedium.contains("sound recording-cass")){
				printFormats.add("SoundCassette");
			}else if (titleMedium.contains("large print")){
				printFormats.add("LargePrint");
			}else if (titleMedium.contains("braille")){
				printFormats.add("Braille");
			}else if (titleMedium.contains("book club kit") || titleMedium.contains("bookclub kit")){
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
				printFormats.add("CDROM");
			}else if (titleMedium.contains("dvd")){
				printFormats.add("DVD");
			}

		}
		String titleForm = MarcUtil.getFirstFieldVal(record, "245k");
		if (titleForm != null){
			titleForm = titleForm.toLowerCase();
			if (titleForm.contains("sound recording-cass")){
				printFormats.add("SoundCassette");
			}else if (titleForm.contains("large print")){
				printFormats.add("LargePrint");
			}else if (titleForm.contains("book club kit") || titleForm.contains("bookclub kit")){
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
			title = title.toLowerCase();
			if (title.contains("book club kit") || title.contains("bookclub kit")){
				printFormats.add("BookClubKit");
			}

			if (title.contains("young reader")) {
				printFormats.add("Young Reader");
			}
		}
		// Note: that checking for young reader's editions is not done in the format determination class.b54047699
		String subTitle = MarcUtil.getFirstFieldVal(record, "245b");
		if (subTitle != null){
			subTitle = subTitle.toLowerCase();
			if (subTitle.contains("young reader")) {
				printFormats.add("Young Reader");
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
				}else if (sysDetailsValue.contains("go reader")) {
					result.add("GoReader");
				}
			}
		}
	}

	private void getFormatFromEdition(Record record, Set<String> result) {
		// Check for large print book (large format in 650, 300, or 250 fields)
		// Check for blu-ray in 300 fields
//		DataField edition = record.getDataField("250");
		List<DataField> editions = record.getDataFields("250");
		for (DataField edition : editions) {
			if (edition != null) {
				if (edition.getSubfield('a') != null) {
					String editionData = edition.getSubfield('a').getData().toLowerCase();
					if (editionData.contains("large type") || editionData.contains("large print")) {
						result.add("LargePrint");
					} else if (editionData.contains("young reader")) {
						result.add("Young Reader");
					} else if (editionData.contains("go reader")) {
						result.add("GoReader");
					} else if (editionData.contains("wonderbook")) {
						result.add("WonderBook");
//				} else if (find4KUltraBluRayPhrases(editionData)) {
//					result.add("4KUltraBlu-Ray");
						// not sure this is a good idea yet. see D-2432
					} else {
						String gameFormat = getGameFormatFromValue(editionData);
						if (gameFormat != null) {
							result.add(gameFormat);
						}
					}
				}
			}
		}
	}


	private void getFormatFromPhysicalDescription(Record record, Set<String> result) {
		List<DataField> physicalDescription = MarcUtil.getDataFields(record, "300");
		if (physicalDescription != null) {
			Iterator<DataField> fieldsIter = physicalDescription.iterator();
			DataField           field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				List<Subfield> subFields = field.getSubfields();
				for (Subfield subfield : subFields) {
					if (subfield.getCode() != 'e') {
						String physicalDescriptionData = subfield.getData().toLowerCase();
						if (physicalDescriptionData.contains("large type") || physicalDescriptionData.contains("large print")) {
							result.add("LargePrint");
						} else if (physicalDescriptionData.contains("braille")){
							result.add("Braille");
						} else if (find4KUltraBluRayPhrases(physicalDescriptionData)) {
							result.add("4KUltraBlu-Ray");
						} else if (physicalDescriptionData.contains("bluray") || physicalDescriptionData.contains("blu-ray")) {
							result.add("Blu-ray");
						} else if (physicalDescriptionData.contains("cd-rom") || physicalDescriptionData.contains("cdrom")) {
							result.add("CDROM");
						} else if (physicalDescriptionData.contains("computer optical disc")) {
							result.add("Software");
						} else if (physicalDescriptionData.contains("sound cassettes")) {
							result.add("SoundCassette");
						} else if (physicalDescriptionData.contains("sound discs") || physicalDescriptionData.contains("audio discs") || physicalDescriptionData.contains("compact disc")) {
							result.add("SoundDisc");
						} else if (physicalDescriptionData.contains("wonderbook")) {
							result.add("WonderBook");
						}
						//Since this is fairly generic, only use it if we have no other formats yet
						if (result.size() == 0 && subfield.getCode() == 'f' && physicalDescriptionData.matches("^.*?\\d+\\s+(p\\.|pages).*$")) {
							result.add("Book");
						}
					}
				}
			}
		}
	}

	private void getFormatFromNotes(Record record, Set<String> result) {
		// Check for formats in the 538 field
		List<DataField> systemDetailsNotes = record.getDataFields("538");
		for (DataField sysDetailsNote2 : systemDetailsNotes) {
			if (sysDetailsNote2 != null) {
				if (sysDetailsNote2.getSubfield('a') != null) {
					String sysDetailsValue = sysDetailsNote2.getSubfield('a').getData().toLowerCase();
					String gameFormat      = getGameFormatFromValue(sysDetailsValue);
					if (gameFormat != null) {
						result.add(gameFormat);
					} else {
						if (sysDetailsValue.contains("playaway view")) {
							result.add("PlayawayView");
						} else if (sysDetailsValue.contains("playaway")) {
							result.add("Playaway");
						} else if (find4KUltraBluRayPhrases(sysDetailsValue)) {
							result.add("4KUltraBlu-Ray");
						} else if (sysDetailsValue.contains("bluray") || sysDetailsValue.contains("blu-ray")) {
							result.add("Blu-ray");
						} else if (sysDetailsValue.contains("dvd-rom") || sysDetailsValue.contains("dvdrom")) {
							result.add("CDROM");
						} else if (sysDetailsValue.contains("dvd")) {
							result.add("DVD");
						} else if (sysDetailsValue.contains("vertical file")) {
							result.add("VerticalFile");
						}
					}
				}
			}
		}

		// Check for formats in the 500 tag
		List<DataField> noteFields = record.getDataFields("500");
		for (DataField noteField : noteFields) {
			if (noteField != null) {
				if (noteField.getSubfield('a') != null) {
					String noteValue = noteField.getSubfield('a').getData().toLowerCase();
					if (noteValue.contains("vox book")) {
						result.add("VoxBooks");
					} else if (noteValue.contains("wonderbook")) {
						result.add("WonderBook");
					} else if (noteValue.contains("playaway view")) {
						result.add("PlayawayView");
					} else if (noteValue.contains("playaway")) {
						result.add("Playaway");
					} else if (noteValue.contains("vertical file")) {
						result.add("VerticalFile");
					}
				}
			}
		}

		// Check for formats in the 502 tag
		DataField dissertationNoteField = record.getDataField("502");
		if (dissertationNoteField != null) {
			if (dissertationNoteField.getSubfield('a') != null) {
				String noteValue = dissertationNoteField.getSubfield('a').getData().toLowerCase();
				if (noteValue.contains("thesis (m.a.)")) {
					result.add("Thesis");
				}
			}
		}

		// Check for formats in the 590 tag
		DataField localNoteField = record.getDataField("590");
		if (localNoteField != null) {
			if (localNoteField.getSubfield('a') != null) {
				String noteValue = localNoteField.getSubfield('a').getData().toLowerCase();
				if (noteValue.contains("archival materials")) {
					result.add("Archival Materials");
				}
			}
		}
	}

	private void getGameFormatFrom753(Record record, Set<String> result) {
		// Check for formats in the 753 field "System Details Access to Computer Files"
		DataField sysDetailsTag = record.getDataField("753");
		if (sysDetailsTag != null) {
			if (sysDetailsTag.getSubfield('a') != null) {
				String sysDetailsValue = sysDetailsTag.getSubfield('a').getData().toLowerCase();
				String gameFormat = getGameFormatFromValue(sysDetailsValue);
				if (gameFormat != null){
					result.add(gameFormat);
				}
			}
		}
	}

	private String getGameFormatFromValue(String value) {
		if (value.contains("kinect sensor")) {
			return "Kinect";
		} else if (value.contains("xbox series x") && !value.contains("compatible") && !value.contains("optimized for xbox series x")) {
			//xbox one games can contain the phrase "optimized for Xbox Series X"
			return "XboxSeriesX";
		} else if (value.contains("xbox one") && !value.contains("compatible")) {
			return "XboxOne";
		} else if (value.contains("xbox") && !value.contains("compatible")) {
			return "Xbox360";
		} else if (value.contains("playstation vita") /*&& !value.contains("compatible")*/) {
			return "PlayStationVita";
		} else if (value.contains("playstation 5") && !value.contains("compatible")) {
			return "PlayStation5";
		} else if (value.contains("playstation 4") && !value.contains("compatible")) {
			return "PlayStation4";
		} else if (value.contains("playstation 3") && !value.contains("compatible")) {
			return "PlayStation3";
		} else if (value.contains("playstation") && !value.contains("compatible")) {
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

	private void getFormatFromSubjects(Record record, Set<String> result) {
		List<DataField> topicalTerm = MarcUtil.getDataFields(record, "650");
		if (topicalTerm != null) {
			Iterator<DataField> fieldsIter = topicalTerm.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					if (subfield.getCode() == 'a'){
						String subfieldData = subfield.getData().toLowerCase();
						if (subfieldData.contains("large type") || subfieldData.contains("large print")) {
							result.add("LargePrint");
						}else if (subfieldData.contains("playaway view")) {
							result.add("PlayawayView");
						}else if (subfieldData.contains("playaway")) {
							result.add("Playaway");
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
						}
					} else if (subfield.getCode() == 'v') {
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
					if (fieldData.contains("playaway view")) {
						result.add("PlayawayView");
					}else if (fieldData.contains("playaway digital audio") || fieldData.contains("findaway world")) {
						result.add("Playaway");
					}
				}
			}
		}
	}

	private void getFormatFrom007(Record record, Set<String> result) {
		ControlField formatField = MarcUtil.getControlField(record, "007");
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


	private void getFormatFromLeader(Set<String> result, String leader, ControlField fixedField) {
		char leaderBit;
		char formatCode;// check the Leader at position 6
		if (leader.length() >= 6) {
			leaderBit = leader.charAt(6);
			switch (Character.toUpperCase(leaderBit)) {
				case 'C':
				case 'D':
					result.add("MusicalScore");
					break;
				case 'E':
				case 'F':
					result.add("Map");
					break;
				case 'G':
					// We appear to have a number of items without 007 tags marked as G's.
					// These seem to be Videos rather than Slides.
					// result.add("Slide");
					result.add("Video");
					break;
				case 'I':
					result.add("SoundRecording");
					break;
				case 'J':
					result.add("MusicRecording");
					break;
				case 'K':
					result.add("Photo");
					break;
				case 'M':
					result.add("Electronic");
					break;
				case 'O':
				case 'P':
					result.add("Kit");
					break;
				case 'R':
					result.add("PhysicalObject");
					break;
				case 'T':
					result.add("Manuscript");
					break;
			}
		}

		if (leader.length() >= 7) {
			// check the Leader at position 7
			leaderBit = leader.charAt(7);
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
					if (fixedField != null && fixedField.getData().length() >= 22) {
						formatCode = fixedField.getData().toUpperCase().charAt(21);
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

	private Boolean find4KUltraBluRayPhrases(String subject) {
		subject = subject.toLowerCase();
		return
				subject.contains("4k ultra hd blu-ray") ||
						subject.contains("4k ultra hd bluray") ||
						subject.contains("4k ultrahd blu-ray") ||
						subject.contains("4k ultrahd bluray") ||
						subject.contains("4k uh blu-ray") ||
						subject.contains("4k uh bluray") ||
						subject.contains("4k ultra high-definition blu-ray") ||
						subject.contains("4k ultra high-definition bluray") ||
						subject.contains("4k ultra high definition blu-ray") ||
						subject.contains("4k ultra high definition bluray")
				;
	}

	private HashSet<String> translateCollection(String mapName, Set<String> values, RecordIdentifier identifier) {
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

	private String translateValue(String mapName, String value, RecordIdentifier identifier){
		return translateValue(mapName, value, identifier, true);
	}

	private String translateValue(String mapName, String value, RecordIdentifier identifier, boolean reportErrors){
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
