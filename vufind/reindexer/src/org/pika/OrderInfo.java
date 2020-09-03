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

class OrderInfo {
	private String orderRecordId;
	private String status;
	private String locationCode;
	private int numCopies;
	String getOrderRecordId() {
		return orderRecordId;
	}
	void setOrderRecordId(String orderRecordId) {
		this.orderRecordId = orderRecordId;
	}
	
	public String getStatus() {
		return status;
	}
	public void setStatus(String status) {
		this.status = status;
	}
	public String getLocationCode() {
		return locationCode;
	}
	public void setLocationCode(String locationCode) {
		this.locationCode = locationCode;
	}

	int getNumCopies() {
		return numCopies;
	}

	void setNumCopies(int numCopies) {
		this.numCopies = numCopies;
	}
}
