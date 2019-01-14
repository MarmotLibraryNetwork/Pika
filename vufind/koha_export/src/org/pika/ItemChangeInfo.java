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
	private int itemLost;
	private int withdrawn;
//	private int suppress;
	private int restricted;
	private String onLoan;
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

	public int getItemLost() {
		return itemLost;
	}

	public void setItemLost(int itemLost) {
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

	public int getRestricted() {
		return restricted;
	}

	public void setRestricted(int restricted) {
		this.restricted = restricted;
	}

	public String getOnLoan() {
		return onLoan;
	}

	public void setOnLoan(String onLoan) {
		this.onLoan = onLoan;
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
