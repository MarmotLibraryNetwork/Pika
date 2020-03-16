package org.pika;

import org.apache.log4j.Logger;

import java.math.BigInteger;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.text.Normalizer;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   2/10/2020
 */
public class GroupedWork5 extends GroupedWorkBase implements Cloneable {

	String groupingLanguage = "";

	static Logger logger = Logger.getLogger(GroupedWork4.class);

	private static Pattern validCategories               = Pattern.compile("^(book|music|movie|comic)$");

	GroupedWork5() {
		version = 5;
	}

	@Override
	String getTitle() {
		return fullTitle;
	}

	@Override
	public void setTitle(String title, String subtitle, int numNonFilingCharacters) {
		if (subtitle != null && subtitle.length() > 0){
			title = normalizePassedInSubtitle(title, subtitle);
		}else{
			//Check for a subtitle within the main title
			title = normalizeSubtitleWithinMainTitle(title);
		}
		title = normalizeTitle(title, numNonFilingCharacters);
		this.fullTitle = title;

	}

	@Override
	String getAuthor() {
		return author;
	}

	@Override
	void setAuthor(String author) {
		originalAuthorName = author;
		this.author = normalizeAuthor(author);
	}

	@Override
	void overridePermanentId(String groupedWorkPermanentId) {
		this.permanentId = groupedWorkPermanentId;
	}

	@Override
	public void setGroupingCategory(String groupingCategory) {
		if (!validCategories.matcher(groupingCategory).matches()) {
			logger.error("Invalid grouping category " + groupingCategory);
		}else {
			this.groupingCategory = groupingCategory;
		}
	}

	@Override
	String getGroupingCategory() {
		return groupingCategory;
	}

	String getGroupingLanguage() {
		return groupingLanguage;
	}

	/**
	 * @param languageCode The ISO 639-2 (Bibliographic) language code See http://id.loc.gov/vocabulary/iso639-2.html
	 */
	void setGroupingLanguage(String languageCode){
		this.groupingLanguage = languageCode;
	}

	String getPermanentId() {
		if (this.permanentId == null) {
			StringBuilder permanentId;
			try {
				MessageDigest idGenerator = MessageDigest.getInstance("MD5");
				String        fullTitle   = getAuthoritativeTitle();
				if (fullTitle.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(fullTitle.getBytes());
				}

				String author = getAuthoritativeAuthor();
				if (author.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(author.getBytes());
				}
				if (groupingCategory.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(groupingCategory.getBytes());
				}
				if (groupingLanguage.equals("")) {
					idGenerator.update("--null--".getBytes());
				} else {
					idGenerator.update(groupingLanguage.getBytes());
				}

				if (uniqueIdentifier != null) {
					idGenerator.update(uniqueIdentifier.getBytes());
				}
				permanentId = new StringBuilder(new BigInteger(1, idGenerator.digest()).toString(16));
				while (permanentId.length() < 32) {
					permanentId.insert(0, "0");
				}
				//Insert -'s for formatting
				this.permanentId = permanentId.substring(0, 8) + "-" + permanentId.substring(8, 12) + "-" + permanentId.substring(12, 16) + "-" + permanentId.substring(16, 20) + "-" + permanentId.substring(20);
			} catch (NoSuchAlgorithmException e) {
				System.out.println("Error generating permanent id" + e.toString());
			}
		}
		//System.out.println("Permanent Id is " + this.permanentId);
		return this.permanentId;
	}


	/*
	* Normalizing functions and patterns
	*
	* */

