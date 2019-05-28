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
 * Interface PatronDriverInterface
 *
 * This interface defines the methods needed for all patron-related interactions that happen between
 * Pika and the ILS.
 *
 * Within the extension of this interface you need to include the traits your driver will integrate :
 *
 *  PatronHoldsOperations
 *  PatronCheckOutsOperations
 *  PatronBookingsOperations
 *  PatronFineOperations
 *  PatronReadingHistoryOperations
 *  PatronSelfRegistrationOperations
 *  PatronPinOperations
 *
 */
abstract class PatronDriverInterface {

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param string  $username        The patron username
	 * @param string  $password        The patron password
	 * @param boolean $validatedViaSSO True if the patron has already been validated via SSO.  If so we don't need to validation, just retrieve information
	 *
	 * @return  null|User|PEAR_Error   The User object for the patron
	 *                                 If an error occurs, return a PEAR_Error
	 * @access  public
	 */

	public abstract function patronLogin($username, $password, $validatedViaSSO);


	/**
	 * Update the User's information in Pika from data in the ILS
	 *
	 * @param User    $patron               The User Object to make updates to
	 * @param boolean $canUpdateContactInfo Permission check that updating is allowed
	 *
	 * @return array                        Array of error messages for errors that occurred while updating the user's
	 *                                      information in Pika
	 */
	public abstract function updatePatronInfo($patron, $canUpdateContactInfo);

	/**
	 *  Whether or not the ILS has a 'username' field that can be substituted for the login field.
	 * The default implementation is that it does not have the username field
	 *
	 * @return bool
	 */
	public function hasUsernameField(){
		return false;
	}

}