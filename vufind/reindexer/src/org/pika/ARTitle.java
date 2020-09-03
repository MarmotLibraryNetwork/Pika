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
 * Accelerated Reader information for a title
 * Pika
 * User: Mark Noble
 * Date: 10/21/2015
 * Time: 5:11 PM
 */
class ARTitle {
	private String title;
	private String author;
	private String bookLevel;
	private String arPoints;
	private String interestLevel;

	public String getTitle() {
		return title;
	}

	public void setTitle(String title) {
		this.title = title;
	}

	public String getAuthor() {
		return author;
	}

	public void setAuthor(String author) {
		this.author = author;
	}

	String getBookLevel() {
		return bookLevel;
	}

	void setBookLevel(String bookLevel) {
		this.bookLevel = bookLevel;
	}

	String getArPoints() {
		return arPoints;
	}

	void setArPoints(String arPoints) {
		this.arPoints = arPoints;
	}

	String getInterestLevel() {
		return interestLevel;
	}

	void setInterestLevel(String interestLevel) {
		this.interestLevel = interestLevel;
	}
}
