<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 10/19/2016
 *
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_Masquerade extends MyAccount {
	// When username & password are passed as POST parameters, index.php will automatically attempt to login the user
	// When the parameters aren't passed and there is no user logged in, MyAccount::__construct will prompt user to login,
	// with a followup action back to this class


	function launch(){
		$result = $this->initiateMasquerade();
		if ($result['success']){
			header('Location: /MyAccount/Home');
			session_commit();
			exit();
		}else{
			// Display error and embedded Masquerade As Form
			global $interface;
			$interface->assign('error', $result['error']);
			$this->display('masqueradeAs.tpl', 'Masquerade');
		}
	}

	static function initiateMasquerade(){

		global $library;
		if (!empty($library) && $library->allowMasqueradeMode){
			if (!empty($_REQUEST['cardNumber'])){

				$libraryCard = $_REQUEST['cardNumber'];
				global $guidingUser;
				if (empty($guidingUser)){
					$user = UserAccount::getLoggedInUser();
					if ($user && $user->canMasquerade()){
						$masqueradedUser = new User();
						$masqueradedUser->barcode = $libraryCard;
						if ($masqueradedUser->find(true)){
							if ($masqueradedUser->id == $user->id){
								return [
									'success' => false,
									'error'   => 'No need to masquerade as yourself.'
								];
							}
						}else{
							// Check for another ILS with a different login configuration
							$accountProfile = new AccountProfile();
							$accountProfile->groupBy('loginConfiguration');
							$numConfigurations = $accountProfile->count('loginConfiguration');
							if ($numConfigurations > 1){
								// Now that we know there is more than loginConfiguration type, check the opposite column
								$masqueradedUser = new User();
								$masqueradedUser->barcode = $libraryCard;
								$masqueradedUser->find(true);
							}

							if ($masqueradedUser->N == 0){
								// Test for a user that hasn't logged into Pika before
								$masqueradedUser = UserAccount::findNewUser($libraryCard);
								if (!$masqueradedUser){
									return [
										'success' => false,
										'error'   => 'Invalid User'
									];
								}
							}
						}

						// Now that we have found the masqueraded User, check Masquerade Levels
						if ($masqueradedUser){
							//Check for errors
							switch ($user->getMasqueradeLevel()){
								case 'location' :
									if (empty($user->homeLocationId)){
										return [
											'success' => false,
											'error'   => 'Could not determine your home library branch.'
										];
									}
									if (empty($masqueradedUser->homeLocationId)){
										return [
											'success' => false,
											'error'   => 'Could not determine the patron\'s home library branch.'
										];
									}
									if ($user->homeLocationId != $masqueradedUser->homeLocationId){
										return [
											'success' => false,
											'error'   => 'You do not have the same home library branch as the patron.'
										];
									}
									break;
								case 'library' :
									$guidingUserLibrary = $user->getHomeLibrary();
									if (!$guidingUserLibrary){
										return [
											'success' => false,
											'error'   => 'Could not determine your home library.'
										];
									}
									$masqueradedUserLibrary = $masqueradedUser->getHomeLibrary();
									if (!$masqueradedUserLibrary){
										return [
											'success' => false,
											'error'   => 'Could not determine the patron\'s home library.'
										];
									}
									if ($guidingUserLibrary->libraryId != $masqueradedUserLibrary->libraryId){
										return [
											'success' => false,
											'error'   => 'You do not have the same home library as the patron.'
										];
									}
									break;
								case 'any' :

							}

							//Setup the guiding user and masqueraded user
							global $guidingUser;
							$guidingUser = $user;
							// NOW login in as masquerade user
							$accountProfile = $user->getAccountProfile();
							if($accountProfile->usingPins()) {
								$_REQUEST['username'] = $masqueradedUser->getBarcode();
								$_REQUEST['password'] = $masqueradedUser->getPassword();
							} else {
								$_REQUEST['username'] = $masqueradedUser->cat_username;
								$_REQUEST['password'] = $masqueradedUser->getBarcode();
							}

							$user = UserAccount::login();
							if (!empty($user) && !PEAR_Singleton::isError($user)){
								@session_start(); // (suppress notice if the session is already started)
								$_SESSION['guidingUserId'] = $guidingUser->id;
								$_SESSION['activeUserId']  = $user->id;
								return ['success' => true];
							}else{
								unset($_SESSION['guidingUserId']);
								$user = $guidingUser;
								return [
									'success' => false,
									'error'   => 'Failed to initiate masquerade as specified user.'
								];
							}
						}
					}else{
						return [
							'success' => false,
							'error'   => $user ? 'You are not allowed to Masquerade.' : 'Not logged in. Please Log in.'
						];
					}
				}else{
					return [
						'success' => false,
						'error'   => 'Already Masquerading.'
					];
				}
			}else{
				return [
					'success' => false,
					'error'   => 'Please enter a valid Library Card Number.'
				];
			}
		}else{
			return [
				'success' => false,
				'error'   => 'Masquerade Mode is not allowed.'
			];
		}
	}

	static function endMasquerade(){
		if (UserAccount::isLoggedIn()){
			global $guidingUser,
			       $masqueradeMode;
			@session_start();  // (suppress notice if the session is already started)
			unset($_SESSION['guidingUserId']);
			$masqueradeMode = false;
			if ($guidingUser){
				$accountProfile = $guidingUser->getAccountProfile();
				if($accountProfile->usingPins()) {
					$_REQUEST['username'] = $guidingUser->barcode;
					$_REQUEST['password'] = $guidingUser->getPassword();
				} else {
					$_REQUEST['username'] = $guidingUser->cat_username;
					$_REQUEST['password'] = $guidingUser->barcode;
				}
				$user = UserAccount::login();
				if ($user && !PEAR_Singleton::isError($user)){
					return ['success' => true];
				}
			}
		}
		return ['success' => false];
	}

}
