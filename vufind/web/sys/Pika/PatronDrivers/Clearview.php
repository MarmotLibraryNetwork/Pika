<?php
/**
 *
 * Class Clearview
 *
 * Methods needed for completing Clearview Library Network actions in the Polaris ILS
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date      6-10-2024
 *
 */


namespace Pika\PatronDrivers;
class Clearview extends Polaris
{
    public array $ereceipt_options = [
        0 => "None",
        2 => "Email",
        8 => "Text Message",
        100 => "Email and Text Message",
    ];

    public array $notification_options = [
        2 => "Email",
        8 => "Text Message",
    ];
    
}
