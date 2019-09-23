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
 * Trait PatronHoldsOperations
 *
 *  Defines all the Patron-related actions needs to manage holds through the ILS
 */
trait PatronHoldsOperations {

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 *
	 * @return MyHold[]  An array of the patron's MyHold objects
	 */
	public abstract function getMyHolds($patron);

	/**
	 * Place Hold on a Bib Record
	 *
	 * This is responsible for  placing bib level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $pickupBranch The branch where the user wants to pickup the item when available
	 * @param null|string $cancelDate   The date to cancel the Hold if it isn't filled
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display (if item holds are required, this is a form to select the item).
	 *                                  needsItemLevelHold - An indicator that item level holds are required
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null);

	/**
	 * Place an Item-level Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $itemId       The id of the item to hold
	 * @param null|string $pickupBranch The branch where the user wants to pickup the item when available
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeItemHold($patron, $recordId, $itemId, $pickupBranch);

	/**
	 * Place Volume-level Hold
	 *
	 * This is responsible for placing volume level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $volumeId     The id of the volume to hold
	 * @param null|string $pickupBranch The branch where the user wants to pickup the item when available
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch);

	/**
	 * @param User   $patron            The User to change the hold for
	 * @param string $holdId            The Id needed to change the hold's pick up location
	 * @param string $newPickupLocation The pickup location to change the hold to
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 * @access  public
	 */
	public abstract function changeHoldPickupLocation($patron, $holdId, $newPickupLocation);

	/**
	 * Cancels a hold for a patron
	 *
	 * @param User   $patron   The User to cancel the hold for
	 * @param string $cancelId Information about the hold to be cancelled
	 *
	 * @return  array
	 */
	public abstract function cancelHold($patron, $bibId, $holdId);

	/**
	 * Freezes/Suspends/Pauses a hold for a patron.
	 *
	 * Intended to prevent a hold from being fulfilled without having to cancel the hold or losing a patron's position in
	 * the hold queue unnecessarily.
	 *
	 * @param User   $patron           The User to freeze the hold for
	 * @param string $holdToFreezeId   The Id needed to freeze the hold
	 * @param string $dateToReactivate The date a hold should be automatically thawed.
	 *
	 * @return array
	 * @access  public
	 */
	public abstract function freezeHold($patron, $bibId, $holdId, $dateToReactivate = null);


	/**
	 * Unfreeze/Unsuspend/Resume a hold for a patron
	 *
	 * @param User   $patron       The User to thaw the hold for
	 * @param string $holdToThawId The Id needed to thaw the hold
	 *
	 * @return array
	 * @access  public
	 */
	public abstract function thawHold($patron, $bibId, $holdId);

}


/**
 * Class MyHold
 *
 * Basic Object to define the properties of a patron's Hold needed to display the hold on the MyHolds page.
 * This class should be as ILS-agnostic as possible.
 */
class MyHold {

	public $user;        // The name of the patron that the hold belongs to
	public $userId;      // The Pika Id number of the patron the hold belongs to

	public $id;          // The id of the hold? or the record Id?
	public $cancelId;    // The hold id needed to cancel, freeze, thaw or change pickup location of this hold
	public $cancelable;  // Whether or not the hold can be cancelled

	public $coverUrl;
	public $linkUrl;

	public $title;
	public $title2;
	public $volume;
	public $author; // Can be an array
	public $format = array();

	public $location;           // The name of the location the hold will arrive at
	public $locationUpdateable; // Whether or not the pick up location can be changed for this hold

	public $create;                 // The date the hold was placed
	public $availableTime;          //The date the hold became available for pick up
	public $expire;                 // The date an available hold will expire.  The Pick-Up By date
	public $automaticCancellation;  // The date the hold will automatically cancel if not fulfilled

	public $status;     // The status of the hold
	public $position;   //The place that the hold is in the hold queue. (eg. 4 of 24)

	public $allowFreezeHolds; // Whether or not freezing/thawing holds is allowed
	public $frozen;           // Whether or not the hold is frozen
	public $reactivate;       // The date the frozen hold will automatically thaw
	public $freezable;        // Whether or not the hold is freezable

}