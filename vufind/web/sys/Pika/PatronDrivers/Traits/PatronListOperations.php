<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/11/2020
 *
 */


namespace Pika;


trait PatronListOperations {

	/**
	 * Import Lists that are stored in the ILS or classic Opac
	 *
	 * @param  User $patron
	 * @return array - an array of results including the names of the lists that were imported as well as number of titles.
	 */
	public abstract function importListsFromIls($patron);
}