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

import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
import org.apache.commons.text.similarity.FuzzyScore;
import org.apache.solr.client.solrj.impl.HttpSolrClient;
import org.apache.solr.client.solrj.SolrServerException;
import org.apache.solr.client.solrj.impl.BinaryRequestWriter;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrClient;
import org.apache.solr.common.SolrInputDocument;
//import org.slf4j.Logger;
//import org.slf4j.LoggerFactory;

import java.io.*;
import java.nio.charset.StandardCharsets;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;

/**
 * Indexer
 *
 * Pika
 * User: Mark Noble
 * Date: 11/25/13
 * Time: 2:26 PM
 */
public class GroupedWorkIndexer {
	private       String                                   serverName;
	private       org.apache.logging.log4j.Logger          logger;
	private final PikaSystemVariables                      systemVariables;
	private       HttpSolrClient                           solrServer;
	private       ConcurrentUpdateSolrClient               updateServer;
	private       HashMap<String, MarcRecordProcessor>     indexingRecordProcessors              = new HashMap<>();
	private       OverDriveProcessor                       overDriveProcessor;
	private       HashMap<String, HashMap<String, String>> translationMaps                       = new HashMap<>();
	// The file based translation Maps
	private       HashMap<String, LexileTitle>             lexileInformation                     = new HashMap<>();
	private       HashMap<String, ARTitle>                 arInformation                         = new HashMap<>();
	private       String                                   solrPort                              = PikaConfigIni.getIniValue("Reindex", "solrPort");
	private       String                                   baseLogPath                           = PikaConfigIni.getIniValue("Site", "baseLogPath");
	private       Integer                                  maxWorksToProcess                     = PikaConfigIni.getIntIniValue("Reindex", "maxWorksToProcess");
	private       Integer                                  availableAtLocationBoostValue         = PikaConfigIni.getIntIniValue("Reindex", "availableAtLocationBoostValue");
	private       Integer                                  ownedByLocationBoostValue             = PikaConfigIni.getIntIniValue("Reindex", "ownedByLocationBoostValue");
	private       boolean                                  giveOnOrderItemsTheirOwnShelfLocation = PikaConfigIni.getBooleanIniValue("Reindex", "giveOnOrderItemsTheirOwnShelfLocation");

	private Connection        pikaConn;
	private PreparedStatement getRatingStmt;
	private PreparedStatement getNovelistStmt;
	private PreparedStatement getGroupedWorkPrimaryIdentifiers;
	private PreparedStatement getDateFirstDetectedStmt;

	private Long    indexStartTime;
	public  boolean fullReindex;
	private long    lastReindexTime;
	private boolean okToIndex = true;

	private HashSet<String> worksWithInvalidLiteraryForms = new HashSet<>();
	private TreeSet<Scope>  scopes                        = new TreeSet<>();

	//Keep track of what we are indexing for validation purposes
	private TreeMap<String, TreeSet<String>>     ilsRecordsIndexed = new TreeMap<>();
	private TreeMap<String, TreeSet<String>>     ilsRecordsSkipped = new TreeMap<>();
	private TreeMap<String, ScopedIndexingStats> indexingStats     = new TreeMap<>();
	TreeSet<String> overDriveRecordsIndexed = new TreeSet<>();
	TreeSet<String> overDriveRecordsSkipped = new TreeSet<>();
	private int orphanedGroupedWorkPrimaryIdentifiersProcessed = 0;



