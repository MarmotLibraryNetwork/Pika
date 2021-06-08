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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';

use Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory;

class Admin_OverDriveAPIData extends Admin_Admin {

	function launch(){
//		require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
		$driver = OverDriveDriverFactory::getDriver();

		$overdriveAccounts = $driver->getOverDriveAccountIds();

		if (!empty($overdriveAccounts)){
			$contents = '';
			foreach ($overdriveAccounts as $sharedCollectionId => $overdriveAccountId){
				$libraryInfo = $driver->getLibraryAccountInformation($overdriveAccountId);
				if ($libraryInfo){
					if (empty($_REQUEST['id'])){
						$contents    .= "<h3>Main - {$libraryInfo->name}</h3>";
						$contents    .= "<p>Shared Collection Id : $sharedCollectionId</p>";
						$contents    .= "Website: <a href='{$libraryInfo->links->dlrHomepage->href}'>{$libraryInfo->links->dlrHomepage->href}</a>";
						$productInfo = $driver->getProductsInAccount($libraryInfo->collectionToken, null, 0, 1);
						if (!empty($productInfo->totalItems)){
							$contents .= "<p>Total Items: {$productInfo->totalItems}</p>";
						}
						$contents .= $this->easy_printr('Library Account Information', "libraryAccountInfo_{$libraryInfo->collectionToken}", $libraryInfo);
					}

					$hasAdvantageAccounts = false;
					try {
						$advantageAccounts    = $driver->getAdvantageAccountInformation($overdriveAccountId);
						$hasAdvantageAccounts = !empty($advantageAccounts) && !isset($advantageAccounts->errorCode);
						if (empty($_REQUEST['id'])){
							if ($hasAdvantageAccounts){
								$contents .= "<h4>Advantage Accounts</h4>";
								foreach ($advantageAccounts->advantageAccounts as $accountInfo){
									$contents    .= $accountInfo->name . ' - ' . $accountInfo->collectionToken . '<br>';
									$productInfo = $driver->getProductsInAccount($accountInfo->collectionToken, null, 0, 1);
									if (!empty($productInfo->totalItems)){
										$contents .= "<p>Total Items: {$productInfo->totalItems}</p>";
									}
								}
							}else{
								$contents .= '<div class="alert alert-warning">No advantage accounts for this collection</div>';
							}
						}
					} catch (Exception $e){
						$contents .= '<div class="alert alert-danger">Error retrieving Advantage Info</div>';
					}

					if (!empty($_REQUEST['id'])){
						$overDriveId = trim($_REQUEST['id']);
						$productKey  = $libraryInfo->collectionToken;
						if (empty($_REQUEST['formAction'])){
							$_REQUEST['formAction'] = 'Product'; // If an id is supplied in the url but not an action assume Product Response call
						}

						if ($_REQUEST['formAction'] == 'Product'){
							$contents .= "<h3>Product</h3>";
							$contents .= "<h4>Product for $overDriveId</h4>";
							$searchResponse = $driver->getProductById($overDriveId, $productKey);
							if ($searchResponse){
								if (!empty($searchResponse->contentDetails[0]->href)){
									$siteTitleUrl = $searchResponse->contentDetails[0]->href;
									$contents .= '<a href="'.$siteTitleUrl .'">OverDrive Site Title Page</a><br>';
								}
								$contents .= $this->easy_printr("Product - {$libraryInfo->name} shared collection", "product_{$overDriveId}_{$productKey}", $searchResponse);
							}else{
								$contents .= ("No product<br>");
							}
							if ($hasAdvantageAccounts){
								foreach ($advantageAccounts->advantageAccounts as $accountInfo){
									$contents .= ("<h4>Product - {$accountInfo->name}</h4>");
									$searchResponse = $driver->getProductById($overDriveId, $accountInfo->collectionToken);
									if ($searchResponse){
										$contents .= $this->easy_printr("Product response", "product_{$overDriveId}_{$accountInfo->collectionToken}", $searchResponse);
									}else{
										$contents .= ("No product<br>");
									}
								}
							}
						}elseif ($_REQUEST['formAction'] == 'Metadata'){
							$contents .= "<h3>Metadata</h3>";
							$contents .= "<h4>Metadata for $overDriveId</h4>";
							$searchResponse = $driver->getProductMetadata($overDriveId, $productKey);
							if ($searchResponse){
								$contents .= $this->easy_printr("Metadata - {$libraryInfo->name} shared collection", "metadata_{$overDriveId}_{$productKey}", $searchResponse);
							}else{
								$contents .= ("No searchResponse<br>");
							}
							if ($hasAdvantageAccounts){
								foreach ($advantageAccounts->advantageAccounts as $accountInfo){
									$contents .= ("<h4>Metadata - {$accountInfo->name}</h4>");
									$searchResponse = $driver->getProductMetadata($overDriveId, $accountInfo->collectionToken);
									if ($searchResponse){
										$contents .= $this->easy_printr("Metadata response", "metadata_{$overDriveId}_{$accountInfo->collectionToken}", $searchResponse);
									}else{
										$contents .= ("No searchResponse<br>");
									}
								}
							}
						}elseif ($_REQUEST['formAction'] == 'Availability'){
							$contents     .= "<div class='col-tn-12'><h3>Availability</h3></div>";
							$contents     .= ("<h4>Availability - Main collection: {$libraryInfo->name}</h4>");
							$availability = $driver->getProductAvailability($overDriveId, $productKey);
							if ($availability && !isset($availability->errorCode)){
								$contents .= $this->buildAvailabilityHTMLtable($availability);
								$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$productKey}", $availability);
							}else{
								$contents .= ("Not owned<br>");
								if ($availability){
									$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$productKey}", $availability);
								}
							}
							$contents     .= "<h4>Availability - Alternate - Main collection: {$libraryInfo->name}</h4>";
							$availability = $driver->getProductAvailabilityAlt($overDriveId, $productKey);
							if ($availability && !isset($availability->errorCode) && $availability->totalItems != 0){
								$contents .= $this->buildAvailabilityHTMLtable($availability->availability[0]);
								$contents .= $this->easy_printr("Availability Alternate response", "availability_alt_{$overDriveId}_{$productKey}", $availability);
							}else{
								$contents .= ("Not owned<br>");
								if ($availability){
									$contents .= $this->easy_printr("Availability Alternate response", "availability_alt_{$overDriveId}_{$productKey}", $availability);
								}
							}


							if ($hasAdvantageAccounts){
								foreach ($advantageAccounts->advantageAccounts as $accountInfo){
									$contents     .= ("<h4>Availability - {$accountInfo->name}</h4>");
									$availability = $driver->getProductAvailability($overDriveId, $accountInfo->collectionToken);
									if ($availability && !isset($availability->errorCode)){
										//TODO: how to determine the difference between advantage and advantage plus
										$contents .= $this->buildAvailabilityHTMLtable($availability);
										$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
									}else{
										$contents .= ("Not owned<br>");
										if ($availability){
											$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
										}
									}

									$contents     .= ("<h4>Availability Alternate - {$accountInfo->name}</h4>");
									$availability = $driver->getProductAvailabilityAlt($overDriveId, $accountInfo->collectionToken);
									if ($availability && !isset($availability->errorCode) && $availability->totalItems != 0){
										$contents .= $this->buildAvailabilityHTMLtable($availability->availability[0]);
										$contents .= $this->easy_printr("Availability Alternate response", "availability_alt_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
									}else{
										$contents .= ("Not owned<br>");
										if ($availability){
											$contents .= $this->easy_printr("Availability Alternate response", "availability_alt_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
										}
									}
								}
							}
						}elseif ($_REQUEST['formAction'] == 'Magazine Issues'){
							$contents .= "<h3>Magazine Issues</h3>";
							$contents .= "<h4>Issues for $overDriveId</h4>";
							$issuesData = $driver->getIssuesData($overDriveId, $productKey);
							if ($issuesData){
								$contents .= $this->easy_printr("Issues - {$libraryInfo->name} shared collection", "issues_{$overDriveId}_{$productKey}", $issuesData);
							}else{
								$contents .= ("No magazine issues found.<br>");
							}
							if ($hasAdvantageAccounts){
								foreach ($advantageAccounts->advantageAccounts as $accountInfo){
									$contents .= ("<h4>Issues for - {$accountInfo->name}</h4>");
									$issuesData = $driver->getIssuesData($overDriveId, $accountInfo->collectionToken);
									if ($issuesData){
										$contents .= $this->easy_printr("Metadata response", "issues_{$overDriveId}_{$accountInfo->collectionToken}", $issuesData);
									}else{
										$contents .= ("No magazine issues found.<br>");
									}
								}
							}
						}elseif ($_REQUEST['formAction'] == 'Search CrossRefId'){
							$searchResponse = $driver->searchAPI($productKey, $overDriveId); //cross Ref Id
							if ($searchResponse){
								$contents .= '<br>' . $this->easy_printr("Search - {$libraryInfo->name} shared collection", "search_{$overDriveId}_{$productKey}", $searchResponse);
							}else{
								$contents .= ('<br>' . "No search Response<br>");
							}
						}

					}

				}else{
					$contents .= '<div class="alert alert-danger">Failed to get library information for OverDrive.</div>';
				}
				$contents .= '<hr>';
			}

		}else{
			$contents = '<div class="alert alert-danger">No Overdrive Account is set.</div>';
		}

		global $interface;
		$interface->assign('overDriveAPIData', $contents);
		$this->display('overdriveApiData.tpl', 'OverDrive API Data');
	}

	function easy_printr($title, $section, &$var){
		$contents = "<a onclick='$(\"#{$section}\").toggle();return false;' href='#'>{$title}</a>";
		$contents .= "<pre style='display:none' id='{$section}'>";
		$contents .= htmlspecialchars(json_encode($var, JSON_PRETTY_PRINT));  // Display as JSON
//		$contents .= print_r($var, true);
		$contents .= '</pre>';
		return $contents;
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'cataloging'];
	}

	/**
	 * @param $availability object JSON availability object from availability response
	 * @return string
	 */
	function buildAvailabilityHTMLtable($availability){
		global $interface;
		$interface->assign("availability", $availability);
		$contents = $interface->fetch("Admin/OverDriveAPIInfoAvailabilityTable.tpl");
//		$contents = ("Copies Owned (Shared + Advantage): {$availability->copiesOwned}<br>");
//		$contents .= ("Available Copies (Shared + Advantage): {$availability->copiesAvailable}<br>");
//		$contents .= ("Num Holds (Shared + Advantage): {$availability->numberOfHolds}<br>");
		return $contents;

	}
}
