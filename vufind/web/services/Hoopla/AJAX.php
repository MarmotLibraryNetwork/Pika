<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 1/8/2018
 *
 */

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/services/AJAX/MARC_AJAX_Basic.php';
require_once ROOT_DIR . '/services/SourceAndId.php';

class Hoopla_AJAX extends AJAXHandler {

	use MARC_AJAX_Basic;

	protected $methodsThatRespondWithJSONUnstructured = array(
		'reloadCover',
		'getHooplaCheckOutPrompt',
		'checkOutHooplaTitle',
		'returnHooplaTitle',
	);

	protected $methodsThatRespondThemselves = array(
		'downloadMarc',
	);


	/**
	 * @return array
	 */
	function getHooplaCheckOutPrompt(){
		$user   = UserAccount::getLoggedInUser();
		$fullId = new SourceAndId($_REQUEST['id']);
		$id     = $fullId->getRecordId();
		if ($user){
			$hooplaUsers = $user->getRelatedHooplaUsers();

			if ($id){
				require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
				$driver = new HooplaDriver();

				global $interface;
				$interface->assign('hooplaId', $id);

				//TODO: need to determine what happens to cards without a Hoopla account
				$hooplaUserStatuses = array();
				foreach ($hooplaUsers as $tmpUser){
					$checkOutStatus                   = $driver->getHooplaPatronStatus($tmpUser);
					$hooplaUserStatuses[$tmpUser->id] = $checkOutStatus;
				}

				if (count($hooplaUsers) > 1){
					$interface->assign('hooplaUsers', $hooplaUsers);
					$interface->assign('hooplaUserStatuses', $hooplaUserStatuses);

					return
						array(
							'title'   => 'Hoopla Check Out',
							'body'    => $interface->fetch('Hoopla/ajax-hoopla-checkout-prompt.tpl'),
							'buttons' => '<button class="btn btn-primary" type= "button" title="Check Out" onclick="return VuFind.Hoopla.checkOutHooplaTitle(\'' . $id . '\');">Check Out</button>',
						);
				}elseif (count($hooplaUsers) == 1){
					/** @var User $hooplaUser */
					$hooplaUser = reset($hooplaUsers);
					if ($hooplaUser->id != $user->id){
						$interface->assign('hooplaUser', $hooplaUser); // Display the account name when not using the main user
					}
					$checkOutStatus = $hooplaUserStatuses[$hooplaUser->id];
					if (!$checkOutStatus){
						require_once ROOT_DIR . '/RecordDrivers/HooplaRecordDriver.php';
						$hooplaRecord          = new HooplaRecordDriver($fullId);
						$accessLink            = reset($hooplaRecord->getAccessLink()); // Base Hoopla Title View Url
						$hooplaRegistrationUrl = $accessLink['url'];
						$hooplaRegistrationUrl .= (parse_url($hooplaRegistrationUrl, PHP_URL_QUERY) ? '&' : '?') . 'showRegistration=true'; // Add Registration URL parameter

						return
							array(
								'title'   => 'Create Hoopla Account',
								'body'    => $interface->fetch('Hoopla/ajax-hoopla-single-user-checkout-prompt.tpl'),
								'buttons' =>
									'<button id="theHooplaButton" class="btn btn-default" type="button" title="Check Out" onclick="return VuFind.Hoopla.checkOutHooplaTitle(\'' . $id . '\', ' . $hooplaUser->id . ');">I registered, Check Out now</button>'
									. '<a class="btn btn-primary" role="button" href="' . $hooplaRegistrationUrl . '" target="_blank" title="Register at Hoopla" onclick="$(\'#theHooplaButton+a,#theHooplaButton\').toggleClass(\'btn-primary btn-default\');">Register at Hoopla</a>',
							);

					}
					if ($hooplaUser->hooplaCheckOutConfirmation){
						$interface->assign('hooplaPatronStatus', $checkOutStatus);
						return
							array(
								'title'   => 'Confirm Hoopla Check Out',
								'body'    => $interface->fetch('Hoopla/ajax-hoopla-single-user-checkout-prompt.tpl'),
								'buttons' => '<button class="btn btn-primary" type="button" title="Check Out" onclick="return VuFind.Hoopla.checkOutHooplaTitle(\'' . $id . '\', ' . $hooplaUser->id . ');">Check Out</button>',
							);
					}else{
						// Go ahead and checkout the title
						return array(
							'title'   => 'Checking out Hoopla title',
							'body'    => '<script>VuFind.Hoopla.checkOutHooplaTitle(\'' . $id . '\', ' . $hooplaUser->id . ')</script>',
							'buttons' => '',
						);
					}
				}else{
					// No Hoopla Account Found, give the user an error message
					$invalidAccountMessage = translate('hoopla_invalid_account_or_library');
					global $logger;
					$logger->log('No valid Hoopla account was found to check out a Hoopla title.', PEAR_LOG_ERR);
					return
						array(
							'title'   => 'Invalid Hoopla Account',
							'body'    => '<p class="alert alert-danger">' . $invalidAccountMessage . '</p>',
							'buttons' => '',
						);
				}
			} else {
				return
					array(
						'title'   => 'Invalid Hoopla ID',
						'body'    => '<p class="alert alert-danger">Invalid Hoopla Id provided.</p>',
						'buttons' => '',
					);
			}
		}else{
			return
				array(
					'title'   => 'Error',
					'body'    => 'You must be logged in to checkout an item.'
						. '<script>Globals.loggedIn = false;  VuFind.Hoopla.getHooplaCheckOutPrompt(\'' . $id . '\')</script>',
					'buttons' => '',
				);
		}

	}

	function checkOutHooplaTitle(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				global $interface;
				if ($patron->id != $user->id){
					$interface->assign('hooplaUser', $patron); // Display the account name when not using the main user
				}

				$sourceAndId = new SourceAndId($_REQUEST['id']);
				require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
				$driver = new HooplaDriver();
				$result = $driver->checkoutHooplaItem($sourceAndId, $patron);
				if (!empty($_REQUEST['stopHooplaConfirmation'])) {
					$patron->hooplaCheckOutConfirmation = false;
					$patron->update();
				}
				if ($result['success']){
					$checkOutStatus = $driver->getHooplaPatronStatus($patron);
					$interface->assign('hooplaPatronStatus', $checkOutStatus);
					$title = empty($result['title']) ? "Title checked out successfully" : $result['title'] . " checked out successfully";
					return array(
						'success' => true,
						'title'   => $title,
						'message' => $interface->fetch('Hoopla/hoopla-checkout-success.tpl'),
						'buttons' => '<a class="btn btn-primary" href="/MyAccount/CheckedOut" role="button">View My Check Outs</a>',
					);
				}else{
					return $result;
				}
			}else{
				return array('success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to checkout titles for that user.');
			}
		}else{
			return array('success' => false, 'message' => 'You must be logged in to checkout an item.');
		}
	}

	function returnHooplaTitle(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron) {
				$sourceAndID = new HooplaSourceAndId($_REQUEST['id']);
				require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
				$driver = new HooplaDriver();
				$result = $driver->returnHooplaItem($sourceAndID, $patron);
				return $result;
			}else{
				return array('success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to return titles for that user.');
			}
		}else{
			return array('success' => false, 'message' => 'You must be logged in to return an item.');
		}
	}

}

/**
 * Class HooplaSourceAndID  Modified the default source and ID handler so that the
 * assumed source if none is provided is hoopla
 */
class HooplaSourceAndID extends SourceAndId {
	static $defaultSource = 'hoopla';
}