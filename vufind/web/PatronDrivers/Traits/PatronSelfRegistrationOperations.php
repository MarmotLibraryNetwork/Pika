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
 * Trait PatronSelfRegistrationOperations
 *
 * Defines all the patron-related actions needed to handle Self-Registration through the ILS
 */
trait PatronSelfRegistrationOperations {


	/**
	 * Send a Self Registration request to the ILS.  The relevant input should be taken from the $_REQUEST variable.
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  barcode - the barcode for the self-registered user
	 */
	public abstract function selfRegister();

	/**
	 * @return array  An Object structure array to build the fields for the Self-Registration form.
	 */
	public abstract function getSelfRegistrationFields();
}