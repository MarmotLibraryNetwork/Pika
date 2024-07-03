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

	private final String  indexingProfileSourceName;
	private final Pattern locationCodePattern;

	OwnershipRule(String indexingProfileSourceName, @NotNull String locationCode){
		this.indexingProfileSourceName = indexingProfileSourceName;

		if (locationCode.isEmpty()){
			locationCode = ".*";
		}
		this.locationCodePattern = Pattern.compile(locationCode, Pattern.CASE_INSENSITIVE);
	}

	private HashMap<String, Boolean> ownershipResults = new HashMap<>();
	boolean isItemOwned(@NotNull String indexingProfileSourceName, @NotNull String locationCode){
		boolean isOwned = false;
		if (this.indexingProfileSourceName.equals(indexingProfileSourceName)){
			if (ownershipResults.containsKey(locationCode)){
				return ownershipResults.get(locationCode);
			}

			isOwned = locationCodePattern.matcher(locationCode).lookingAt();
			ownershipResults.put(locationCode, isOwned);
		}
		return  isOwned;
	}
}
