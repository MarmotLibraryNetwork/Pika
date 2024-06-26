/*
 * Copyright (C) 2023  Marmot Library Network
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

import org.apache.logging.log4j.Logger;
import org.apache.solr.client.solrj.SolrQuery;
import org.apache.solr.client.solrj.SolrServerException;
import org.apache.solr.client.solrj.impl.BinaryRequestWriter;
import org.apache.solr.client.solrj.impl.HttpSolrClient;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrClient;
import org.apache.solr.client.solrj.response.QueryResponse;
import org.apache.solr.client.solrj.response.UpdateResponse;
import org.apache.solr.common.SolrDocument;
import org.apache.solr.common.SolrDocumentList;

import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.HashMap;
import java.util.HashSet;

/**
 * Handles setting up solr documents for User Lists
 *
 * Pika
 * User: Mark Noble
 * Date: 7/10/2015
 * Time: 5:14 PM
 */
public class UserListProcessor {
	private GroupedWorkIndexer    indexer;
	private Connection            pikaConn;
	private Logger                logger;
	private boolean               fullReindex;
	private HashMap<Long, Long>   librariesByHomeLocation     = new HashMap<>();
	private HashMap<Long, String> locationCodesByHomeLocation = new HashMap<>();
	private HashSet<Long>         listPublisherUsers          = new HashSet<>();

	public UserListProcessor(GroupedWorkIndexer indexer, Connection pikaConn, Logger logger, boolean fullReindex) {
		this.indexer                       = indexer;
		this.pikaConn                      = pikaConn;
		this.logger                        = logger;
		this.fullReindex                   = fullReindex;
		//Load a list of all list publishers
		try (
			PreparedStatement listPublishersStmt = pikaConn.prepareStatement("SELECT userId FROM `user_roles` INNER JOIN roles ON user_roles.roleId = roles.roleId WHERE name = 'listPublisher'");
			ResultSet         listPublishersRS   = listPublishersStmt.executeQuery()
		){
			while (listPublishersRS.next()) {
				listPublisherUsers.add(listPublishersRS.getLong(1));
			}
		} catch (Exception e) {
			logger.error("Error loading a list of users with the listPublisher role", e);
		}
	}

	public Long processPublicUserLists(long lastReindexTime, HttpSolrClient solrServer, boolean userListsOnly) {
		GroupedReindexMain.addNoteToReindexLog("Starting to process public lists");
		long                       numListsProcessed = 0L;
		long                       numListsSkipped   = 0L;
		String                     solrPort          = PikaConfigIni.getIniValue("Reindex", "solrPort");
		final String               baseSolrUrl       = "http://localhost:" + solrPort + "/solr/grouped";
		ConcurrentUpdateSolrClient updateServer      = new ConcurrentUpdateSolrClient.Builder(baseSolrUrl).withQueueSize(500).withThreadCount(8).build();
		updateServer.setRequestWriter(new BinaryRequestWriter());
		// Build a new update server in case the full index one closed on us
		try {
			PreparedStatement listsStmt;
			final String      sql = "SELECT user_list.id AS id, deleted, public, title, description, user_list.created, dateUpdated, firstname, lastname, displayName, homeLocationId, user_id FROM user_list INNER JOIN user ON user_id = user.id ";
			if (fullReindex || userListsOnly) {
				if (userListsOnly) {
					// Delete all lists from the index
					// (During a full re-index all documents are deleted, so lists should already be gone.)
					logger.info("Deleting all lists from index");
					try {
						UpdateResponse response = updateServer.deleteByQuery("recordtype:list");
						if (logger.isInfoEnabled()){
							//TODO: getResponse is an object
							logger.info(response.getResponse());
						}
					} catch (SolrServerException | IOException e) {
						logger.warn("Error deleting lists from index", e);
					}
				}
				//Get a list of all public lists
				listsStmt = pikaConn.prepareStatement(sql + "WHERE public = 1 AND deleted = 0");
			} else {
				//Get a list of all lists that are were changed since the last update
				listsStmt = pikaConn.prepareStatement(sql + "WHERE dateUpdated > ?");
				listsStmt.setLong(1, lastReindexTime);
			}


			try (
							PreparedStatement getLibraryForHomeLocation = pikaConn.prepareStatement("SELECT libraryId, locationId FROM location");
							ResultSet librariesByHomeLocationRS = getLibraryForHomeLocation.executeQuery()
			) {
				while (librariesByHomeLocationRS.next()) {
					librariesByHomeLocation.put(librariesByHomeLocationRS.getLong("locationId"), librariesByHomeLocationRS.getLong("libraryId"));
				}
			}

			try (
							PreparedStatement getCodeForHomeLocation    = pikaConn.prepareStatement("SELECT code, locationId FROM location");
							ResultSet codesByHomeLocationRS = getCodeForHomeLocation.executeQuery()
			) {
				while (codesByHomeLocationRS.next()) {
					locationCodesByHomeLocation.put(codesByHomeLocationRS.getLong("locationId"), codesByHomeLocationRS.getString("code"));
				}
			}

			try (
			PreparedStatement getTitlesForListStmt = pikaConn.prepareStatement("SELECT groupedWorkPermanentId, notes FROM user_list_entry WHERE listId = ?");
			ResultSet         allPublicListsRS     = listsStmt.executeQuery()
			) {
				while (allPublicListsRS.next()) {
					if (updateSolrForList(updateServer, solrServer, getTitlesForListStmt, allPublicListsRS, userListsOnly)) {
						numListsProcessed++;
					} else {
						numListsSkipped++;
					}
				}
				if (numListsProcessed > 0 && (fullReindex || userListsOnly)) {
					GroupedReindexMain.addNoteToReindexLog("Committing changes for public lists, processed " + numListsProcessed);
					GroupedReindexMain.addNoteToReindexLog("Number of public lists skipped (belonged to no scopes) : " + numListsSkipped);
					updateServer.commit(true, true);
				}
			}

		} catch (Exception e) {
			logger.error("Error processing public lists", e);
		}
		GroupedReindexMain.addNoteToReindexLog("Finished processing public lists");
		return numListsProcessed;
	}

