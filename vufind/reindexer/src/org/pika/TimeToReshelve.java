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

import java.util.regex.Pattern;

/**
 * Stores information about time to reshelve to override status for items
 * Pika
 * User: Mark Noble
 * Date: 1/28/2016
 * Time: 9:33 PM
 */
public class TimeToReshelve {
	private String locations;
	private Pattern locationsPattern;
	private long numHoursToOverride;
	private String status;
	private String groupedStatus;

	public void setLocations(String locations) {
		this.locations = locations;
		locationsPattern = Pattern.compile(locations);
	}

	public Pattern getLocationsPattern() {
		return locationsPattern;
	}

	public long getNumHoursToOverride() {
		return numHoursToOverride;
	}

	public void setNumHoursToOverride(long numHoursToOverride) {
		this.numHoursToOverride = numHoursToOverride;
	}

	public String getStatus() {
		return status;
	}

	public void setStatus(String status) {
		this.status = status;
	}

	public String getGroupedStatus() {
		return groupedStatus;
	}

	public void setGroupedStatus(String groupedStatus) {
		this.groupedStatus = groupedStatus;
	}
}