	final private static Pattern initialsFix                   = Pattern.compile("(?<=[A-Z])\\.(?=(\\s|[A-Z]|$))");
	final private static Pattern apostropheStrip               = Pattern.compile("'s");
	final private static Pattern specialCharacterStrip         = Pattern.compile("[^\\p{L}\\d\\s]");
	final private static Pattern consecutiveSpaceStrip         = Pattern.compile("\\s{2,}");
	final private static Pattern bracketedCharacterStrip       = Pattern.compile("\\[(.*?)\\]");
	final private static Pattern sortTrimmingPattern           = Pattern.compile("(?i)^(?:(?:a|an|the|el|la|\"|')\\s)(.*)$");
	final private static Pattern commonSubtitlesSimplePattern  = Pattern.compile("(by\\s\\w+\\s\\w+|a novel of .*|stories|an autobiography|a biography|a memoir in books|poems|the movie|large print|graphic novel|magazine|audio cd|book club kit|with illustrations|book \\d+|the original classic edition|classic edition|a novel)$");
	final private static Pattern commonSubtitlesComplexPattern = Pattern.compile("((a|una)\\s(.*)novel(a|la)?|a(.*)memoir|a(.*)mystery|a(.*)thriller|by\\s\\w+\\s\\w+|an? .* story|a .*\\s?book|[\\w\\s]+series book \\d+|the[\\w\\s]+chronicles book \\d+|[\\w\\s]+trilogy book \\d+)$");
	final private static Pattern editionRemovalPattern         = Pattern.compile("(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|revised|\\d+\\S*)\\s+(edition|ed|ed\\.|update)");
	final private static Pattern firstPattern                  = Pattern.compile("1st");
	final private static Pattern secondPattern                 = Pattern.compile("2nd");
	final private static Pattern thirdPattern                  = Pattern.compile("3rd");
	final private static Pattern fourthPattern                 = Pattern.compile("4th");
	final private static Pattern fifthPattern                  = Pattern.compile("5th");
	final private static Pattern sixthPattern                  = Pattern.compile("6th");
	final private static Pattern seventhPattern                = Pattern.compile("7th");
	final private static Pattern eighthPattern                 = Pattern.compile("8th");
	final private static Pattern ninthPattern                  = Pattern.compile("9th");
	final private static Pattern tenthPattern                  = Pattern.compile("10th");
	final private static Pattern dashPattern                   = Pattern.compile("&#8211");
	final private static Pattern ampersandPattern              = Pattern.compile("&");
	final private static Pattern digitPattern                  = Pattern.compile("\\p{Nd}");

	private String normalizeAuthor(String author) {
		return AuthorNormalizer.getNormalizedName(author);
	}

	private String normalizeTitle(String fullTitle, int numNonFilingCharacters) {
		String groupingTitle;
		if (numNonFilingCharacters > 0 && numNonFilingCharacters < fullTitle.length()){
			groupingTitle = fullTitle.substring(numNonFilingCharacters);
		}else{
			groupingTitle = fullTitle;
		}

		groupingTitle = normalizeDiacritics(groupingTitle);
		groupingTitle = makeValueSortable(groupingTitle);
		groupingTitle = cleanTitleCharacters(groupingTitle);

		//Remove any bracketed parts of the title
		groupingTitle = removeBracketedPartOfTitle(groupingTitle); // this also removes any special characters.  TODO: make that its own step

		//Remove some common subtitles that are meaningless (do again here in case they were part of the title).
		String titleBeforeRemovingSubtitles = groupingTitle.trim();
		groupingTitle = removeCommonSubtitles(groupingTitle);

		groupingTitle = normalizeNumericTitleText(groupingTitle);

		//Remove editions
		groupingTitle = removeEditionInformation(groupingTitle);

		int titleEnd = 100;
		if (titleEnd < groupingTitle.length()) {
			groupingTitle = groupingTitle.substring(0, titleEnd);
		}
		groupingTitle = groupingTitle.trim(); //TODO: trim before the 100 character truncation
		if (groupingTitle.length() == 0 && titleBeforeRemovingSubtitles.length() > 0){
			logger.info("Title '" + fullTitle + "' was normalized to nothing, reverting to '" + titleBeforeRemovingSubtitles + "'");
			groupingTitle = titleBeforeRemovingSubtitles.trim();
		}
		return groupingTitle;
	}

	private String cleanTitleCharacters(String groupingTitle) {
		//Fix abbreviations
		groupingTitle = initialsFix.matcher(groupingTitle).replaceAll(" ");
		//Replace & with and for better matching
		groupingTitle = dashPattern.matcher(groupingTitle).replaceAll("-");
		groupingTitle = ampersandPattern.matcher(groupingTitle).replaceAll("and"); // TODO: avoid encoded sequences like &#174;

		groupingTitle = apostropheStrip.matcher(groupingTitle).replaceAll("s");
		groupingTitle = specialCharacterStrip.matcher(groupingTitle).replaceAll(" ").toLowerCase();

		//Replace consecutive spaces
		groupingTitle = consecutiveSpaceStrip.matcher(groupingTitle).replaceAll(" ");
		return groupingTitle;
	}

	private String removeEditionInformation(String groupingTitle) {
		groupingTitle = editionRemovalPattern.matcher(groupingTitle).replaceAll("");
		return groupingTitle;
	}

