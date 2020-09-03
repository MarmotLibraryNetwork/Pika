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

public class TestResource {
	private int record_id;
	private String source;
	private String title;
	private String author;
	private String isbn;
	private String upc;
	private String format;
	private String format_category;
	
	public TestResource(int record_id, String source, String title, String author, String isbn, String upc, String format, String format_category){
		this.record_id = record_id;
		this.source = source;
		this.title = title;
		this.author = author;
		this.isbn = isbn;
		this.upc = upc;
		this.format = format;
		this.format_category = format_category;
	}

	public int getRecord_id() {
		return record_id;
	}

	public String getSource() {
		return source;
	}

	public String getTitle() {
		return title;
	}

	public String getAuthor() {
		return author;
	}

	public String getIsbn() {
		return isbn;
	}

	public String getUpc() {
		return upc;
	}

	public String getFormat() {
		return format;
	}

	public String getFormat_category() {
		return format_category;
	}
}