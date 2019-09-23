<?php
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
	 * Method to extract a patron's existing reading history in the ILS.
	 * This method is meant to be used by the Pika cron process to do the initial load
	 * of a patron's reading history.
	 *
	 * Since Reading Histories can be quite large this is meant to be called multiple times to fetch pieces of the reading
	 * successively until the entire history is fetched.
	 *
	 * @param User     $patron         The patron to fetch reading history entries for from the ILS
	 * @param null|int $loadAdditional The number of the next round needed for fetching more entries
	 *
	 * @return  array                 An array with the following keys
	 *                                titles  - An array of reading history entries
	 *                                nextRound - The number of the next round of entries to fetch
	 */
	public abstract function loadReadingHistoryFromIls($patron, $loadAdditional = null);
	//TODO: Make explicit definition of the elements of a reading history entry.

	/**
	 * Opt the patron into Reading History within the ILS.
	 *
	 * @param User $patron
	 *
	 * @return boolean $success  Whether or not the opt-in action was successful
	 */
	public abstract function optInReadingHistory($patron);

	/**
	 * Opt out the patron from Reading History within the ILS.
	 *
	 * @param User $patron
	 *
	 * @return boolean $success  Whether or not the opt-out action was successful
	 */
	public abstract function optOutReadingHistory($patron);

	/**
	 * Delete all Reading History within the ILS for the patron.
	 *
	 * @param User $patron
	 *
	 * @return boolean $success  Whether or not the delete all action was successful
	 */
	public abstract function deleteAllReadingHistory($patron);

}
