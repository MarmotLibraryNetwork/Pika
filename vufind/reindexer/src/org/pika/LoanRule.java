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

public class LoanRule {
	private Long    loanRuleId;
	private String  name;
	private Boolean holdable;
	private Boolean bookable;

	public Boolean getBookable() {
		return bookable;
	}

	public void setBookable(Boolean bookable) {
		this.bookable = bookable;
	}

	public Long getLoanRuleId() {
		return loanRuleId;
	}

	public void setLoanRuleId(Long loanRuleId) {
		this.loanRuleId = loanRuleId;
	}

	public String getName() {
		return name;
	}

	public void setName(String name) {
		this.name = name;
	}

	public Boolean getHoldable() {
		return holdable;
	}

	public void setHoldable(Boolean holdable) {
		this.holdable = holdable;
	}


}
