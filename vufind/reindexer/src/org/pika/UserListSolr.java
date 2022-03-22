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

import org.apache.solr.common.SolrInputDocument;

import java.util.Date;
import java.util.HashSet;
import java.util.LinkedHashSet;

/**
 * Class to populate a Solr Document for a User List
 * Pika
 * User: Mark Noble
 * Date: 5/15/14
 * Time: 9:34 AM
 */
public class UserListSolr {
	private final GroupedWorkIndexer groupedWorkIndexer;
	private       long               id;
	private       HashSet<String>    relatedRecordIds          = new HashSet<>();
	private       String             author;
	private       String             title;
	private       HashSet<String>    contents                  = new HashSet<>();
	private       HashSet<String>    scopes                    = new HashSet<>();
	private       String             description;
	private       long               numTitles                 = 0;
	private       long               created;
	private       long               owningLibrary;
	private       String             owningLocation;
	private       boolean            ownerHasListPublisherRole = false;

	public UserListSolr(GroupedWorkIndexer groupedWorkIndexer) {
		this.groupedWorkIndexer = groupedWorkIndexer;
	}

	public SolrInputDocument getSolrDocument() {
		SolrInputDocument doc = new SolrInputDocument();
		doc.addField("id", "list" + id);
		doc.addField("recordtype", "list");

		doc.addField("record_details", relatedRecordIds);

		doc.addField("title", title);
		doc.addField("title_display", title);
		
		doc.addField("title_sort", Util.makeValueSortable(title));

		doc.addField("author", author);

		doc.addField("table_of_contents", contents);
		doc.addField("description", description);
		doc.addField("display_description", description);
		doc.addField("keywords", description);

		//TODO: Should we count number of views to determine popularity?
		doc.addField("popularity", Long.toString(numTitles));
//		doc.addField("num_holdings", numTitles);
		doc.addField("num_titles", numTitles);

		Long                  daysSinceAdded = null;
		LinkedHashSet<String> timeSinceAdded = new LinkedHashSet<>();
		if (created != 0) {
			Date dateAdded = new Date(created * 1000);
			daysSinceAdded = Util.getDaysSinceAddedForDate(dateAdded);
			timeSinceAdded = Util.getTimeSinceAddedForDate(dateAdded);
			doc.addField("days_since_added", daysSinceAdded);
		}

		// Set the scoped fields
		for (String scopeName: getScopes()) {
				if (created != 0) {
					doc.addField("local_days_since_added_" + scopeName, daysSinceAdded);
					if (timeSinceAdded.size() > 0) {
						doc.addField("local_time_since_added_" + scopeName, timeSinceAdded);
					}
				}
				doc.addField("format_" + scopeName, "List");
				doc.addField("format_category_" + scopeName, "Lists");
				doc.addField("scope_has_related_records", scopeName);
		}

		return doc;
	}

	public void setTitle(String title) {
		this.title = title;
	}

	public void setDescription(String description) {
		this.description = description;
	}

	public void setAuthor(String author) {
		this.author = author;
	}

	public void addListTitle(String groupedWorkId, Object title, Object author) {
		relatedRecordIds.add("grouped_work:" + groupedWorkId);
		contents.add(title + " - " + author);
		numTitles++;
	}

	public void setCreated(long created) {
		this.created = created;
	}

	public void setId(long id) {
		this.id = id;
	}

	public void setOwningLocation(String owningLocation) {
		this.owningLocation = owningLocation;
	}

	public void setOwningLibrary(long owningLibrary) {
		this.owningLibrary = owningLibrary;
	}

	public void setOwnerHasListPublisherRole(boolean ownerHasListPublisherRole){
		this.ownerHasListPublisherRole = ownerHasListPublisherRole;
	}

	private boolean scopesDetermined = false;

	public HashSet<String> getScopes() {
		if (!scopesDetermined){
			determineListScopes();
		}
		return scopes;
	}

	/**
	 * Determine which scope this User List will be in
	 */
	public void determineListScopes(){
		for (Scope scope: groupedWorkIndexer.getScopes()) {
			boolean okToInclude;
			final int publicListsToInclude = scope.getPublicListsToInclude();
			if (scope.isLibraryScope()) {
				okToInclude = (publicListsToInclude == 2) || //All public lists
								((publicListsToInclude == 1) && (scope.getLibraryId() == owningLibrary)) || //All lists for the current library
								((publicListsToInclude == 3) && ownerHasListPublisherRole && (scope.getLibraryId() == owningLibrary)) || //All lists for list publishers at the current library
								((publicListsToInclude == 4) && ownerHasListPublisherRole) //All lists for list publishers
				;
			} else {
				okToInclude = (publicListsToInclude == 3) || //All public lists
								((publicListsToInclude == 1) && (scope.getLibraryId() == owningLibrary)) || //All lists for the current library
								((publicListsToInclude == 2) && scope.getScopeName().equals(owningLocation)) || //All lists for the current location
								((publicListsToInclude == 4) && ownerHasListPublisherRole && (scope.getLibraryId() == owningLibrary)) || //All lists for list publishers at the current library
								((publicListsToInclude == 5) && ownerHasListPublisherRole && scope.getScopeName().equals(owningLocation)) || //All lists for list publishers the current location
								((publicListsToInclude == 6) && ownerHasListPublisherRole) //All lists for list publishers
				;
			}
			if (okToInclude) {
				final String scopeName = scope.getScopeName();
				scopes.add(scopeName);
			}
		}
		scopesDetermined = true;
	}
}
