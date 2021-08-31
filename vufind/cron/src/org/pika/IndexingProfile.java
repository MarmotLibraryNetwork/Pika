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

/**
 * A copy of indexing profile information from the database
 *
 * Pika
 * User: Mark Noble
 * Date: 6/30/2015
 * Time: 10:38 PM
 */
public class IndexingProfile {
	public  Long    id;
	public  String  sourceName;
	public  String  marcPath;
	public  String  filenamesToInclude;
	public  String  marcEncoding;
	public  String  individualMarcPath;
	public  int     numCharsToCreateFolderFrom;
	public  boolean createFolderFromLeadingCharacters;
	public  String  recordNumberTag;
	public  char    recordNumberField;
	private String  recordNumberPrefix;
	private char    eContentDescriptor = ' ';
	private String  itemTag;
	private boolean doAutomaticEcontentSuppression;

	public String getRecordNumberTag() {
		return recordNumberTag;
	}

	public void setRecordNumberTag(String recordNumberTag) {
		this.recordNumberTag = recordNumberTag;
	}


	public String getRecordNumberPrefix() {
		return recordNumberPrefix;
	}

	public void setRecordNumberPrefix(String recordNumberPrefix) {
		this.recordNumberPrefix = recordNumberPrefix;
	}

	public boolean useEContentSubfield() {
		return this.eContentDescriptor != ' ';
	}

	public String getItemTag() {
		return itemTag;
	}

	public void setItemTag(String itemTag) {
		this.itemTag = itemTag;
	}

	public char getEContentDescriptor() {
		return eContentDescriptor;
	}

	public void setEContentDescriptor(char eContentDescriptor) {
		this.eContentDescriptor = eContentDescriptor;
	}

	public boolean isDoAutomaticEcontentSuppression() {
		return doAutomaticEcontentSuppression;
	}

	public void setDoAutomaticEcontentSuppression(boolean doAutomaticEcontentSuppression) {
		this.doAutomaticEcontentSuppression = doAutomaticEcontentSuppression;
	}
}