	/**
	 * @param updateServer          Solr indexer core
	 * @param solrServer            Solr searcher core
	 * @param getTitlesForListStmt  SQL statement to fetch grouped work ID and list note for list entry
	 * @param allPublicListsRS      SQL results for user list to process
	 * @return Whether the list was indexed or not
	 * @throws SQLException
	 * @throws SolrServerException
	 * @throws IOException
	 */
	private boolean updateSolrForList(ConcurrentUpdateSolrClient updateServer, HttpSolrClient solrServer, PreparedStatement getTitlesForListStmt, ResultSet allPublicListsRS, boolean userListsOnly) throws SQLException, SolrServerException, IOException {
		UserListSolr userListSolr = new UserListSolr(indexer);
		long         listId       = allPublicListsRS.getLong("id");

		long userId   = allPublicListsRS.getLong("user_id");
		if (!fullReindex  && !userListsOnly) {
			int deleted  = allPublicListsRS.getInt("deleted");
			int isPublic = allPublicListsRS.getInt("public");
			if (deleted == 1 || isPublic == 0) {
				// Remove list from search when deleted or made private
				try {
					updateServer.deleteByQuery("id:list" + listId);
				} catch (SolrServerException | IOException e) {
					logger.error("Failed to delete User List " + listId, e);
				}
			}
		} else {
			// Set all the properties needed to determine the scopes the user list belongs to
			userListSolr.setOwnerHasListPublisherRole(listPublisherUsers.contains(userId));
			long patronHomeLibrary = allPublicListsRS.getLong("homeLocationId");
			if (librariesByHomeLocation.containsKey(patronHomeLibrary)) {
				userListSolr.setOwningLibrary(librariesByHomeLocation.get(patronHomeLibrary));
			} else {
				//Don't know the owning library for some reason
				if (logger.isInfoEnabled()) {
					logger.info("Don't know library for user " + userId + ", owner of public list " + listId);
				}
				userListSolr.setOwningLibrary(-1);
			}
			if (locationCodesByHomeLocation.containsKey(patronHomeLibrary)) {
				userListSolr.setOwningLocation(locationCodesByHomeLocation.get(patronHomeLibrary));
			} else {
				//Don't know the owning location
				if (logger.isInfoEnabled()) {
					logger.info("Don't know location for user " + userId + ", owner of public list " + listId);
				}
				userListSolr.setOwningLocation("");
			}

			// If the list does not appear in any scope, skip further processing
			int numberOfScopes = userListSolr.getScopes().size();
			if (numberOfScopes > 0) {
				if (logger.isDebugEnabled()) {
					logger.debug("Processing list " + listId + " " + allPublicListsRS.getString("title"));
				}

				userListSolr.setId(listId);
				userListSolr.setTitle(allPublicListsRS.getString("title"));
				userListSolr.setDescription(allPublicListsRS.getString("description"));
				userListSolr.setCreated(allPublicListsRS.getLong("created"));

				String displayName = allPublicListsRS.getString("displayName");
				if (displayName != null && !displayName.isEmpty()) {
					userListSolr.setAuthor(displayName);
				} else {
					if (logger.isDebugEnabled()) {
						logger.debug("User " + userId + " owner of public list " + listId +
										" does not have their display name set, falling back to first initial, last name"
						);
					}
					String firstName = allPublicListsRS.getString("firstname");
					String lastName  = allPublicListsRS.getString("lastname");
					if (firstName == null) firstName = "";
					if (lastName == null) lastName = "";
					String firstNameFirstChar = "";
					if (!firstName.isEmpty()) {
						firstNameFirstChar = firstName.charAt(0) + ". ";
					}
					userListSolr.setAuthor(firstNameFirstChar + lastName);
				}


				//Get information about all the list titles.
				getTitlesForListStmt.setLong(1, listId);
//				StringBuilder groupedWorkIds = new StringBuilder();
				try (ResultSet allTitlesRS = getTitlesForListStmt.executeQuery()) {
					while (allTitlesRS.next()) {
						String groupedWorkId = allTitlesRS.getString("groupedWorkPermanentId");
						if (!allTitlesRS.wasNull() && !groupedWorkId.isEmpty() && !groupedWorkId.contains(":")) {
							// Skip archive object Ids
//						groupedWorkIds.append(groupedWorkId).append(',');

							SolrQuery query = new SolrQuery();
							query.setQuery("id:" + groupedWorkId);
//							query.setQuery("id:" + groupedWorkId + " AND recordtype:grouped_work");
							query.setFields("title", "author");

							try {
								QueryResponse    response = solrServer.query(query);
								SolrDocumentList results  = response.getResults();
								//Should only ever get one response
								if (results.size() >= 1) {
									SolrDocument curWork = results.get(0);
									userListSolr.addListTitle(groupedWorkId, curWork.getFieldValue("title"), curWork.getFieldValue("author"));
								}
							} catch (Exception e) {
								logger.error("User Lists: Error loading information about list entry title " + groupedWorkId, e);
							}


						}
						//TODO: Handle Archive Objects from a User List
					}
				}
				//TODO: we can query all of the grouped work Ids in a single solr query and process all the results  (set the return size)
				// Attempted below. Queries were much slower to process for marmot test

//			if (groupedWorkIds.length() > 0) {
//				SolrQuery query = new SolrQuery();
//				query.setRequestHandler("/get"); // The slash is needed to set the url path rather than the obsolete qt parameter
//				query.setParam("ids", groupedWorkIds.toString());
////				query.setQuery("recordtype:grouped_work");
////				query.setFilterQueries("recordtype:grouped_work");
//				query.setFields("id", "title", "author");
//
//				String groupedWorkId = "";
//				try {
//					QueryResponse    response = solrServer.query(query);
//					SolrDocumentList results  = response.getResults();
//					for (SolrDocument curWork : results) {
//						groupedWorkId = curWork.getFieldValue("id").toString();
//						userListSolr.addListTitle(groupedWorkId, curWork.getFieldValue("title"), curWork.getFieldValue("author"));
//					}
//				} catch (Exception e) {
//					logger.error("Error loading information about title " + groupedWorkId, e);
//				}
//			}

				// Index in the solr catalog
				try {
					updateServer.add(userListSolr.getSolrDocument());
				} catch (Exception e) {
					logger.error("User List indexing error: ", e);
					return false;
				}
				return true;
			} else {
				if (logger.isDebugEnabled()){
					logger.debug("List " + listId + " belonged to no search scopes and was not indexed");
				}
			}
		}
		return false;
	}
}
