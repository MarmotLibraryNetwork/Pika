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
 * Information about an item that has changed within Koha
 * Pika
 * User: Mark Noble
 * Date: 10/13/2014
 * Time: 9:47 AM
 */
public class ItemChangeInfo {
	private String itemId;
	private String location;
	private String subLocation;
	private String shelfLocation;
	private int damaged;
	private int withdrawn;
	//	private int suppress;
//	private int restricted;
	private String itemLost;
	private String dueDate;
	private String notForLoan;

	public String getItemId() {
		return itemId;
	}

	public void setItemId(String itemId) {
		this.itemId = itemId;
	}

	public String getLocation() {
		return location;
	}

	public void setLocation(String location) {
		this.location = location;
	}

	public int getDamaged() {
		return damaged;
	}

	public void setDamaged(int damaged) {
		this.damaged = damaged;
	}

	public String getItemLost() {
		return itemLost;
	}

	public void setItemLost(String itemLost) {
		this.itemLost = itemLost;
	}

	public int getWithdrawn() {
		return withdrawn;
	}

	public void setWithdrawn(int withdrawn) {
		this.withdrawn = withdrawn;
	}

//	public int getSuppress() {
//		return suppress;
//	}
//
//	public void setSuppress(int suppress) {
//		this.suppress = suppress;
//	}

//	public int getRestricted() {
//		return restricted;
//	}
//
//	public void setRestricted(int restricted) {
//		this.restricted = restricted;
//	}

	public String getDueDate() {
		return dueDate;
	}

	public void setDueDate(String dueDate) {
		this.dueDate = dueDate;
	}

	public String getNotForLoan() {
		return notForLoan;
	}

	public void setNotForLoan(String notForLoan) {
		this.notForLoan = notForLoan;
	}

	public String getShelfLocation() {
		return shelfLocation;
	}

	public void setShelfLocation(String shelfLocation) {
		this.shelfLocation = shelfLocation;
	}

	public String getSubLocation() {
		return subLocation;
	}

	public void setSubLocation(String subLocation) {
		this.subLocation = subLocation;
	}
}
