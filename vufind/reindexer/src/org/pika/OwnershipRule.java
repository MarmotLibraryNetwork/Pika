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

import com.sun.istack.internal.NotNull;

import java.util.HashMap;
import java.util.regex.Pattern;

/**
 * Required information to determine what records are owned directly by a library or location
 *
 * Pika
 * User: Mark Noble
 * Date: 7/10/2015
 * Time: 10:49 AM
 */
public class OwnershipRule {
	private String recordType;

	private Pattern locationCodePattern;
	private Pattern subLocationCodePattern;

	OwnershipRule(String recordType, @NotNull String locationCode, @NotNull String subLocationCode){
		this.recordType = recordType;

		if (locationCode.length() == 0){
			locationCode = ".*";
		}
		this.locationCodePattern = Pattern.compile(locationCode, Pattern.CASE_INSENSITIVE);
		if (subLocationCode.length() == 0){
			subLocationCode = ".*";
		}
		this.subLocationCodePattern = Pattern.compile(subLocationCode, Pattern.CASE_INSENSITIVE);
	}

	private HashMap<String, Boolean> ownershipResults = new HashMap<>();
	boolean isItemOwned(@NotNull String recordType, @NotNull String locationCode, @NotNull String subLocationCode){
		boolean isOwned = false;
		if (this.recordType.equals(recordType)){
			String key = locationCode + "-" + subLocationCode;
			if (ownershipResults.containsKey(key)){
				return ownershipResults.get(key);
			}

			isOwned = locationCodePattern.matcher(locationCode).lookingAt() && (subLocationCode == null || subLocationCodePattern.matcher(subLocationCode).lookingAt());
			ownershipResults.put(key, isOwned);
		}
		return  isOwned;
	}
}
