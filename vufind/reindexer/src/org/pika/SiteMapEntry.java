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
 * Created by jabedo on 9/25/2016.
 *
 * A single entry within a site map.
 */
class SiteMapEntry implements Comparable {

	/*
	*
	* The ownership is determined by scope and the sitemap will be loaded by scope.
	* So you need to either have a set of SiteMaps (1 per scope) or update SiteMapEntry to include the scope and then have a list of works within the SiteMapEntry.  The first option is probably better.
	* When you add a grouped work to a SiteMap you will need to loop through all of the scopes that you are building sitemaps for and check each scope to see if the record isLibraryOwned.  The logic will be similar to the logic in: updateIndexingStats.
	*
	*/
	private Long Id;
	private String permanentId;
	private double popularity;

	public Long getId() {
		return Id;
	}

	String getPermanentId() {
		return permanentId;
	}

	SiteMapEntry(Long Id, String permanentId, Double popularity) {
		this.permanentId = permanentId;
		this.Id = Id;
		this.popularity = popularity;
	}

	@Override
	public int compareTo(Object o) {
		//compare object based on popularity
		SiteMapEntry toCompare = (SiteMapEntry) o;
		return Double.compare(toCompare.popularity, this.popularity);
	}
}