	private String normalizeNumericTitleText(String groupingTitle) {
		//Normalize numeric titles
		if (digitPattern.matcher(groupingTitle).find()) {
			groupingTitle = firstPattern.matcher(groupingTitle).replaceAll("first");
			groupingTitle = secondPattern.matcher(groupingTitle).replaceAll("second");
			groupingTitle = thirdPattern.matcher(groupingTitle).replaceAll("third");
			groupingTitle = fourthPattern.matcher(groupingTitle).replaceAll("fourth");
			groupingTitle = fifthPattern.matcher(groupingTitle).replaceAll("fifth");
			groupingTitle = sixthPattern.matcher(groupingTitle).replaceAll("sixth");
			groupingTitle = seventhPattern.matcher(groupingTitle).replaceAll("seventh");
			groupingTitle = eighthPattern.matcher(groupingTitle).replaceAll("eighth");
			groupingTitle = ninthPattern.matcher(groupingTitle).replaceAll("ninth");
			groupingTitle = tenthPattern.matcher(groupingTitle).replaceAll("tenth");
		}
		return groupingTitle;
	}

	private String removeCommonSubtitles(String groupingTitle) {
		boolean changeMade = true;
		while (changeMade){
			changeMade = false;
			Matcher commonSubtitleMatcher = commonSubtitlesSimplePattern.matcher(groupingTitle);
			if (commonSubtitleMatcher.find()) {
				groupingTitle = commonSubtitleMatcher.replaceAll("").trim();
				changeMade = true;
			}
		}
		return groupingTitle;
	}

	private String removeBracketedPartOfTitle(String groupingTitle) {
		//Remove any bracketed parts of the title
		String tmpTitle = bracketedCharacterStrip.matcher(groupingTitle).replaceAll("");
		//Make sure we don't strip the entire title
		if (tmpTitle.length() > 0){
			//And make sure we don't have just special characters
			tmpTitle = specialCharacterStrip.matcher(tmpTitle).replaceAll(" ").toLowerCase().trim();
			if (tmpTitle.length() > 0) {
				groupingTitle = tmpTitle;
				//}else{
				//	logger.warn("Just saved us from trimming " + groupingTitle + " to nothing");
			}
		}else{
			//The entire title is in brackets, just remove the brackets
			groupingTitle = groupingTitle.replace("[", "").replace("]","");
		}
		return groupingTitle;
	}

	private static String normalizeDiacritics(String textToNormalize){
		return Normalizer.normalize(textToNormalize, Normalizer.Form.NFKC);
	}

	private String normalizePassedInSubtitle(String title, String subtitle) {
		if (!title.endsWith(subtitle)){ //TODO: remove overdrive series statements in subtitle
			//Remove any complex subtitles since we know the beginning of the string
			String newSubtitle = cleanTitleCharacters(subtitle);
			if (newSubtitle.length() > 0) {
				newSubtitle = removeComplexSubtitles(newSubtitle);
				if (newSubtitle.length() > 0) {
					title += " " + newSubtitle;
					//} else {
					//	logger.debug("Removed subtitle " + subtitle);
				}
			}
		}else{
			logger.debug("Not appending subtitle '" + subtitle + "' because it was already part of the title '" + title + "'.");
		}
		return title;
	}

	private String removeComplexSubtitles(String newSubtitle) {
		newSubtitle = commonSubtitlesComplexPattern.matcher(newSubtitle).replaceAll("");
		return newSubtitle;
	}

	private String normalizeSubtitleWithinMainTitle(String title) {
		if (title.endsWith(":")){
			title = title.substring(0, title.length() -1);
		}
		int colonIndex = title.lastIndexOf(':');
		if (colonIndex > 0){
			String subtitleFromTitle = title.substring(colonIndex + 1).trim();
			String newSubtitle       = cleanTitleCharacters(subtitleFromTitle);
			String mainTitle         = title.substring(0, colonIndex).trim();
			newSubtitle = removeComplexSubtitles(newSubtitle);
			if (newSubtitle.length() > 0) {
				title =  mainTitle + " " + newSubtitle;
				//} else{
				//	logger.debug("Removed subtitle " + subtitleFromTitle);
			}
		}
		return title;
	}

	private static String makeValueSortable(String curTitle) {
		if (curTitle == null) return "";
		String sortTitle = curTitle.toLowerCase();
		Matcher sortMatcher = sortTrimmingPattern.matcher(sortTitle);
		if (sortMatcher.matches()) {
			sortTitle = sortMatcher.group(1);
		}
		sortTitle = sortTitle.trim();
		return sortTitle;
	}


}
