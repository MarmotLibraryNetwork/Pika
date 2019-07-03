package org.vufind;

public class HooplaExtractInfo {

	Long titleId;
	boolean active;
	String title;
	String kind;
	boolean parentalAdvisory;
	boolean demo;
	boolean profanity;
	String rating;
	boolean abridged;
	boolean children;  // Title appropriate for children
	double price;

	public Long getTitleId() {
		return titleId;
	}

	public void setTitleId(Long titleId) {
		this.titleId = titleId;
	}

	public boolean isActive() {
		return active;
	}

	public void setActive(boolean active) {
		this.active = active;
	}

	public String getTitle() {
		return title;
	}

	public void setTitle(String title) {
		this.title = title;
	}

	public String getKind() {
		return kind;
	}

	public void setKind(String kind) {
		this.kind = kind;
	}

	public boolean isParentalAdvisory() {
		return parentalAdvisory;
	}

	public void setParentalAdvisory(boolean parentalAdvisory) {
		this.parentalAdvisory = parentalAdvisory;
	}

	public boolean isDemo() {
		return demo;
	}

	public void setDemo(boolean demo) {
		this.demo = demo;
	}

	public boolean isProfanity() {
		return profanity;
	}

	public void setProfanity(boolean profanity) {
		this.profanity = profanity;
	}

	public String getRating() {
		return rating;
	}

	public void setRating(String rating) {
		this.rating = rating;
	}

	public boolean isAbridged() {
		return abridged;
	}

	public void setAbridged(boolean abridged) {
		this.abridged = abridged;
	}

	public boolean isChildren() {
		return children;
	}

	public void setChildren(boolean children) {
		this.children = children;
	}

	public double getPrice() {
		return price;
	}

	public void setPrice(double price) {
		this.price = price;
	}
}