	public GroupedWorkIndexer(String serverName, Connection pikaConn, Connection econtentConn, boolean fullReindex, boolean singleWorkIndex, org.apache.logging.log4j.Logger logger) {
		indexStartTime                        = new Date().getTime() / 1000;
		this.serverName                       = serverName;
		this.logger                           = logger;
		this.pikaConn                         = pikaConn;
		this.fullReindex                      = fullReindex;
		if (availableAtLocationBoostValue == null){
			availableAtLocationBoostValue = 1; // No boost
		}
		if (ownedByLocationBoostValue == null){
			ownedByLocationBoostValue = 1; //No boost
		}
		if (maxWorksToProcess == null){
			maxWorksToProcess = -1;
		}

		systemVariables = new PikaSystemVariables(this.logger, this.pikaConn);
		//Load the last Index time
		final Long longValuedVariable = systemVariables.getLongValuedVariable("last_reindex_time");
		if (longValuedVariable == null){
			// Set Variable for the first time
			systemVariables.setVariable("last_reindex_time", 0L);
		} else {
			lastReindexTime = longValuedVariable;
		}

		//Load a few statements we will need later
		try{
			getGroupedWorkPrimaryIdentifiers = pikaConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers WHERE grouped_work_id = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			getDateFirstDetectedStmt          = pikaConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE source = ? AND ilsId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		} catch (Exception e){
			logger.error("Could not load statements to get identifiers ", e);
		}

		//Initialize the updateServer and solr server
		GroupedReindexMain.addNoteToReindexLog("Setting up update server and solr server");
		final String baseSolrUrl = "http://localhost:" + solrPort + "/solr/grouped";
		if (fullReindex){
			Boolean isRunning = systemVariables.getBooleanValuedVariable("systemVariables");
			if (isRunning == null){ // Not found
				isRunning = false;
			}
			if (isRunning){
				logger.error("System Variable 'full_reindex_running' is on at beginning of full reindex. This could indicate a full index is already running, or there was an error during the last full reindex.");
			}

			//MDN 10-21-2015 - use the grouped core since we are using replication.
			solrServer   = new HttpSolrClient.Builder(baseSolrUrl).build();
			updateServer = new ConcurrentUpdateSolrClient.Builder(baseSolrUrl).withQueueSize(500).withThreadCount(8).build();
			updateServer.setRequestWriter(new BinaryRequestWriter());

			//Stop replication from the master
			String url                              = baseSolrUrl + "/replication?command=disablereplication";
			URLPostResponse stopReplicationResponse = Util.getURL(url, logger);
			if (!stopReplicationResponse.isSuccess()){
				logger.error("Error restarting replication " + stopReplicationResponse.getMessage());
			}
			if (logger.isInfoEnabled()){
				logger.info("Replication Disable command response :" + stopReplicationResponse.getMessage());
			}

			// Stop replication polling by the searcher
			url = PikaConfigIni.getIniValue("Index", "url");
			if (url != null && !url.isEmpty()){
				url += "/grouped/replication?command=disablepoll";
				URLPostResponse stopSearcherReplicationPollingResponse = Util.getURL(url, logger);
				if (!stopSearcherReplicationPollingResponse.isSuccess()){
					logger.error("Error disabling polling of solr searcher for replication.");
				}
				if (logger.isInfoEnabled()){
					logger.info("Searcher Replication Polling Disable command response : " + stopSearcherReplicationPollingResponse.getMessage());
				}
			} else {
				logger.error("Unable to get solr search index url. Could not disable replication polling.");
			}

			updateFullReindexRunning(true);
		}else{
			//TODO: Bypass this if called from an export process?
			//TODO: Bypass when process user lists only

			//Check to see if a partial reindex is running
			boolean       partialReindexRunning;
			final Boolean aBoolean = systemVariables.getBooleanValuedVariable("partial_reindex_running");
			if (aBoolean == null) {
				// Set Variable for the first time
				logger.warn("System Variable 'partial_reindex_running' was not set");
				partialReindexRunning = false;
			} else {
				partialReindexRunning = aBoolean;
			}
			if (partialReindexRunning) {
				//Oops, a reindex is already running.
				String note = "A partial reindex is already running, check to make sure that reindexes don't overlap since that can cause poor performance";
				logger.warn(note);
				GroupedReindexMain.addNoteToReindexLog(note);
			} else {
				updatePartialReindexRunning(true);
			}

			if (!singleWorkIndex) {
				//Check to make sure that at least a couple of minutes have elapsed since the last index
				//Periodically in the middle of the night we get indexes every minute or multiple times a minute
				//which is annoying especially since it generally means nothing is changing.
				long elapsedTime         = indexStartTime - lastReindexTime;
				long minIndexingInterval = 2 * 60;
				if (elapsedTime < minIndexingInterval) {
					try {
						GroupedReindexMain.addNoteToReindexLog("Pausing between indexes, last index ran " + Math.ceil(elapsedTime / 60f) + " minutes ago");
						GroupedReindexMain.addNoteToReindexLog("Pausing for " + (minIndexingInterval - elapsedTime) + " seconds");
						Thread.sleep((minIndexingInterval - elapsedTime) * 1000);
					} catch (InterruptedException e) {
						logger.warn("Pause was interrupted while pausing between indexes");
					}
				} else {
					GroupedReindexMain.addNoteToReindexLog("Index last ran " + (elapsedTime) + " seconds ago");
				}
			}

			updateServer = new ConcurrentUpdateSolrClient.Builder(baseSolrUrl).withQueueSize(500).withThreadCount(8).build();
			updateServer.setRequestWriter(new BinaryRequestWriter());
			solrServer   = new HttpSolrClient.Builder(baseSolrUrl).build();
		}

		loadScopes();

		//Initialize processors based on our indexing profiles and the primary identifiers for the records.
		try (
			PreparedStatement uniqueIdentifiersStmt = pikaConn.prepareStatement("SELECT DISTINCT type FROM grouped_work_primary_identifiers");
			PreparedStatement getIndexingProfile    = pikaConn.prepareStatement("SELECT * FROM indexing_profiles WHERE sourceName = ?");
			ResultSet uniqueIdentifiersRS           = uniqueIdentifiersStmt.executeQuery();
		){
			while (uniqueIdentifiersRS.next()) {
				String sourceName = uniqueIdentifiersRS.getString("type");
				if (sourceName.equalsIgnoreCase("overdrive")) {
					//Overdrive doesn't have an indexing profile.
					//Only load processor if there are overdrive titles
					overDriveProcessor = new OverDriveProcessor(this, econtentConn, logger, fullReindex, serverName);
				} else {
					getIndexingProfile.setString(1, sourceName);
					try (ResultSet indexingProfileRS = getIndexingProfile.executeQuery()) {
						if (indexingProfileRS.next()) {
							String ilsIndexingClassString = indexingProfileRS.getString("indexingClass");
							switch (ilsIndexingClassString) {
								// eContent Processors
								case "SideLoadedEContent":
									indexingRecordProcessors.put(sourceName, new SideLoadedEContentProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "OverDriveSideLoad":
									indexingRecordProcessors.put(sourceName, new OverDriveSideLoadProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Hoopla":
									indexingRecordProcessors.put(sourceName, new HooplaProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								// Sierra library Processors
								case "Sierra":
									indexingRecordProcessors.put(sourceName, new SierraRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Marmot":
									indexingRecordProcessors.put(sourceName, new MarmotRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Flatirons":
									indexingRecordProcessors.put(sourceName, new FlatironsRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Lion":
									indexingRecordProcessors.put(sourceName, new LionRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Addison":
									indexingRecordProcessors.put(sourceName, new AddisonRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Aurora":
									indexingRecordProcessors.put(sourceName, new AuroraRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "NorthernWaters":
									indexingRecordProcessors.put(sourceName, new NorthernWatersRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								case "Sacramento":
									indexingRecordProcessors.put(sourceName, new SacramentoRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								// Symphony Processors
								case "AACPL":
									indexingRecordProcessors.put(sourceName, new AACPLRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								//Horizon Processors
								case "WCPL":
									indexingRecordProcessors.put(sourceName, new WCPLRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
									break;
								default:
									logger.error("Unknown indexing class " + ilsIndexingClassString);
									okToIndex = false;
									return;
							}
						} else if (fullReindex && logger.isInfoEnabled()) {
							logger.info("Could not find indexing profile for type " + sourceName);
							// This indicates there are related records in the grouping primary identifiers table for a source that no
							// longer has a corresponding indexing profile.
							// Most likely cause of this is a sideload that has been removed.
						}
					}
				}
			}

			setupIndexingStats(); //TODO: only during fullReindex

		}catch (Exception e){
			logger.error("Error loading record processors for ILS records", e);
		}
		//Load translation maps
		loadSystemTranslationMaps(); // This loads the file-based mmaps

		//Setup prepared statements to load local enrichment
		try {
			//No need to filter for ratings greater than 0 because the user has to rate from 1-5
			getRatingStmt = pikaConn.prepareStatement("SELECT AVG(rating) AS averageRating, groupedWorkPermanentId FROM user_work_review WHERE groupedWorkPermanentId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			getNovelistStmt = pikaConn.prepareStatement("SELECT * FROM novelist_data WHERE groupedWorkPermanentId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		} catch (SQLException e) {
			logger.error("Could not prepare statements to load local enrichment", e);
		}

		loadLexileData();
		loadAcceleratedReaderData();

		if (fullReindex){
			clearIndex();
		}
	}

	private void setupIndexingStats() {
		ArrayList<String> sourceNames = new ArrayList<>(indexingRecordProcessors.keySet());
		sourceNames.add("overdrive");

		for (Scope curScope : scopes){
			ScopedIndexingStats scopedIndexingStats = new ScopedIndexingStats(curScope.getScopeName(), sourceNames);
			indexingStats.put(curScope.getScopeName(), scopedIndexingStats);
		}
	}

	boolean isOkToIndex(){
		return okToIndex;
	}

	private boolean libraryAndLocationDataLoaded = false;

	private void loadScopes() {
		if (!libraryAndLocationDataLoaded){
			//Setup translation maps for system and location
			try {
				loadLibraryScopes();

				loadLocationScopes();
			} catch (SQLException e) {
				logger.error("Error setting up system maps", e);
			}
			libraryAndLocationDataLoaded = true;
			if (logger.isInfoEnabled()) {
				logger.info("Loaded " + scopes.size() + " scopes");
			}
		}
	}

	private void loadLocationScopes() throws SQLException {
		PreparedStatement locationInformationStmt = pikaConn.prepareStatement("SELECT library.libraryId, locationId, code, " +
				"library.subdomain, location.facetLabel, location.displayName, library.pTypes, library.restrictOwningBranchesAndSystems, location.publicListsToInclude, " +
				"library.enableOverdriveCollection AS enableOverdriveCollectionLibrary, " +
				"location.enableOverdriveCollection AS enableOverdriveCollectionLocation, " +
				"library.includeOverdriveAdult AS includeOverdriveAdultLibrary, location.includeOverdriveAdult as includeOverdriveAdultLocation, " +
				"library.includeOverdriveTeen AS includeOverdriveTeenLibrary, location.includeOverdriveTeen as includeOverdriveTeenLocation, " +
				"library.includeOverdriveKids AS includeOverdriveKidsLibrary, location.includeOverdriveKids as includeOverdriveKidsLocation, " +
				"library.sharedOverdriveCollection, " +
				"location.additionalLocationsToShowAvailabilityFor, includeAllLibraryBranchesInFacets, " +
				"location.includeAllRecordsInShelvingFacets, location.includeAllRecordsInDateAddedFacets, location.includeOnOrderRecordsInDateAddedFacetValues, location.baseAvailabilityToggleOnLocalHoldingsOnly, " +
				"location.includeOnlineMaterialsInAvailableToggle, location.includeLibraryRecordsToInclude " +
				"FROM location INNER JOIN library ON library.libraryId = location.libraryId ORDER BY code ASC",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationOwnedRecordRulesStmt = pikaConn.prepareStatement("SELECT location_records_owned.*, indexing_profiles.sourceName FROM location_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationRecordInclusionRulesStmt = pikaConn.prepareStatement("SELECT location_records_to_include.*, indexing_profiles.sourceName FROM location_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

		ResultSet locationInformationRS = locationInformationStmt.executeQuery();
		while (locationInformationRS.next()){
			String code        = locationInformationRS.getString("code").toLowerCase();
			String facetLabel  = locationInformationRS.getString("facetLabel");
			String displayName = locationInformationRS.getString("displayName");
			if (facetLabel.length() == 0){
				facetLabel = displayName;
			}

			//Determine if we need to build a scope for this location
			Long   libraryId  = locationInformationRS.getLong("libraryId");
			Long   locationId = locationInformationRS.getLong("locationId");
			String pTypes     = locationInformationRS.getString("pTypes");
			if (pTypes == null) pTypes = "";
			boolean includeOverDriveCollectionLibrary  = locationInformationRS.getBoolean("enableOverdriveCollectionLibrary");
			boolean includeOverDriveCollectionLocation = locationInformationRS.getBoolean("enableOverdriveCollectionLocation");

			Scope locationScopeInfo = new Scope();
			locationScopeInfo.setIsLibraryScope(false);
			locationScopeInfo.setIsLocationScope(true);
			locationScopeInfo.setScopeName(code);
			locationScopeInfo.setLibraryId(libraryId);
			locationScopeInfo.setLocationId(locationId);
			locationScopeInfo.setRelatedPTypes(pTypes.split(","));
			locationScopeInfo.setFacetLabel(facetLabel);
			locationScopeInfo.setIncludeOverDriveCollection(includeOverDriveCollectionLibrary && includeOverDriveCollectionLocation);
			locationScopeInfo.setSharedOverdriveCollectionId(locationInformationRS.getLong("sharedOverdriveCollection"));
			boolean includeOverdriveAdult = locationInformationRS.getBoolean("includeOverdriveAdultLibrary") && locationInformationRS.getBoolean("includeOverdriveAdultLocation");
			boolean includeOverdriveTeen  = locationInformationRS.getBoolean("includeOverdriveTeenLibrary") && locationInformationRS.getBoolean("includeOverdriveTeenLocation");
			boolean includeOverdriveKids  = locationInformationRS.getBoolean("includeOverdriveKidsLibrary") && locationInformationRS.getBoolean("includeOverdriveKidsLocation");
			locationScopeInfo.setIncludeOverDriveAdultCollection(includeOverdriveAdult);
			locationScopeInfo.setIncludeOverDriveTeenCollection(includeOverdriveTeen);
			locationScopeInfo.setIncludeOverDriveKidsCollection(includeOverdriveKids);
			locationScopeInfo.setRestrictOwningLibraryAndLocationFacets(locationInformationRS.getBoolean("restrictOwningBranchesAndSystems"));
			locationScopeInfo.setPublicListsToInclude(locationInformationRS.getInt("publicListsToInclude"));
			locationScopeInfo.setAdditionalLocationsToShowAvailabilityFor(locationInformationRS.getString("additionalLocationsToShowAvailabilityFor"));
			locationScopeInfo.setIncludeAllLibraryBranchesInFacets(locationInformationRS.getBoolean("includeAllLibraryBranchesInFacets"));
			locationScopeInfo.setIncludeAllRecordsInShelvingFacets(locationInformationRS.getBoolean("includeAllRecordsInShelvingFacets"));
			locationScopeInfo.setIncludeAllRecordsInDateAddedFacets(locationInformationRS.getBoolean("includeAllRecordsInDateAddedFacets"));
			locationScopeInfo.setIncludeOnOrderRecordsInDateAddedFacetValues(locationInformationRS.getBoolean("includeOnOrderRecordsInDateAddedFacetValues"));
			locationScopeInfo.setBaseAvailabilityToggleOnLocalHoldingsOnly(locationInformationRS.getBoolean("baseAvailabilityToggleOnLocalHoldingsOnly"));
			locationScopeInfo.setIncludeOnlineMaterialsInAvailableToggle(locationInformationRS.getBoolean("includeOnlineMaterialsInAvailableToggle"));

			//Load information about what should be included in the scope
			locationOwnedRecordRulesStmt.setLong(1, locationId);
			ResultSet locationOwnedRecordRulesRS = locationOwnedRecordRulesStmt.executeQuery();
			while (locationOwnedRecordRulesRS.next()){
				locationScopeInfo.addOwnershipRule(new OwnershipRule(locationOwnedRecordRulesRS.getString("sourceName"), locationOwnedRecordRulesRS.getString("location")));
			}

			locationRecordInclusionRulesStmt.setLong(1, locationId);
			ResultSet locationRecordInclusionRulesRS = locationRecordInclusionRulesStmt.executeQuery();
			while (locationRecordInclusionRulesRS.next()){
				locationScopeInfo.addInclusionRule(new InclusionRule(locationRecordInclusionRulesRS.getString("sourceName"),
						locationRecordInclusionRulesRS.getString("location"),
						locationRecordInclusionRulesRS.getString("iType"),
						locationRecordInclusionRulesRS.getString("audience"),
						locationRecordInclusionRulesRS.getString("format"),
						locationRecordInclusionRulesRS.getBoolean("includeHoldableOnly"),
						locationRecordInclusionRulesRS.getBoolean("includeItemsOnOrder"),
						locationRecordInclusionRulesRS.getBoolean("includeEContent"),
						locationRecordInclusionRulesRS.getString("marcTagToMatch"),
						locationRecordInclusionRulesRS.getString("marcValueToMatch"),
						locationRecordInclusionRulesRS.getBoolean("includeExcludeMatches"),
						locationRecordInclusionRulesRS.getString("urlToMatch"),
						locationRecordInclusionRulesRS.getString("urlReplacement")
				));
			}

			boolean includeLibraryRecordsToInclude = locationInformationRS.getBoolean("includeLibraryRecordsToInclude");
			if (includeLibraryRecordsToInclude){
				libraryRecordInclusionRulesStmt.setLong(1, libraryId);
				ResultSet libraryRecordInclusionRulesRS = libraryRecordInclusionRulesStmt.executeQuery();
				while (libraryRecordInclusionRulesRS.next()){
					locationScopeInfo.addInclusionRule(new InclusionRule(libraryRecordInclusionRulesRS.getString("sourceName"),
							libraryRecordInclusionRulesRS.getString("location"),
							libraryRecordInclusionRulesRS.getString("iType"),
							libraryRecordInclusionRulesRS.getString("audience"),
							libraryRecordInclusionRulesRS.getString("format"),
							libraryRecordInclusionRulesRS.getBoolean("includeHoldableOnly"),
							libraryRecordInclusionRulesRS.getBoolean("includeItemsOnOrder"),
							libraryRecordInclusionRulesRS.getBoolean("includeEContent"),
							libraryRecordInclusionRulesRS.getString("marcTagToMatch"),
							libraryRecordInclusionRulesRS.getString("marcValueToMatch"),
							libraryRecordInclusionRulesRS.getBoolean("includeExcludeMatches"),
							libraryRecordInclusionRulesRS.getString("urlToMatch"),
							libraryRecordInclusionRulesRS.getString("urlReplacement")
					));
				}
			}

			if (!scopes.contains(locationScopeInfo)){
				//Connect this scope to the library scopes
				for (Scope curScope : scopes){
					if (curScope.isLibraryScope() && Objects.equals(curScope.getLibraryId(), libraryId)){
						curScope.addLocationScope(locationScopeInfo);
						locationScopeInfo.setLibraryScope(curScope);
						break;
					}
				}
				scopes.add(locationScopeInfo);
			}else{
				if (logger.isDebugEnabled()) {
					logger.debug("Not adding location scope because a library scope with the name " + locationScopeInfo.getScopeName() + " exists already.");
				}
				for (Scope existingLibraryScope : scopes){
					if (existingLibraryScope.getScopeName().equals(locationScopeInfo.getScopeName())){
						existingLibraryScope.setIsLocationScope(true);
						break;
					}
				}
			}
		}
	}

	private PreparedStatement libraryRecordInclusionRulesStmt;
	private void loadLibraryScopes() throws SQLException {
		PreparedStatement libraryInformationStmt = pikaConn.prepareStatement("SELECT libraryId, subdomain, " +
				"displayName, facetLabel, pTypes, enableOverdriveCollection, restrictOwningBranchesAndSystems, publicListsToInclude, " +
				"additionalLocationsToShowAvailabilityFor, " +
				"sharedOverdriveCollection, includeOverdriveAdult, includeOverdriveTeen, includeOverdriveKids, " +
				"includeAllRecordsInShelvingFacets, includeAllRecordsInDateAddedFacets, includeOnOrderRecordsInDateAddedFacetValues, includeOnlineMaterialsInAvailableToggle " +
				"FROM library ORDER BY subdomain ASC",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement libraryOwnedRecordRulesStmt = pikaConn.prepareStatement("SELECT library_records_owned.*, indexing_profiles.sourceName FROM library_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		libraryRecordInclusionRulesStmt = pikaConn.prepareStatement("SELECT library_records_to_include.*, indexing_profiles.sourceName FROM library_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		ResultSet libraryInformationRS = libraryInformationStmt.executeQuery();
		while (libraryInformationRS.next()){
			String facetLabel  = libraryInformationRS.getString("facetLabel");
			String subdomain   = libraryInformationRS.getString("subdomain");
			String displayName = libraryInformationRS.getString("displayName");
			if (facetLabel.length() == 0){
				facetLabel = displayName;
			}
			//These options determine how scoping is done
			Long   libraryId = libraryInformationRS.getLong("libraryId");
			String pTypes    = libraryInformationRS.getString("pTypes");
			if (pTypes == null) {pTypes = "";}
			boolean includeOverdrive            = libraryInformationRS.getBoolean("enableOverdriveCollection");
			Long    sharedOverdriveCollectionId = libraryInformationRS.getLong("sharedOverdriveCollection");
			boolean includeOverdriveAdult       = libraryInformationRS.getBoolean("includeOverdriveAdult");
			boolean includeOverdriveTeen        = libraryInformationRS.getBoolean("includeOverdriveTeen");
			boolean includeOverdriveKids        = libraryInformationRS.getBoolean("includeOverdriveKids");

			Scope newScope = new Scope();
			newScope.setIsLibraryScope(true);
			newScope.setIsLocationScope(false);
			newScope.setScopeName(subdomain);
			newScope.setLibraryId(libraryId);
			newScope.setFacetLabel(facetLabel);
			newScope.setRelatedPTypes(pTypes.split(","));
			newScope.setIncludeOverDriveCollection(includeOverdrive);
			newScope.setPublicListsToInclude(libraryInformationRS.getInt("publicListsToInclude"));
			newScope.setAdditionalLocationsToShowAvailabilityFor(libraryInformationRS.getString("additionalLocationsToShowAvailabilityFor"));
			newScope.setIncludeAllRecordsInShelvingFacets(libraryInformationRS.getBoolean("includeAllRecordsInShelvingFacets"));
			newScope.setIncludeAllRecordsInDateAddedFacets(libraryInformationRS.getBoolean("includeAllRecordsInDateAddedFacets"));
			newScope.setIncludeOnOrderRecordsInDateAddedFacetValues(libraryInformationRS.getBoolean("includeOnOrderRecordsInDateAddedFacetValues"));

			newScope.setIncludeOnlineMaterialsInAvailableToggle(libraryInformationRS.getBoolean("includeOnlineMaterialsInAvailableToggle"));

			newScope.setIncludeOverDriveAdultCollection(includeOverdriveAdult);
			newScope.setIncludeOverDriveTeenCollection(includeOverdriveTeen);
			newScope.setIncludeOverDriveKidsCollection(includeOverdriveKids);
			newScope.setSharedOverdriveCollectionId(sharedOverdriveCollectionId);

			newScope.setRestrictOwningLibraryAndLocationFacets(libraryInformationRS.getBoolean("restrictOwningBranchesAndSystems"));

			//Load information about what should be included in the scope
			libraryOwnedRecordRulesStmt.setLong(1, libraryId);
			ResultSet libraryOwnedRecordRulesRS = libraryOwnedRecordRulesStmt.executeQuery();
			while (libraryOwnedRecordRulesRS.next()){
				newScope.addOwnershipRule(new OwnershipRule(libraryOwnedRecordRulesRS.getString("sourceName"), libraryOwnedRecordRulesRS.getString("location")));
			}

			libraryRecordInclusionRulesStmt.setLong(1, libraryId);
			ResultSet libraryRecordInclusionRulesRS = libraryRecordInclusionRulesStmt.executeQuery();
			while (libraryRecordInclusionRulesRS.next()){
				newScope.addInclusionRule(new InclusionRule(libraryRecordInclusionRulesRS.getString("sourceName"),
						libraryRecordInclusionRulesRS.getString("location"),
						libraryRecordInclusionRulesRS.getString("iType"),
						libraryRecordInclusionRulesRS.getString("audience"),
						libraryRecordInclusionRulesRS.getString("format"),
						libraryRecordInclusionRulesRS.getBoolean("includeHoldableOnly"),
						libraryRecordInclusionRulesRS.getBoolean("includeItemsOnOrder"),
						libraryRecordInclusionRulesRS.getBoolean("includeEContent"),
						libraryRecordInclusionRulesRS.getString("marcTagToMatch"),
						libraryRecordInclusionRulesRS.getString("marcValueToMatch"),
						libraryRecordInclusionRulesRS.getBoolean("includeExcludeMatches"),
						libraryRecordInclusionRulesRS.getString("urlToMatch"),
						libraryRecordInclusionRulesRS.getString("urlReplacement")
				));
			}


			scopes.add(newScope);
		}
	}

	private void loadAcceleratedReaderData(){
		try{
			String acceleratedReaderPath = PikaConfigIni.getIniValue("Reindex", "arExportPath");
			File arFile = new File(acceleratedReaderPath);
			if (arFile.exists()) {
				if (logger.isInfoEnabled()){
					logger.info("Starting to read accelerated reader data");
				}
				int    numLines = 0;
				try (CSVReader arDataReader = new CSVReader(new InputStreamReader(new FileInputStream(acceleratedReaderPath), StandardCharsets.ISO_8859_1), '\t')) {
//				try (CSVReader arDataReader = new CSVReader(new FileReader(arFile), '\t')) {
					//Skip over the header
					String[] keys = arDataReader.readNext();
					ArrayList<Integer> isbnKeys = new ArrayList<>();
					for (int i = 14; i < keys.length; i++){
						if (keys[i].startsWith("ISBN")){
							isbnKeys.add(i);
						}
					}
					String[] arFields = arDataReader.readNext();
					numLines++;
					while (arFields != null) {
						ARTitle  titleInfo = new ARTitle();
						if (arFields.length >= 11) {
							// arFields[0] is language code
							// arFields[9] is fiction/nonfiction
							if (logger.isDebugEnabled()) {
								//Only ever use the title & author to check if something is wrong
								titleInfo.setTitle(arFields[2]);
								titleInfo.setAuthor(arFields[6]);
							}
							titleInfo.setBookLevel(arFields[7]);
							titleInfo.setArPoints(arFields[8]);
							titleInfo.setInterestLevel(arFields[10]);
//							String[] ISBNs = {
//									arFields[14].trim(),
//									arFields[17].trim(),
//									arFields[20].trim(),
//									arFields[23].trim(),
//									arFields[26].trim(),
//									arFields[29].trim(),
//									arFields[32].trim(),
//									arFields[35].trim(),
//									arFields[38].trim(),
//									arFields[41].trim(),
//									arFields[44].trim(),
//									arFields[47].trim(),
//									arFields[50].trim(),
//							};
//							for (String isbn : ISBNs) {
//								if (isbn.length() > 0) {
//									isbn = isbn.replaceAll("[^\\dX]", "");
//									arInformation.put(isbn, titleInfo);
//								}
//							}

							for (int i : isbnKeys){
								if (arFields.length > i) { // length should be longer than index. eg index 17 requires an 18 length array
									try {
										ISBN isbn = new ISBN(arFields[i]);
										if (isbn.isValidIsbn()) {
											arInformation.put(isbn.toString(), titleInfo);
										}
									} catch (Exception e) {
										logger.info("Error getting ISBN (col " + i + " from AR data line " + numLines, e);
									}
								}
							}
							numLines++;
//							if (logger.isInfoEnabled() && (numLines % 100 == 0 /*|| numLines > 5200*/)){
//								logger.info("Processed accelerated reader data file line " + numLines);
//							}
						}

						arFields = arDataReader.readNext();
					}
				}
				if (logger.isInfoEnabled()) {
					logger.info("Read " + numLines + " lines of accelerated reader data");
				}
			} else if (fullReindex) {
				logger.warn("Accelerated Reader data file not found : " + acceleratedReaderPath);
			}
		}catch (Exception e){
			logger.error("Error loading accelerated reader data", e);
		}
	}

	private void loadLexileData() {
		String   lexileExportPath = PikaConfigIni.getIniValue("Reindex", "lexileExportPath");
		String[] lexileFields     = new String[0];
		int      curLine          = 0;
		try {
			File lexileData = new File(lexileExportPath);
			if (lexileData.exists()) {
				if (logger.isInfoEnabled()){
					logger.info("Starting to read lexile data");
				}
				try (CSVReader lexileReader = new CSVReader(new FileReader(lexileData), '\t')) {
					//Skip over the header
					lexileReader.readNext();
					lexileFields = lexileReader.readNext();
					curLine++;
					while (lexileFields != null) {
						LexileTitle titleInfo = new LexileTitle();
						if (lexileFields.length >= 11) {
							ISBN isbn = new ISBN(lexileFields[3]);
							if (isbn.isValidIsbn()) {
								titleInfo.setTitle(lexileFields[0]);
								titleInfo.setAuthor(lexileFields[1]);
								titleInfo.setLexileCode(lexileFields[4]);
								try {
									titleInfo.setLexileScore(lexileFields[5]);
								} catch (NumberFormatException e) {
									logger.warn("Failed to parse lexile score " + lexileFields[5], e);
								}
								if (!lexileFields[10].equalsIgnoreCase("none")) {
									titleInfo.setSeries(lexileFields[10]);
								}
								if (lexileFields.length >= 12) {
									titleInfo.setAwards(lexileFields[11]);
								}
//								if (lexileFields.length >= 13) {
//									titleInfo.setDescription(lexileFields[12]);
//								}
								lexileInformation.put(isbn.toString(), titleInfo);
							}
						}
						lexileFields = lexileReader.readNext();
						curLine++;
					}
				}
				if (logger.isInfoEnabled()) {
					logger.info("Read " + lexileInformation.size() + " lines of lexile data");
				}
			} else if (fullReindex) {
				logger.warn("Lexile data file not found : " + lexileExportPath);
			}
		} catch (Exception e) {
			logger.error("Error loading lexile data on " + curLine + Arrays.toString(lexileFields), e);
		}
	}

	private void clearIndex() {
		logger.info("Clearing all documents from index");
		try {
			updateServer.deleteByQuery("*:*", 500);
			Thread.sleep(10000);
			//TODO: actually check the index folder that index files have been deleted. (There should be only 2 files left in the index folder)

			// https://solr.apache.org/guide/8_7/reindexing.html#delete-all-documents
			//
			// It’s important to verify that all documents have been deleted, as that ensures the Lucene index
			// segments have been deleted as well.
			//
			//To verify that there are no segments in your index, look in the data directory and confirm it is
			// empty. Since the data directory can be customized, see the section Specifying a Location for
			// Index Data with the dataDir Parameter for where to look to find the index files.
			//
			//Note you will need to verify the indexes have been removed in every shard and every replica on
			// every node of a cluster. It is not sufficient to only query for the number of documents
			// because you may have no documents but still have index segments.
			//
			//Once the indexes have been cleared, you can start reindexing by re-running the original index process.

		} catch (Exception e) {
			logger.error("Error clearing all documents from index", e);
		}
	}

	void deleteRecord(String id) {
		if (logger.isInfoEnabled()) {
			logger.info("Clearing existing work from index " + id);
		}
		try {
			updateServer.deleteById(id);
			//With this commit, we get errors in the log "Previous SolrRequestInfo was not closed!"
			//Allow auto commit functionality to handle this
			//updateServer.commit(true, false, false);
		} catch (Exception e) {
			logger.error("Error deleting work from index", e);
		}
	}

	void createSiteMaps(HashMap<Scope, ArrayList<SiteMapEntry>>siteMapsByScope, HashSet<Long> uniqueGroupedWorks ) {

		File   dataDir                = new File(PikaConfigIni.getIniValue("SiteMap", "filePath"));
		String maxPopTitlesDefault    = PikaConfigIni.getIniValue("SiteMap", "num_titles_in_most_popular_sitemap");
		String maxUniqueTitlesDefault = PikaConfigIni.getIniValue("SiteMap", "num_title_in_unique_sitemap");
		String url                    = PikaConfigIni.getIniValue("Site", "url");
		try {
			SiteMap siteMap = new SiteMap(logger, pikaConn, Integer.parseInt(maxUniqueTitlesDefault), Integer.parseInt(maxPopTitlesDefault));
			siteMap.createSiteMaps(url, dataDir, siteMapsByScope, uniqueGroupedWorks);

		} catch (IOException e) {
			logger.error("Error creating site map", e);
		}
	}


	void finishIndexing(boolean processingIndividualWork, boolean processingUserListsOnly){
		GroupedReindexMain.addNoteToReindexLog("Finishing indexing");
		if (fullReindex) {
			try {
				GroupedReindexMain.addNoteToReindexLog("Calling final commit");
				updateServer.commit(true, true, false);
			} catch (IOException e) {
				logger.error("Error calling final commit", e);
			} catch (SolrServerException e) {
				logger.error("Error with Solr calling final commit", e);
			}
			//Restart replication from the master
			String url = "http://localhost:" + solrPort + "/solr/grouped/replication?command=enablereplication";
			URLPostResponse startReplicationResponse = Util.getURL(url, logger);
			if (!startReplicationResponse.isSuccess()){
				logger.error("Error restarting replication " + startReplicationResponse.getMessage());

			//MDN 10-21-2015 do not swap indexes when using replication
			//Swap the indexes
			/*GroupedReindexMain.addNoteToReindexLog("Swapping indexes");
			try {
				Util.getURL("http://localhost:" + solrPort + "/solr/admin/cores?action=SWAP&core=grouped2&other=grouped", logger);
			} catch (Exception e) {
				logger.error("Error shutting down update server", e);
			}*/
			}
			if (logger.isInfoEnabled()){
				logger.info("Replication Enable command response :" + startReplicationResponse.getMessage());
			}
			enableSearcherSolrPolling();
		}else {
			try {
				GroupedReindexMain.addNoteToReindexLog("Doing a soft commit to make sure changes are saved");
				updateServer.commit(false, false, true);
				GroupedReindexMain.addNoteToReindexLog("Shutting down the update server");
				updateServer.blockUntilFinished();
				updateServer.shutdownNow();
			} catch (Exception e) {
				logger.error("Error shutting down update server", e);
			}
		}

		if (!processingIndividualWork && !processingUserListsOnly) {
//			enableSearcherSolrPolling();
			updateLastReindexTime();
		}

		//Write validation information
		if (fullReindex) {
			writeWorksWithInvalidLiteraryForms();
			writeValidationInformation();
			writeStats();
			updateFullReindexRunning(false);
		}else{
			updatePartialReindexRunning(false);
		}
	}

	private void enableSearcherSolrPolling() {
		int     tries   = 0;
		boolean success = false;
		String url = PikaConfigIni.getIniValue("Index", "url");
		if (url != null && !url.isEmpty()) {
			url += "/grouped/replication?command=enablepoll";
			do {
				URLPostResponse startSearcherReplicationPollingResponse = null;
				try {
					startSearcherReplicationPollingResponse = Util.getURL(url, logger);
					success = startSearcherReplicationPollingResponse.isSuccess();
				} catch (Exception e) {
					if (tries == 2){
						logger.error("Failed to get response to enable polling for 3 tries");
					}
				}
				if (!success) {
					try {
						Thread.sleep(1000);
					} catch (InterruptedException e) {
						logger.error("Error during thread sleep", e);
					}
					if (tries == 2) {
						logger.error("Error enabling polling of solr searcher for replication after 3 tries.");
					}
				}
				if (logger.isInfoEnabled()) {
					logger.info("Searcher Replication Polling Enable command response : " + startSearcherReplicationPollingResponse.getMessage());
				}
			} while (!success && ++tries < 3);
		} else {
			logger.error("Unable to get solr search index url. Could not re-enable replication polling.");
		}
	}

	private void writeStats() {
		try {
			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date              curDate          = new Date();
			String            curDateFormatted = dayFormatter.format(curDate);
			File              recordsFile      = new File(dataDir.getAbsolutePath() + "/reindex_stats_" + curDateFormatted + ".csv");
			CSVWriter         recordWriter     = new CSVWriter(new FileWriter(recordsFile));
			ArrayList<String> headers          = new ArrayList<>();
			headers.add("Scope Name");
			headers.add("Owned works");
			headers.add("Total works");
			TreeSet<String> recordProcessorNames = new TreeSet<>();
			recordProcessorNames.addAll(indexingRecordProcessors.keySet());
			recordProcessorNames.add("overdrive");
			for (String processorName : recordProcessorNames){
				headers.add("Owned " + processorName + " records");
				headers.add("Owned " + processorName + " physical items");
				headers.add("Owned " + processorName + " on order items");
				headers.add("Owned " + processorName + " e-content items");
				headers.add("Total " + processorName + " records");
				headers.add("Total " + processorName + " physical items");
				headers.add("Total " + processorName + " on order items");
				headers.add("Total " + processorName + " e-content items");
			}
			recordWriter.writeNext(headers.toArray(new String[headers.size()]));

			//Write custom scopes
			for (String curScope: indexingStats.keySet()){
				ScopedIndexingStats stats = indexingStats.get(curScope);
				recordWriter.writeNext(stats.getData());
			}
			recordWriter.flush();
			recordWriter.close();
		} catch (IOException e) {
			logger.error("Unable to write statistics", e);
		}
	}

	private void writeValidationInformation() {
		for (String recordType : ilsRecordsIndexed.keySet()){
			writeExistingRecordsFile(ilsRecordsIndexed.get(recordType), "reindexer_" + recordType + "_records_processed");
		}
		for (String recordType : ilsRecordsSkipped.keySet()){
			writeExistingRecordsFile(ilsRecordsSkipped.get(recordType), "reindexer_" + recordType + "_records_skipped");
		}

		writeExistingRecordsFile(overDriveRecordsIndexed, "reindexer_overdrive_records_processed");
		writeExistingRecordsFile(overDriveRecordsSkipped, "reindexer_overdrive_records_skipped");
	}

	private SimpleDateFormat dayFormatter = new SimpleDateFormat("yyyy-MM-dd");
	private void writeExistingRecordsFile(TreeSet<String> recordNumbersInExport, String filePrefix) {
		try {
			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date curDate = new Date();
			String curDateFormatted = dayFormatter.format(curDate);
			File recordsFile = new File(dataDir.getAbsolutePath() + "/" + filePrefix + "_" + curDateFormatted + ".csv");
			CSVWriter recordWriter = new CSVWriter(new FileWriter(recordsFile));
			for (String curRecord: recordNumbersInExport){
				recordWriter.writeNext(new String[]{curRecord});
			}
			recordWriter.flush();
			recordWriter.close();
		} catch (IOException e) {
			logger.error("Unable to write existing records to " + filePrefix, e);
		}
	}

	private void updatePartialReindexRunning(boolean running) {
		if (!fullReindex) {
			if (logger.isInfoEnabled()) {
				logger.info("Updating partial reindex running");
			}
			//Update that the partial re-indexing is in the variables table
			if (!systemVariables.setVariable("partial_reindex_running", running)){
				logger.error("Error updating partial_reindex_running");
			}
		}
	}

	private void updateFullReindexRunning(boolean running) {
		if (logger.isInfoEnabled()) {
			logger.info("Updating full reindex running");
		}
		//Update that the full reindexing running in the variables table
		if (!systemVariables.setVariable("full_reindex_running", running)) {
			logger.error("Error updating full_reindex_running");
		}
	}

	private void writeWorksWithInvalidLiteraryForms() {
		if (logger.isInfoEnabled()) {
			logger.info("Writing works with invalid literary forms");
			File worksWithInvalidLiteraryFormsFile = new File(baseLogPath + "/" + serverName + "/worksWithInvalidLiteraryForms.txt");
			try {
				if (worksWithInvalidLiteraryForms.size() > 0) {
					try (FileWriter writer = new FileWriter(worksWithInvalidLiteraryFormsFile, false)) {
						final String message = "Found " + worksWithInvalidLiteraryForms.size() + " grouped works with invalid literary forms (fic vs nonfic)\r\n";
						logger.info(message);
						GroupedReindexMain.addNoteToReindexLog(message);
						writer.write(message);
						writer.write("Works with inconsistent literary forms\r\n");
						for (String curId : worksWithInvalidLiteraryForms) {
							writer.write(curId + "\r\n");
						}
					}
				}
			} catch (Exception e) {
				logger.error("Error writing works with invalid literary forms", e);
			}
		}
	}

	private void updateLastReindexTime() {
		//Update the last re-index time in the variables table.  This needs to be the time the index started to catch anything that changes during the index
		if (!systemVariables.setVariable("last_reindex_time", indexStartTime)){
			logger.error("Error setting last reindex time");
		}
	}

	/**
	 * Runs either the regular partial indexing or the regular full reindex
	 *
	 * @param siteMapsByScope
	 * @param uniqueGroupedWorks
	 * @return the number of works that were processed
	 */
	Long processGroupedWorks(HashMap<Scope, ArrayList<SiteMapEntry>> siteMapsByScope, HashSet<Long> uniqueGroupedWorks) {
		long numWorksProcessed = 0L;
		try {
			PreparedStatement getAllGroupedWorks;
			PreparedStatement getNumWorksToIndex;
			PreparedStatement setLastUpdatedTime = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?");
			if (fullReindex){
				getAllGroupedWorks = pikaConn.prepareStatement("SELECT * FROM grouped_work", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getNumWorksToIndex = pikaConn.prepareStatement("SELECT count(id) FROM grouped_work", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			}else{
				//Load all grouped works that have changed since the last time the index ran
				getAllGroupedWorks = pikaConn.prepareStatement("SELECT * FROM grouped_work WHERE date_updated IS NULL OR date_updated >= ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getAllGroupedWorks.setLong(1, lastReindexTime);
				getNumWorksToIndex = pikaConn.prepareStatement("SELECT count(id) FROM grouped_work WHERE date_updated IS NULL OR date_updated >= ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getNumWorksToIndex.setLong(1, lastReindexTime);
			}

			//Get the number of works we will be processing
			ResultSet numWorksToIndexRS = getNumWorksToIndex.executeQuery();
			numWorksToIndexRS.next();
			long numWorksToIndex = numWorksToIndexRS.getLong(1);
			GroupedReindexMain.addNoteToReindexLog("Starting to process " + numWorksToIndex + " grouped works");

			ResultSet groupedWorks = getAllGroupedWorks.executeQuery();
			GroupedReindexMain.addNoteToReindexLog("First work to be retrieved from DB");
			long reportIntervalStart = new Date().getTime();
			while (groupedWorks.next()) {
				long   id                = groupedWorks.getLong("id");
				String permanentId       = groupedWorks.getString("permanent_id");
				String grouping_category = groupedWorks.getString("grouping_category");
				Long   lastUpdated       = groupedWorks.getLong("date_updated");
				if (groupedWorks.wasNull()){
					lastUpdated = null;
				}
				processGroupedWork(id, permanentId, grouping_category, siteMapsByScope, uniqueGroupedWorks);

				numWorksProcessed++;
				if (numWorksProcessed % 1000 == 0){
					GroupedReindexMain.updateNumWorksProcessed(numWorksProcessed);
					if (fullReindex && (numWorksProcessed % 25000 == 0)){
						//Testing shows that regular commits do seem to improve performance.
						//However, we can't do it too often or we get errors with too many searchers warming.
						//This is happening now with the auto commit settings in solrconfig.xml
					/*try {
						logger.info("Doing a regular commit during full indexing");
						updateServer.commit(false, false, true);
					}catch (Exception e){
						logger.warn("Error committing changes", e);
					}*/
						long reportIntervalEnd = new Date().getTime();
						long interval = ((reportIntervalEnd - reportIntervalStart)/1000)/60;
						reportIntervalStart = reportIntervalEnd; // set up next interval
						GroupedReindexMain.addNoteToReindexLog(numWorksProcessed + " grouped works processed. Interval for this batch (mins) : " + interval);
					}
					if (!fullReindex && maxWorksToProcess != -1 && numWorksProcessed >= maxWorksToProcess){
						String message = "Stopping processing now because we've reached the max works to process.";
						GroupedReindexMain.addNoteToReindexLog(message);
						logger.warn(message);
						break;
					}
				}
				if (lastUpdated == null){
					setLastUpdatedTime.setLong(1, indexStartTime - 1); //Set just before the index started so we don't index multiple times
					setLastUpdatedTime.setLong(2, id);
					setLastUpdatedTime.executeUpdate();
				}
			}
		} catch (SQLException e) {
			logger.error("Unexpected SQL error", e);
		}
		if (logger.isInfoEnabled()) {
			logger.info("Finished processing grouped works.  Processed a total of " + numWorksProcessed + " grouped works");
		}
		if (orphanedGroupedWorkPrimaryIdentifiersProcessed > 0){
			GroupedReindexMain.addNoteToReindexLog(orphanedGroupedWorkPrimaryIdentifiersProcessed + " orphaned Grouped Work Primary Identifiers were processed. (indexing profile no longer exists for the ids)");
		}
		return numWorksProcessed;
	}

	/**
	 * Index a specific indexing profile
	 * 
	 * @param indexingProfileToProcess The indexing profile that will be indexed
	 * @return the number of works that were processed
	 */
	Long processGroupedWorks(String indexingProfileToProcess) {
		long numWorksProcessed = 0L;
		try (
			PreparedStatement getGroupedWorksForProfile = pikaConn.prepareStatement("SELECT * FROM grouped_work WHERE id IN (SELECT grouped_work_id FROM grouped_work_primary_identifiers WHERE type = \"" + indexingProfileToProcess.toLowerCase() + "\")", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement getNumWorksToIndex        = pikaConn.prepareStatement("SELECT COUNT(*) FROM grouped_work WHERE id IN (SELECT grouped_work_id FROM grouped_work_primary_identifiers WHERE type = \"" + indexingProfileToProcess.toLowerCase() + "\")", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement setLastUpdatedTime        = pikaConn.prepareStatement("UPDATE grouped_work SET date_updated = ? WHERE id = ?")
		){

			//Get the number of works we will be processing
			try (ResultSet numWorksToIndexRS = getNumWorksToIndex.executeQuery()){
				numWorksToIndexRS.next();
				long numWorksToIndex = numWorksToIndexRS.getLong(1);
				GroupedReindexMain.addNoteToReindexLog("Starting to process " + numWorksToIndex + " grouped works for profile " + indexingProfileToProcess);
			}

			//Load all grouped works that have records tied to indexing profile
			try(ResultSet groupedWorks = getGroupedWorksForProfile.executeQuery()) {
				while (groupedWorks.next()) {
					long   primaryIdentifierId = groupedWorks.getLong("id");
					String permanentId         = groupedWorks.getString("permanent_id");
					String grouping_category   = groupedWorks.getString("grouping_category");
					Long   lastUpdated         = groupedWorks.getLong("date_updated");
					if (groupedWorks.wasNull()) {
						lastUpdated = null;
					}
					processGroupedWork(primaryIdentifierId, permanentId, grouping_category);

					numWorksProcessed++;
					if (numWorksProcessed % 500 == 0) {
						GroupedReindexMain.updateNumWorksProcessed(numWorksProcessed);
						if (numWorksProcessed % 10000 == 0) {
							//Testing shows that regular commits do seem to improve performance.
							//However, we can't do it too often or we get errors with too many searchers warming.
							//This is happening now with the auto commit settings in solrconfig.xml
					/*try {
						logger.info("Doing a regular commit during full indexing");
						updateServer.commit(false, false, true);
					}catch (Exception e){
						logger.warn("Error committing changes", e);
					}*/
							GroupedReindexMain.addNoteToReindexLog(numWorksProcessed + " grouped works processed.");
						}
					}
					if (lastUpdated == null) {
						setLastUpdatedTime.setLong(1, indexStartTime - 1); //Set just before the index started so we don't index multiple times
						setLastUpdatedTime.setLong(2, primaryIdentifierId);
						setLastUpdatedTime.executeUpdate();
					}
				}
			}
		} catch (SQLException e) {
			logger.error("Unexpected SQL error", e);
		}
		if (logger.isInfoEnabled()) {
			logger.info("Finished processing grouped works.  Processed a total of " + numWorksProcessed + " grouped works");
		}
		return numWorksProcessed;
	}

	/**
	 *  Process a work during the regular full or partial Reindexing process or a single work
	 *
	 * @param id grouped work database id number
	 * @param permanentId  the hash-number used as the grouped work permanentId
	 * @param grouping_category the work's grouping category
	 * @param siteMapsByScope
	 * @param uniqueGroupedWorks
	 * @throws SQLException
	 */
	void processGroupedWork(Long id, String permanentId, String grouping_category, HashMap<Scope, ArrayList<SiteMapEntry>> siteMapsByScope, HashSet<Long> uniqueGroupedWorks) throws SQLException {
		//Create a solr record for the grouped work
		GroupedWorkSolr groupedWork = new GroupedWorkSolr(this, logger);
		groupedWork.setId(permanentId);
		groupedWork.setGroupingCategory(grouping_category);

		//Load Novelist data for the work
		boolean loadedNovelistSeries = loadNovelistInfo(groupedWork);

		getGroupedWorkPrimaryIdentifiers.setLong(1, id);
		int numPrimaryIdentifiers;
		try (ResultSet groupedWorkPrimaryIdentifiers = getGroupedWorkPrimaryIdentifiers.executeQuery()) {
			numPrimaryIdentifiers = 0;
			while (groupedWorkPrimaryIdentifiers.next()) {
				RecordIdentifier identifier = new RecordIdentifier(groupedWorkPrimaryIdentifiers.getString("type"), groupedWorkPrimaryIdentifiers.getString("identifier"));

				//Make a copy of the grouped work so we can revert if we don't add any records
				GroupedWorkSolr originalWork;
				try {
					originalWork = groupedWork.clone();
				} catch (CloneNotSupportedException cne) {
					logger.error("Could not clone grouped work", cne);
					return;
				}
				//Figure out how many records we had originally
				int numRecords = groupedWork.getNumRecords();
				if (logger.isDebugEnabled()) {
					logger.debug("Processing " + identifier + " work currently has " + numRecords + " records");
				}

				//This does the bulk of the work building fields for the solr document
				if (updateGroupedWorkForPrimaryIdentifier(groupedWork, identifier, loadedNovelistSeries)) {
					//If we didn't add any records to the work (because they are all suppressed) revert to the original
					if (groupedWork.getNumRecords() == numRecords) {
						//No change in the number of records, revert to the previous
						if (logger.isDebugEnabled()) {
							logger.debug("Record " + identifier + " did not contribute any records to the work, reverting to previous state " + groupedWork.getNumRecords());
						}
						groupedWork = originalWork;
					} else {
						if (logger.isDebugEnabled()) {
							logger.debug("Record " + identifier + " added to work " + permanentId);
						}
						numPrimaryIdentifiers++;
					}
				}
			}
		}

		if (numPrimaryIdentifiers > 0) {
			//Add a grouped work to any scopes that are relevant
			groupedWork.updateIndexingStats(indexingStats);


			//Load local (Pika) enrichment for the work
			loadLocalEnrichment(groupedWork);
			//Load lexile data for the work
			loadLexileDataForWork(groupedWork, loadedNovelistSeries);
			//Load accelerated reader data for the work
			loadAcceleratedDataForWork(groupedWork);

			//Write the record to Solr.
			try {
				SolrInputDocument inputDocument = groupedWork.getSolrDocument(availableAtLocationBoostValue, ownedByLocationBoostValue);
				if (logger.isDebugEnabled()) {
					logger.debug("Adding solr document for work " + groupedWork.getId());
				}
				updateServer.add(inputDocument);
				//logger.debug("Updated solr \r\n" + inputDocument.toString());

			} catch (Exception e) {
				logger.error("Error adding grouped work to solr " + groupedWork.getId(), e);
			}
		}else{
			//Log that this record did not have primary identifiers after
			if (logger.isDebugEnabled()) {
				logger.debug("Grouped work " + permanentId + " did not have any primary identifiers for it, suppressing");
			}
			if (!fullReindex){
				try {
					updateServer.deleteById(permanentId);
				}catch (Exception e){
					logger.error("Error deleting suppressed work " + permanentId, e);
				}
			}

		}



	/*	loop through each of the scopes
				if library owned add to appropriate list*/

		if (fullReindex) {
			if (siteMapsByScope == null)
				return;
			int ownershipCount = 0;
			for (Scope scope : this.getScopes()) {
				if (scope.isLibraryScope() && groupedWork.getIsLibraryOwned(scope)) {
					if (!siteMapsByScope.containsKey(scope)) {
						siteMapsByScope.put(scope, new ArrayList<SiteMapEntry>());
					}
					siteMapsByScope.get(scope).add(new SiteMapEntry(id, permanentId, groupedWork.getPopularity()));
					ownershipCount++;
				}
			}
			if (ownershipCount == 1) //unique works
				uniqueGroupedWorks.add(id);
		}

	}

	/**
	 * Index a specific grouped work that has related records for the specified indexing profile to be indexed
	 *
	 * @param primaryIdentifierId database row id for this primary Identify
	 * @param permanentId  grouped work Id
	 * @param grouping_category grouping category of the work
	 * @throws SQLException
	 */
	void processGroupedWork(Long primaryIdentifierId, String permanentId, String grouping_category) throws SQLException {
		//Create a solr record for the grouped work
		GroupedWorkSolr groupedWork = new GroupedWorkSolr(this, logger);
		groupedWork.setId(permanentId);
		groupedWork.setGroupingCategory(grouping_category);

		//Load Novelist data for the work
		boolean loadedNovelistSeries = loadNovelistInfo(groupedWork);

		getGroupedWorkPrimaryIdentifiers.setLong(1, primaryIdentifierId);
		int numPrimaryIdentifiers;
		try (ResultSet groupedWorkPrimaryIdentifiers = getGroupedWorkPrimaryIdentifiers.executeQuery()) {
			numPrimaryIdentifiers = 0;
			while (groupedWorkPrimaryIdentifiers.next()) {
				RecordIdentifier identifier = new RecordIdentifier(groupedWorkPrimaryIdentifiers.getString("type"), groupedWorkPrimaryIdentifiers.getString("identifier"));

				//Make a copy of the grouped work so we can revert if we don't add any records
				GroupedWorkSolr originalWork;
				try {
					originalWork = groupedWork.clone();
				} catch (CloneNotSupportedException cne) {
					logger.error("Could not clone grouped work", cne);
					return;
				}
				//Figure out how many records we had originally
				int numRecords = groupedWork.getNumRecords();
				if (logger.isDebugEnabled()) {
					logger.debug("Processing " + identifier + " work currently has " + numRecords + " records");
				}

				//This does the bulk of the work building fields for the solr document
				if (updateGroupedWorkForPrimaryIdentifier(groupedWork, identifier, loadedNovelistSeries)) {
					//If we didn't add any records to the work (because they are all suppressed) revert to the original
					if (groupedWork.getNumRecords() == numRecords) {
						//No change in the number of records, revert to the previous
						if (logger.isDebugEnabled()) {
							logger.debug("Record " + identifier + " did not contribute any records to the work, reverting to previous state " + groupedWork.getNumRecords());
						}
						groupedWork = originalWork;
					} else {
						if (logger.isDebugEnabled()) {
							logger.debug("Record " + identifier + " added to work " + permanentId);
						}
						numPrimaryIdentifiers++;
					}
				}
			}
		}

		if (numPrimaryIdentifiers > 0) {
			//Add a grouped work to any scopes that are relevant
			groupedWork.updateIndexingStats(indexingStats);

			//Load local (Pika) enrichment for the work
			loadLocalEnrichment(groupedWork);
			//Load lexile data for the work
			loadLexileDataForWork(groupedWork, loadedNovelistSeries);
			//Load accelerated reader data for the work
			loadAcceleratedDataForWork(groupedWork);

			//Write the record to Solr.
			try {
				SolrInputDocument inputDocument = groupedWork.getSolrDocument(availableAtLocationBoostValue, ownedByLocationBoostValue);
				if (logger.isDebugEnabled()) {
					logger.debug("Adding solr document for work " + groupedWork.getId());
				}
				updateServer.add(inputDocument);
				//logger.debug("Updated solr \r\n" + inputDocument.toString());

			} catch (Exception e) {
				logger.error("Error adding grouped work to solr " + groupedWork.getId(), e);
			}
		}else{
			//Log that this record did not have primary identifiers after
			if (logger.isDebugEnabled()) {
				logger.debug("Grouped work " + permanentId + " did not have any primary identifiers for it, suppressing");
			}
			if (!fullReindex){
				try {
					updateServer.deleteById(permanentId);
				}catch (Exception e){
					logger.error("Error deleting suppressed work " + permanentId, e);
				}
			}

		}
	}

	private long lexileDataMatches = 0;

	long getLexileDataMatches(){
		return lexileDataMatches;
	}

	private void loadLexileDataForWork(GroupedWorkSolr groupedWork, boolean loadedNovelistSeries) {
		for (String isbn : groupedWork.getIsbns()) {
			if (lexileInformation.containsKey(isbn)) {
				LexileTitle lexileTitle = lexileInformation.get(isbn);
				String      lexileCode  = lexileTitle.getLexileCode();
				if (lexileCode.length() > 0) {
					groupedWork.setLexileCode(this.translateSystemValue("lexile_code", lexileCode, groupedWork.getId()));
				}
				groupedWork.setLexileScore(lexileTitle.getLexileScore());
				groupedWork.addAwards(lexileTitle.getAwards());
				if (!loadedNovelistSeries) {
					final String lexileSeries = lexileTitle.getSeries();
					if (lexileSeries != null && !lexileSeries.isEmpty()) {
						groupedWork.addSeries(lexileSeries.replace("Ser.", "Series"), "");
					}
				}
				lexileDataMatches++;
				if (fullReindex && logger.isDebugEnabled()) {
					FuzzyScore   score                = new FuzzyScore(Locale.ENGLISH);
					String       groupTitle           = groupedWork.getTitle();
					final String groupWorkPermanentId = groupedWork.getId();
					String       lexTitle             = lexileTitle.getTitle();
					if (groupTitle.length() > 10) {
						// Only check titles with more than 10 characters, bcs the mismatch testing is probably not useful with less
						groupTitle = groupTitle.toLowerCase();
						if (lexTitle != null && !lexTitle.isEmpty()) {
							lexTitle = lexTitle.toLowerCase();
							int titleMatches = score.fuzzyScore(groupTitle, lexTitle);
							if (titleMatches < 10) {

								// A large piece of the mismatches are where the ArTitle is the work subtitle instead
								// So we test the subtitle too, if it is long enough
								String groupSubTitle = groupedWork.getSubTitle();

								if (groupSubTitle != null && groupSubTitle.length() > 10) {
									groupSubTitle = groupSubTitle.toLowerCase();
									int subTitleMatches = score.fuzzyScore(groupSubTitle, lexTitle);
									if (subTitleMatches < 10) {
										logger.debug("Possible mismatch of Lexile Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' with subtitle '" + groupSubTitle + "' for isbn " + isbn + ", Lexile Title " + lexTitle);
									}
								} else if (logger.isDebugEnabled()){
									logger.debug("Possible mismatch of Lexile Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' for isbn " + isbn + ", Lexile Title : " + lexTitle);
								}
							} else if (logger.isDebugEnabled()) {
								logger.debug("Matched Lexile Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' on isbn " + isbn + " with Lexile Title : " + lexTitle);
							}
						} else if (logger.isDebugEnabled()) {
							logger.debug("Lexile match had no title for isbn " + isbn + " on group work " + groupWorkPermanentId);
						}
					} else if (logger.isDebugEnabled()) {
						logger.debug("Matched Lexile Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' on isbn " + isbn + " with Lexile Title : " + lexTitle);
					}
				}
				break;
			}
		}
	}

	private long ARDataMatches = 0;

	long getARDataMatches() {
		return ARDataMatches;
	}

	private void loadAcceleratedDataForWork(GroupedWorkSolr groupedWork) {
		for (String isbn : groupedWork.getIsbns()) {
			if (isbn != null && !isbn.isEmpty()) {
				if (arInformation.containsKey(isbn)) {
					ARTitle arTitle = arInformation.get(isbn);
					if (logger.isDebugEnabled() && fullReindex) {
						// Only do title match checking for debugging
						FuzzyScore   score                = new FuzzyScore(Locale.ENGLISH);
						 String groupTitle           = groupedWork.getTitle();
						final String groupWorkPermanentId = groupedWork.getId();
						 String ARTitle              = arTitle.getTitle();
						if (groupTitle.length() > 10) {
							// Only check titles with more than 10 characters, bcs the mismatch testing is probably not useful with less
							groupTitle = groupTitle.toLowerCase();
							if (ARTitle != null && !ARTitle.isEmpty()) {
								ARTitle = ARTitle.toLowerCase();
								int titleMatches = score.fuzzyScore(groupTitle, ARTitle);
								if (titleMatches < 10) {
									// A large piece of the mismatches are where the ArTitle is the work subtitle instead
									// So we test the subtitle too, if it is long enough
									String groupSubTitle = groupedWork.getSubTitle();

									if (groupSubTitle != null && groupSubTitle.length() > 10) {
										groupSubTitle = groupSubTitle.toLowerCase();
										int subTitleMatches = score.fuzzyScore(groupSubTitle, ARTitle);
										if (subTitleMatches < 10) {
											logger.debug("Possible mismatch of AR Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' with subtitle '" + groupSubTitle + "' and AR data for isbn " + isbn + ", ar title " + ARTitle);
										}
									} else {
										logger.debug("Possible mismatch of AR Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' and AR data for isbn " + isbn + ", ar title " + ARTitle);
									}
								} else if (logger.isDebugEnabled()) {
									logger.debug("Matched AR Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' on isbn " + isbn + " with AR Title : " + ARTitle);
								}
							} else if (logger.isDebugEnabled()) {
								logger.debug("Accelerated Reader match had no title for isbn " + isbn + " on group work " + groupWorkPermanentId);
							}
						} else if (logger.isDebugEnabled()) {
							logger.debug("Matched AR Data for grouped work " + groupWorkPermanentId + " title '" + groupTitle + "' on isbn " + isbn + " with AR Title : " + ARTitle);
						}
					}
					String bookLevel = arTitle.getBookLevel();
					if (bookLevel.length() > 0) {
						groupedWork.setAcceleratedReaderReadingLevel(bookLevel);
						ARDataMatches++;
					}
					groupedWork.setAcceleratedReaderPointValue(arTitle.getArPoints());
					groupedWork.setAcceleratedReaderInterestLevel(arTitle.getInterestLevel());
					break;
				}
			}
		}
	}

	private void loadLocalEnrichment(GroupedWorkSolr groupedWork) {
		//Load rating
		try {
			getRatingStmt.setString(1, groupedWork.getId());
			try (ResultSet ratingsRS = getRatingStmt.executeQuery()) {
				if (ratingsRS.next() && !ratingsRS.wasNull()) {
					float averageRating = ratingsRS.getFloat("averageRating");
					groupedWork.setUserRating(averageRating);
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load local enrichment", e);
		}
	}

	private boolean loadNovelistInfo(GroupedWorkSolr groupedWork){
		boolean loadedNovelistSeries = false;
		try{
			getNovelistStmt.setString(1, groupedWork.getId());
			try (ResultSet novelistRS = getNovelistStmt.executeQuery()) {
				if (novelistRS.next()) {
					String series = novelistRS.getString("seriesTitle");
					if (!novelistRS.wasNull()) {
						groupedWork.clearSeriesData();
						String volume = novelistRS.getString("volume");
						groupedWork.addSeries(series, volume, true);
						loadedNovelistSeries = true;
					}
				}
			}
		}catch (Exception e){
			logger.error("Unable to load novelist data", e);
		}
		return loadedNovelistSeries;
	}

	/**
	 * @param groupedWork Solr document object
	 * @param identifier  record id for the primary identifier
	 * @param loadedNovelistSeries whether the Novelist Series info was loaded
	 * @return  whether a primary identifier had a processor and was processed
	 */
	private boolean updateGroupedWorkForPrimaryIdentifier(GroupedWorkSolr groupedWork, RecordIdentifier identifier, boolean loadedNovelistSeries) {
		final String indexingSource = identifier.getSource().toLowerCase();
		if ("overdrive".equals(indexingSource)) {
			overDriveProcessor.processRecord(groupedWork, identifier.getIdentifier(), loadedNovelistSeries);
		} else if (indexingRecordProcessors.containsKey(indexingSource)) {
			indexingRecordProcessors.get(indexingSource).processRecord(groupedWork, identifier, loadedNovelistSeries);
		} else {
			orphanedGroupedWorkPrimaryIdentifiersProcessed++;
			if (logger.isDebugEnabled()) {
				logger.debug("Orphaned primary identifier, no processor for " + identifier);
			}
			return false;
		}
		groupedWork.addAlternateId(identifier.getIdentifier());
		return true;
	}

	/**
	 * System translation maps are used for things that are not customizable (or that shouldn't be customized)
	 * by library.  For example, translations of language codes, or things where MARC standards define the values.
	 *
	 * We can also load translation maps that are specific to an indexing profile.  That is done within
	 * the record processor itself.
	 */
	private void loadSystemTranslationMaps() {
		//Load all translationMaps, first from default, then from the site specific configuration
		File   defaultTranslationMapDirectory = new File("../../sites/default/translation_maps");
		File[] defaultTranslationMapFiles     = defaultTranslationMapDirectory.listFiles((dir, name) -> name.endsWith("properties"));

		File   serverTranslationMapDirectory = new File("../../sites/" + serverName + "/translation_maps");
		File[] serverTranslationMapFiles     = serverTranslationMapDirectory.listFiles((dir, name) -> name.endsWith("properties"));

		if (defaultTranslationMapFiles != null) {
			for (File curFile : defaultTranslationMapFiles) {
				String mapName = curFile.getName().replace(".properties", "");
				mapName = mapName.replace("_map", "");
				translationMaps.put(mapName, loadSystemTranslationMap(curFile));
			}
			if (serverTranslationMapFiles != null) {
				for (File curFile : serverTranslationMapFiles) {
					String mapName = curFile.getName().replace(".properties", "");
					mapName = mapName.replace("_map", "");
					translationMaps.put(mapName, loadSystemTranslationMap(curFile));
				}
			}
		}
	}

	private HashMap<String, String> loadSystemTranslationMap(File translationMapFile) {
		Properties props = new Properties();
		try {
			props.load(new FileReader(translationMapFile));
		} catch (IOException e) {
			logger.error("Could not read translation map, " + translationMapFile.getAbsolutePath(), e);
		}
		HashMap<String, String> translationMap = new HashMap<>();
		for (Object keyObj : props.keySet()){
			String key = (String)keyObj;
			translationMap.put(key.toLowerCase(), props.getProperty(key));
		}
		return translationMap;
	}

	private HashSet<String> unableToTranslateWarnings = new HashSet<>();
	private HashSet<String> missingTranslationMaps = new HashSet<>();
	String translateSystemValue(String mapName, String value, RecordIdentifier identifier){
		return translateSystemValue(mapName, value, identifier.getSourceAndId());
	}

	String translateSystemValue(String mapName, String value, String identifier){
		if (value == null){
				return null;
			}
		HashMap<String, String> translationMap = translationMaps.get(mapName);
		String translatedValue;
		if (translationMap == null){
			if (!missingTranslationMaps.contains(mapName)) {
				missingTranslationMaps.add(mapName);
				logger.error("Unable to find system translation map for " + mapName);
			}
			translatedValue = value;
		}else{
			String lowerCaseValue = value.toLowerCase();
			if (translationMap.containsKey(lowerCaseValue)){
				translatedValue = translationMap.get(lowerCaseValue);
			}else{
				if (translationMap.containsKey("*")){
					translatedValue = translationMap.get("*");
				}else{
					String concatenatedValue = mapName + ":" + value;
					if (!unableToTranslateWarnings.contains(concatenatedValue)){
						if (fullReindex) {
							logger.warn("Could not translate '" + concatenatedValue + "' sample record " + identifier);
						}
						unableToTranslateWarnings.add(concatenatedValue);
					}
					translatedValue = value;
				}
			}
		}
		if (translatedValue != null){
			translatedValue = translatedValue.trim();
			if (translatedValue.length() == 0){
				translatedValue = null;
			}
		}
		return translatedValue;
	}

	LinkedHashSet<String> translateSystemCollection(String mapName, Set<String> values, RecordIdentifier identifier) {
		LinkedHashSet<String> translatedCollection = new LinkedHashSet<>();
		for (String value : values){
				String translatedValue = translateSystemValue(mapName, value, identifier);
				if (translatedValue != null) {
						translatedCollection.add(translatedValue);
					}
			}
		return  translatedCollection;
	}


	void addWorkWithInvalidLiteraryForms(String id) {
		this.worksWithInvalidLiteraryForms.add(id);
	}

	public TreeSet<Scope> getScopes() {
		return this.scopes;
	}

	Date getDateFirstDetected(RecordIdentifier identifier){
		Long dateFirstDetected = null;
		try {
			getDateFirstDetectedStmt.setString(1, identifier.getSource());
			getDateFirstDetectedStmt.setString(2, identifier.getIdentifier());
			ResultSet dateFirstDetectedRS = getDateFirstDetectedStmt.executeQuery();
			if (dateFirstDetectedRS.next()) {
				dateFirstDetected = dateFirstDetectedRS.getLong("dateFirstDetected");
			}
		}catch (Exception e){
			logger.error("Error loading date first detected for " + identifier);
		}
		if (dateFirstDetected != null){
			return new Date(dateFirstDetected * 1000);
		}else {
			return null;
		}
	}

	long processPublicUserLists() {
		return processPublicUserLists(false);
	}

	long processPublicUserLists(boolean userListsOnly) {
		UserListProcessor listProcessor = new UserListProcessor(this, pikaConn, logger, fullReindex);
		return listProcessor.processPublicUserLists(lastReindexTime, updateServer, solrServer, userListsOnly);
	}

	public boolean isGiveOnOrderItemsTheirOwnShelfLocation() {
		return giveOnOrderItemsTheirOwnShelfLocation;
	}
}
