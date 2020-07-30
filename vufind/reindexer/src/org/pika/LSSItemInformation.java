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

import java.util.Date;

/**
 * Contains supplemental information about items from Library.Solutions for Schools that is not included in the
 * MARC export
 *
 * Pika
 * User: Mark Noble
 * Date: 7/24/2015
 * Time: 10:35 PM
 */
public class LSSItemInformation {
	private String resourceId;
	private String itemBarcode;
	private String holdingsCode;
	private String itemStatus;
	private String controlNumber;
	private int totalCirculations;
	private int checkoutsThisYear;
	private Date dateAddedToSystem;

	public String getResourceId() {
		return resourceId;
	}

	public void setResourceId(String resourceId) {
		this.resourceId = resourceId;
	}

	public String getItemBarcode() {
		return itemBarcode;
	}

	public void setItemBarcode(String itemBarcode) {
		this.itemBarcode = itemBarcode;
	}

	public String getHoldingsCode() {
		return holdingsCode;
	}

	public void setHoldingsCode(String holdingsCode) {
		this.holdingsCode = holdingsCode;
	}

	public String getItemStatus() {
		return itemStatus;
	}

	public void setItemStatus(String itemStatus) {
		this.itemStatus = itemStatus;
	}

	public String getControlNumber() {
		return controlNumber;
	}

	public void setControlNumber(String controlNumber) {
		this.controlNumber = controlNumber;
	}

	public int getTotalCirculations() {
		return totalCirculations;
	}

	public void setTotalCirculations(int totalCirculations) {
		this.totalCirculations = totalCirculations;
	}

	public int getCheckoutsThisYear() {
		return checkoutsThisYear;
	}

	public void setCheckoutsThisYear(int checkoutsThisYear) {
		this.checkoutsThisYear = checkoutsThisYear;
	}

	public Date getDateAddedToSystem() {
		return dateAddedToSystem;
	}

	public void setDateAddedToSystem(Date dateAddedToSystem) {
		this.dateAddedToSystem = dateAddedToSystem;
	}
}
