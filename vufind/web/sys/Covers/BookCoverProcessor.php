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

require_once ROOT_DIR . '/services/SourceAndId.php';

class BookCoverProcessor {

	public $error;

	private $configArray;

	private $category;
	private $format;
	private $size;
	private $upc;
	private $isn;
	private $issn;
//	private $isbn10;
//	private $isbn13;

	/** @var null|SourceAndId */
	private $sourceAndId;
	private $groupedWorkId;
	private $listId;

	/** @var null|GroupedWorkDriver */
	private $groupedWork = null;

	private $reload;
	private $bookCoverPath;
	private $cacheName;
	private $cacheFile;
	private $localFile;

	/** @var  \Monolog\Logger $logger */
	private $logger;
	private bool $doCoverLogging;
	/** @var  Timer $timer */
	private $timer;
	private bool $doTimings;

	function logTime($message){
		if ($this->doTimings){
			$this->timer->logTime($message);
		}
	}

	public function loadCover($configArray, $timer, $logger){
		$this->configArray    = $configArray;
		$this->timer          = $timer;
		$this->doTimings      = $this->configArray['System']['coverTimings'] ?? false;
		$this->logger         = $logger;
		$this->doCoverLogging = $this->configArray['System']['coverLogging'] ?? false;

		if ($this->doCoverLogging){
			$this->logger->info('Starting to load cover');
		}
		if ($this->doTimings){
			$this->timer->enableTimings(true); // Have to turn on the timer's switch as well as this one.
		}
		$this->bookCoverPath = $configArray['Site']['coverPath'];
		if (!$this->loadParameters()){
			return;
		}
		if (!$this->reload){
			if ($this->doCoverLogging){
				$this->logger->info('Looking for Cached cover');
			}
			if ($this->getCachedCover()){
				return;
			}
		}

		// Grouped work level case
		if (isset($this->groupedWorkId) && $this->getGroupedWorkCover()){
			return;
			// User List Covers
		}elseif (isset($this->listId) && $this->getUserListCover($this->listId)){
			return;
		}else{
			// Record level cases

			// Try special handling for sideloads
			// Will exit if we find a cover
			if (isset($this->sourceAndId)){
				$source = $this->sourceAndId->getSource();
				if ($source == 'overdrive'){
					if ($this->getOverDriveCover($this->sourceAndId)){
						return;
					}
				}else{
					$coverSource = $this->sourceAndId->getIndexingProfile()->coverSource;
					if ($this->loadCoverBySpecifiedSource($coverSource)){
						return;
					}
				}
			}

			// Now try outside content providers with the supplied ISN, ISBN, or UPC
			if ($this->doCoverLogging){
				$this->logger->info('Looking for cover from providers');
			}
			if ($this->getCoverFromProvider()){ // This needs to run before getGroupedWorkCover() to try any values passed via the cover url
				return;
			}

			//Finally, check the ISBNs from the MARC
			$recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId);
			if ($this->getCoverFromProviderUsingRecordDriverData($recordDriver)){
				return;
			}

			// Out of options, Now try going getting a cover from a related record of the parent grouped work
			if ($this->getGroupedWorkCover()){
				return;
			}

		}

