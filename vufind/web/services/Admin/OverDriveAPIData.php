<?php
require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_OverDriveAPIData extends Admin_Admin
{
	function launch()
	{
		require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
		$driver = OverDriveDriverFactory::getDriver();

		$overdriveAccounts = $driver->getOverDriveAccountIds();

		if (!empty($overdriveAccounts)) {
			$contents = '';
			foreach ($overdriveAccounts as $sharedCollectionId => $overdriveAccountId) {
				$libraryInfo = $driver->getLibraryAccountInformation($overdriveAccountId);
				if ($libraryInfo) {
					$contents .= "<h3>Main - {$libraryInfo->name}</h3>";
					$contents .= "<p>Shared Collection Id : $sharedCollectionId</p>";
					$contents .= "Website: <a href='{$libraryInfo->links->dlrHomepage->href}'>{$libraryInfo->links->dlrHomepage->href}</a>";
					$productInfo = $driver->getProductsInAccount($libraryInfo->collectionToken, null, 0, 1);
					if (!empty($productInfo->totalItems)) {
						$contents .= "<p>Total Items: {$productInfo->totalItems}</p>";
					}
					$contents .= $this->easy_printr('Library Account Information', "libraryAccountInfo_{$libraryInfo->collectionToken}", $libraryInfo);

					$hasAdvantageAccounts = false;
					try {
						$advantageAccounts = $driver->getAdvantageAccountInformation($overdriveAccountId);
						$hasAdvantageAccounts = !empty($advantageAccounts) && !isset($advantageAccounts->errorCode);
						if ($hasAdvantageAccounts)  {
							$contents .= "<h4>Advantage Accounts</h4>";
							foreach ($advantageAccounts->advantageAccounts as $accountInfo) {
								$contents .= $accountInfo->name . ' - ' . $accountInfo->collectionToken . '<br/>';
								$productInfo = $driver->getProductsInAccount($accountInfo->collectionToken, null, 0, 1);
								if (!empty($productInfo->totalItems)) {
									$contents .= "<p>Total Items: {$productInfo->totalItems}</p>";
								}

							}
						} else {
							$contents .= '<div class="alert alert-warning">No advantage accounts for this collection</div>';
						}
					} catch (Exception $e) {
						$contents .= '<div class="alert alert-danger">Error retrieving Advantage Info</div>';
					}

					if (!empty($_REQUEST['id'])) {
						$overDriveId = trim($_REQUEST['id']);
						$productKey  = $libraryInfo->collectionToken;
						$contents    .= "<h3>Metadata</h3>";
						$contents    .= "<h4>Metadata for $overDriveId</h4>";
						$metadata    = $driver->getProductMetadata($overDriveId, $productKey);
						if ($metadata) {
							$contents .= $this->easy_printr("Metadata for $overDriveId in shared collection", "metadata_{$overDriveId}_{$productKey}", $metadata);
						} else {
							$contents .= ("No metadata<br/>");
						}

						if ($hasAdvantageAccounts) {
							foreach ($advantageAccounts->advantageAccounts as $accountInfo) {
								$contents .= ("<h4>Metadata - {$accountInfo->name}</h4>");
								$metadata = $driver->getProductMetadata($overDriveId, $accountInfo->collectionToken);
								if ($metadata) {
									$contents .= $this->easy_printr("Metadata response", "metadata_{$overDriveId}_{$accountInfo->collectionToken}", $metadata);
								} else {
									$contents .= ("No metadata<br/>");
								}
							}
						}

						$contents     .= "<h3>Availability</h3>";
						$contents     .= ("<h4>Availability - Main collection: {$libraryInfo->name}</h4>");
						$availability = $driver->getProductAvailability($overDriveId, $productKey);
						if ($availability && !isset($availability->errorCode)) {
							$contents .= ("Copies Owned: {$availability->copiesOwned} <br/>");
							$contents .= ("Available Copies: {$availability->copiesAvailable }<br/>");
							$contents .= ("Num Holds (entire collection): {$availability->numberOfHolds }<br/>");
							$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$productKey}", $availability);
						} else {
							$contents .= ("Not owned<br/>");
							if ($availability) {
								$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$productKey}", $availability);
							}
						}

						if ($hasAdvantageAccounts) {
							foreach ($advantageAccounts->advantageAccounts as $accountInfo) {
								$contents     .= ("<h4>Availability - {$accountInfo->name}</h4>");
								$availability = $driver->getProductAvailability($overDriveId, $accountInfo->collectionToken);
								if ($availability && !isset($availability->errorCode)) {
									//TODO: how to determine the difference between advantage and advantage plus
									$contents .= ("Copies Owned (Shared + Advantage): {$availability->copiesOwned }<br/>");
									$contents .= ("Available Copies (Shared + Advantage): {$availability->copiesAvailable }<br/>");
									$contents .= ("Num Holds (Shared + Advantage): {$availability->numberOfHolds }<br/>");
									$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
								} else {
									$contents .= ("Not owned<br/>");
									if ($availability) {
										$contents .= $this->easy_printr("Availability response", "availability_{$overDriveId}_{$accountInfo->collectionToken}", $availability);
									}
								}
							}
						}
					}

				} else {
					$contents .= '<div class="alert alert-danger">Failed to get library information for OverDrive.</div>';
				}
				$contents .= '<hr>';
			}

		} else {
			$contents = '<div class="alert alert-danger">No Overdrive Account is set.</div>';
		}

		global $interface;
		$interface->assign('overDriveAPIData', $contents);
		$this->display('overdriveApiData.tpl', 'OverDrive API Data');
	}

	function easy_printr($title, $section, &$var)
	{
		$contents = "<a onclick='$(\"#{$section}\").toggle();return false;' href='#'>{$title}</a>";
		$contents .=  "<pre style='display:none' id='{$section}'>";
		$contents .= print_r($var, true);
		$contents .= '</pre>';
		return $contents;
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'cataloging');
	}
}