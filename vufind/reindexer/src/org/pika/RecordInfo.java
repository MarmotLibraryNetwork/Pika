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

import java.util.HashMap;
import java.util.HashSet;
import java.util.TreeMap;
import java.util.regex.Pattern;

/**
 * Information about a Record within the system
 *
 * Pika
 * User: Mark Noble
 * Date: 7/11/2015
 * Time: 12:05 AM
 */
public class RecordInfo {
	private String source;
	private String subSource;
	private String recordIdentifier;

	//Formats exist at both the item and record level because
	//Various systems define them in both ways.
	private HashSet<String> formats             = new HashSet<>();
	private HashSet<String> formatCategories    = new HashSet<>();
	private HashSet<String> allFormatCategories = null;
	private long            formatBoost         = 1;
	private String          primaryFormat       = null;

	private String  edition;
	private String  publisher;
	private String  publicationDate;
	private String  physicalDescription;
	private boolean hasVolumes;

	private String          primaryLanguage;
	private HashSet<String> languages            = new HashSet<>();
	private HashSet<String> translations         = new HashSet<>();

	private HashSet<ItemInfo> relatedItems = new HashSet<>();

	private boolean abridged = false;

	public RecordInfo(RecordIdentifier sourceAndId){
		this.source = sourceAndId.getSource();
		this.recordIdentifier = sourceAndId.getIdentifier();
	}

	public RecordInfo(String source, String recordIdentifier) {
		this.source           = source;
		this.recordIdentifier = recordIdentifier;
	}

	/**
	 * When dealing with the econtent in the ils, the source is set to
	 * external_econtent; and then the subSource will be set as the sourceName
	 * from the indexing profile.
	 *
	 * @param subSource the sourceName of the indexing profile of ils eContent
	 */
	void setSubSource(String subSource) {
		this.subSource = subSource;
	}

	public long getFormatBoost() {
		return formatBoost;
	}

	public void setFormatBoost(long formatBoost) {
		if (formatBoost > this.formatBoost) {
			this.formatBoost = formatBoost;
		}
	}

	void setEdition(String edition) {
		if (edition != null && !edition.isEmpty()) {
			this.edition = edition.replaceAll("[\\s.,;]$", "");
		}
	}

	void setPrimaryLanguage(String primaryLanguage) {
		this.primaryLanguage = primaryLanguage;
	}

	void setPublisher(String publisher) {
		if (publisher != null && !publisher.isEmpty()) {
			this.publisher = publisher.replaceAll("[\\s.,;]$", "");
		}
	}

	void setPublicationDate(String publicationDate) {
		if (publicationDate != null && !publicationDate.isEmpty()) {
			this.publicationDate = publicationDate.replaceAll("[\\s.,;]$", "");
		}
	}

	void setPhysicalDescription(String physicalDescription) {
		this.physicalDescription = physicalDescription;
	}

	HashSet<ItemInfo> getRelatedItems() {
		return relatedItems;
	}

	public String getRecordIdentifier() {
		return recordIdentifier;
	}

	private String recordDetails = null;

	String getDetails() {
		if (recordDetails == null) {
			//None of this changes by scope so we can just form it once and then return the previous value
			recordDetails = this.getFullIdentifier() + "|" +
							getPrimaryFormat() + "|" +
							getPrimaryFormatCategory() + "|" +
							Util.getCleanDetailValue(edition) + "|" +
							Util.getCleanDetailValue(primaryLanguage) + "|" + //TODO: needed anymore?
							Util.getCleanDetailValue(publisher) + "|" +
							Util.getCleanDetailValue(publicationDate) + "|" +
							Util.getCleanDetailValue(physicalDescription) //+ "|"
							+ (abridged ? "|1" : "") // only add if it is abridged
			;
		}
		return recordDetails;
	}