		// Build default cover or use placeholder image
		if ($this->doCoverLogging){
			$this->logger->info('No image found, using default image');
		}
		$this->getDefaultCover();
	}

	private function loadCoverBySpecifiedSource($coverSource, SourceAndId $sourceAndId = null){
		$sourceAndId ??= $this->sourceAndId;
		switch ($coverSource){
//			case 'Zinio':
//				if ($this->getZinioCover($sourceAndId)){
//					return true;
//				}
//				break;
			case 'Colorado State Government Documents' :
				if ($this->getColoradoGovDocCover()){
					return true;
				}
				break;
			case 'Classroom Video on Demand':
				if ($this->getClassroomVideoOnDemandCover($sourceAndId)){
					return true;
				}
				break;
			case 'Films on Demand':
				if ($this->getFilmsOnDemandCover($sourceAndId)){
					return true;
				}
				break;
			case 'Proquest':
				if ($this->getEbraryCover($sourceAndId)){
					return true;
				}
				break;
			case 'CreativeBug':
				if ($this->getCreativeBugCover($sourceAndId)){
					return true;
				}
				break;
			case 'CHNC':
				if ($this->getCHNCCover($sourceAndId)){
					return true;
				}
				break;
			case 'ILS MARC':
				// Now try some special cases with ILS records
				// (Fetching a custom cover needs to take precedence over checking outside content providers)
				if ($this->getCoverFromMarc()){
					return true;
				}
				break;
			case 'SideLoad General':
			default:
				if ($this->getSideLoadedCover($sourceAndId)){
					return true;
				}
		}
		return false;

	}

	/**
	 * Check the MARC of a side loaded record for image URLs
	 *
	 * @param SourceAndId $sourceAndId
	 *
	 * @return bool
	 */
	private function getSideLoadedCover($sourceAndId){
//		require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
		$driver = RecordDriverFactory::initRecordDriverById($sourceAndId);
		if (!empty($driver) && $driver->isValid()){
			try {
				/** @var File_MARC_Data_Field[] $linkFields */
				$fileMARCRecord = $driver->getMarcRecord();
				if ($fileMARCRecord){
					$linkFields = $fileMARCRecord->getFields('856');
					foreach ($linkFields as $linkField){
						if ($linkField->getIndicator(1) == 4 && $linkField->getIndicator(2) == 2){
							$coverUrl = $linkField->getSubfield('u')->getData();
							return $this->processImageURL($coverUrl);
						}
					}
				}
			} catch (File_MARC_Exception $e){
				$this->logger->error("Marc file exception while loading cover for $sourceAndId : " . $e->getMessage());
			}

			return $this->getCoverFromProviderUsingRecordDriverData($driver);
		}
		return false;
	}

	/**
	 * Create cover image for Colorado State Government Documents
	 *
	 * @return bool
	 */
	private function getColoradoGovDocCover(){
		$this->format = "ColoradoFlag";
		if ($this->getDefaultCover(200)){
			return true;
		}else{
			$filename = "interface/themes/responsive/images/state_flag_of_colorado.png";
			if ($this->processImageURL($filename)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @param SourceAndId $sourceAndId
	 * @return bool
	 */
	private function getCHNCCover(SourceAndId $sourceAndId){
//		if ($this->getSideLoadedCover($sourceAndId)){
//			return true;
//		}
		$this->format = "Digital Newspaper";
		return $this->getDefaultCover();
	}


	/**
	 * @param SourceAndId $sourceAndId
	 *
	 * @return bool
	 */
	private function getEbraryCover(SourceAndId $sourceAndId){
		$coverId  = preg_replace('/^[a-zA-Z]+/', '', $sourceAndId->getRecordId());
		$coverUrl = "http://ebookcentral.proquest.com/covers/$coverId-l.jpg";
		if ($this->processImageURL($coverUrl)){
			return true;
		}
		return false;
	}

	private function getClassroomVideoOnDemandCover(SourceAndId $sourceAndId){
		$coverId  = preg_replace('/^10+/', '', $sourceAndId->getRecordId());
		$coverUrl = "http://cvod.infobase.com/image/$coverId";
		if ($this->processImageURL($coverUrl)){
			return true;
		}
		return false;
	}

	private function getFilmsOnDemandCover(SourceAndId $sourceAndId){
		$coverId  = preg_replace('/^10+/', '', $sourceAndId->getRecordId());
		$coverUrl = "http://fod.infobase.com/image/$coverId";
		if ($this->processImageURL($coverUrl)){
			return true;
		}
		return false;
	}

	private function getOverDriveCover(SourceAndId $sourceAndId){
		$overDriveProduct = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
		if ($overDriveProduct->get('overdriveId', $sourceAndId->getRecordId())){
			if ($overDriveProduct->mediaType == 'Magazine'){
				// Use Latest Issue if magazine
				$overDriveLatestMagazine           = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIMagazineIssues();
				$overDriveLatestMagazine->parentId = $sourceAndId->getRecordId();
				$overDriveLatestMagazine->orderBy('pubDate DESC');
				if ($overDriveLatestMagazine->find(true)){
					if (!empty($overDriveLatestMagazine->coverUrl)){
						return $this->processImageURL($overDriveLatestMagazine->coverUrl);
					}
				}
			}
			// Attempt to use Metadata for cover
			$overDriveMetadata = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductMetaData();
			if ($overDriveMetadata->get('productId', $overDriveProduct->id)){
				$coverUrl = $overDriveMetadata->cover; // full size image
				if ($coverUrl != null){
					return $this->processImageURL($coverUrl);
				}
			}
			// Use cover url provided with the title info
			$coverUrl = $overDriveProduct->cover; // Thumbnail image
			return $this->processImageURL($coverUrl);
		}
		return false;
	}

//	private function getZinioCover(SourceAndId $sourceAndId){
//		require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
//		$driver = new SideLoadedRecord($sourceAndId);
//		if ($driver && $driver->isValid()){
//			/** @var File_MARC_Data_Field[] $linkFields */
//			$linkFields = $driver->getMarcRecord()->getFields('856');
//			foreach ($linkFields as $linkField){
//				if ($linkField->getIndicator(1) == 4 && $linkField->getSubfield('3') != null && $linkField->getSubfield('3')->getData() == 'Image'){
//					$coverUrl = $linkField->getSubfield('u')->getData();
//					$coverUrl = str_replace('size=200', 'size=lg', $coverUrl);
//					return $this->processImageURL($coverUrl);
//				}
//			}
//		}
//		return false;
//	}

	/**
	 * @param SourceAndId $sourceAndId
	 *
	 * @return bool
	 */
	private function getCreativeBugCover(SourceAndId $sourceAndId){
		require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
		$driver = new SideLoadedRecord($sourceAndId);
		if ($driver->isValid()){
			$linkFields = $driver->getMarcRecord()->getFields('856');
			foreach ($linkFields as $linkField){
				if ($linkField->getIndicator(1) == 4 && $linkField->getSubfield('a')){
					$fieldData = $linkField->getSubfield('a')->getData();
					if (stripos($fieldData, '.jpg') > 0 || stripos($fieldData, '.png') > 0){
						$coverUrl = $fieldData;
						return $this->processImageURL($coverUrl);
					}
				}
			}
		}
		return false;
	}

	/**
	 * Process all the URLs parameters that will be used to fetch a cover image
	 *
	 * @return bool
	 */
	private function loadParameters(){
		//Check parameters
		if (empty($_GET)){
			$this->error = 'No parameters provided.';
			return false;
		}
		$this->reload = isset($_GET['reload']);
		// Sanitize incoming parameters to avoid filesystem attacks.  We'll make sure the
		// provided size matches a whitelist, and we'll strip illegal characters from the
		// ISBN.
		if (isset($_GET['size'])){
			$this->size = in_array($_GET['size'], ['small', 'medium', 'large']) ? $_GET['size'] : 'small';
		} else {
			$this->size = 'small';
		}
		if (isset($_GET['isn'])){
			if (is_array($_GET['isn'])){
				$_GET['isn'] = array_pop($_GET['isn']);
			}
			$this->isn = preg_replace('/[^0-9xX]/', '', $_GET['isn']);
		}

		if (isset($_GET['upc'])){
			if (is_array($_GET['upc'])){
				$_GET['upc'] = current($_GET['upc']);
			}
			//Strip any leading zeroes
			//$this->upc = ltrim(preg_replace('/[^0-9xX]/', '', $_GET['upc']), '0');
			// Stripping the leading zeroes results in a generic cover image from Syndetics for Clearview Library District
			$this->upc = preg_replace('/[^0-9xX]/', '', $_GET['upc']);
		}

		if (isset($_GET['issn'])){
			if (is_array($_GET['issn'])){
				$_GET['issn'] = current($_GET['issn']);
			}
			$this->issn = preg_replace('/[^0-9xX-]/', '', $_GET['issn']);
		}

		if (isset($_GET['id'])){
			if (is_array($_GET['id'])){
				$_GET['id'] = current($_GET['id']);
			}
			$_GET['id'] = trim($_GET['id']);

			if (strpos($_GET['id'], ':') > 0){
				$this->sourceAndId = new SourceAndId($_GET['id']);
			}else{
				if (isset($_GET['type'])){
					$type = strtolower($_GET['type']);
					if ($type == 'grouped_work' || $type == 'groupedwork'){
						$this->groupedWorkId = $_GET['id'];
					}elseif ($type == 'userlist'){
						$this->listId = $_GET['id'];
					}else{
						$this->sourceAndId = new SourceAndId($_GET['type'] . ':' . $_GET['id']);
					}
				}elseif (preg_match('/[a-f\\d]{8}-[a-f\\d]{4}-[a-f\\d]{4}-[a-f\\d]{4}-[a-f\\d]{12}/', $_GET['id'])){
					$this->groupedWorkId = $_GET['id'];
				}else{
					$this->sourceAndId = new SourceAndId('ils:' . $_GET['id']);
				}
			}
		}

		$this->category = empty($_GET['category']) ? null : strtolower(trim($_GET['category']));
		$this->format   = empty($_GET['format']) ? null : strtolower(trim($_GET['format']));

		$this->cacheName = $this->groupedWorkId ?? $this->listId ?? $this->sourceAndId ?? $this->isn ?? $this->upc ?? $this->issn ?? false;
		//Novelist Series carousels include covers for titles that are not in the index, so we must resort to using isn, upc, issn
		// Note Using $this->sourceAndId->getSourceAndId() causes a fatal error here when sourceAndId is not set.
		// Using just $this->sourceAndId works in this chain, because when it is set, __toString() is automagically called here.

//		if (isset($this->listId)){
//			$this->cacheName = 'list' . $this->cacheName;
//		}

		if (empty($this->cacheName)){
			$this->error = 'ISN, UPC, or ID must be provided.';
			return false;
		}

		$this->cacheName = preg_replace('/[^a-zA-Z0-9_.-]/', '', $this->cacheName);
		$this->cacheFile = $this->bookCoverPath . '/' . $this->size . '/' . $this->cacheName . '.png';
		$this->logTime('load parameters');
		return true;
	}

	/**
	 * Get a cover image from a source, check & adjust image sizing,
	 * check whether or not it is a good image to use,
	 * then save as a PNG file to best sent on to the user
	 *
	 * @param string $url Source to fetch cover image from
	 * @param bool $cache Whether or not to store the file locally for potential later use
	 * @param bool $attemptRefetch flag for a recursive call if the image wasn't a good one
	 *
	 * @return bool
	 */
	function processImageURL($url, $cache = true, $attemptRefetch = true){
		//TODO: cache URLs so that for grouped works we don't uselessly re-try URLs that have already been tried
		if ($this->doCoverLogging){
			$this->logger->info("Processing $url");
		}

		$userAgent = empty($this->configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $this->configArray['Catalog']['catalogUserAgent'];
		$context   = stream_context_create([
			'http' => [
				'header' => "User-Agent: {$userAgent}\r\n",
			],
		]);

		$this->logTime('Fetch image from external url');
		if (isset($url) && $image = @file_get_contents($url, false, $context)){
			$this->logTime('Fetched image from external url');
			// Figure out file paths -- $tempFile will be used to store the downloaded
			// image for analysis.  $finalFile will be used for long-term storage if
			// $cache is true or for temporary display purposes if $cache is false.
			$tempFile  = str_replace('.png', uniqid('', true), $this->cacheFile);
			$finalFile = $cache ? $this->cacheFile : $tempFile . '.png';
			if ($this->doCoverLogging){
				$this->logger->debug("Processing url $url to $finalFile");
			}

			// If some services can't provide an image, they will serve a 1x1 blank
			// or give us invalid image data.  Let's analyze what came back before
			// proceeding.
			if (!@file_put_contents($tempFile, $image)){
				if ($this->doCoverLogging){
						$this->logger->error("Unable to write to image directory $tempFile.");
					}

				$this->error = "Unable to write to image directory $tempFile.";
				return false;
			}
			[$width, $height, $type] = @getimagesize($tempFile);

			// File too small -- delete it and report failure.
			if ($width < 2 && $height < 2){
				@unlink($tempFile);
				return false;
			}

			// Test Image for partial load
			if (!$imageResource = @imagecreatefromstring($image)){
				if ($this->doCoverLogging){
					$this->logger->error("Could not create image from string $url");
				}
				@unlink($tempFile);
				return false;
			}

			// Check the color of the bottom left corner
			$rgb = imagecolorat($imageResource, 0, $height - 1);
			if ($rgb == 8421504){
				// Confirm by checking the color of the bottom right corner
				$rgb = imagecolorat($imageResource, $width - 1, $height - 1);
				if ($rgb == 8421504){
					// This is an image with partial gray at the bottom
					// (r:128,g:128,b:128)
//				$r = ($rgb >> 16) & 0xFF;
//				$g = ($rgb >> 8) & 0xFF;
//				$b = $rgb & 0xFF;

					if ($this->doCoverLogging){
						$this->logger->error('Partial Gray image loaded.');
					}
					if ($attemptRefetch){
						if ($this->doCoverLogging){
							$this->logger->info('Partial Gray image, attempting refetch.');
						}
						return $this->processImageURL($url, $cache, false); // Refetch once.
					}
				}
			}

			if ($this->size == 'small'){
				$maxDimension = 100;
			}elseif ($this->size == 'medium'){
				$maxDimension = 200;
			}else{
				$maxDimension = 400;
			}

			//Check to see if the image needs to be resized
			if ($width > $maxDimension || $height > $maxDimension){
				// We no longer need the temp file:
				@unlink($tempFile);

				if ($width > $height){
					$new_width  = $maxDimension;
					$new_height = floor($height * ($maxDimension / $width));
				}else{
					$new_height = $maxDimension;
					$new_width  = floor($width * ($maxDimension / $height));
				}

				if ($this->doCoverLogging){
					$this->logger->info("Resizing image New Width: $new_width, New Height: $new_height");
				}

				// create a new temporary image
				$tmp_img = imagecreatetruecolor($new_width, $new_height);

				// copy and resize old image into new image
				if (!imagecopyresampled($tmp_img, $imageResource, 0, 0, 0, 0, $new_width, $new_height, $width, $height)){
					if ($this->doCoverLogging){
						$this->logger->error("Could not resize image $url to $this->localFile");
					}
					return false;
				}

				// save thumbnail into a file
				if (file_exists($finalFile)){
					if ($this->doCoverLogging){
						$this->logger->debug("File $finalFile already exists, deleting");
					}
					unlink($finalFile);
				}

				if (!@imagepng($tmp_img, $finalFile, 9)){
					if ($this->doCoverLogging){
						$this->logger->error("Could not save resized file $$this->localFile");
					}
					return false;
				}

			}else{
				if ($this->doCoverLogging){
					$this->logger->info('Image is the correct size, not resizing.');
				}

				// Conversion needed -- do some normalization for non-PNG images:
				if ($type != IMAGETYPE_PNG){
					if ($this->doCoverLogging){
						$this->logger->info('Image is not a png, converting to png.');
					}

					$conversionOk = true;
					// Try to create a GD image and rewrite as PNG, fail if we can't:
					if (!($imageResource = @imagecreatefromstring($image))){
						if ($this->doCoverLogging){
							$this->logger->error("Could not create image from string $url");
						}
						$conversionOk = false;
					}

					if (!@imagepng($imageResource, $finalFile, 9)){
						if ($this->doCoverLogging){
							$this->logger->error("Could not save image to file $url $this->localFile");
						}
						$conversionOk = false;
					}
					// We no longer need the temp file:
					@unlink($tempFile);
					imagedestroy($imageResource);
					if (!$conversionOk){
						return false;
					}
					if ($this->doCoverLogging){
						$this->logger->info("Finished creating png at $finalFile.");
					}
				}else{
					// If $tempFile is already a PNG, let's store it in the cache.
					@rename($tempFile, $finalFile);

				}
				// Cache the grouped work cover if it doesn't already exist
				if (isset($this->groupedWorkCacheFileName) && !file_exists($this->groupedWorkCacheFileName)){
					@copy($finalFile, $this->groupedWorkCacheFileName);
				}
			}

			// Display the image:
			$this->returnImage($finalFile);

			// If we don't want to cache the image, delete it now that we're done.
			if (!$cache){
				@unlink($finalFile);
			}
			$this->logTime('Finished processing image url');

			return true;
		}else{
			if ($this->doCoverLogging){
				$this->logger->info("Could not load the file as an image $url");
			}
			$this->logTime('Failed to fetch image from external url');
			return false;
		}
	}

	/**
	 * Send the cover image to the user
	 *
	 * @param $localPath
	 */
	private function returnImage($localPath){
		header('Content-type: image/png');
		if ($this->addModificationHeaders($localPath)){
			$this->logTime('Added modification headers');
			$this->addCachingHeader();
			$this->logTime('Added caching headers');
			ob_clean();
			flush();
			readfile($localPath);
			if ($this->doCoverLogging){
				$this->logger->debug("Read file $localPath");
			}
			$this->logTime("read file $localPath");
		}else{
			$this->logTime('Added modification headers');
		}
	}

	private function addCachingHeader(){
		//Add caching information
		$expires = 60 * 60 * 24 * 14;  //expire the cover in 2 weeks on the client side
		header("Cache-Control: maxage=" . $expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
		if ($this->doCoverLogging){
			$this->logger->info('Added caching header');
		}
	}

	private function addModificationHeaders($filename){
		$timestamp = filemtime($filename);
		$this->logTime("Got filetimestamp $timestamp");
		$last_modified = substr(date('r', $timestamp), 0, -5) . 'GMT';
		$etag          = '"' . md5($last_modified) . '"';
		$this->logTime("Got last_modified $last_modified and etag $etag");
		// Send the headers
		header("Last-Modified: $last_modified");
		header("ETag: $etag");

		if ($this->reload){
			return true;
		}
		// See if the client has provided the required headers
		$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
		$if_none_match     = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;
		if (!$if_modified_since && !$if_none_match){
			if ($this->doCoverLogging){
				$this->logger->info('Caching headers not sent, return full image');
			}
			return true;
		}
		// At least one of the headers is there - check them
		if ($if_none_match && $if_none_match != $etag){
			if ($this->doCoverLogging){
				$this->logger->info("ETAG changed ");
			}
			return true; // etag is there but doesn't match
		}
		if ($if_modified_since && $if_modified_since != $last_modified){
			if ($this->doCoverLogging){
				$this->logger->info('Last modified changed');
			}
			return true; // if-modified-since is there but doesn't match
		}
		// Nothing has changed since their last request - serve a 304 and exit
		if ($this->doCoverLogging){
			$this->logger->info('File has not been modified');
		}
		header('HTTP/1.0 304 Not Modified');
		return false;
	}

	/**
	 * Return a cover image if Pika has cached it previously
	 *
	 * @param null|string $filename optional file to check
	 *
	 * @return bool
	 */
	private function getCachedCover($filename = null){
		if (!isset($filename)){
			$filename = $this->cacheFile;
		}
		if (is_readable($filename)){ // Load local cache if available
			$this->logTime('Found cached cover');
			if ($this->doCoverLogging){
				$this->logger->info("$filename exists, returning");
			}
			$this->returnImage($filename);
			return true;
		}
		$this->logTime('Finished checking for cached cover.');
		return false;
	}

	/**
	 * Display a "cover unavailable" graphic and terminate execution.
	 *
	 * @return bool
	 */
	function getDefaultCover($vertical_cutoff_px = 0){
		//Get the resource for the cover so we can load the title and author
		$title  = '';
		$author = '';
		if (isset($this->groupedWorkId)){
			$this->loadGroupedWork();
			if ($this->groupedWork){
				$title          = ucwords($this->groupedWork->getTitle());
				$author         = ucwords($this->groupedWork->getPrimaryAuthor());
				$this->category = 'blank';
			}
		}elseif (isset($this->listId)){
			$noCoverUrl = 'interface/themes/default/images/lists.png';
			return $this->processImageURL($noCoverUrl);
		}else{
			/** @var MarcRecord $driver */
			$recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId, $this->groupedWork);
			if ($recordDriver->isValid()){
				$title          = $recordDriver->getTitle();
				$author         = $recordDriver->getPrimaryAuthor();
				$this->category = 'blank'; // Use the blank image for record view default covers over the no Cover image
			}
		}

		require_once ROOT_DIR . '/sys/Covers/DefaultCoverImageBuilder.php';
		$coverBuilder = new DefaultCoverImageBuilder();
		if (!empty($title) && $coverBuilder->blankCoverExists($this->format, $this->category)){
			if ($this->doCoverLogging){
				$this->logger->debug("Building a default cover, format is {$this->format} category is {$this->category}");
			}
			$coverBuilder->getCover($title, $author, $this->format, $this->category, $this->cacheFile, $vertical_cutoff_px);
			return $this->processImageURL($this->cacheFile);
		}else{
			// Resort to a generic cover image.
			// Do not cache image as we hope a future load will have more information to provide a better cover

			$themes = array_unique(explode(',', $this->configArray['Site']['theme']));
			foreach ($themes as $themeName){
				if ($themeName != 'responsive'){
					// Do not use images in the responsive/images folder, as those are meant for the format category icons
					// rather than cover images.
					if (!empty($this->format)){
						if (is_readable("interface/themes/{$themeName}/images/{$this->format}_{$this->size}.png")){
							$noCoverUrl = "interface/themes/{$themeName}/images/{$this->format}_{$this->size}.png";
							break;
						}elseif (is_readable("interface/themes/{$themeName}/images/{$this->format}.png")){
							$noCoverUrl = "interface/themes/{$themeName}/images/{$this->format}.png";
							break;
						}
					}
					if (!empty($this->category)){
						if (is_readable("interface/themes/{$themeName}/images/{$this->category}_{$this->size}.png")){
							$noCoverUrl = "interface/themes/{$themeName}/images/{$this->category}_{$this->size}.png";
							break;
						}elseif (is_readable("interface/themes/{$themeName}/images/{$this->category}.png")){
							$noCoverUrl = "interface/themes/{$themeName}/images/{$this->category}.png";
							break;
						}
					}
				}
			}

			// Fallback in case 'default' theme isn't included in themes above
			if (!isset($noCoverUrl)){
				if (!empty($this->format)){
					if (is_readable("interface/themes/default/images/{$this->format}_{$this->size}.png")){
						$noCoverUrl = "interface/themes/default/images/{$this->format}_{$this->size}.png";
					}elseif (is_readable("interface/themes/default/images/$this->format.png")){
						$noCoverUrl = "interface/themes/default/images/$this->format.png";
					}
				}elseif (!empty($this->category)){
					if (is_readable("interface/themes/default/images/{$this->category}_{$this->size}.png")){
						$noCoverUrl = "interface/themes/default/images/{$this->category}_{$this->size}.png";
					}elseif (is_readable("interface/themes/default/images/$this->category.png")){
						$noCoverUrl = "interface/themes/default/images/$this->category.png";
					}
				}
			}

			if (!isset($noCoverUrl)){
				// Last resort cover image
				$this->logger->error('Resorted to noCover image for : ' . $_SERVER['REQUEST_URI']);
				// Log when this happens regardless of doCoverLogging setting
				$noCoverUrl = 'interface/themes/default/images/noCover2.png';
			}

			if ($this->doCoverLogging){
				$this->logger->info("Found fallback cover: $noCoverUrl");
			}
			return $this->processImageURL($noCoverUrl, false);
		}
	}

	private function getUserListCover($listId){
		require_once ROOT_DIR . "/sys/LocalEnrichment/UserListEntry.php";
		require_once ROOT_DIR . "/sys/LocalEnrichment/UserList.php";
		$font = ROOT_DIR . '/fonts/DejaVuSansCondensed-Bold.ttf';

		if ($this->reload){
			unlink($this->cacheFile);
		}

		$list = new UserList;
		if ($list->get($listId)){
			[$listItems] = $list->getListEntries();
			$listCount  = count($listItems);
			$imageArray = [];
			if ($listCount >= 4){
				$x          = 0;
				$finalCover = imagecreatetruecolor(100, 100);
				while ($x < 4){
					$bookcoverUrl = $this->getBookcoverUrlForUserListImageCreation($listItems[$x]);
					if ($listEntryCoverImage = @file_get_contents($bookcoverUrl, false)){
						$listEntryImageResource = @imagecreatefromstring($listEntryCoverImage);
						$resizedResource        = imagescale($listEntryImageResource, 50);
						$imageArray[$x]         = $resizedResource;
					}
					$x++;
				}

				if (imagecopymerge($finalCover, $imageArray[0], 0, 0, 0, 0, 50, 50, 100)){
					imagecopymerge($finalCover, $imageArray[1], 0, 50, 0, 0, 50, 50, 100);
					imagecopymerge($finalCover, $imageArray[2], 50, 0, 0, 0, 50, 50, 100);
					imagecopymerge($finalCover, $imageArray[3], 50, 50, 0, 0, 50, 50, 100);
				}
				$fontColor = imagecolorallocate($finalCover, 255, 255, 255);
				$this->addWrappedTextToImage($finalCover, $font, $list->title, 10, 12, 106, $fontColor);
				if (!file_exists($this->cacheFile)){
					imagepng($finalCover, $this->cacheFile);
				}
				$this->returnImage($this->cacheFile);
				return true;
			}elseif ($listCount == 3){
				$x          = 0;
				$finalCover = imagecreatetruecolor(100, 100);
				while ($x < 3){
					$bookcoverUrl = $this->getBookcoverUrlForUserListImageCreation($listItems[$x]);
					if ($listEntryCoverImage = @file_get_contents($bookcoverUrl, false)){
						$listEntryImageResource = @imagecreatefromstring($listEntryCoverImage);
						if ($x == 0){
							$resizedResource = imagescale($listEntryImageResource, -1, 98);
						}else{
							$resizedResource = imagescale($listEntryImageResource, 50);
						}
						$imageArray[$x] = $resizedResource;
					}
					$x++;
				}
				if (imagecopymerge($finalCover, $imageArray[0], 0, 0, 0, 0, 50, 100, 100)){
					imagecopymerge($finalCover, $imageArray[1], 50, 0, 0, 0, 50, 50, 100);
					imagecopymerge($finalCover, $imageArray[2], 50, 50, 0, 0, 50, 50, 100);
				}
				$fontColor = imagecolorallocate($finalCover, 255, 255, 255);
				$this->addWrappedTextToImage($finalCover, $font, $list->title, 10, 12, 106, $fontColor);
				if (!file_exists($this->cacheFile)){
					imagepng($finalCover, $this->cacheFile);
				}
				$this->returnImage($this->cacheFile);
				return true;

			}elseif ($listCount == 2){
				$x          = 0;
				$finalCover = imagecreatetruecolor(100, 100);
				while ($x < 2){
					$bookcoverUrl = $this->getBookcoverUrlForUserListImageCreation($listItems[$x]);
					if ($listEntryCoverImage = @file_get_contents($bookcoverUrl, false)){
						$listEntryImageResource = @imagecreatefromstring($listEntryCoverImage);
						$resizedResource        = imagescale($listEntryImageResource, -1, 100);
						$imageArray[$x]         = $resizedResource;
					}
					$x++;

				}
				if (imagecopymerge($finalCover, $imageArray[0], 0, 0, 0, 0, 50, 100, 100)){
					imagecopymerge($finalCover, $imageArray[1], 50, 0, 0, 0, 50, 100, 100);
				}
				$fontColor = imagecolorallocate($finalCover, 255, 255, 255);
				$this->addWrappedTextToImage($finalCover, $font, $list->title, 10, 12, 106, $fontColor);
				if (!file_exists($this->cacheFile)){
					imagepng($finalCover, $this->cacheFile);
				}
				$this->returnImage($this->cacheFile);
				return true;
			}
		}
		return false;
	}


	/**
	 * @param string $itemId GroupedWorkId or ArchivePID taken from an entry in a User List
	 * @return string|void  A Cover url to fetch
	 */
	private function getBookcoverUrlForUserListImageCreation($itemId){
		$isArchiveId = strpos($itemId, ':') !== false;
		if ($isArchiveId){
			require_once ROOT_DIR . '/RecordDrivers/Factory.php';
			/** @var IslandoraDriver $islandoraObject */
			$islandoraObject = RecordDriverFactory::initIslandoraDriverFromPid($itemId);
			$bookcoverUrl    = $islandoraObject->getBookcoverUrl();
		}else{
			$bookcoverUrl = $this->configArray['Site']['url'] . '/bookcover.php?size=medium&type=grouped_work&id=' . $itemId;
		}
		return $bookcoverUrl;
	}

	private function getGroupedWorkCover(){
		if ($this->loadGroupedWork()){

			// Look for cached grouped work cover image
			// $this->groupedWorkCacheFileName should only be set when we aren't starting with a grouped work
			if (!$this->reload && isset($this->groupedWorkCacheFileName) && $this->getCachedCover($this->groupedWorkCacheFileName)){
				return true;
			}

			// When we go directly to ISBNs, we circumvent checking for bad formats (playaways, audiobooks); and also prevent
			// custom covers.

//			if ($primaryIsbn = $this->groupedWork->getCleanISBN()) {
//				//This will be the novelist isbn if present, the primary isbn field in the index, or the first isbn from the isbns field in the index
//				$this->isn = $primaryIsbn;
//				if ($this->getCoverFromProvider()) {
//					return true;
//				}
//			}

			$recordDetails = $this->groupedWork->getSolrField('record_details');
			$before        = $recordDetails;

			// Sort so that we try Books first for cover data
			// Try to move Audio books to the bottom
			usort($recordDetails, function ($a, $b){
				// Note: Keying off the string '|Book|' avoids sorting up BookClub Kits.
				// Also note that keying off of '|Audio' allows us to sort down by formats like 'Audio CD' but also
				// the format category determination of 'Audio Books'
				if (strpos($a, '|Book|')){
					return -1;
				}elseif (strpos($b, '|Book|')){
					return 1;
				}elseif (strpos($a, '|Audio')){
					return 1;
				}else{
					return 0;
				}
			});
			foreach ($recordDetails as $recordDetail){
				// don't use playaway covers for grouped work 'cause they yuck.
				if (stristr($recordDetail, 'playaway')){
					continue;
				}

				$fullId = strtok($recordDetail, '|');
				if (!isset($this->sourceAndId) || $fullId != $this->sourceAndId->getSourceAndId()){ //Don't check the main record again when going through the related records
					$sourceAndId = new SourceAndId($fullId);

					// Check for a cached image for the related record
					if (!$this->reload){
						$fileToCheckFor = $this->bookCoverPath . '/' . $this->size . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $sourceAndId->getSourceAndId()) . '.png';
						if ($this->getCachedCover($fileToCheckFor)){
							return true;
						}
					}

					$source = $sourceAndId->getSource();
					if ($source == 'overdrive'){
						if ($this->getOverDriveCover($sourceAndId)){
							return true;
						}
					}else{
						$coverSource = $sourceAndId->getIndexingProfile()->coverSource;
						if ($coverSource == 'ILS MARC'){
							/** @var MarcRecord $driver */
							$driver = RecordDriverFactory::initRecordDriverById($sourceAndId, $this->groupedWork);
							//First check to see if there is a specific record defined in an 856 etc.
							if (method_exists($driver, 'getMarcRecord') && $this->getCoverFromMarc($driver->getMarcRecord())){
								return true;
							}else{
								//Finally, check the ISBNs if we don't have an override
								if ($this->getCoverFromProviderUsingRecordDriverData($driver)){
									return true;
								}
							}
						}else{
							if ($this->loadCoverBySpecifiedSource($coverSource, $sourceAndId)){
								return true;
							}
						}
					}

				}
			}

			// Wipe Out existing values so we can try only the new ones below
			$this->isn  = null;
			$this->upc  = null;
			$this->issn = null;

			//Try the best ISBN from search index
			$isbn = $this->groupedWork->getCleanISBN();
			if (!empty($isbn)){
				$this->isn = $isbn;
				if ($this->getCoverFromProvider()){
					return true;
				}
				$this->isn = null; // Wipe out the ISBN field so we can focus on only the UPCs below
			}

			// Try UPCs from search index
			if ($UPCs = $this->groupedWork->getUPCs()){
				foreach ($UPCs as $upc){
					$this->upc = $upc;
					if ($this->getCoverFromProvider()){
						return true;
					}
					// Try with leading zeroes first now, then try without.
					// (the problem is the syndetics will return a generic cover for trimmed upcs)
					$this->upc = ltrim($upc, '0');
					if ($this->upc !== $upc){
						if ($this->getCoverFromProvider()){
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	private function loadGroupedWork(){
		if ($this->groupedWork == null){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			if (!empty($this->groupedWorkId)){
				$this->groupedWork = new GroupedWorkDriver($this->groupedWorkId);
				if (!$this->groupedWork->isValid){
					$this->groupedWork = false;
				}
			}else{
				require_once ROOT_DIR . '/RecordDrivers/Factory.php';
				$recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId);
				if ($recordDriver /*&& $recordDriver->isValid()*/){
					$this->groupedWork = $recordDriver->getGroupedWorkDriver();
					if (!$this->groupedWork->isValid){
						$this->groupedWork = false;
					}else{
						$this->groupedWorkCacheFileName = $this->bookCoverPath . '/' . $this->size . '/' . $recordDriver->getPermanentId() . '.png';
					}
				}
			}

		}
		return $this->groupedWork;
	}

	/**
	 * Look in the MARC of a regular ILS record for cover image info
	 *
	 * @param File_MARC_Record|null $marcRecord
	 *
	 * @return bool
	 */
	private function getCoverFromMarc($marcRecord = null){
		if ($this->doCoverLogging){
			$this->logger->info('Looking for picture as part of 856 tag.');
		}

		if ($marcRecord === null){
			//Process the marc record
			require_once ROOT_DIR . '/sys/MarcLoader.php';
			if ($this->sourceAndId->getSource() != 'overdrive'){
				$marcRecord = MarcLoader::loadMarcRecordByILSId($this->sourceAndId);
			}
		}

		if (!$marcRecord){
			return false;
		}

		if ($this->getCustomCoverImageFromMarc($marcRecord)){
			return true;
		}

		if ($this->getSeedLibraryCoverImage($marcRecord)){
			return true;
		}

		//Check for Flatirons covers
		$marcFields = $marcRecord->getFields('962');
		if ($marcFields){
			if ($this->doCoverLogging){
				$this->logger->info('Found 962 field');
			}
			foreach ($marcFields as $marcField){
				if ($marcField->getSubfield('u')){
					if ($this->doCoverLogging){
						$this->logger->info('Found 962u subfield');
					}
					$subfield_u = $marcField->getSubfield('u')->getData();
					if ($this->processImageURL($subfield_u)){
						return true;
					}
				}else{
					//no image link available on this link
				}
			}
		}

		return false;
	}

	/**
	 *  Check a Marc Record 856 tags for custom cover file (subfield f) or a Url (subfield u).
	 *  The subfield 2 should denote 'pika' or 'image' so we know it is a custom cover.
	 *  (These are added to the record by the library's catalogers manually.)
	 *
	 * @param File_MARC_Record $marcRecord
	 *
	 * @return bool
	 */
	private function getCustomCoverImageFromMarc(File_MARC_Record $marcRecord){
		//Get the 856 tags
		$marcFields = $marcRecord->getFields('856');
		if ($marcFields){
			/** @var File_MARC_Data_Field $marcField */
			foreach ($marcFields as $marcField){
				//Check to see if this is a custom cover added in the record to use
				if ($marcField->getSubfield('2')){
					$customCoverCode = strtolower(trim($marcField->getSubfield('2')->getData()));
					if (in_array($customCoverCode, ['pika', 'pikaimage', 'pika_image', 'image', 'vufind_image', 'vufindimage', 'vufind'])){
						//Can use either subfield f or subfield u
						if ($marcField->getSubfield('f')){
							//Just references the file, add the original directory
							$filename = $this->bookCoverPath . '/original/' . trim($marcField->getSubfield('f')->getData());
							if ($this->processImageURL($filename)){
								//We got a successful match
								return true;
							}
						}elseif ($marcField->getSubfield('u')){
							//Full url to the image
							if ($this->processImageURL(trim($marcField->getSubfield('u')->getData()))){
								//We got a successful match
								return true;
							}
						}
					}
				}
			}
		}
	}

	private function getSeedLibraryCoverImage(File_MARC_Record $marcRecord){
		//Check the 690 field to see if this is a seed catalog entry
		$marcFields = $marcRecord->getFields('690');
		if ($marcFields){
			if ($this->doCoverLogging){
				$this->logger->info('Found 690 field');
			}
			foreach ($marcFields as $marcField){
				if ($marcField->getSubfield('a')){
					if ($this->doCoverLogging){
						$this->logger->info("Found 690a subfield");
					}
					$subfield_a = $marcField->getSubfield('a')->getData();
					if (preg_match('/seed library.*/i', $subfield_a, $matches)){
						if ($this->doCoverLogging){
							$this->logger->info("Title is a seed library title");
						}
						$filename = "interface/themes/responsive/images/seed_library_logo.jpg";
						if ($this->processImageURL($filename)){
							return true;
						}
					}
				}
			}
		}
	}

	/**
	 * Using ISBNs, UPCs found in MARC fields, try to get an image from external providers
	 *
	 * @param MarcRecord $driver
	 *
	 * @return bool
	 */
	private function getCoverFromProviderUsingRecordDriverData($driver){
		//TODO: Would like to use the Grouped Work driver here also but get ISBN & UPC methods are named slightly differently, and may have different purposes than expected

		// Attempt with any data already provided in the url
		if ($this->getCoverFromProvider()){
			return true;
		}
		// Wipe Out existing values so we can try only the new ones below
		$this->isn  = null;
		$this->upc  = null;
		$this->issn = null;

		$ISBNs = $driver->getCleanISBNs();
		if (!empty($ISBNs)){
			foreach ($ISBNs as $isbn){
				$this->isn = $isbn;
				if ($this->getCoverFromProvider()){
					return true;
				}
			}
			$this->isn = null; // Wipe out the ISBN field so we can focus on only the UPCs below
		}
		$UPCs = $driver->getCleanUPCs();
		if (!empty($UPCs)){
			foreach ($UPCs as $upc){
				$this->upc = $upc;
				if ($this->getCoverFromProvider()){
					return true;
				}
				// Try with leading zeroes first now, then try without.
				// (the problem is the syndetics will return a generic cover for trimmed upcs)
				$this->upc = ltrim($upc, '0');
				if ($this->upc !== $upc){
					if ($this->getCoverFromProvider()){
						return true;
					}
				}
			}
		}
		$ISSNs = $driver->getISSNs();
		if (!empty($ISSNs)){
			foreach ($ISSNs as $issn){
				$this->issn = $issn;
				if ($this->getCoverFromProvider()){
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Look for cover images from external content sources with ISBN, UPC provided in the URL
	 *
	 * @return bool
	 */
	private function getCoverFromProvider(){
		// Update to allow retrieval of covers based on upc
		if (!empty($this->isn) || !empty($this->upc) || !empty($this->issn)){
			if ($this->doCoverLogging){
				$this->logger->info('Looking for picture based on isbn and upc.');
			}

			// Fetch from provider
			if (isset($this->configArray['Content']['coverimages'])){
				$providers = explode(',', $this->configArray['Content']['coverimages']);
				foreach ($providers as $provider){
					$provider = explode(':', $provider);
					if ($this->doCoverLogging){
						$this->logger->info("Checking provider " . $provider[0]);
					}
					$func = $provider[0];
					$key  = $provider[1] ?? null;
					if (method_exists($this, $func)){
						if ($this->$func($key)){
							if ($this->doCoverLogging){
								$this->logger->info("Found image from $provider[0]");
							}
							$this->logTime("Checked $func");
							return true;
						}else{
							$this->logTime("Checked $func");
						}
					}
				}
			}

// Not using this process
//			//Have not found an image yet, check files uploaded by publisher
//			if ($this->configArray['Content']['loadPublisherCovers'] && isset($this->isn)){
//				$this->logger->info("Looking for image from publisher isbn10: $this->isbn10 isbn13: $this->isbn13 in $this->bookCoverPath/original/.");
//				$this->makeIsbn10And13();
//				if ($this->getCoverFromPublisher($this->bookCoverPath . '/original/')){
//					return true;
//				}
//				$this->logger->info("Did not find a file in publisher folder.");
//			}

		}
		return false;
	}

	function syndetics($key){
		if (is_null($this->isn) && is_null($this->upc) && is_null($this->issn)){
			return false;
		}
		switch ($this->size){
			case 'small':
				$size = 'SC.GIF';
				break;
			case 'medium':
				$size = 'MC.GIF';
				break;
			case 'large':
				$size = 'LC.JPG';
				break;
			default:
				$size = 'SC.GIF';
		}

		$url = empty($this->configArray['Syndetics']['url']) ? 'http://syndetics.com' : $this->configArray['Syndetics']['url'];
		//$url .= "/index.aspx?type=xw12&pagename={$size}&client={$key}"; // type parameter might not be needed any longer
		$url .= "/index.aspx?pagename={$size}&client={$key}";
		if (!empty($this->isn)){
			$url .= '&isbn=' . $this->isn;
		}
		if (!empty($this->upc)){
			$url .= '&upc=' . $this->upc;
		}
		if (!empty($this->issn)){
			$url .= '&issn=' . $this->issn;
		}
		if ($this->doCoverLogging){
			$this->logger->debug("Syndetics url: $url");
		}
		//TODO: syndetics can do oclc number
		// eg. https://secure.syndetics.com/index.aspx?isbn=/MC.GIF&client=[CLIENT]&oclc=945931618
		return $this->processImageURL($url);
	}

	function librarything($key){
		if (is_null($this->isn) || is_null($key)){
			return false;
		}
		$url = 'http://covers.librarything.com/devkey/' . $key . '/' . $this->size . '/isbn/' . $this->isn;
		return $this->processImageURL($url);
	}

	/**
	 * Retrieve a Content Cafe cover.
	 *
	 * @param string $id Content Cafe client ID.
	 *
	 * @return bool      True if image displayed, false otherwise.
	 */
	function contentCafe($id = null){
		switch ($this->size){
			case 'medium':
				$size = 'M';
				break;
			case 'large':
				$size = 'L';
				break;
			case 'small':
			default:
				$size = 'S';
				break;
		}
		$id  ??= $this->configArray['Contentcafe']['id']; // alternate way to pass the content cafe id to this method.
		$pw  = $this->configArray['Contentcafe']['pw'];
		$url = $this->configArray['Contentcafe']['url'] ?? 'http://contentcafe2.btol.com'; // http://images.btol.com would also work
		$url .= "/ContentCafe/Jacket.aspx?UserID={$id}&Password={$pw}&Return=1&Type={$size}&erroroverride=1&Value=";

		$lookupCode = $this->isn;
		if (!empty($lookupCode)){
			if ($this->processImageURL($url . $lookupCode)){
				return true;
			}
		}

		$lookupCode = $this->issn;
		if (!empty($lookupCode)){
			if ($this->processImageURL($url . $lookupCode)){
				return true;
			}
		}

		$lookupCode = $this->upc;
		if (!empty($lookupCode)){
			if ($this->processImageURL($url . $lookupCode)){
				return true;
			}
		}

		return false;
	}

	function google($id = null){
		if (empty($this->isn)){
			return false;
		}
		if (is_callable('json_decode')){
			$url = 'https://books.google.com/books?jscmd=viewapi&bibkeys=ISBN:' . $this->isn . '&callback=addTheCover';

			$userAgent = empty($this->configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $this->configArray['Catalog']['catalogUserAgent'];
			$context   = stream_context_create([
				'http' => [
					'header' => "User-Agent: {$userAgent}\r\n",
				],
			]);

			$json = @file_get_contents($url, false, $context);
			if (!empty($json) && $json != 'addTheCover({});'){

				// strip off addthecover( -- note that we need to account for length of ISBN (10 or 13)
				$json = substr($json, 21 + strlen($this->isn));
				// strip off );
				$json = substr($json, 0, -3);
				// convert \x26 to &
				$json = str_replace("\\x26", "&", $json);
				if ($json = json_decode($json, true)){
					//The google API always returns small images by default, but we can manipulate the URL to get larger images
					$size = $this->size;
					if (isset($json['thumbnail_url'])){
						$imageUrl = $json['thumbnail_url'];
						if ($size == 'small'){

						}else{
							if ($size == 'medium'){
								$imageUrl = preg_replace('/zoom=\d/', 'zoom=1', $imageUrl);
							}else{ //large
								$imageUrl = preg_replace('/zoom=\d/', 'zoom=0', $imageUrl);
							}
						}
						return $this->processImageURL($imageUrl);
					}
				}
			}
		}
		return false;
	}

	// Removed as cover provider due to unwanted cover image. 11-14-2017 see  https://marmot.myjetbrains.com/youtrack/issue/D-1608
//	function openlibrary($id = null){
//		if (is_null($this->isn)){
//			return false;
//		}
//		// Convert internal size value to openlibrary equivalent:
//		switch ($this->size){
//			case 'large':
//				$size = 'L';
//				break;
//			case 'medium':
//				$size = 'M';
//				break;
//			case 'small':
//			default:
//				$size = 'S';
//				break;
//		}
//
//		// Retrieve the image; the default=false parameter indicates that we want a 404
//		// if the ISBN is not supported.
//		$url = "http://covers.openlibrary.org/b/isbn/{$this->isn}-{$size}.jpg?default=false";
//		return $this->processImageURL($url);
//	}

// Not used at all. Keeping in case it becomes handy in the future
//	function getCoverFromPublisher($folderToCheck){
//		if (!file_exists($folderToCheck)){
//			$this->logger->info("No publisher directory, expected to find in $folderToCheck");
//			return false;
//		}
//		//$this->logger->info("Looking in folder $folderToCheck for cover image supplied by publisher.");
//		//Check to see if the file exists in the folder
//
//		$matchingFiles10 = glob($folderToCheck . $this->isbn10 . "*.jpg");
//		$matchingFiles13 = glob($folderToCheck . $this->isbn13 . "*.jpg");
//		if (count($matchingFiles10) > 0){
//			//We found a match
//			$this->logger->info("Found a publisher file by 10 digit ISBN " . $matchingFiles10[0]);
//			return $this->processImageURL($matchingFiles10[0], true);
//		}elseif (count($matchingFiles13) > 0){
//			//We found a match
//			$this->logger->info("Found a publisher file by 13 digit ISBN " . $matchingFiles13[0]);
//			return $this->processImageURL($matchingFiles13[0], true);
//		}else{
//			//$this->logger->info("Did not find match by isbn 10 or isbn 13, checking sub folders");
//			//Check all subdirectories of the current folder
//			$subDirectories = array();
//			$dh             = opendir($folderToCheck);
//			if ($dh){
//				while (($file = readdir($dh)) !== false){
//
//					if (is_dir($folderToCheck . $file) && $file != '.' && $file != '..'){
//						//$this->logger->info("Found file $file");
//						$subDirectories[] = $folderToCheck . $file . '/';
//					}
//				}
//				closedir($dh);
//				foreach ($subDirectories as $subDir){
//					//$this->log("Looking in subfolder $subDir for cover image supplied by publisher.");
//					if ($this->getCoverFromPublisher($subDir)){
//						return true;
//					}
//				}
//			}
//		}
//		return false;
//	}

//	private function makeIsbn10And13(){
//		if (!empty($this->isn) && strlen($this->isn) >= 10){
//			require_once ROOT_DIR . '/sys/ISBN/ISBNConverter.php';
//			if (strlen($this->isn) == 10){
//				//$this->logger->info("Provided ISBN is 10 digits.");
//				$this->isbn10 = $this->isn;
//				$this->isbn13 = ISBNConverter::convertISBN10to13($this->isbn10);
//			}elseif (strlen($this->isn) == 13){
//				//$this->logger->info("Provided ISBN is 13 digits.");
//				$this->isbn13 = $this->isn;
//				$this->isbn10 = ISBNConverter::convertISBN13to10($this->isbn13);
//			}
//			$this->logger->info("Loaded isbn10 $this->isbn10 and isbn13 $this->isbn13.");
//			$this->logTime("create isbn 10 and isbn 13");
//		}
//	}

	/**
	 * Add text to an image, wrapping based on number of characters.
	 *
	 * @param resource $imageHandle The image resource to use
	 * @param string   $font        The font file to use to generate the text
	 * @param string   $text        The text to write
	 * @param int      $fontSize    The pixel size of the font to use
	 * @param int      $lineSpacing The number of pixels between lines of text
	 * @param int      $startY      The vertical pixel position for the text
	 * @param int      $color       The color identifier
	 * @return float|int  The starting vertical position for the line of text. Use to set where the next line of text should start at
	 */
	private function addWrappedTextToImage($imageHandle, $font, $text, $fontSize, $lineSpacing, $startY, $color){

		$textBox           = imageftbbox($fontSize, 0, $font, $text);
		$totalTextWidth    = abs($textBox[4] - $textBox[6]);
		$numLines          = ceil((float)$totalTextWidth / 100);
		$charactersPerLine = ceil(strlen($text) / $numLines);
		$lines             = explode("\n", wordwrap($text, $charactersPerLine, "\n"));
		if (count($lines) > 4){
			$numLines = 3;
		}
		$startY = $startY - ($numLines * $lineSpacing) - (($numLines - 1) * 3);


		$box_x           = 0;
		$box_y           = $startY - $lineSpacing;
		$backgroundColor = imagecolorallocatealpha($imageHandle, 0, 0, 0, 40);
		imagefilledrectangle($imageHandle, $box_x, $box_y, 100, 100, $backgroundColor);
		$i = 0;
		foreach ($lines as $line){
			$lineBox    = imageftbbox($fontSize, 0, $font, $line);
			$lineWidth  = abs($lineBox[4] - $lineBox[6]);
			$lineHeight = abs($lineBox[3] - $lineBox[5]);
			$x          = (100 - $lineWidth) / 2;                                       //Get the starting position for the text
			imagefttext($imageHandle, $fontSize, 0, $x, $startY, $color, $font, $line); //Write the text to the image
			$startY += $lineHeight;
			if (++$i == 4){
				break;
			}
		}


	}

}
