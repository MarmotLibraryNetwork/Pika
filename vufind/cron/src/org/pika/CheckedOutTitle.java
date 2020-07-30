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
 * A title that is checked out to a user for reading history
 * Pika
 * User: Mark Noble
 * Date: 12/11/2014
 * Time: 1:34 PM
 */
class CheckedOutTitle {
	private Long id;
	private String groupedWorkPermanentId;
	private String source;
	private String sourceId;
	private String title;

	public Long getId() {
		return id;
	}

	public void setId(Long id) {
		this.id = id;
	}

	public String getGroupedWorkPermanentId() {
		return groupedWorkPermanentId;
	}

	void setGroupedWorkPermanentId(String groupedWorkPermanentId) {
		this.groupedWorkPermanentId = groupedWorkPermanentId;
	}

	public String getSource() {
		return source;
	}

	public void setSource(String source) {
		this.source = source;
	}

	String getSourceId() {
		return sourceId;
	}

	void setSourceId(String sourceId) {
		this.sourceId = sourceId;
	}

	public String getTitle() {
		return title;
	}

	public void setTitle(String title){
		this.title = title;
	}
}
