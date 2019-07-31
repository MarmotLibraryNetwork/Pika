<?php
/**
 * Allow the user to select an interface to use to access the site.
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 5/8/13
 * Time: 2:32 PM
 */

class MyAccount_SelectInterface extends Action {
	function launch(){
		global $interface;
		global $logger;
		global $configArray;

		$libraries = array();
		$library   = new Library();
		$library->orderBy('displayName');
		$library->find();
		while ($library->fetch()){
			$libraries[$library->libraryId] = array(
				'id'          => $library->libraryId,
				'displayName' => $library->displayName,
				'subdomain'   => $library->subdomain,
			);
		}
		$interface->assign('libraries', $libraries);

		global $locationSingleton;
		$physicalLocation = $locationSingleton->getActiveLocation();
		$redirectLibrary  = null;
		$user             = UserAccount::getLoggedInUser();
		if (!empty($_REQUEST['library']) && ctype_digit($_REQUEST['library'])){
			$redirectLibrary = $_REQUEST['library'];
		}elseif (!is_null($physicalLocation)){
			$redirectLibrary = $physicalLocation->libraryId;
		}elseif ($user && isset($user->preferredLibraryInterface) && is_numeric($user->preferredLibraryInterface)){
			$redirectLibrary = $user->preferredLibraryInterface;
		}elseif (isset($_COOKIE['PreferredLibrarySystem'])){
			$redirectLibrary = $_COOKIE['PreferredLibrarySystem'];
		}

		if ($redirectLibrary != null){
			$logger->log("Selected library $redirectLibrary", PEAR_LOG_DEBUG);
			$selectedLibrary = $libraries[$redirectLibrary];
			$subDomain       = $selectedLibrary['subdomain'];
			$urlPortions     = parse_url($configArray['Site']['url']);
			$restOfHostName  = strstr($urlPortions['host'], '.');
			if (strpos($urlPortions['host'], '2') > 0){
				$subDomain .= '2';  // Marmot test url handling
			}

			// Build new URL to redirect to
			$baseUrl = $urlPortions['scheme'] . '://' . $subDomain . $restOfHostName;

			if (!empty($_REQUEST['gotoModule'])){
				$gotoModule = $_REQUEST['gotoModule'];
				$interface->assign('gotoModule', $gotoModule);
				$baseUrl .= '/' . $gotoModule;
			}
			if (!empty($_REQUEST['gotoAction'])){
				$gotoAction = $_REQUEST['gotoAction'];
				$interface->assign('gotoAction', $gotoAction);
				$baseUrl .= '/' . $gotoAction;
			}

			if (isset($_REQUEST['rememberThis']) && isset($_REQUEST['submit'])){
				if ($user){
					$user->preferredLibraryInterface = $redirectLibrary;
					$user->update();
					$_SESSION['userinfo'] = serialize($user);
				}
				//Set a cookie to remember the location when not logged in
				//Remember for a year
				setcookie('PreferredLibrarySystem', $redirectLibrary, time() + 60 * 60 * 24 * 365, '/');
			}

			header('Location:' . $baseUrl);
			die;
		}

		$this->display('selectInterface.tpl', 'Select Library Catalog', false);
	}
}