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

import java.util.ArrayList;
import java.util.TreeMap;

/**
 * Store stats about what has been indexed for each scope.
 *
 * Pika
 * User: Mark Noble
 * Date: 3/2/2015
 * Time: 7:14 PM
 */
public class ScopedIndexingStats {
	private String                                       scopeName;
	public int                                           numLocalWorks;
	public int                                           numTotalWorks;
	public TreeMap<String, indexingRecordProcessorStats> indexingRecordProcessorStats = new TreeMap<String, indexingRecordProcessorStats>();

	public ScopedIndexingStats(String scopeName, ArrayList<String> sourceNames) {
		this.scopeName = scopeName;
		for (String sourceName : sourceNames){
			indexingRecordProcessorStats.put(sourceName, new indexingRecordProcessorStats());
		}
	}

	public String getScopeName() {
		return scopeName;
	}

	public String[] getData() {
		ArrayList<String> dataFields = new ArrayList<>();
		dataFields.add(scopeName);
		dataFields.add(Integer.toString(numLocalWorks));
		dataFields.add(Integer.toString(numTotalWorks));
		for (indexingRecordProcessorStats indexingStats : indexingRecordProcessorStats.values()){
			indexingStats.getData(dataFields);
		}
		return dataFields.toArray(new String[dataFields.size()]);
	}
}
