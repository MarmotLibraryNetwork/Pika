package org.pika;

import au.com.bytecode.opencsv.CSVWriter;
import org.apache.solr.client.solrj.SolrServer;
import org.apache.solr.client.solrj.SolrServerException;
import org.apache.solr.client.solrj.impl.BinaryRequestWriter;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrServer;
import org.apache.solr.client.solrj.impl.HttpSolrServer;
import org.apache.solr.common.SolrInputDocument;

import java.io.*;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;

import org.apache.log4j.Logger;

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
	private       Logger                                   logger;
	private final PikaSystemVariables                      systemVariables;
	private       SolrServer                               solrServer;
	private       ConcurrentUpdateSolrServer               updateServer;
	private       HashMap<String, MarcRecordProcessor>     ilsRecordProcessors                   = new HashMap<>();
	private       OverDriveProcessor                       overDriveProcessor;
	private       HashMap<String, HashMap<String, String>> translationMaps                       = new HashMap<>();
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
	private boolean fullReindex;
	private long    lastReindexTime;
	private boolean partialReindexRunning;
	private boolean okToIndex = true;

	private HashSet<String> worksWithInvalidLiteraryForms = new HashSet<>();
	private TreeSet<Scope>  scopes                        = new TreeSet<>();

	//Keep track of what we are indexing for validation purposes
	private TreeMap<String, TreeSet<String>>     ilsRecordsIndexed = new TreeMap<>();
	private TreeMap<String, TreeSet<String>>     ilsRecordsSkipped = new TreeMap<>();
	private TreeMap<String, ScopedIndexingStats> indexingStats     = new TreeMap<>();
	TreeSet<String> overDriveRecordsIndexed = new TreeSet<>();
	TreeSet<String> overDriveRecordsSkipped = new TreeSet<>();



	public GroupedWorkIndexer(String serverName, Connection pikaConn, Connection econtentConn, boolean fullReindex, boolean singleWorkIndex, Logger logger) {
		indexStartTime                        = new Date().getTime() / 1000;
		this.serverName                       = serverName;
		this.logger                           = logger;
		this.pikaConn                         = pikaConn;
		this.fullReindex                      = fullReindex;
//		solrPort                              = PikaConfigIni.getIniValue("Reindex", "solrPort");
//		availableAtLocationBoostValue         = PikaConfigIni.getIntIniValue("Reindex", "availableAtLocationBoostValue");
//		ownedByLocationBoostValue             = PikaConfigIni.getIntIniValue("Reindex", "ownedByLocationBoostValue");
//		giveOnOrderItemsTheirOwnShelfLocation = PikaConfigIni.getBooleanIniValue("Reindex", "giveOnOrderItemsTheirOwnShelfLocation");
//		baseLogPath                           = PikaConfigIni.getIniValue("Site", "baseLogPath");
//		maxWorksToProcess                     = PikaConfigIni.getIntIniValue("Reindex", "maxWorksToProcess");
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
		lastReindexTime = systemVariables.getLongValuedVariable("last_reindex_time");

		//Check to see if a partial reindex is running
		partialReindexRunning = systemVariables.getBooleanValuedVariable("partial_reindex_running");

		//Load a few statements we will need later
		try{
			getGroupedWorkPrimaryIdentifiers = pikaConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers where grouped_work_id = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//MDN 4/14 - Do not restrict by valid for enrichment since many popular titles
			//Wind up with different work id's due to differences in cataloging.
			//getGroupedWorkIdentifiers = pikaConn.prepareStatement("SELECT * FROM grouped_work_identifiers inner join grouped_work_identifiers_ref on identifier_id = grouped_work_identifiers.id where grouped_work_id = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//TODO: Restore functionality to not include any identifiers that aren't tagged as valid for enrichment
			//getGroupedWorkIdentifiers = pikaConn.prepareStatement("SELECT * FROM grouped_work_identifiers inner join grouped_work_identifiers_ref on identifier_id = grouped_work_identifiers.id where grouped_work_id = ? and valid_for_enrichment = 1", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

			getDateFirstDetectedStmt          = pikaConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE source = ? AND ilsId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		} catch (Exception e){
			logger.error("Could not load statements to get identifiers ", e);
		}

		//Initialize the updateServer and solr server
		GroupedReindexMain.addNoteToReindexLog("Setting up update server and solr server");
		if (fullReindex){
			//MDN 10-21-2015 - use the grouped core since we are using replication.
			solrServer   = new HttpSolrServer("http://localhost:" + solrPort + "/solr/grouped");
			updateServer = new ConcurrentUpdateSolrServer("http://localhost:" + solrPort + "/solr/grouped", 500, 8);
			updateServer.setRequestWriter(new BinaryRequestWriter());

			//Stop replication from the master
			String url                              = "http://localhost:" + solrPort + "/solr/grouped/replication?command=disablereplication";
			URLPostResponse stopReplicationResponse = Util.getURL(url, logger);
			if (!stopReplicationResponse.isSuccess()){
				logger.error("Error restarting replication " + stopReplicationResponse.getMessage());
			}

			updateFullReindexRunning(true);
		}else{
			//TODO: Bypass this if called from an export process?

			//Check to make sure that at least a couple of minutes have elapsed since the last index
			//Periodically in the middle of the night we get indexes every minute or multiple times a minute
			//which is annoying especially since it generally means nothing is changing.
			long elapsedTime         = indexStartTime - lastReindexTime;
			long minIndexingInterval = 2 * 60;
			if (elapsedTime < minIndexingInterval && !singleWorkIndex) {
				try {
					if (logger.isDebugEnabled()) {
						logger.debug("Pausing between indexes, last index ran " + Math.ceil(elapsedTime / 60) + " minutes ago");
						logger.debug("Pausing for " + (minIndexingInterval - elapsedTime) + " seconds");
					}
					GroupedReindexMain.addNoteToReindexLog("Pausing between indexes, last index ran " + Math.ceil(elapsedTime / 60) + " minutes ago");
					GroupedReindexMain.addNoteToReindexLog("Pausing for " + (minIndexingInterval - elapsedTime) + " seconds");
					Thread.sleep((minIndexingInterval - elapsedTime) * 1000);
				} catch (InterruptedException e) {
					logger.warn("Pause was interrupted while pausing between indexes");
				}
			}else{
				GroupedReindexMain.addNoteToReindexLog("Index last ran " + (elapsedTime) + " seconds ago");
			}

			if (partialReindexRunning){
				//Oops, a reindex is already running.
				//No longer really care about this since it doesn't happen and there are other ways of finding a stuck process
				logger.warn("A partial reindex is already running, check to make sure that reindexes don't overlap since that can cause poor performance");
				GroupedReindexMain.addNoteToReindexLog("A partial reindex is already running, check to make sure that reindexes don't overlap since that can cause poor performance");
			}else{
				updatePartialReindexRunning(true);
			}
			updateServer = new ConcurrentUpdateSolrServer("http://localhost:" + solrPort + "/solr/grouped", 500, 8);
			updateServer.setRequestWriter(new BinaryRequestWriter());
			solrServer = new HttpSolrServer("http://localhost:" + solrPort + "/solr/grouped");
		}

		loadScopes();

		//Initialize processors based on our indexing profiles and the primary identifiers for the records.
		try (
			PreparedStatement uniqueIdentifiersStmt = pikaConn.prepareStatement("SELECT DISTINCT type FROM grouped_work_primary_identifiers");
			PreparedStatement getIndexingProfile    = pikaConn.prepareStatement("SELECT * FROM indexing_profiles WHERE name = ?");
			ResultSet uniqueIdentifiersRS           = uniqueIdentifiersStmt.executeQuery();
		){
			while (uniqueIdentifiersRS.next()){
				String curIdentifier = uniqueIdentifiersRS.getString("type");
				getIndexingProfile.setString(1, curIdentifier);
				try (ResultSet indexingProfileRS = getIndexingProfile.executeQuery()) {
					if (indexingProfileRS.next()) {
						String ilsIndexingClassString = indexingProfileRS.getString("indexingClass");
						switch (ilsIndexingClassString) {
							case "Marmot":
								ilsRecordProcessors.put(curIdentifier, new MarmotRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
	//						case "Nashville":
	//							ilsRecordProcessors.put(curIdentifier, new NashvilleRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
	//							break;
	//						case "NashvilleSchools":
	//							ilsRecordProcessors.put(curIdentifier, new NashvilleSchoolsRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
	//							break;
							case "WCPL":
								ilsRecordProcessors.put(curIdentifier, new WCPLRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Anythink":
								ilsRecordProcessors.put(curIdentifier, new AnythinkRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Aspencat":
								ilsRecordProcessors.put(curIdentifier, new AspencatRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Flatirons":
								ilsRecordProcessors.put(curIdentifier, new FlatironsRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Addison":
								ilsRecordProcessors.put(curIdentifier, new AddisonRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Aurora":
								ilsRecordProcessors.put(curIdentifier, new AuroraRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Arlington":
								ilsRecordProcessors.put(curIdentifier, new ArlingtonRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "CarlX": // Currently the Nashville Processor
								ilsRecordProcessors.put(curIdentifier, new CarlXRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "SantaFe":
								ilsRecordProcessors.put(curIdentifier, new SantaFeRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Sacramento":
								ilsRecordProcessors.put(curIdentifier, new SacramentoRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "AACPL":
								ilsRecordProcessors.put(curIdentifier, new AACPLRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Lion":
								ilsRecordProcessors.put(curIdentifier, new LionRecordProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "SideLoadedEContent":
								ilsRecordProcessors.put(curIdentifier, new SideLoadedEContentProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							case "Hoopla":
								ilsRecordProcessors.put(curIdentifier, new HooplaProcessor(this, pikaConn, indexingProfileRS, logger, fullReindex));
								break;
							default:
								logger.error("Unknown indexing class " + ilsIndexingClassString);
								okToIndex = false;
								return;
						}
					} else if (curIdentifier.equalsIgnoreCase("overdrive")) {
						//Overdrive doesn't have an indexing profile.
						//Only load processor if there are overdrive titles
						overDriveProcessor = new OverDriveProcessor(this, econtentConn, logger, fullReindex);
					} else if (fullReindex){
						logger.error("Could not find indexing profile for type " + curIdentifier);
					}
				}
			}

			setupIndexingStats();

		}catch (Exception e){
			logger.error("Error loading record processors for ILS records", e);
		}
		//Load translation maps
		loadSystemTranslationMaps();

		//Setup prepared statements to load local enrichment
		try {
			//No need to filter for ratings greater than 0 because the user has to rate from 1-5
			getRatingStmt = pikaConn.prepareStatement("SELECT AVG(rating) AS averageRating, groupedRecordPermanentId FROM user_work_review WHERE groupedRecordPermanentId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			getNovelistStmt = pikaConn.prepareStatement("SELECT * FROM novelist_data WHERE groupedRecordPermanentId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		} catch (SQLException e) {
			logger.error("Could not prepare statements to load local enrichment", e);
		}

		String lexileExportPath = PikaConfigIni.getIniValue("Reindex", "lexileExportPath");
		loadLexileData(lexileExportPath);

		String arExportPath = PikaConfigIni.getIniValue("Reindex", "arExportPath");
		loadAcceleratedReaderData(arExportPath);

		if (fullReindex){
			clearIndex();
		}
	}

	private void setupIndexingStats() {
		ArrayList<String> recordProcessorNames = new ArrayList<>(ilsRecordProcessors.keySet());
		recordProcessorNames.add("overdrive");

		for (Scope curScope : scopes){
			ScopedIndexingStats scopedIndexingStats = new ScopedIndexingStats(curScope.getScopeName(), recordProcessorNames);
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
		PreparedStatement locationInformationStmt = pikaConn.prepareStatement("SELECT library.libraryId, locationId, code, subLocation, " +
				"library.subdomain, location.facetLabel, location.displayName, library.pTypes, library.restrictOwningBranchesAndSystems, location.publicListsToInclude, " +
				"library.enableOverdriveCollection as enableOverdriveCollectionLibrary, " +
				"location.enableOverdriveCollection as enableOverdriveCollectionLocation, " +
				"library.includeOverdriveAdult as includeOverdriveAdultLibrary, location.includeOverdriveAdult as includeOverdriveAdultLocation, " +
				"library.includeOverdriveTeen as includeOverdriveTeenLibrary, location.includeOverdriveTeen as includeOverdriveTeenLocation, " +
				"library.includeOverdriveKids as includeOverdriveKidsLibrary, location.includeOverdriveKids as includeOverdriveKidsLocation, " +
				"library.sharedOverdriveCollection, " +
				"location.additionalLocationsToShowAvailabilityFor, includeAllLibraryBranchesInFacets, " +
				"location.includeAllRecordsInShelvingFacets, location.includeAllRecordsInDateAddedFacets, location.includeOnOrderRecordsInDateAddedFacetValues, location.baseAvailabilityToggleOnLocalHoldingsOnly, " +
				"location.includeOnlineMaterialsInAvailableToggle, location.includeLibraryRecordsToInclude " +
				"FROM location INNER JOIN library on library.libraryId = location.libraryId ORDER BY code ASC",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationOwnedRecordRulesStmt = pikaConn.prepareStatement("SELECT location_records_owned.*, indexing_profiles.name FROM location_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationRecordInclusionRulesStmt = pikaConn.prepareStatement("SELECT location_records_to_include.*, indexing_profiles.name FROM location_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

		ResultSet locationInformationRS = locationInformationStmt.executeQuery();
		while (locationInformationRS.next()){
			String code        = locationInformationRS.getString("code").toLowerCase();
			String subLocation = locationInformationRS.getString("subLocation");
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
			String scopeName = code;
			if (subLocation != null && subLocation.length() > 0){
				scopeName = subLocation.toLowerCase();
			}
			locationScopeInfo.setScopeName(scopeName);
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
				locationScopeInfo.addOwnershipRule(new OwnershipRule(locationOwnedRecordRulesRS.getString("name"), locationOwnedRecordRulesRS.getString("location"), locationOwnedRecordRulesRS.getString("subLocation")));
			}

			locationRecordInclusionRulesStmt.setLong(1, locationId);
			ResultSet locationRecordInclusionRulesRS = locationRecordInclusionRulesStmt.executeQuery();
			while (locationRecordInclusionRulesRS.next()){
				locationScopeInfo.addInclusionRule(new InclusionRule(locationRecordInclusionRulesRS.getString("name"),
						locationRecordInclusionRulesRS.getString("location"),
						locationRecordInclusionRulesRS.getString("subLocation"),
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
					locationScopeInfo.addInclusionRule(new InclusionRule(libraryRecordInclusionRulesRS.getString("name"),
							libraryRecordInclusionRulesRS.getString("location"),
							libraryRecordInclusionRulesRS.getString("subLocation"),
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
		PreparedStatement libraryOwnedRecordRulesStmt = pikaConn.prepareStatement("SELECT library_records_owned.*, indexing_profiles.name from library_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		libraryRecordInclusionRulesStmt = pikaConn.prepareStatement("SELECT library_records_to_include.*, indexing_profiles.name from library_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
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

			//Determine if we need to build a scope for this library
			//MDN 10/1/2014 always build scopes because it makes coding more consistent elsewhere.
			//We need to build a scope
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
				newScope.addOwnershipRule(new OwnershipRule(libraryOwnedRecordRulesRS.getString("name"), libraryOwnedRecordRulesRS.getString("location"), libraryOwnedRecordRulesRS.getString("subLocation")));
			}

			libraryRecordInclusionRulesStmt.setLong(1, libraryId);
			ResultSet libraryRecordInclusionRulesRS = libraryRecordInclusionRulesStmt.executeQuery();
			while (libraryRecordInclusionRulesRS.next()){
				newScope.addInclusionRule(new InclusionRule(libraryRecordInclusionRulesRS.getString("name"),
						libraryRecordInclusionRulesRS.getString("location"),
						libraryRecordInclusionRulesRS.getString("subLocation"),
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

	private void loadAcceleratedReaderData(String acceleratedReaderPath){
		try{
			File arFile = new File(acceleratedReaderPath);
			BufferedReader arDataReader = new BufferedReader(new FileReader(arFile));
			//Skip over the header
			arDataReader.readLine();
			String arDataLine = arDataReader.readLine();
			int numLines = 0;
			while (arDataLine != null){
				ARTitle titleInfo = new ARTitle();
				String[] arFields = arDataLine.split("\\t");
				if (arFields.length >= 29){
					titleInfo.setTitle(arFields[2]);
					titleInfo.setAuthor(arFields[6]);
					titleInfo.setBookLevel(arFields[7]);
					titleInfo.setArPoints(arFields[8]);
					titleInfo.setInterestLevel(arFields[10]);
					String isbn1 = arFields[11];
					if (isbn1.length() > 0) {
						isbn1 = isbn1.replaceAll("[^\\dX]", "");
						arInformation.put(isbn1, titleInfo);
					}
					String isbn2 = arFields[14];
					if (isbn2.length() > 0) {
						isbn2 = isbn2.replaceAll("[^\\dX]", "");
						arInformation.put(isbn2, titleInfo);
					}
					String isbn3 = arFields[17];
					if (isbn3.length() > 0) {
						isbn3 = isbn3.replaceAll("[^\\dX]", "");
						arInformation.put(isbn3, titleInfo);
					}
					String isbn4 = arFields[20];
					if (isbn4.length() > 0) {
						isbn4 = isbn4.replaceAll("[^\\dX]", "");
						arInformation.put(isbn4, titleInfo);
					}
					String isbn5 = arFields[23];
					if (isbn5.length() > 0) {
						isbn5 = isbn5.replaceAll("[^\\dX]", "");
						arInformation.put(isbn5, titleInfo);
					}
					String isbn6 = arFields[26];
					if (isbn6.length() > 0) {
						isbn6 = isbn6.replaceAll("[^\\dX]", "");
						arInformation.put(isbn6, titleInfo);
					}
					String isbn7 = arFields[29];
					if (isbn7.length() > 0) {
						isbn7 = isbn7.replaceAll("[^\\dX]", "");
						arInformation.put(isbn7, titleInfo);
					}
					numLines++;
				}
				arDataLine = arDataReader.readLine();
			}
			if (logger.isInfoEnabled()) {
				logger.info("Read " + numLines + " lines of accelerated reader data");
			}
		}catch (Exception e){
			logger.error("Error loading accelerated reader data", e);
		}
	}

	private void loadLexileData(String lexileExportPath) {
		String[] lexileFields = new String[0];
		int      curLine      = 0;
		try {
			File           lexileData   = new File(lexileExportPath);
			try (BufferedReader lexileReader = new BufferedReader(new FileReader(lexileData))) {
				//Skip over the header
				lexileReader.readLine();
				String lexileLine = lexileReader.readLine();
				curLine++;
				while (lexileLine != null) {
					lexileFields = lexileLine.split("\\t");
					LexileTitle titleInfo = new LexileTitle();
					if (lexileFields.length >= 11) {
						titleInfo.setTitle(lexileFields[0]);
						titleInfo.setAuthor(lexileFields[1]);
						String isbn = lexileFields[3];
						titleInfo.setLexileCode(lexileFields[4]);
						titleInfo.setLexileScore(lexileFields[5]);
						if (lexileFields.length >= 11) {
							titleInfo.setSeries(lexileFields[10]);
						}
						if (lexileFields.length >= 12) {
							titleInfo.setAwards(lexileFields[11]);
						}
						if (lexileFields.length >= 13) {
							titleInfo.setDescription(lexileFields[12]);
						}
						lexileInformation.put(isbn, titleInfo);
					}
					lexileLine = lexileReader.readLine();
					curLine++;
				}
			}
			if (logger.isInfoEnabled()) {
				logger.info("Read " + lexileInformation.size() + " lines of lexile data");
			}
		} catch (Exception e) {
			logger.error("Error loading lexile data on " + curLine + Arrays.toString(lexileFields), e);
		}
	}

	private void clearIndex() {
		//Check to see if we should clear the existing index
		if (logger.isInfoEnabled()) {
			logger.info("Clearing existing marc records from index");
		}
		try {
			updateServer.deleteByQuery("recordtype:grouped_work");
			//With this commit, we get errors in the log "Previous SolrRequestInfo was not closed!"
			//Allow auto commit functionality to handle this
			//updateServer.commit(true, false, false);
		} catch (Exception e) {
			logger.error("Error deleting from index", e);
		}
	}

	void deleteRecord(String id) {
		if (logger.isInfoEnabled()) {
			logger.info("Clearing existing work from index");
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

		File dataDir = new File(PikaConfigIni.getIniValue("SiteMap", "filePath"));
		String maxPopTitlesDefault = PikaConfigIni.getIniValue("SiteMap", "num_titles_in_most_popular_sitemap");
		String maxUniqueTitlesDefault = PikaConfigIni.getIniValue("SiteMap", "num_title_in_unique_sitemap");
		String url = PikaConfigIni.getIniValue("Site", "url");
		try {
			SiteMap siteMap = new SiteMap(logger, pikaConn, Integer.parseInt(maxUniqueTitlesDefault), Integer.parseInt(maxPopTitlesDefault));
			siteMap.createSiteMaps(url, dataDir, siteMapsByScope, uniqueGroupedWorks);

		} catch (IOException ex) {
			logger.error("Error creating site map");
		}
	}


	void finishIndexing(boolean processingIndividualWork){
		GroupedReindexMain.addNoteToReindexLog("Finishing indexing");
		if (fullReindex) {
			try {
				GroupedReindexMain.addNoteToReindexLog("Calling final commit");
				updateServer.commit(true, true, false);
//				updateServer.commit();
			} catch (IOException e) {
				logger.error("Error calling final commit", e);
			} catch (SolrServerException e) {
				logger.error("Error with Solr calling final commit", e);
			}
			//Swap the indexes
			if (fullReindex)  {
				//Restart replication from the master
				String url = "http://localhost:" + solrPort + "/solr/grouped/replication?command=enablereplication";
				URLPostResponse startReplicationResponse = Util.getURL(url, logger);
				if (!startReplicationResponse.isSuccess()){
					logger.error("Error restarting replication " + startReplicationResponse.getMessage());
				}

				//MDN 10-21-2015 do not swap indexes when using replication
				/*GroupedReindexMain.addNoteToReindexLog("Swapping indexes");
				try {
					Util.getURL("http://localhost:" + solrPort + "/solr/admin/cores?action=SWAP&core=grouped2&other=grouped", logger);
				} catch (Exception e) {
					logger.error("Error shutting down update server", e);
				}*/
			}
		}else {
			try {
				GroupedReindexMain.addNoteToReindexLog("Doing a soft commit to make sure changes are saved");
				updateServer.commit(false, false, true);
				GroupedReindexMain.addNoteToReindexLog("Shutting down the update server");
				updateServer.blockUntilFinished();
				updateServer.shutdown();
			} catch (Exception e) {
				logger.error("Error shutting down update server", e);
			}
		}

		writeWorksWithInvalidLiteraryForms();
		if (!processingIndividualWork) {
			updateLastReindexTime();
		}

		//Write validation information
		if (fullReindex) {
			writeValidationInformation();
			writeStats();
			updateFullReindexRunning(false);
		}else{
			updatePartialReindexRunning(false);
		}
	}

	private void writeStats() {
		try {
			File dataDir = new File(PikaConfigIni.getIniValue("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date curDate = new Date();
			String curDateFormatted = dayFormatter.format(curDate);
			File recordsFile = new File(dataDir.getAbsolutePath() + "/reindex_stats_" + curDateFormatted + ".csv");
			CSVWriter recordWriter = new CSVWriter(new FileWriter(recordsFile));
			ArrayList<String> headers = new ArrayList<>();
			headers.add("Scope Name");
			headers.add("Owned works");
			headers.add("Total works");
			TreeSet<String> recordProcessorNames = new TreeSet<>();
			recordProcessorNames.addAll(ilsRecordProcessors.keySet());
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
		}
		File worksWithInvalidLiteraryFormsFile = new File (baseLogPath + "/" + serverName + "/worksWithInvalidLiteraryForms.txt");
		try {
			if (worksWithInvalidLiteraryForms.size() > 0) {
				try (FileWriter writer = new FileWriter(worksWithInvalidLiteraryFormsFile, false)) {
					final String message = "Found " + worksWithInvalidLiteraryForms.size() + " grouped works with invalid literary forms\r\n";
					logger.debug(message);
					writer.write(message);
					writer.write("Works with inconsistent literary forms\r\n");
					for (String curId : worksWithInvalidLiteraryForms) {
						writer.write(curId + "\r\n");
					}
				}
			}
		}catch(Exception e){
			logger.error("Error writing works with invalid literary forms", e);
		}
	}

	private void updateLastReindexTime() {
		//Update the last re-index time in the variables table.  This needs to be the time the index started to catch anything that changes during the index
		if (!systemVariables.setVariable("last_reindex_time", indexStartTime)){
			logger.error("Error setting last reindex time");
		}
	}

	Long processGroupedWorks(HashMap<Scope, ArrayList<SiteMapEntry>> siteMapsByScope, HashSet<Long> uniqueGroupedWorks) {
		Long numWorksProcessed = 0L;
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
			Long numWorksToIndex = numWorksToIndexRS.getLong(1);
			GroupedReindexMain.addNoteToReindexLog("Starting to process " + numWorksToIndex + " grouped works");

			ResultSet groupedWorks = getAllGroupedWorks.executeQuery();
			while (groupedWorks.next()) {
				Long   id                = groupedWorks.getLong("id");
				String permanentId       = groupedWorks.getString("permanent_id");
				String grouping_category = groupedWorks.getString("grouping_category");
				Long   lastUpdated       = groupedWorks.getLong("date_updated");
				if (groupedWorks.wasNull()){
					lastUpdated = null;
				}
				processGroupedWork(id, permanentId, grouping_category, siteMapsByScope, uniqueGroupedWorks);

				numWorksProcessed++;
				if (fullReindex && (numWorksProcessed % 5000 == 0)){
					//Testing shows that regular commits do seem to improve performance.
					//However, we can't do it too often or we get errors with too many searchers warming.
					//This is happening now with the auto commit settings in solrconfig.xml
					/*try {
						logger.info("Doing a regular commit during full indexing");
						updateServer.commit(false, false, true);
					}catch (Exception e){
						logger.warn("Error committing changes", e);
					}*/
					GroupedReindexMain.addNoteToReindexLog("Processed " + numWorksProcessed + " grouped works processed.");
					GroupedReindexMain.updateNumWorksProcessed(numWorksProcessed);
				}
				if (maxWorksToProcess != -1 && numWorksProcessed >= maxWorksToProcess){
					logger.warn("Stopping processing now because we've reached the max works to process.");
					break;
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
		return numWorksProcessed;
	}

	void processGroupedWork(Long id, String permanentId, String grouping_category, HashMap<Scope, ArrayList<SiteMapEntry>> siteMapsByScope, HashSet<Long> uniqueGroupedWorks) throws SQLException {
		//Create a solr record for the grouped work
		GroupedWorkSolr groupedWork = new GroupedWorkSolr(this, logger);
		groupedWork.setId(permanentId);
		groupedWork.setGroupingCategory(grouping_category);

		getGroupedWorkPrimaryIdentifiers.setLong(1, id);
		int numPrimaryIdentifiers;
		try (ResultSet groupedWorkPrimaryIdentifiers = getGroupedWorkPrimaryIdentifiers.executeQuery()) {
			numPrimaryIdentifiers = 0;
			while (groupedWorkPrimaryIdentifiers.next()) {
				String type       = groupedWorkPrimaryIdentifiers.getString("type");
				String identifier = groupedWorkPrimaryIdentifiers.getString("identifier");

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
					logger.debug("Processing " + type + ":" + identifier + " work currently has " + numRecords + " records");
				}

				//This does the bulk of the work building fields for the solr document
				updateGroupedWorkForPrimaryIdentifier(groupedWork, type, identifier);

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
			groupedWorkPrimaryIdentifiers.close();
		}

		if (numPrimaryIdentifiers > 0) {
			//Add a grouped work to any scopes that are relevant
			groupedWork.updateIndexingStats(indexingStats);

			//Update the grouped record based on data for each work
			//getGroupedWorkIdentifiers.setLong(1, id);
			/*ResultSet groupedWorkIdentifiers = getGroupedWorkIdentifiers.executeQuery();
			//This just adds isbns, issns, upcs, and oclc numbers to the index
			while (groupedWorkIdentifiers.next()) {
				String type = groupedWorkIdentifiers.getString("type");
				String identifier = groupedWorkIdentifiers.getString("identifier");
				updateGroupedWorkForSecondaryIdentifier(groupedWork, type, identifier);
			}
			groupedWorkIdentifiers.close();*/

			//Load local (Pika) enrichment for the work
			loadLocalEnrichment(groupedWork);
			//Load lexile data for the work
			loadLexileDataForWork(groupedWork);
			//Load accelerated reader data for the work
			loadAcceleratedDataForWork(groupedWork);
			//Load Novelist data
			loadNovelistInfo(groupedWork);

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
					logger.error("Error deleting suppressed record", e);
				}
			}

		}



	/*	loop thru each of the scopes
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

	private void loadLexileDataForWork(GroupedWorkSolr groupedWork) {
		for (String isbn : groupedWork.getIsbns()) {
			if (lexileInformation.containsKey(isbn)) {
				LexileTitle lexileTitle = lexileInformation.get(isbn);
				String      lexileCode  = lexileTitle.getLexileCode();
				if (lexileCode.length() > 0) {
					groupedWork.setLexileCode(this.translateSystemValue("lexile_code", lexileCode, groupedWork.getId()));
				}
				groupedWork.setLexileScore(lexileTitle.getLexileScore());
				groupedWork.addAwards(lexileTitle.getAwards());
				if (lexileTitle.getSeries().length() > 0) {
					groupedWork.addSeries(lexileTitle.getSeries());
				}
				break;
			}
		}
	}

	private void loadAcceleratedDataForWork(GroupedWorkSolr groupedWork){
		for(String isbn : groupedWork.getIsbns()){
			if (arInformation.containsKey(isbn)){
				ARTitle arTitle = arInformation.get(isbn);
				String bookLevel = arTitle.getBookLevel();
				if (bookLevel.length() > 0){
					groupedWork.setAcceleratedReaderReadingLevel(bookLevel);
				}
				groupedWork.setAcceleratedReaderPointValue(arTitle.getArPoints());
				groupedWork.setAcceleratedReaderInterestLevel(arTitle.getInterestLevel());
				break;
			}
		}
	}

	private void loadLocalEnrichment(GroupedWorkSolr groupedWork) {
		//Load rating
		try{
			getRatingStmt.setString(1, groupedWork.getId());
			ResultSet ratingsRS = getRatingStmt.executeQuery();
			if (ratingsRS.next()){
				Float averageRating = ratingsRS.getFloat("averageRating");
				if (!ratingsRS.wasNull()){
					groupedWork.setUserRating(averageRating);
				}
			}
			ratingsRS.close();
		}catch (Exception e){
			logger.error("Unable to load local enrichment", e);
		}
	}

	private void loadNovelistInfo(GroupedWorkSolr groupedWork){
		try{
			getNovelistStmt.setString(1, groupedWork.getId());
			ResultSet novelistRS = getNovelistStmt.executeQuery();
			if (novelistRS.next()){
				String series = novelistRS.getString("seriesTitle");
				if (!novelistRS.wasNull()){
					groupedWork.clearSeriesData();
					groupedWork.addSeries(series);
					String volume = novelistRS.getString("volume");
					if (novelistRS.wasNull()){
						volume = "";
					}
					groupedWork.addSeriesWithVolume(series + "|" + volume);
				}
			}
			novelistRS.close();
		}catch (Exception e){
			logger.error("Unable to load novelist data", e);
		}
	}

	private void updateGroupedWorkForPrimaryIdentifier(GroupedWorkSolr groupedWork, String type, String identifier)  {
		groupedWork.addAlternateId(identifier);
		type = type.toLowerCase();
		switch (type) {
			case "overdrive":
				overDriveProcessor.processRecord(groupedWork, identifier);
				break;
			default:
				if (ilsRecordProcessors.containsKey(type)) {
					ilsRecordProcessors.get(type).processRecord(groupedWork, identifier);
				}else if (logger.isDebugEnabled()){
					logger.debug("Could not find a record processor for type " + type);
				}
				break;
		}
	}

	/*private void updateGroupedWorkForSecondaryIdentifier(GroupedWorkSolr groupedWork, String type, String identifier) {
		type = type.toLowerCase();
		if (type.equals("isbn")){
			groupedWork.addIsbn(identifier);
		}else if (type.equals("upc")){
			groupedWork.addUpc(identifier);
		}else if (type.equals("order")){
			//Add as an alternate id
			groupedWork.addAlternateId(identifier);
		}else if (!type.equals("issn") && !type.equals("oclc")){
			logger.warn("Unknown identifier type " + type);
		}
	}*/

	/**
	 * System translation maps are used for things that are not customizable (or that shouldn't be customized)
	 * by library.  For example, translations of language codes, or things where MARC standards define the values.
	 *
	 * We can also load translation maps that are specific to an indexing profile.  That is done within
	 * the record processor itself.
	 */
	private void loadSystemTranslationMaps(){
		//Load all translationMaps, first from default, then from the site specific configuration
		File defaultTranslationMapDirectory = new File("../../sites/default/translation_maps");
		File[] defaultTranslationMapFiles = defaultTranslationMapDirectory.listFiles(new FilenameFilter() {
			@Override
			public boolean accept(File dir, String name) {
				return name.endsWith("properties");
			}
		});

		File serverTranslationMapDirectory = new File("../../sites/" + serverName + "/translation_maps");
		File[] serverTranslationMapFiles = serverTranslationMapDirectory.listFiles(new FilenameFilter() {
			@Override
			public boolean accept(File dir, String name) {
				return name.endsWith("properties");
			}
		});

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

	LinkedHashSet<String> translateSystemCollection(String mapName, Set<String> values, String identifier) {
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

	Date getDateFirstDetected(String source, String recordId){
		Long dateFirstDetected = null;
		try {
			getDateFirstDetectedStmt.setString(1, source);
			getDateFirstDetectedStmt.setString(2, recordId);
			ResultSet dateFirstDetectedRS = getDateFirstDetectedStmt.executeQuery();
			if (dateFirstDetectedRS.next()) {
				dateFirstDetected = dateFirstDetectedRS.getLong("dateFirstDetected");
			}
		}catch (Exception e){
			logger.error("Error loading date first detected for " + recordId);
		}
		if (dateFirstDetected != null){
			return new Date(dateFirstDetected * 1000);
		}else {
			return null;
		}
	}

	long processPublicUserLists() {
		UserListProcessor listProcessor = new UserListProcessor(this, pikaConn, logger, fullReindex, availableAtLocationBoostValue, ownedByLocationBoostValue);
		return listProcessor.processPublicUserLists(lastReindexTime, updateServer, solrServer);
	}

	public boolean isGiveOnOrderItemsTheirOwnShelfLocation() {
		return giveOnOrderItemsTheirOwnShelfLocation;
	}
}
