/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import java.util.HashSet;

public class HomePickUpInformation {

	private boolean       isHomePickup;
	private HashSet<Long> homePickUpPTypes;

	public HomePickUpInformation(boolean isHomePickup, HashSet<Long> homePickUpPTypes) {
		this.isHomePickup = isHomePickup;
		this.homePickUpPTypes = homePickUpPTypes;
	}

	public boolean isHomePickup() {
		return isHomePickup;
	}

	public String getHomePickUpPTypes() {
		if (homePickUpPTypes.contains(9999L)){
			return "9999";
		}else{
			return Util.getCsvSeparatedStringFromLongs(homePickUpPTypes);
		}
	}
}
