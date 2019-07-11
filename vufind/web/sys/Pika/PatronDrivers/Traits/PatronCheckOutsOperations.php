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
 * Trait PatronCheckOutsOperations
 *
 * Defines all the Patron-related actions needs to manage checkouts through the ILS
 */
trait PatronCheckOutsOperations {


	/**
	 * Get Patron Checkouts
	 *
	 * This is responsible for retrieving all checked out titles for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 *
	 * @return MyCheckOut[] An array of the patron's MyCheckOut objects
	 * @access  public
	 */
	public abstract function getMyCheckouts($patron);


	/**
	 * Renew a checked out title for the patron
	 *
	 * @param User   $patron      The User to renew the item for
	 * @param string $renewItemId The ID needed to renew the Item
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  itemId  - the Id of the item that was renewed
	 * @access  public
	 */
	public abstract function renewItem($patron, $renewItemId);

}


/**
 * Class MyCheckOut
 *
 * Basic Object to define the properties of a patron's Checked Out Item.
 * This class should be as ILS-agnostic as possible.
 */
class MyCheckOut {

	public $id;
	public $source;

	public $canrenew;
	public $renewCount;
	public $renewIndicator;  // Probably the ID needed to renew the title.  TODO: refactor this name
	public $renewmessage;    // TODO: this is an obsolete variable in the template that needs to be removed. Reflected an renew action not through AJAX
	public $renew_message;   // Text that will be displayed in place of the renew button, explaining why the title can
	// not be renewed.  Currently only used in the ByWater Koha driver, for automatic renewals

	public $recordId;
	public $itemId;

	public $userId;
	public $user;  // string - The name of the user this check out belongs to

	public $link;
	public $coverUrl;

	public $title;
	public $title2;
	public $volume;
	public $author; // Can be an array of strings

	public $checkoutdate;
	public $dueDate;
	public $overdue;
	public $daysUntilDue;

	public $fine;

	public $format;
	public $barcode;

	public $groupedWorkId;
	public $ratingData;

	public $holdQueLength;  // The length of the hold queue on the title (Quirky information requested by WCPL)
}