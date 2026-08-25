<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

function getSearchUpdates(): array{

	return [
		'2026.03.0_AddSearchSourceToSearchTable' => [
			'release'         => '2026.03.0',
			'releaseStep'     => 1,
			'title'           => 'Store the Search Source with saved searches',
			'description'     => 'Adds the searchSource column to the search table so a saved search records the scope it was made under. Most databases already have the column from a legacy update; where that is so this does nothing and still reports success, so the update is marked as run rather than offered again forever.',
			'continueOnError' => false,
			'sql'             => [
				'addSearchSourceColumnToSearchTable',
			],
		],

		'2026.03.0_BackfillSearchSourceForNonCatalogSearches' => [
			'release'         => '2026.03.0',
			'releaseStep'     => 2,
			'title'           => 'Set the Search Source of existing genealogy & archive searches',
			'description'     => 'Searches saved before the source was stored with them all read as catalog searches, because that is what the searchSource column defaults to. Where the saved search is a genealogy, Archive or Archive2 search its source can be recovered from the search type stored with it, so set those. A catalog search does not record which collection, branch or consortium it was scoped to, so those rows are left alone.',
			'continueOnError' => false,
			'sql'             => [
				'backfillSearchSourceFromSearchType',
			],
		],

	];
}

// Functions definitions that get executed by any of the updates above

/**
 * Add the searchSource column to the search table, unless it is already there.
 *
 * The column was first added by a legacy update, so most databases already carry it, and an
 * unconditional ALTER TABLE fails on those.  A failed update is never marked as run, so it goes on
 * being offered every time anyone opens Database Maintenance, and clearing that by hand is a chore.
 * There is no portable SQL for "add this column only if it is missing", so look before altering and
 * report failure only when the column is genuinely absent and cannot be added.
 *
 * @return bool  False only if the column is missing and the ALTER TABLE to add it failed.
 */
function addSearchSourceColumnToSearchTable(): bool{
	global $pikaLogger;
	$logger = $pikaLogger->withName('DBMaintenance');

	if (searchTableHasSearchSourceColumn()){
		$logger->notice('The search table already has a searchSource column, nothing to add');
		return true;
	}

	require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
	$search = new SearchEntry();
	$result = $search->query("ALTER TABLE `search` ADD COLUMN `searchSource` VARCHAR(30) NOT NULL DEFAULT 'local' AFTER `search_object`");
	if (PEAR::isError($result)){
		$logger->error('Failed to add the searchSource column to the search table', ['message' => $result->getMessage()]);
		return false;
	}

	// A DDL statement returns neither rows nor an affected count, so its return value says nothing
	// about whether it worked.  Ask the table itself instead.
	if (!searchTableHasSearchSourceColumn()){
		$logger->error('Adding the searchSource column to the search table reported no error, but the column is still not there');
		return false;
	}

	$logger->notice('Added the searchSource column to the search table');
	return true;
}

/**
 * Does the search table carry the searchSource column yet?
 *
 * @return bool
 */
function searchTableHasSearchSourceColumn(): bool{
	require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
	$search = new SearchEntry();
	$search->query("SHOW COLUMNS FROM `search` LIKE 'searchSource'");
	// The number of matching columns, or false when the query itself failed - which is indeed not
	// an answer of "yes", but is worth telling apart from an empty result.
	return is_numeric($search->N) && $search->N > 0;
}

/**
 * Recover the search source of saved genealogy and archive searches from the search type stored in
 * their minified search object.
 *
 * SearchObjectFactory::deminify() picks the search object class by switching on that search type,
 * and for these three indexes the type is the same string as the search source.  A catalog search
 * stores 'basic' or 'advanced' there instead, and nothing in the minified object records which
 * collection, branch or consortium the search was scoped to, so catalog rows keep the 'local' the
 * column defaults to.
 *
 * Only rows still holding that default are considered, so the update is safe to re-run and cannot
 * overwrite a source recorded by SearchObject_Base::addToHistory().
 *
 * @return bool  False if any row that should have been updated could not be.
 */
function backfillSearchSourceFromSearchType(): bool{
	require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
	// minSO is declared at the foot of the search object base class.  unserialize() below needs it
	// loaded, or the stored search comes back as __PHP_Incomplete_Class no matter what it holds.
	require_once ROOT_DIR . '/sys/SearchObject/Base.php';

	global $pikaLogger;
	$logger = $pikaLogger->withName('DBMaintenance');

	if (!searchTableHasSearchSourceColumn()){
		$logger->error('Cannot recover search sources: the search table has no searchSource column. Run the update that adds it first.');
		return false;
	}

	$recoverableTypes = ['genealogy', 'islandora', 'islandora2'];

	// The search table holds every search run in the last couple of days as well as the ones patrons
	// have saved, and every row carries a serialized search object.  PEAR's mysqli driver buffers a
	// whole result set before the first row can be read, so asking for all the matching rows at once
	// exhausts the memory limit on a table of any size.  Walk the table in batches instead, and read
	// only the two columns this needs rather than the whole row.
	//
	// Each batch picks up where the last one left off by id rather than by offset: rows drop out of
	// the search below as they are updated, so counting past a fixed number of them would step over
	// the ones left behind.
	$batchSize = 500;
	$lastId    = 0;

	$updated = 0;
	$skipped = 0;
	$failed  = 0;

	do {
		set_time_limit(500);

		// A DataObject cannot run a second query, so each batch needs its own.
		$search = new SearchEntry();
		$search->selectAdd();
		$search->selectAdd('id');
		$search->selectAdd('search_object');
		$search->whereAdd("searchSource = 'local'");
		$search->whereAdd('id > ' . $lastId);
		$search->orderBy('id');
		$search->limit(0, $batchSize);
		$rowsInBatch = $search->find();
		if ($rowsInBatch === false){
			$logger->error('Failed to read the next batch of saved searches while recovering their search sources');
			return false;
		}

		while ($search->fetch()){
			$lastId = (int)$search->id;

			if (empty($search->search_object)){
				$skipped++;
				continue;
			}

			try {
				$minSO = unserialize($search->search_object, ['allowed_classes' => ['minSO']]);
			} catch (\Throwable $e){
				$logger->warning("Could not read saved search $lastId while recovering its search source", ['message' => $e->getMessage()]);
				$skipped++;
				continue;
			}

			if (!($minSO instanceof minSO) || !in_array($minSO->ty, $recoverableTypes, true)){
				// A catalog search, or a row too corrupt to read.  Either way, there is nothing to recover.
				$skipped++;
				continue;
			}

			// Update through a second object holding only the primary key and the one column, so the
			// UPDATE does not rewrite the serialized search back over itself, and so the row being
			// fetched is not modified while the result set is still being read.
			$searchToUpdate               = new SearchEntry();
			$searchToUpdate->id           = $lastId;
			$searchToUpdate->searchSource = $minSO->ty;
			if ($searchToUpdate->update() === false){
				$logger->error("Failed to set the search source of saved search $lastId to $minSO->ty");
				$failed++;
			}else{
				$updated++;
			}
		}

		$search->free();
		unset($search);
	} while ($rowsInBatch == $batchSize);

	$logger->notice("Recovered the search source of $updated saved searches, left $skipped unchanged, $failed failed");
	return $failed === 0;
}
