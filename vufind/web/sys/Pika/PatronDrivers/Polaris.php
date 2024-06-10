<?php

/**
 *
 * Class Sierra
 *
 * Methods needed for completing patron actions in the Polaris ILS
 *
 * This class implements the Polaris API for patron interactions:
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date      6-10-2024
 *
 */
namespace Pika\PatronDrivers;

class Polaris implements \DriverInterface
{

    /**
     * @inheritDoc
     */
    public function __construct($accountProfile)
    {
    }

    public function patronLogin($username, $password, $validatedViaSSO)
    {
        // TODO: Implement patronLogin() method.
    }

    /**
     * @inheritDoc
     */
    public function hasNativeReadingHistory()
    {
        // TODO: Implement hasNativeReadingHistory() method.
    }

    /**
     * @inheritDoc
     */
    public function getNumHoldsOnRecord($id)
    {
        // TODO: Implement getNumHoldsOnRecord() method.
    }

    /**
     * @inheritDoc
     */
    public function getMyCheckouts($patron)
    {
        // TODO: Implement getMyCheckouts() method.
    }

    /**
     * @inheritDoc
     */
    public function renewItem($patron, $recordId, $itemId, $itemIndex)
    {
        // TODO: Implement renewItem() method.
    }

    /**
     * @inheritDoc
     */
    public function getMyHolds($patron)
    {
        // TODO: Implement getMyHolds() method.
    }

    /**
     * @inheritDoc
     */
    public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null)
    {
        // TODO: Implement placeHold() method.
    }

    /**
     * @inheritDoc
     */
    function placeItemHold($patron, $recordId, $itemId, $pickupBranch)
    {
        // TODO: Implement placeItemHold() method.
    }

    /**
     * @inheritDoc
     */
    function cancelHold($patron, $recordId, $cancelId)
    {
        // TODO: Implement cancelHold() method.
    }

    function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate)
    {
        // TODO: Implement freezeHold() method.
    }

    function thawHold($patron, $recordId, $itemToThawId)
    {
        // TODO: Implement thawHold() method.
    }

    function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation)
    {
        // TODO: Implement changeHoldPickupLocation() method.
    }
}