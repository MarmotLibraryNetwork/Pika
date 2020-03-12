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
