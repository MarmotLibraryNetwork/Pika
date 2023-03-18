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

import java.util.HashSet;

public class LoanRuleDeterminer {
	protected Logger        logger;
	private   String        location;
	private   String        trimmedLocation;
	private   String        patronType;
	private   HashSet<Long> patronTypes;
	private   String        itemType;
	private   HashSet<Long> itemTypes;
	private   Long          loanRuleId;
	private   boolean       active;
	private   Long          rowNumber;

	public LoanRuleDeterminer(Logger logger){
		this.logger = logger;
	}

	public Long getRowNumber() {
		return rowNumber;
	}

	public void setRowNumber(Long rowNumber) {
		this.rowNumber = rowNumber;
	}

	public String getLocation() {
		return location;
	}

	public void setLocation(String location) {
		location      = location.trim();
		this.location = location;
		if (location.endsWith("*")) {
			trimmedLocation = location.substring(0, location.length() - 1).toLowerCase();
		} else {
			trimmedLocation = location.toLowerCase();
		}
	}

	public Long getLoanRuleId() {
		return loanRuleId;
	}

	public void setLoanRuleId(Long loanRuleId) {
		this.loanRuleId = loanRuleId;
	}

	public boolean isActive() {
		return active;
	}

	public void setActive(boolean active) {
		this.active = active;
	}

	public String getPatronType() {
		return patronType;
	}

	public void setPatronType(String patronType) {
		this.patronType = patronType;
		patronTypes     = splitNumberRangeString(patronType);
	}

	public String getItemType() {
		return itemType;
	}

	public void setItemType(String itemType) {
		this.itemType = itemType;
		itemTypes     = splitNumberRangeString(itemType);
	}

	private HashSet<Long> splitNumberRangeString(String numberRangeString) {
		HashSet<Long> result = new HashSet<>();
		try {
			String[] iTypeValues = numberRangeString.split(",");
			for (String iTypeValue : iTypeValues) {
				if (iTypeValue.contains("-")) {
					String[] iTypeRange = iTypeValue.split("-");
					if (!iTypeRange[0].isEmpty()) {
						long iTypeRangeStart = Long.parseLong(iTypeRange[0]);
						if (iTypeRange.length == 2 && !iTypeRange[1].isEmpty()) {
							long iTypeRangeEnd = Long.parseLong(iTypeRange[1]);
							for (long j = iTypeRangeStart; j <= iTypeRangeEnd; j++) {
								result.add(j);
							}
						} else {
							logger.error("Ending range value missing for Loan Rule Determiner row number " + this.rowNumber + " for range value : " + numberRangeString);
						}
					} else {
						logger.error("Beginning range value missing for Loan Rule Determiner row number " + this.rowNumber + " for range value : " + numberRangeString);
					}
				} else {
					if (!iTypeValue.isEmpty()) {
						result.add(Long.parseLong(iTypeValue));
					} else {
						logger.warn("Empty parsed value for Loan Rule Determiner row number " + this.rowNumber + " for range value : " + numberRangeString + " (check for leading, trailing or double commas");
					}
				}
			}
		} catch (NumberFormatException e) {
			logger.error("Error parsing value for Loan Rule Determiner row number " + this.rowNumber, e);
		}
		if (result.size() == 0) {
			logger.warn("No value(s) set for Loan Rule Determiner row number " + this.rowNumber + " for range value : " + numberRangeString);
		}
		return result;
	}


	public boolean matchesLocation(String locationCode) {
		return location.equals("*") || location.equals("?????") || locationCode.toLowerCase().startsWith(this.trimmedLocation);
	}

	public HashSet<Long> getPatronTypes() {
		return patronTypes;
	}

	public HashSet<Long> getItemTypes() {
		return itemTypes;
	}

}
