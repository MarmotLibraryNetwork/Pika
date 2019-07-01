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
