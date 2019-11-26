<?php
/**
 *  Class for defining the properties for a Booking
 *  A Booking is akin to a hold with a scheduled time component
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 11/21/2019
 *
 */


namespace Pika\PatronDrivers;


class MyBooking {
	public $id;
	public $title;
	public $sortTitle;
	public $startDateTime; // timestamp
	public $endDateTime;   // timestamp
	public $status;
	public $cancelName;
	public $cancelValue;

	// These are set when the MARC record is available
	public $author;
	public $format;
	public $linkUrl;
	public $coverUrl;

	// These are set when there is Grouped Work information
	public $groupedWorkId;
	public $ratingData;

	// These are set in the CatalogConnection
	public $userId;
	public $userDisplayName;


}