	String getPrimaryFormat() {
		if (primaryFormat == null) {
			HashMap<String, Integer> relatedFormats = new HashMap<>();
			for (String format : formats) {
				relatedFormats.put(format, 1);
			}
			for (ItemInfo curItem : relatedItems) {
				if (curItem.getFormat() != null) {
					relatedFormats.put(curItem.getFormat(), relatedFormats.getOrDefault(curItem.getFormat(), 1));
				}
			}
			int    timesUsed      = 0;
			String mostUsedFormat = null;
			for (String curFormat : relatedFormats.keySet()) {
				if (relatedFormats.get(curFormat) > timesUsed) {
					mostUsedFormat = curFormat;
					timesUsed      = relatedFormats.get(curFormat);
				}
			}
			if (mostUsedFormat == null) {
				return "Unknown";
			} else {
				primaryFormat = mostUsedFormat;
			}
		}

		return primaryFormat;
	}

	private String getPrimaryFormatCategory() {
		HashMap<String, Integer> relatedFormats = new HashMap<>();
		for (String format : formatCategories) {
			relatedFormats.put(format, 1);
		}
		for (ItemInfo curItem : relatedItems) {
			if (curItem.getFormatCategory() != null) {
				relatedFormats.put(curItem.getFormatCategory(), relatedFormats.getOrDefault(curItem.getFormatCategory(), 1));
			}
		}
		int    timesUsed      = 0;
		String mostUsedFormat = null;
		for (String curFormat : relatedFormats.keySet()) {
			if (relatedFormats.get(curFormat) > timesUsed) {
				mostUsedFormat = curFormat;
				timesUsed      = relatedFormats.get(curFormat);
			}
		}
		if (mostUsedFormat == null) {
			return "Unknown";
		}
		return mostUsedFormat;
	}

	public void addItem(ItemInfo itemInfo) {
		relatedItems.add(itemInfo);
		itemInfo.setRecordInfo(this);
	}

	private       HashSet<String> allFormats     = null;
	private final Pattern         nonWordPattern = Pattern.compile("\\W");

	HashSet<String> getAllSolrFieldEscapedFormats() {
		if (allFormats == null) {
			allFormats = new HashSet<>();
			for (String curFormat : formats) {
				allFormats.add(nonWordPattern.matcher(curFormat).replaceAll("_").toLowerCase());
			}
			for (ItemInfo curItem : relatedItems) {
				if (curItem.getFormat() != null) {
					allFormats.add(nonWordPattern.matcher(curItem.getFormat()).replaceAll("_").toLowerCase());
				}
			}
		}
		return allFormats;
	}

	HashSet<String> getFormats() {
		return formats;
	}

	HashSet<String> getAllSolrFieldEscapedFormatCategories() {
		if (allFormatCategories == null) {
			allFormatCategories = new HashSet<>();
			for (String curFormat : formatCategories) {
				allFormatCategories.add(nonWordPattern.matcher(curFormat).replaceAll("_").toLowerCase());
			}
			for (ItemInfo curItem : relatedItems) {
				if (curItem.getFormatCategory() != null) {
					allFormatCategories.add(nonWordPattern.matcher(curItem.getFormatCategory()).replaceAll("_").toLowerCase());
				}
			}
		}
		return allFormatCategories;
	}

	HashSet<String> getFormatCategories() {
		return formatCategories;
	}

	private HashSet<ItemInfo> getRelatedItemsForScope(String scopeName) {
		HashSet<ItemInfo> values = new HashSet<>();
		for (ItemInfo curItem : relatedItems) {
			if (curItem.isValidForScope(scopeName)) {
				values.add(curItem);
			}
		}
		return values;
	}

	int getNumCopiesOnOrder() {
		int numOrders = 0;
		for (ItemInfo curItem : relatedItems) {
			if (curItem.isOrderItem()) {
				numOrders += curItem.getNumCopies();
			}
		}
		return numOrders;
	}

