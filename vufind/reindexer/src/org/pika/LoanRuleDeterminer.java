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

import java.util.HashSet;

public class LoanRuleDeterminer {
	private String        location;
	private String        trimmedLocation;
	private String        patronType;
	private HashSet<Long> patronTypes;
	private String        itemType;
	private HashSet<Long> itemTypes;
	private Long          loanRuleId;
	private boolean       active;
	private Long          rowNumber;

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
		HashSet<Long> result      = new HashSet<>();
		String[]      iTypeValues = numberRangeString.split(",");

		for (String iTypeValue : iTypeValues) {
			if (iTypeValue.indexOf('-') > 0) {
				String[] iTypeRange      = iTypeValue.split("-");
				Long     iTypeRangeStart = Long.parseLong(iTypeRange[0]);
				Long     iTypeRangeEnd   = Long.parseLong(iTypeRange[1]);
				for (Long j = iTypeRangeStart; j <= iTypeRangeEnd; j++) {
					result.add(j);
				}
			} else {
				result.add(Long.parseLong(iTypeValue));
			}
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
