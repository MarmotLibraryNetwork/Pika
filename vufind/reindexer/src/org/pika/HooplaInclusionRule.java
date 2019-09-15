package org.pika;

import org.apache.log4j.Logger;

public class HooplaInclusionRule {
	protected Logger logger;

	private Long libraryId;
	private Long locationId;
	private String kind;  // Hoopla Format
	private float maxPrice;
	private boolean excludeParentalAdvisory;
	private boolean excludeProfanity;
	private boolean includeChildrenTitlesOnly;

	public HooplaInclusionRule(Logger logger) {
		this.logger = logger;
	}

	public Long getLibraryId() {
		return libraryId;
	}

	public void setLibraryId(Long libraryId) {
		this.libraryId = libraryId;
	}

	public Long getLocationId() {
		return locationId;
	}

	public void setLocationId(Long locationId) {
		this.locationId = locationId;
	}

	public String getKind() {
		return kind;
	}

	public void setKind(String kind) {
		this.kind = kind;
	}

	public float getMaxPrice() {
		return maxPrice;
	}

	public void setMaxPrice(float maxPrice) {
		this.maxPrice = maxPrice;
	}

	public boolean isExcludeParentalAdvisory() {
		return excludeParentalAdvisory;
	}

	public void setExcludeParentalAdvisory(boolean excludeParentalAdvisory) {
		this.excludeParentalAdvisory = excludeParentalAdvisory;
	}

	public boolean isExcludeProfanity() {
		return excludeProfanity;
	}

	public void setExcludeProfanity(boolean excludeProfanity) {
		this.excludeProfanity = excludeProfanity;
	}

	public boolean isIncludeChildrenTitlesOnly() {
		return includeChildrenTitlesOnly;
	}

	public void setIncludeChildrenTitlesOnly(boolean includeChildrenTitlesOnly) {
		this.includeChildrenTitlesOnly = includeChildrenTitlesOnly;
	}

	/**
	 * @param hooplaExtractInfo
	 * @param locationId
	 */
	public boolean doesLocationRuleApply(HooplaExtractInfo hooplaExtractInfo, Long locationId) {
		if (kind.equalsIgnoreCase(hooplaExtractInfo.getKind())) {
			return this.locationId != null && this.locationId.equals(locationId);
		}
		return false;
	}

	/**
	 * @param hooplaExtractInfo
	 * @param libraryId
	 */
	public boolean doesLibraryRuleApply(HooplaExtractInfo hooplaExtractInfo, Long libraryId){
		if (kind.equalsIgnoreCase(hooplaExtractInfo.getKind())) {
			return this.libraryId != null && this.libraryId.equals(libraryId);
		}
		return false;
	}

	public boolean isHooplaTitleExcluded(HooplaExtractInfo hooplaExtractInfo) {
		if (maxPrice == 0 || hooplaExtractInfo.getPrice() == 0 || hooplaExtractInfo.getPrice() <= maxPrice) {  // include title, if hoopla price isn't set or the max price isn't set or the hoopla price is less than or equal to the max price; and...
			if (!hooplaExtractInfo.isParentalAdvisory() || !isExcludeParentalAdvisory()) {    // if the title doesn't have a PA warning or we aren't excluding PA warning titles; and...
				if (!hooplaExtractInfo.isProfanity() || !isExcludeProfanity()) {              // if the title doesn't have profanity or we aren't excluding profanity; and...
					if (!isIncludeChildrenTitlesOnly() || hooplaExtractInfo.isChildren()) {  // if we aren't limiting to only children's titles or it is a children's title.
						return false;
					} else if (logger.isDebugEnabled()) {
						logger.debug("Not including because not a children's title for library " + this.libraryId + " or location " + this.locationId + " hoolpa id# " + hooplaExtractInfo.getTitleId() + " - " + hooplaExtractInfo.getTitle());
					}
				} else if (logger.isDebugEnabled()) {
					logger.debug("Excluding due to profanity for library " + this.libraryId + " or location " + this.locationId + " hoolpa id# " + hooplaExtractInfo.getTitleId() + " - " + hooplaExtractInfo.getTitle());
				}
			} else if (logger.isDebugEnabled()) {
				logger.debug("Excluding due to parental advisory for library " + this.libraryId + " or location " + this.locationId + " hoolpa id# " + hooplaExtractInfo.getTitleId() + " - " + hooplaExtractInfo.getTitle());
			}
		} else if (logger.isDebugEnabled()) {
			logger.debug("Excluding due to price  for library " + this.libraryId + " or location " + this.locationId + " hoolpa id# " + hooplaExtractInfo.getTitleId() + " - " + hooplaExtractInfo.getTitle());
		}
		return true; // Otherwise, exclude the title.
	}
}
