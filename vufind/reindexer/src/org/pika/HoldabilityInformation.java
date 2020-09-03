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
 * Information about holdability for a title, includes related pTypes when applicable
 *
 * Pika
 * User: Mark Noble
 * Date: 8/26/2015
 * Time: 2:56 PM
 */
public class HoldabilityInformation {
	private boolean isHoldable;
	private HashSet<Long> holdablePTypes;

	public HoldabilityInformation(boolean holdable, HashSet<Long> holdablePTypes) {
		this.isHoldable = holdable;
		this.holdablePTypes = holdablePTypes;
	}

	public boolean isHoldable() {
		return isHoldable;
	}

	String holdablePTypesString = null;
	public String getHoldablePTypes() {
		if (holdablePTypesString == null){
			if (holdablePTypes.contains(9999L) || holdablePTypes.contains(999L)){
				holdablePTypesString = "9999";
			}else{
				holdablePTypesString = Util.getCsvSeparatedStringFromLongs(holdablePTypes);
			}
		}
		return holdablePTypesString;
	}
}
