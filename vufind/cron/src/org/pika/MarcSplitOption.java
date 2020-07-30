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

import org.marc4j.MarcStreamWriter;
import org.marc4j.MarcWriter;
import org.marc4j.marc.*;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.util.List;
import java.util.regex.Pattern;

/**
 * Information about how to split a MARC record
 * Pika
 * User: Mark Noble
 * Date: 11/21/2014
 * Time: 6:18 PM
 */
class MarcSplitOption {
	private MarcWriter marcWriter;
	private Pattern locationsToIncludePattern;
	private String itemTag;
	private char locationSubfield;

	void setFilename(String basePath, String filename) throws FileNotFoundException {
		if (basePath.endsWith("/")) {
			basePath = basePath.substring(0, basePath.length() -1);
		}
		File basePathFile = new File(basePath);
		if (!basePathFile.exists()){
			basePathFile.mkdirs();
		}
		marcWriter = new MarcStreamWriter(new FileOutputStream(basePath + "/" + filename), "UTF8", true);
	}

	void setLocationsToInclude(String locationsToInclude) {
		locationsToIncludePattern = Pattern.compile(locationsToInclude);
	}

	void setItemTag(String itemTag) {
		this.itemTag = itemTag;
	}

	void setLocationSubfield(char locationSubfield) {
		this.locationSubfield = locationSubfield;
	}

	public void close() {
		marcWriter.close();
	}

	void processRecord(Record curBib) {
		//Check to see if the bib is valid for this splitter
		List<DataField> itemFields = curBib.getDataFields(itemTag);
		boolean validBib = false;
		for (DataField curItem : itemFields){
			Subfield locationSubfieldInst = curItem.getSubfield(locationSubfield);
			if (locationSubfieldInst != null){
				String locationCode = locationSubfieldInst.getData().trim();
				if (locationsToIncludePattern.matcher(locationCode).matches()){
					validBib = true;
					break;
				}
			}
		}

		if (validBib) {
			MarcFactory factory = MarcFactory.newInstance();
			//if we have a valid bib, make a copy and write it to the split marc
			Record marcCopy = factory.newRecord();
			marcCopy.setLeader(curBib.getLeader());
			for (ControlField curField : curBib.getControlFields()) {
				marcCopy.addVariableField(curField);
			}
			for (DataField curField : curBib.getDataFields()) {
				boolean addField = true;
				if (curField.getTag().equals(itemTag)) {
					Subfield locationSubfieldInst = curField.getSubfield(locationSubfield);
					if (locationSubfieldInst != null) {
						String locationCode = locationSubfieldInst.getData();
						addField = locationsToIncludePattern.matcher(locationCode).matches();
					}
				} else {
					addField = true;
				}
				if (addField) {
					marcCopy.addVariableField(curField);
				}
			}
			marcWriter.write(marcCopy);
		}
	}
}