	String getFullIdentifier() {
		String fullIdentifier;
		if (subSource != null && subSource.length() > 0) {
			fullIdentifier = source + ":" + subSource + ":" + recordIdentifier;
		} else {
			fullIdentifier = source + ":" + recordIdentifier;
		}
		return fullIdentifier;
	}

	int getNumPrintCopies() {
		int numPrintCopies = 0;
		for (ItemInfo curItem : relatedItems) {
			if (!curItem.isOrderItem() && !curItem.isEContent()) {
				numPrintCopies += curItem.getNumCopies();
			}
		}
		return numPrintCopies;
	}

	HashSet<String> getAllEContentSources() {
		HashSet<String> values = new HashSet<>();
		for (ItemInfo curItem : relatedItems) {
			values.add(curItem.geteContentSource());
		}
		return values;
	}

	HashSet<String> getAllCallNumbers() {
		HashSet<String> values = new HashSet<>();
		for (ItemInfo curItem : relatedItems) {
			values.add(curItem.getCallNumber());
		}
		return values;
	}

	void addFormats(HashSet<String> translatedFormats) {
		this.formats.addAll(translatedFormats);
	}

	void addFormat(String translatedFormat) {
		this.formats.add(translatedFormat);
	}

	void addFormatCategories(HashSet<String> translatedFormatCategories) {
		this.formatCategories.addAll(translatedFormatCategories);
	}

	void addFormatCategory(String translatedFormatCategory) {
		this.formatCategories.add(translatedFormatCategory);
	}

	void updateIndexingStats(TreeMap<String, ScopedIndexingStats> indexingStats) {
		for (ScopedIndexingStats scopedStats : indexingStats.values()) {
			String                       sourceName = this.subSource == null ? this.source : this.subSource;
			indexingRecordProcessorStats stats      = scopedStats.indexingRecordProcessorStats.get(sourceName.toLowerCase());
			if (stats != null) {
				HashSet<ItemInfo> itemsForScope = getRelatedItemsForScope(scopedStats.getScopeName());
				if (itemsForScope.size() > 0) {
					stats.numRecordsTotal++;
					boolean recordLocallyOwned = false;
					for (ItemInfo curItem : itemsForScope) {
						//Check the type (physical, eContent, on order)
						boolean locallyOwned = curItem.isLocallyOwned(scopedStats.getScopeName())
								|| curItem.isLibraryOwned(scopedStats.getScopeName());
						if (locallyOwned) {
							recordLocallyOwned = true;
						}
						if (curItem.isEContent()) {
							stats.numEContentTotal += curItem.getNumCopies();
							if (locallyOwned) {
								stats.numEContentOwned += curItem.getNumCopies();
							}
						} else if (curItem.isOrderItem()) {
							stats.numOrderItemsTotal += curItem.getNumCopies();
							if (locallyOwned) {
								stats.numOrderItemsOwned += curItem.getNumCopies();
							}
						} else {
							stats.numPhysicalItemsTotal += curItem.getNumCopies();
							if (locallyOwned) {
								stats.numPhysicalItemsOwned += curItem.getNumCopies();
							}
						}
					}
					if (recordLocallyOwned) {
						stats.numRecordsOwned++;
					}
				}
			}
		}
	}

	boolean hasItemFormats() {
		for (ItemInfo curItem : relatedItems) {
			if (curItem.getFormat() != null) {
				return true;
			}
		}
		return false;
	}

	// These pertain to Sierra Volume records (eg with ids starting with .j)
	void setHasVolumes(boolean hasVolumes) {
		this.hasVolumes = hasVolumes;
	}

	boolean hasVolumes() {
		return hasVolumes;
	}

	public HashSet<String> getLanguages() {
		return languages;
	}

	public HashSet<String> getTranslations() {
		return translations;
	}

	void setLanguages(HashSet<String> languages) {
		this.languages.addAll(languages);
	}

	void setTranslations(HashSet<String> translations){
		this.translations.addAll(translations);
	}

	public void setAbridged(boolean abridged) {
		this.abridged = abridged;
	}
}
