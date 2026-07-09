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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class Fines extends MyAccount {
	private $currency_symbol = '$';

	function launch(){
		global $interface,
		       $configArray;

		$ils = $configArray['Catalog']['ils'];
		$interface->assign('showDate', $ils == 'Polaris' || $ils == 'Symphony' || $ils == 'Horizon' || $ils == 'Koha' || $ils == 'CarlX');
		$interface->assign('showReason', $ils != 'Koha');
		$useOutstanding = ($ils == 'Polaris' || $ils == 'Symphony' || $ils == 'Koha');
		$interface->assign('showOutstanding', $useOutstanding);

		if (UserAccount::isLoggedIn()) {
			global $offlineMode;
			if (!$offlineMode) {
				// Get My Fines
				$user = UserAccount::getLoggedInUser();
				$fines = $user->getMyFines();
				$interface->assign('userFines', $fines);
//			$minimumFineAmount = $interface->getTemplateVars('minimumFineAmount');
//			$canShowPayFineButton = false;

				// Get Account Labels, Add Up Totals
				foreach ($fines as $userId => $finesDetails) {
					$userAccountLabel[$userId] = $user->getUserReferredTo($userId)->getNameAndLibraryLabel();
					$total                     = $totalOutstanding = 0;
					foreach ($finesDetails as $fine) {
						if (!empty($fine['amount']) && $fine['amount'][0] == '-') {
							$amount = -ltrim($fine['amount'], '-' . $this->currency_symbol);
						} else {
							$amount = ltrim($fine['amount'], $this->currency_symbol);
						}
						if (is_numeric($amount)) $total += $amount;
						if ($useOutstanding && $fine['amountOutstanding']) {
							$outstanding = ltrim($fine['amountOutstanding'], $this->currency_symbol);
							if (is_numeric($outstanding)) $totalOutstanding += $outstanding;
						}
					}

//				if ($total > $minimumFineAmount) $canShowPayFineButton = true;
					$fineTotals[$userId] = $this->currency_symbol . number_format($total, 2);
//				$fineTotals[$userId] = formatNumber($total);  // formatNumber code below doesn't seem to work on $total

					if ($useOutstanding) {
//					if ($totalOutstanding > $minimumFineAmount) $canShowPayFineButton = true;
						$outstandingTotal[$userId] = $this->currency_symbol . number_format($totalOutstanding, 2);
					}

				}

				$showFinePayments = $configArray['Catalog']['showFinePayments'];
				$interface->assign('showFinePayments', $showFinePayments);

//				$homeLibrary      = Library::getLibraryForLocation($user->homeLocationId);
				$homeLibrary      = $user->getHomeLibrary();
				$showEcommerceLink = isset($homeLibrary) && $homeLibrary->showEcommerceLink == 1;

				if ($showEcommerceLink) {
					$interface->assign('minimumFineAmount',        $homeLibrary->minimumFineAmount);
					$interface->assign('payFinesLinkText',         $homeLibrary->payFinesLinkText);
					$interface->assign('showRefreshAccountButton', $homeLibrary->showRefreshAccountButton);

					// Determine E-commerce Link
					$eCommerceLink = null;
					if ($homeLibrary->payFinesLink == 'default') {
						$defaultEcommerceLink = $configArray['Site']['ecommerceLink'];
						if (!empty($defaultEcommerceLink)) {
							$eCommerceLink = $defaultEcommerceLink;
						} else {
							$showEcommerceLink = false;
						}
					} elseif (!empty($homeLibrary->payFinesLink)) {
						$eCommerceLink = $homeLibrary->payFinesLink;
					} else {
						$showEcommerceLink = false;
					}
					$interface->assign('ecommerceLink', $eCommerceLink);
				}
				$interface->assign('showEcommerceLink', $showEcommerceLink);



//			$interface->assign('canShowPayFineButton', $canShowPayFineButton);
				$interface->assign('userAccountLabel', $userAccountLabel);
				$interface->assign('fineTotals', $fineTotals);
				if ($useOutstanding) $interface->assign('outstandingTotal', $outstandingTotal);
			}
		}
		$this->display('fines.tpl', 'My Fines');
	}

}

/**
 * @param $number          number to be displayed as monetary value
 * @return mixed|string    string to be displayed
 */
function formatNumber($number){
	// money_format() does not exist on windows
	if (function_exists('money_format')){
		return money_format('%.2n', $number);
	}else{
		return safeMoneyFormat($number);
	}
}
