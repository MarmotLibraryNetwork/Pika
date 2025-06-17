<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
 * Created by PhpStorm.
 * User: pbrammeier
 * Date: 7/16/2015
 * Time: 2:01 PM
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
class MyAccount_Bookings extends MyAccount {

	function launch() {
		global $interface;
		global $library;
		$user = UserAccount::getLoggedInUser();
		$sierraUserId = $user->ilsUserId;



//		// Get Booked Items
//		$bookings = $user->getMyBookings();
//		$interface->assign('recordList', $bookings);

		if (!empty($sierraUserId)){
			// Work-around for the fact that we can not screen scrape the classic interface anymore for bookings
			// (This largely follows the logic in setClassicViewLinks() in Record_Record )
			$catalogConnection = CatalogFactory::getCatalogConnectionInstance();// This will use the $activeRecordIndexingProfile to get the catalog connector
			if (!empty($catalogConnection->accountProfile->vendorOpacUrl)){
				global $searchSource;
				$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
				$searchLocation     = Location::getSearchLocation($searchSource);
				if (!empty($searchLocation->ilsLocationId)){
					$sierraOpacScope = $searchLocation->ilsLocationId;
				}else{
					$sierraOpacScope = !empty($library->scope) ? $library->scope : (empty($configArray['OPAC']['defaultScope']) ? '93' : $configArray['OPAC']['defaultScope']);
				}
				$classicUrl = $classicOpacBaseURL . "/patroninfo~{$sierraOpacScope}/$sierraUserId/bookings";
				$interface->assign('classicBookingUrl', $classicUrl);
			}
		}


		// Additional Template Settings
		if ($library->showLibraryHoursNoticeOnAccountPages) {
			$libraryHoursMessage = Location::getLibraryHoursMessage($user->homeLocationId);
			$interface->assign('libraryHoursMessage', $libraryHoursMessage);
		}

		// Build Page //
		$this->display('bookings.tpl', 'My Scheduled Items');
	}
}
