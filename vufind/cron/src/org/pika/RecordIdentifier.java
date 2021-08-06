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

public class RecordIdentifier {
	private String  source;
	private String  identifier;
	private boolean suppressed;
	private String  suppressionReason;

	RecordIdentifier(String source, String identifier){
		setValue(source, identifier);
	}

	@Override
	public int hashCode() {
		return toString().hashCode();
	}

	private String myString = null;
	public String toString(){
		if (myString == null && source != null && identifier != null){
			myString = source + ":" + identifier;
		}
		return myString;
	}

	@Override
	public boolean equals(Object obj) {
		if (obj instanceof  RecordIdentifier){
			RecordIdentifier tmpObj = (RecordIdentifier)obj;
			return (tmpObj.source.equals(source) && tmpObj.identifier.equals(identifier));
		}else{
			return false;
		}
	}

	String getSourceAndId(){
		return toString();
	}

	String getSource() {
		return source;
	}

	boolean isValid() {
			return identifier.length() > 0;
		}

	public String getIdentifier() {
		return identifier;
	}

	void setValue(String source, String identifier) {
		this.source     = source.toLowerCase();
		identifier      = identifier.trim();
		this.identifier = identifier;
	}

	boolean isSuppressed() {
		return suppressed;
	}

	public void setSuppressed(boolean suppressed) {
		this.suppressed = suppressed;
	}

	public void setSuppressionReason(String suppressionReason) {
		this.suppressionReason = suppressionReason;
	}

	public String getSuppressionReason() {
		return suppressionReason;
	}
}
