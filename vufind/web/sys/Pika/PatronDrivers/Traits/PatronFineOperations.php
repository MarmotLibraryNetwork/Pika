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
 * Trait PatronFineOperations
 *
 * Handle patron related interactions with the ILS regarding fines
 */
trait PatronFineOperations {

	/**
	 * Get Patron Fines
	 *
	 * This is responsible for retrieving all fines info for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 *
	 * @return MyFine[] An array of the patron's MyFine objects
	 * @access  public
	 */
	public abstract function getMyFines($patron);

}

/**
 * Class MyFine
 *
 * Basic Object to define the properties of a patron's Fine.
 * This class should be as ILS-agnostic as possible.
 */
class MyFine {

	public $amount;
	public $amountOutstanding;
	public $date;
	public $reason;
	public $message;

}