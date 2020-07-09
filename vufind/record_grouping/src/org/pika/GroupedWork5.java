package org.pika;

import org.apache.log4j.Logger;
import org.jetbrains.annotations.NotNull;

import java.math.BigInteger;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
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

	static Logger logger = Logger.getLogger(GroupedWork5.class);

	private static final Pattern validCategories = Pattern.compile("^(book|music|movie|comic)$");

	GroupedWork5() {
		version = 5;
	}

	GroupedWork5(Connection pikaConn){
		super(pikaConn);
		version = 5;
	}

	@Override
	String getTitle() {
		return fullTitle;
	}

	@Override
	public void setTitle(String title, String subtitle, int numNonFilingCharacters) {
		setTitle(title, subtitle);
	}

	@Override
	public void setTitle(String title, String subtitle) {
		String fullTitle;

		//Normalize diacritical characters first
		title = normalizeDiacritics(title);

		//Process subtitles before we deal with the full title
		if (subtitle != null && !subtitle.isEmpty()) {
			subtitle = normalizeDiacritics(subtitle);
			fullTitle = normalizePassedInSubtitle(title, subtitle);
		} else {
			fullTitle = normalizeSubtitleWithinMainTitle(title);
		}

		// Now do the main normalizations on the full title
		this.fullTitle = normalizeTitle(fullTitle);
		}

	@Override
	String getAuthor() {
		return author;
	}

	@Override
	void setAuthor(String author) {
		originalAuthorName = author;
		if (author.matches("\\d+")) {
			// Movie running time as grouping author needs no normalization
			this.author = author;
		} else {
			this.author = normalizeAuthor(author);
		}
	}

	@Override
	void overridePermanentId(String groupedWorkPermanentId) {
		this.permanentId = groupedWorkPermanentId;
	}

	@Override
	public void setGroupingCategory(String groupingCategory, RecordIdentifier identifier) {
		if (validCategories.matcher(groupingCategory).matches()) {
			this.groupingCategory = groupingCategory;
		} else {
			logger.error("Invalid grouping category for " + identifier + " : " + groupingCategory);
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

	@Override
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

				String author = getAuthoritativeAuthor();				if (author.equals("")) {
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
	final private static Pattern sortTrimmingPattern           = Pattern.compile("(?i)^(?:(?:a|an|the|el|la|las|\"|')\\s)(.*)$");
	final private static Pattern commonSubtitlesSimplePattern  = Pattern.compile("(\\sby\\s\\w+\\s\\w+|a novel of .*|stories|an autobiography|a biography|a memoir in books|poems|the movie|large print|the graphic novel|graphic novel|magazine|audio cd|book club kit|with illustrations|playaway view|playaway|book \\d+|the original classic edition|classic edition|a novel)$");
	final private static Pattern commonSubtitlesComplexPattern = Pattern.compile("((a|una)\\s(.*)novel(a|la)?|a(.*)memoir|a(.*)mystery|a(.*)thriller|by\\s\\w+\\s\\w+|an? .* story|a .*\\s?book|[\\w\\s]+series book \\d+|the[\\w\\s]+chronicles book \\d+|[\\w\\s]+trilogy book \\d+)$");
	final private static Pattern editionRemovalPattern         = Pattern.compile("(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|revised|\\d+\\S*)\\s+(edition|ed|ed\\.|update|anniversary edition|comprehensive edition|anniversary commemorative edition)");
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
	final private static Pattern numericSeasonPattern          = Pattern.compile("season\\s\\p{Nd}");
	final private static Pattern numericSeriesPattern          = Pattern.compile("series\\s\\p{Nd}");

	private String normalizeAuthor(String author) {
		return AuthorNormalizer.getNormalizedName(author);
	}

	private String normalizeTitle(String fullTitle) {
		String groupingTitle;

		groupingTitle = makeValueSortable(fullTitle);
		//Remove any bracketed parts of the title
		groupingTitle = removeBracketedPartOfTitle(groupingTitle);

		groupingTitle = cleanTitleCharacters(groupingTitle);
		// Note: This would remove brackets

		//Remove some common subtitles that are meaningless (do again here in case they were part of the title).
		String titleBeforeRemovingSubtitles = groupingTitle.trim();
		groupingTitle = removeCommonSubtitlePatterns(groupingTitle);

		groupingTitle = normalizeNumericTitleText(groupingTitle);

		//Remove editions
		groupingTitle = removeEditionInformation(groupingTitle);

		groupingTitle = normalizeMovieSeasons(groupingTitle);

		//Trim before truncating
		groupingTitle = groupingTitle.trim();
		final int titleEnd = 400;
		if (titleEnd < groupingTitle.length()) {
			groupingTitle = groupingTitle.substring(0, titleEnd).trim();
		}

		if (groupingTitle.length() == 0 && titleBeforeRemovingSubtitles.length() > 0){
			if (logger.isDebugEnabled()) {
				logger.debug("Title '" + fullTitle + "' was normalized to nothing, reverting to '" + titleBeforeRemovingSubtitles + "'");
			}
			groupingTitle = titleBeforeRemovingSubtitles;
		}
		return groupingTitle;
	}

	/**
	 * @param titleString a full title, title, or subtitle
	 * @return a cleaned title string
	 */
	private String cleanTitleCharacters(String titleString) {
		//Fix abbreviations
		titleString = initialsFix.matcher(titleString).replaceAll(" ");
		titleString = dashPattern.matcher(titleString).replaceAll("-"); // todo: combine with html entities decoding
		titleString = ampersandPattern.matcher(titleString).replaceAll("and"); // TODO: avoid encoded sequences like &#174;
		//Replace & with and for better matching (Note: this must happen *before* the specialCharacterStrip is applied

		titleString = apostropheStrip.matcher(titleString).replaceAll("s");
		titleString = specialCharacterStrip.matcher(titleString).replaceAll(" ").toLowerCase();
		//Note: specialCharacterStrip will remove diacritical characters

		//Replace consecutive spaces
		titleString = consecutiveSpaceStrip.matcher(titleString).replaceAll(" ");
		return titleString;
	}

	private String removeEditionInformation(String groupingTitle) {
		groupingTitle = editionRemovalPattern.matcher(groupingTitle).replaceAll("");
		return groupingTitle;
	}
	private String normalizeMovieSeasons(String groupingTitle) {
		if (numericSeasonPattern.matcher(groupingTitle).find()){
			groupingTitle = groupingTitle.replaceAll("season 1", "season one");
			groupingTitle = groupingTitle.replaceAll("season 2", "season two");
			groupingTitle = groupingTitle.replaceAll("season 3", "season three");
			groupingTitle = groupingTitle.replaceAll("season 4", "season four");
			groupingTitle = groupingTitle.replaceAll("season 5", "season five");
			groupingTitle = groupingTitle.replaceAll("season 6", "season six");
			groupingTitle = groupingTitle.replaceAll("season 7", "season seven");
			groupingTitle = groupingTitle.replaceAll("season 8", "season eight");
			groupingTitle = groupingTitle.replaceAll("season 9", "season nine");
			groupingTitle = groupingTitle.replaceAll("season 10", "season ten");
		} else
		if (numericSeriesPattern.matcher(groupingTitle).find()){
			groupingTitle = groupingTitle.replaceAll("series 1", "series one");
			groupingTitle = groupingTitle.replaceAll("series 2", "series two");
			groupingTitle = groupingTitle.replaceAll("series 3", "series three");
			groupingTitle = groupingTitle.replaceAll("series 4", "series four");
			groupingTitle = groupingTitle.replaceAll("series 5", "series five");
			groupingTitle = groupingTitle.replaceAll("series 6", "series six");
			groupingTitle = groupingTitle.replaceAll("series 7", "series seven");
			groupingTitle = groupingTitle.replaceAll("series 8", "series eight");
			groupingTitle = groupingTitle.replaceAll("series 9", "series nine");
			groupingTitle = groupingTitle.replaceAll("series 10", "series ten");
		}
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

	private String removeCommonSubtitlePatterns(String groupingTitle) {
		boolean changeMade;
		do{
			changeMade = false;
			Matcher commonSubtitleMatcher = commonSubtitlesSimplePattern.matcher(groupingTitle);
			if (commonSubtitleMatcher.find()) {
				groupingTitle = commonSubtitleMatcher.replaceAll("").trim();
				changeMade = true;
			}
		} while (changeMade);
		return groupingTitle;
	}

	private String removeBracketedPartOfTitle(String groupingTitle) {
		//Remove any bracketed parts of the title
		String tmpTitle = bracketedCharacterStrip.matcher(groupingTitle).replaceAll("");
		//Make sure we don't strip the entire title
		if (tmpTitle.length() > 0){
			// I Suspect this is bad idea. pascal 7/9/2020
			//And make sure we don't have just special characters
			String noSpecialCharactersTmpTitle = specialCharacterStrip.matcher(tmpTitle).replaceAll(" ").toLowerCase().trim();
			//Note: specialCharacterStrip will remove diacritical characters
			if (noSpecialCharactersTmpTitle.length() == 0) {
				logger.info("After removing brackets, there were only special characters: '" + groupingTitle + "' to '" + tmpTitle + "'" );
			} else {
				groupingTitle = tmpTitle;
			}
		}else{
			//The entire title is in brackets, just remove the brackets
			groupingTitle = groupingTitle.replace("[", "").replace("]","");
		}
		return groupingTitle;
	}

	private static String normalizeDiacritics(String textToNormalize){
		textToNormalize = Normalizer.normalize(textToNormalize, Normalizer.Form.NFD).replaceAll("\\p{InCombiningDiacriticalMarks}+", "");
		// Some characters that need replaced manually. (Replacing capitalized versions with lower case equivalent)
		textToNormalize = textToNormalize
				.replaceAll("ø", "o").replaceAll("Ø", "o")
				.replaceAll("Æ", "ae").replaceAll("æ", "ae")
				.replaceAll("Ð", "d").replaceAll("ð", "d")
				.replaceAll("Œ", "oe").replaceAll("œ", "oe")
				.replaceAll("Þ", "th")
				.replaceAll("ß", "ss")
				.replaceAll("ƒ", "f");
		return Normalizer.isNormalized(textToNormalize, Normalizer.Form.NFKC) ? textToNormalize : Normalizer.normalize(textToNormalize, Normalizer.Form.NFKC);
	}

	private String removeComplexSubtitles(String newSubtitle) {
		newSubtitle = commonSubtitlesComplexPattern.matcher(newSubtitle).replaceAll("");
		//Note: This removes Overdrive series statements from the subtitle as well
		return newSubtitle;
	}

	/**
	 * Normalize the subtitle when we know what the subtitle is
	 *
	 * @param title    title that doesn't contain the subtitle
	 * @param subtitle the separate subtitle
	 * @return the full title with the subtitle included
	 */
	private String normalizePassedInSubtitle(@NotNull String title, String subtitle) {
		if (!title.endsWith(subtitle)){
			// Remove bracketed subtitles
			String newSubtitle = bracketedCharacterStrip.matcher(subtitle).replaceAll("");
			if (!newSubtitle.isEmpty()) {
				newSubtitle = cleanTitleCharacters(newSubtitle);
				if (!newSubtitle.isEmpty()) {
					//Remove any complex subtitles since we know the beginning of the string
					newSubtitle = removeComplexSubtitles(newSubtitle);
					if (!newSubtitle.isEmpty()) {
						title += " " + newSubtitle;
					}
				}
			}
		}else{
			if (logger.isDebugEnabled()) {
				logger.debug("Not appending subtitle '" + subtitle + "' because it was already part of the title '" + title + "'.");
			}
		}
		return title;
	}

	/**
	 * Check for a subtitle within the main title by searching for a colon character
	 * and clean up the subtitle
	 *
	 * @param title a full title that may have a subtitle
	 * @return the full title with a cleaned up subtitle
	 */
	private String normalizeSubtitleWithinMainTitle(String title) {
		if (title.endsWith(":")){
			title = title.substring(0, title.length() -1);
		}
		int colonIndex = title.lastIndexOf(':');
		if (colonIndex > 0){
			String mainTitle         = title.substring(0, colonIndex).trim();
			String subtitleFromTitle = title.substring(colonIndex + 1).trim();
			return normalizePassedInSubtitle(mainTitle, subtitleFromTitle);
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
