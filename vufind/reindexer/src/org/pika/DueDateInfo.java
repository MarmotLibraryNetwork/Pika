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
 * Created by mnoble on 8/8/2017.
 */
class DueDateInfo {

	private String itemId;
	private Date dueDate;

	public DueDateInfo() {}

	public DueDateInfo(String itemId, Date dueDate) {
		this.itemId  = itemId;
		this.dueDate = dueDate;
	}

	public DueDateInfo(String itemId, String dueDate) {
		long date = Long.parseLong(dueDate);
		this.itemId  = itemId;
		this.dueDate = new Date(date);
	}

	public void setItemId(String itemId) {
		this.itemId = itemId;
	}

	public String getItemId() {
		return itemId;
	}

	public void setDueDate(Date dueDate) {
		this.dueDate = dueDate;
	}

	public Date getDueDate() {
		return dueDate;
	}
}
