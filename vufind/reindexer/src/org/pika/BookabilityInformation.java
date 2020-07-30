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
 * Information about bookability for a title, includes related pTypes when applicable
 * Pika
 * User: Mark Noble
 * Date: 8/26/2015
 * Time: 3:10 PM
 */
public class BookabilityInformation {
	private boolean isBookable;
	private HashSet<Long> bookablePTypes;

	public BookabilityInformation(boolean bookable, HashSet<Long> bookablePTypes) {
		this.isBookable = bookable;
		this.bookablePTypes = bookablePTypes;
	}

	public boolean isBookable() {
		return isBookable;
	}

	public String getBookablePTypes() {
		if (bookablePTypes.contains(9999L) || bookablePTypes.contains(999L)){
			return "9999";
		}else{
			return Util.getCsvSeparatedStringFromLongs(bookablePTypes);
		}
	}
}
