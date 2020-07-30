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

package org.nashville;

/**
 * Stores information about how patrons are converted between millennium/lss and CARL.X for use during conversion.
 *
 * Created by mnoble on 6/21/2017.
 */
class CarlXPatronMap {
	private String legacyId;
	private String source;
	private String patronId;
	private String patronGuid;

	CarlXPatronMap(String legacyId, String patronId, String patronGuid, String source) {
		this.legacyId = legacyId;
		this.patronId = patronId;
		this.patronGuid = patronGuid;
		this.source = source;
	}

	public String getKey() {
		if (source.equalsIgnoreCase("millennium")){
			//For millennium trim off the .p and the check digit so we get a match and change source to match Pika
			source = "ils";
			legacyId = legacyId.replace(".p", "");
			legacyId = legacyId.substring(0, legacyId.length() -1);
		}else if (source.equalsIgnoreCase("ls")){
			source = "lss";
		}
		return source + "-" + legacyId;
	}

	String getPatronGuid() {
		return patronGuid;
	}

	String getPatronId() {
		return patronId;
	}
}
