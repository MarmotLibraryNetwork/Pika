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

/**
 * Additional information about a relevant loan rule includes additional information about what PTypes the loan rule is relevant for
 *
 * Pika
 * User: Mark Noble
 * Date: 8/26/2015
 * Time: 2:51 PM
 */
public class RelevantLoanRule {
	private HashSet<Long>	patronTypes;
	private LoanRule loanRule;

	public RelevantLoanRule(LoanRule loanRule, HashSet<Long> patronTypes) {
		this.loanRule = loanRule;
		this.patronTypes = patronTypes;
	}

	public HashSet<Long> getPatronTypes() {
		return patronTypes;
	}

	public LoanRule getLoanRule() {
		return loanRule;
	}
}
