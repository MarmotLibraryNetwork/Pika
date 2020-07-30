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

/**
 * Contains statistics for an individual record processor (within a scope)
 *
 * Pika
 * User: Mark Noble
 * Date: 7/25/2015
 * Time: 9:13 PM
 */
public class indexingRecordProcessorStats {
	public int numRecordsOwned;
	public int numPhysicalItemsOwned;
	public int numOrderItemsOwned;
	public int numEContentOwned;
	public int numRecordsTotal;
	public int numPhysicalItemsTotal;
	public int numOrderItemsTotal;
	public int numEContentTotal;

	public void getData(ArrayList<String> dataFields) {
		dataFields.add(Integer.toString(numRecordsOwned));
		dataFields.add(Integer.toString(numPhysicalItemsOwned));
		dataFields.add(Integer.toString(numOrderItemsOwned));
		dataFields.add(Integer.toString(numEContentOwned));
		dataFields.add(Integer.toString(numRecordsTotal));
		dataFields.add(Integer.toString(numPhysicalItemsTotal));
		dataFields.add(Integer.toString(numOrderItemsTotal));
		dataFields.add(Integer.toString(numEContentTotal));
	}
}
