<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 11/13/2019
 *
 */


namespace Pika;


/**
 * Class MyCheckOut
 *
 * Basic Object to define the properties of a patron's Checked Out Item.
 * This class should be as ILS-agnostic as possible.
 */
class MyCheckOut {

	public $id;
	public $source;

	public $canrenew; //TODO camel case it please
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

	public $checkoutdate; //TODO camel case it please
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