<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 11/21/2019
 *
 */


namespace Pika;


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