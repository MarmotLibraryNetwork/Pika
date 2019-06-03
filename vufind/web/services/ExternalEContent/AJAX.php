<?php
/**
 *  Extends the AJAX Record class so that ExternalEcontent AJAX calls can be processed too.
 *
 * (This class has to exist because the parent directory causes the action loading to expect it)
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 6/2/2019
 *
 */

require_once ROOT_DIR . '/services/Record/AJAX.php';

class ExternalEContent_AJAX  extends Record_AJAX {

}