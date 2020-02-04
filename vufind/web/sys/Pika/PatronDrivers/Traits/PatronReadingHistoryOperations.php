<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
 *
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

/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */

/**
 * Trait PatronReadingHistoryOperations
 *
 * Defines all the patron-related actions needed to handle Reading History through the ILS
 */
trait PatronReadingHistoryOperations {

	/**
	 * Return whether or not the ILS has it's own Reading History functions.
	 * Pika will attempt to integrate with them if they exist
	 *
	 * @return boolean
	 */
	public abstract function hasNativeReadingHistory();

	/**
	 * Method to get a patron's existing reading history in the ILS.
	 * This method is meant to be used by the Pika cron process to do the initial load
	 * of a patron's reading history.
	 *
	 * Since Reading Histories can be quite large this is meant to be called multiple times to fetch pieces of the reading
	 * successively until the entire history is fetched.
	 *
	 * @param User     $patron         The patron to fetch reading history entries for from the ILS
	 * @param null|int $loadAdditional The number of the next round needed for fetching more entries
	 * @return  array                 An array with the following keys
	 *                                titles  - An array of reading history entries
	 *                                nextRound - The number of the next round of entries to fetch
	 */
	public abstract function loadReadingHistoryFromIls($patron, $loadAdditional = null);

	/**
	 * Opt the patron into Reading History within the ILS.
	 *
	 * @param User $patron
	 * @return boolean $success  Whether or not the opt-in action was successful
	 */
	public abstract function optInReadingHistory($patron);

	/**
	 * Opt out the patron from Reading History within the ILS.
	 *
	 * @param  User $patron
	 * @return boolean $success  Whether or not the opt-out action was successful
	 */
	public abstract function optOutReadingHistory($patron);

	/**
	 * Delete all Reading History within the ILS for the patron.
	 *
	 * @param  User $patron
	 * @return boolean $success  Whether or not the delete all action was successful
	 */
	// As currently implemented, Pika doesn't delete reading history entries in the ILS
	//TODO: discuss and document whether we should or not in fact delete reading history in the ILS
//	public abstract function deleteAllReadingHistory($patron);

	/**
	 * Delete selected items from reading history
	 *
	 * @param User  $patron
	 * @param array $selectedTitles
	 * @return mixed
	 */
	// As currently implemented, Pika doesn't delete reading history entries in the ILS
	//TODO: discuss and document whether we should or not in fact delete reading history in the ILS
//	public abstract function deleteMarkedReadingHistory($patron, $selectedTitles);

	/**
	 * Route reading history actions to the appropriate function according to the readingHistoryAction
	 * URL parameter which will be one of:
	 *
	 * deleteAll    -> deleteAllReadingHistory
	 * deleteMarked -> deleteMarkedReadingHistory
	 * optIn        -> optInReadingHistory
	 * optOut       -> optOutReadingHistory
	 *
	 * @param  User   $patron
	 * @param  string $action One of the following; deleteAll, deleteMarked, optIn, optOut
	 * @return mixed
	 * @deprecated
	 */
//	public abstract function doReadingHistoryAction($patron, $action, $selectedTitles);

}
