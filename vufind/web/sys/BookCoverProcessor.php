<?php
require_once ROOT_DIR . '/services/SourceAndId.php';

class BookCoverProcessor {

	public  $error;

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

	/** @var null|GroupedWorkDriver */
	private $groupedWork = null;

	private $reload;
	private $bookCoverPath;
	private $cacheName;
	private $cacheFile;
	private $localFile;

	/** @var  Logger $logger */
	private $logger;
	private $doCoverLogging;
	/** @var  Timer $timer */
	private $timer;
	private $doTimings;

	function log($message, $level = PEAR_LOG_DEBUG){
		if ($this->doCoverLogging){
			$this->logger->log($message, $level);
		}
	}

	function logTime($message){
		if ($this->doTimings){
			$this->timer->logTime($message);
		}
	}

	public function loadCover($configArray, $timer, $logger){
		$this->configArray    = $configArray;
		$this->timer          = $timer;
		$this->doTimings      = $this->configArray['System']['coverTimings'];
		$this->logger         = $logger;
		$this->doCoverLogging = $this->configArray['Logging']['coverLogging'];

		$this->log("Starting to load cover", PEAR_LOG_INFO);
		$this->bookCoverPath = $configArray['Site']['coverPath'];
		if (!$this->loadParameters()){
			return;
		}
		if (!$this->reload){
			$this->log("Looking for Cached cover", PEAR_LOG_INFO);
			if ($this->getCachedCover()){
				return;
			}
		}

		// Grouped work level case
		if (isset($this->groupedWorkId) && $this->getGroupedWorkCover()){
			return;
		}else{
			// Record level cases

			// Try special handling for sideloads
			if (isset($this->sourceAndId)){
				$source = $this->sourceAndId->getSource();
				if ($source == 'overdrive'){
					//			$this->initDatabaseConnection(); // bootstrap.php does this
					//Will exit if we find a cover
					if ($this->getOverDriveCover($this->sourceAndId)){
						return;
					}
				}elseif ($source == 'Colorado State Government Documents'){
					if ($this->getColoradoGovDocCover()){
						return;
					}
				}elseif ($source == 'Classroom Video on Demand'){
					if ($this->getClassroomVideoOnDemandCover($this->sourceAndId)){
						return;
					}
				}elseif (stripos($source, 'films on demand') !== false){
					if ($this->getFilmsOnDemandCover($this->sourceAndId)){
						return;
					}
				}elseif (stripos($source, 'proquest') !== false || stripos($source, 'ebrary') !== false){
					if ($this->getEbraryCover($this->sourceAndId)){
						return;
					}
				}elseif (stripos($source, 'Creative Bug') !== false){
					if ($this->getCreativeBugCover($this->sourceAndId)){
						return;
					}
				}elseif (stripos($source, 'rbdigital') !== false || stripos($source, 'zinio') !== false){
					if ($this->getZinioCover($this->sourceAndId)){
						return;
					}
					// Any Sideloaded Collection that has a cover in the 856 tag (and additional conditions)
				}elseif ($source != 'ils'){
					if ($this->getSideLoadedCover($this->sourceAndId)){
						return;
					}
				}
			}

			// Now try some special cases with ILS records
			// (Fetching a custom cover needs to take precedence over checking outside content providers)
			if ($source == 'ils' && $this->getCoverFromMarc()){
				return;
			}

			// Now try outside content providers with the supplied ISN, ISBN, or UPC
			$this->log("Looking for cover from providers", PEAR_LOG_INFO);
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

		// Build default cover or use place holder image
		$this->log("No image found, using die image", PEAR_LOG_INFO);
		$this->getDefaultCover();
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
		if ($driver->isValid()){
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
				$this->logger->log("Marc file exception while loading cover for $sourceAndId : " . $e->getMessage(), PEAR_LOG_ERR);
			}

			return $this->getCoverFromProviderUsingRecordDriverData($driver);
		}
		return false;
	}

	private function getColoradoGovDocCover(){
		$filename = "interface/themes/responsive/images/state_flag_of_colorado.png";
		if ($this->processImageURL($filename)){
			return true;
		}else{
			return false;
		}
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
		require_once ROOT_DIR . '/sys/OverDrive/OverDriveAPIProduct.php';
		require_once ROOT_DIR . '/sys/OverDrive/OverDriveAPIProductMetaData.php';
		$overDriveProduct = new OverDriveAPIProduct();
		if ($overDriveProduct->get('overdriveId', $sourceAndId->getRecordId())){
			$overDriveMetadata = new OverDriveAPIProductMetaData();
			if ($overDriveMetadata->get('productId', $overDriveProduct->id)){
				$coverUrl = $overDriveMetadata->cover;
				if ($coverUrl != null){
					return $this->processImageURL($coverUrl);
				}
			}
		}
		return false;
	}

	private function getZinioCover(SourceAndId $sourceAndId){
		require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
		$driver = new SideLoadedRecord($sourceAndId);
		if ($driver && $driver->isValid()){
			/** @var File_MARC_Data_Field[] $linkFields */
			$linkFields = $driver->getMarcRecord()->getFields('856');
			foreach ($linkFields as $linkField){
				if ($linkField->getIndicator(1) == 4 && $linkField->getSubfield('3') != null && $linkField->getSubfield('3')->getData() == 'Image'){
					$coverUrl = $linkField->getSubfield('u')->getData();
					$coverUrl = str_replace('size=200', 'size=lg', $coverUrl);
					return $this->processImageURL($coverUrl);
				}
			}
		}
		return false;
	}

	/**
	 * @param SourceAndId $sourceAndId
	 *
	 * @return bool
	 */
	private function getCreativeBugCover($sourceAndId){
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
			$this->error = "No parameters provided.";
			return false;
		}
		$this->reload = isset($_GET['reload']);
		// Sanitize incoming parameters to avoid filesystem attacks.  We'll make sure the
		// provided size matches a whitelist, and we'll strip illegal characters from the
		// ISBN.
		$this->size = in_array($_GET['size'], array('small', 'medium', 'large')) ? $_GET['size'] : 'small';
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
			$this->upc = ltrim(preg_replace('/[^0-9xX]/', '', $_GET['upc']), '0');
		}

		if (isset($_GET['issn'])){
			if (is_array($_GET['issn'])){
				$_GET['issn'] = current($_GET['issn']);
			}
			$this->issn = preg_replace('/[^0-9xX]/', '', $_GET['issn']);
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

		$this->category = !empty($_GET['category']) ? strtolower(trim($_GET['category'])) : null;
		$this->format   = !empty($_GET['format']) ? strtolower(trim($_GET['format'])) : null;

		if (isset($this->groupedWorkId)){
			$this->cacheName = $this->groupedWorkId;
		}elseif (isset($this->sourceAndId)){
			$this->cacheName = $this->sourceAndId->getRecordId();
		}elseif (!is_null($this->isn)){
			$this->cacheName = $this->isn;
		}elseif (!is_null($this->upc)){
			$this->cacheName = $this->upc;
		}elseif (!is_null($this->issn)){
			$this->cacheName = $this->issn;
		}else{
			$this->error = "ISN, UPC, or id must be provided.";
			return false;
		}
		$this->cacheName = preg_replace('/[^a-zA-Z0-9_.-]/', '', $this->cacheName);
		$this->cacheFile = $this->bookCoverPath . '/' . $this->size . '/' . $this->cacheName . '.png';
		$this->logTime("load parameters");
		return true;
	}

	/**
	 * Get a cover image from a source, check & adjust image sizing,
	 * check whether or not it is a good image to use,
	 * then save as a PNG file to best sent on to the user
	 *
	 * @param string $url            Source to fetch cover image from
	 * @param bool   $cache          Whether or not to store the file locally for potential later use
	 * @param bool   $attemptRefetch flag for a recursive call if the image wasn't a good one
	 *
	 * @return bool
	 */
	function processImageURL($url, $cache = true, $attemptRefetch = true){
		$this->log("Processing $url", PEAR_LOG_INFO);

		$userAgent = empty($this->configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $this->configArray['Catalog']['catalogUserAgent'];
		$context   = stream_context_create(array(
			'http' => array(
				'header' => "User-Agent: {$userAgent}\r\n",
			),
		));

		if ($image = @file_get_contents($url, false, $context)){
			// Figure out file paths -- $tempFile will be used to store the downloaded
			// image for analysis.  $finalFile will be used for long-term storage if
			// $cache is true or for temporary display purposes if $cache is false.
			$tempFile  = str_replace('.png', uniqid(), $this->cacheFile);
			$finalFile = $cache ? $this->cacheFile : $tempFile . '.png';
			$this->log("Processing url $url to $finalFile", PEAR_LOG_DEBUG);

			// If some services can't provide an image, they will serve a 1x1 blank
			// or give us invalid image data.  Let's analyze what came back before
			// proceeding.
			if (!@file_put_contents($tempFile, $image)){
				$this->log("Unable to write to image directory $tempFile.", PEAR_LOG_ERR);
				$this->error = "Unable to write to image directory $tempFile.";
				return false;
			}
			list($width, $height, $type) = @getimagesize($tempFile);

			// File too small -- delete it and report failure.
			if ($width < 2 && $height < 2){
				@unlink($tempFile);
				return false;
			}

			// Test Image for for partial load
			if (!$imageResource = @imagecreatefromstring($image)){
				$this->log("Could not create image from string $url", PEAR_LOG_ERR);
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

					$this->log('Partial Gray image loaded.', PEAR_LOG_ERR);
					if ($attemptRefetch){
						$this->log('Partial Gray image, attempting refetch.', PEAR_LOG_INFO);
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

				$this->log("Resizing image New Width: $new_width, New Height: $new_height", PEAR_LOG_INFO);

				// create a new temporary image
				$tmp_img = imagecreatetruecolor($new_width, $new_height);

				// copy and resize old image into new image
				if (!imagecopyresampled($tmp_img, $imageResource, 0, 0, 0, 0, $new_width, $new_height, $width, $height)){
					$this->log("Could not resize image $url to $this->localFile", PEAR_LOG_ERR);
					return false;
				}

				// save thumbnail into a file
				if (file_exists($finalFile)){
					$this->log("File $finalFile already exists, deleting", PEAR_LOG_DEBUG);
					unlink($finalFile);
				}

				if (!@imagepng($tmp_img, $finalFile, 9)){
					$this->log("Could not save resized file $$this->localFile", PEAR_LOG_ERR);
					return false;
				}

			}else{
				$this->log("Image is the correct size, not resizing.", PEAR_LOG_INFO);

				// Conversion needed -- do some normalization for non-PNG images:
				if ($type != IMAGETYPE_PNG){
					$this->log("Image is not a png, converting to png.", PEAR_LOG_INFO);

					$conversionOk = true;
					// Try to create a GD image and rewrite as PNG, fail if we can't:
					if (!($imageResource = @imagecreatefromstring($image))){
						$this->log("Could not create image from string $url", PEAR_LOG_ERR);
						$conversionOk = false;
					}

					if (!@imagepng($imageResource, $finalFile, 9)){
						$this->log("Could not save image to file $url $this->localFile", PEAR_LOG_ERR);
						$conversionOk = false;
					}
					// We no longer need the temp file:
					@unlink($tempFile);
					imagedestroy($imageResource);
					if (!$conversionOk){
						return false;
					}
					$this->log("Finished creating png at $finalFile.", PEAR_LOG_INFO);
				}else{
					// If $tempFile is already a PNG, let's store it in the cache.
					@rename($tempFile, $finalFile);

				}
				// Cache the grouped work cover if doesn't already exist
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
			$this->logTime("Finished processing image url");

			return true;
		}else{
			$this->log("Could not load the file as an image $url", PEAR_LOG_INFO);
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
			$this->logTime("Added modification headers");
			$this->addCachingHeader();
			$this->logTime("Added caching headers");
			ob_clean();
			flush();
			readfile($localPath);
			$this->log("Read file $localPath", PEAR_LOG_DEBUG);
			$this->logTime("echo file $localPath");
		}else{
			$this->logTime("Added modification headers");
		}
	}

	private function addCachingHeader(){
		//Add caching information
		$expires = 60 * 60 * 24 * 14;  //expire the cover in 2 weeks on the client side
		header("Cache-Control: maxage=" . $expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
		$this->log("Added caching header", PEAR_LOG_INFO);
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
			$this->log("Caching headers not sent, return full image", PEAR_LOG_INFO);
			return true;
		}
		// At least one of the headers is there - check them
		if ($if_none_match && $if_none_match != $etag){
			$this->log("ETAG changed ", PEAR_LOG_INFO);
			return true; // etag is there but doesn't match
		}
		if ($if_modified_since && $if_modified_since != $last_modified){
			$this->log("Last modified changed", PEAR_LOG_INFO);
			return true; // if-modified-since is there but doesn't match
		}
		// Nothing has changed since their last request - serve a 304 and exit
		$this->log("File has not been modified", PEAR_LOG_INFO);
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
			$this->logTime("Found cached cover");
			$this->log("$filename exists, returning", PEAR_LOG_INFO);
			$this->returnImage($filename);
			return true;
		}
		$this->logTime("Finished checking for cached cover.");
		return false;
	}

	/**
	 * Display a "cover unavailable" graphic and terminate execution.
	 */
	function getDefaultCover(){
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
		}else{
			/** @var MarcRecord $driver */
			$recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId, $this->groupedWork);
			if ($recordDriver->isValid()){
				$title          = $recordDriver->getTitle();
				$author         = $recordDriver->getAuthor();
				$this->category = 'blank'; // Use the blank image for record view default covers over the no Cover image
			}
		}

		require_once ROOT_DIR . '/sys/DefaultCoverImageBuilder.php';
		$coverBuilder = new DefaultCoverImageBuilder();
		if (!empty($title) && $coverBuilder->blankCoverExists($this->format, $this->category)){
			$this->log("Building a default cover, format is {$this->format} category is {$this->category}", PEAR_LOG_DEBUG);
			$coverBuilder->getCover($title, $author, $this->format, $this->category, $this->cacheFile);
			return $this->processImageURL($this->cacheFile);
		}else{
			$themes = array_unique(explode(',', $this->configArray['Site']['theme']));
			//TODO: the responsive theme images aren't meant for book cover art, just the format category facet icons
			foreach ($themes as $themeName){
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
				}else{
					$noCoverUrl = "interface/themes/default/images/noCover2.png";
				}
			}

			$this->log("Found fallback cover: $noCoverUrl", PEAR_LOG_INFO);
			return $this->processImageURL($noCoverUrl);
		}
	}

	private function getGroupedWorkCover(){
		if ($this->loadGroupedWork()){

			// Look for cached grouped work cover image
			// $this->groupedWorkCacheFileName should only be set when we aren't starting with a grouped work
			if (!$this->reload && isset($this->groupedWorkCacheFileName) && $this->getCachedCover($this->groupedWorkCacheFileName)){
				return true;
			}

			if($primaryIsbn = $this->groupedWork->getSolrField('primary_isbn')) {
				$this->isn = $primaryIsbn;
				if ($this->getCoverFromProvider()) {
					return true;
				}
			}

			$recordDetails = $this->groupedWork->getSolrField('record_details');
			foreach ($recordDetails as $recordDetail){
				// don't use playaway covers for grouped work 'cause they yuck.
				if(stristr($recordDetail, 'playaway')) {
					continue;
				}

				$fullId = strtok($recordDetail, '|');
				if (!isset($this->sourceAndId) || $fullId != $this->sourceAndId->getSourceAndId()){ //Don't check the main record again when going through the related records
					$sourceAndId = new SourceAndId($fullId);

					// Check for a cached image for the related record
					if (!$this->reload){
						$fileToCheckFor = $this->bookCoverPath . '/' . $this->size . '/' . $sourceAndId->getRecordId() . '.png';
						if ($this->getCachedCover($fileToCheckFor)){
							return true;
						}
					}

					$source      = $sourceAndId->getSource();
					if ($source != 'ils'){
						if (strcasecmp($source, 'OverDrive') == 0){
							if ($this->getOverDriveCover($sourceAndId)){
								return true;
							}
						}elseif (strcasecmp($source, 'Colorado State Government Documents') == 0){
							if ($this->getColoradoGovDocCover()){
								return true;
							}
						}elseif (stripos($source, 'films on demand') !== false){
							if ($this->getFilmsOnDemandCover($sourceAndId)){
								return true;
							}
						}elseif (strcasecmp($source, 'Classroom Video on Demand') == 0){
							if ($this->getClassroomVideoOnDemandCover($sourceAndId)){
								return true;
							}
						}elseif (stripos($source, 'proquest') !== false || stripos($source, 'ebrary') !== false){
							if ($this->getEbraryCover($sourceAndId)){
								return true;
							}
						}elseif (stripos($source, 'Creative Bug') !== false){
							if ($this->getCreativeBugCover($sourceAndId)){
								return true;
							}
						}elseif (stripos($source, 'rbdigital') !== false || stripos($source, 'zinio') !== false){
							if ($this->getZinioCover($sourceAndId)){
								return true;
							}
						}else{
							if ($this->getSideLoadedCover($sourceAndId)){
								return true;
							}
						}
					}else{
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
					}
				}
			}

			// Wipe Out existing values so we can try only the new ones below
			$this->isn  = null;
			$this->upc  = null;
			$this->issn = null;

			//Try best ISBN from search index
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
					$this->upc = ltrim($upc, '0');
					if ($this->getCoverFromProvider()){
						return true;
					}
					//If we tried trimming the leading zeroes, also try without.
					if ($this->upc !== $upc){
						$this->upc = $upc;
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
					} else {
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
		$this->log("Looking for picture as part of 856 tag.", PEAR_LOG_INFO);

		if ($marcRecord === null){
//			$this->initDatabaseConnection(); //this is done in bootstrap.php
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
			$this->log("Found 962 field", PEAR_LOG_INFO);
			foreach ($marcFields as $marcField){
				if ($marcField->getSubfield('u')){
					$this->log("Found 962u subfield", PEAR_LOG_INFO);
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
					if (in_array($customCoverCode, array('pika', 'pikaimage', 'pika_image', 'image', 'vufind_image', 'vufindimage', 'vufind'))){
						// We don't need to check *two* codes. One is really enough
//						if ($marcField->getSubfield('3')){
//							$customCoverCode2 = strtolower(trim($marcField->getSubfield('3')->getData()));
//							if (in_array($customCoverCode2, array('cover image','coverimage', 'cover', 'image'))){
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
//							}
//						}
					}
				}
			}
		}
	}

	private function getSeedLibraryCoverImage(File_MARC_Record $marcRecord){
		//Check the 690 field to see if this is a seed catalog entry
		$marcFields = $marcRecord->getFields('690');
		if ($marcFields){
			$this->log("Found 690 field", PEAR_LOG_INFO);
			foreach ($marcFields as $marcField){
				if ($marcField->getSubfield('a')){
					$this->log("Found 690a subfield", PEAR_LOG_INFO);
					$subfield_a = $marcField->getSubfield('a')->getData();
					if (preg_match('/seed library.*/i', $subfield_a, $matches)){
						$this->log("Title is a seed library title", PEAR_LOG_INFO);
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
		//TODO: Would like to use the Grouped Work driver her also but get ISBN & UPC methods are named slightly differently, and may have different purposes than expected

		// Wipe Out existing values so we can try only the new ones below
		$this->isn  = null;
		$this->upc  = null;
		$this->issn = null;

		$ISBNs = $driver->getCleanISBNs();
		if ($ISBNs){
			foreach ($ISBNs as $isbn){
				$this->isn = $isbn;
				if ($this->getCoverFromProvider()){
					return true;
				}
			}
			$this->isn = null; // Wipe out the ISBN field so we can focus on only the UPCs below
		}
		$UPCs = $driver->getCleanUPCs();
		if ($UPCs){
			foreach ($UPCs as $upc){
				$this->upc = ltrim($upc, '0');
				if ($this->getCoverFromProvider()){
					return true;
				}
				//If we tried trimming the leading zeroes, also try without.
				if ($this->upc !== $upc){
					$this->upc = $upc;
					if ($this->getCoverFromProvider()){
						return true;
					}
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
			$this->log("Looking for picture based on isbn and upc.", PEAR_LOG_INFO);

			// Fetch from provider
			if (isset($this->configArray['Content']['coverimages'])){
				$providers = explode(',', $this->configArray['Content']['coverimages']);
				foreach ($providers as $provider){
					$provider = explode(':', $provider);
					$this->log("Checking provider " . $provider[0], PEAR_LOG_INFO);
					$func = $provider[0];
					$key  = isset($provider[1]) ? $provider[1] : null;
					if (method_exists($this, $func) && $this->$func($key)){
						$this->log("Found image from $provider[0]", PEAR_LOG_INFO);
						$this->logTime("Checked $func");
						return true;
					}else{
						$this->logTime("Checked $func");
					}
				}
			}

// Not using this process
//			//Have not found an image yet, check files uploaded by publisher
//			if ($this->configArray['Content']['loadPublisherCovers'] && isset($this->isn)){
//				$this->log("Looking for image from publisher isbn10: $this->isbn10 isbn13: $this->isbn13 in $this->bookCoverPath/original/.", PEAR_LOG_INFO);
//				$this->makeIsbn10And13();
//				if ($this->getCoverFromPublisher($this->bookCoverPath . '/original/')){
//					return true;
//				}
//				$this->log("Did not find a file in publisher folder.", PEAR_LOG_INFO);
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
		$url .= "/index.aspx?type=xw12&pagename={$size}&client={$key}";
		if (!empty($this->isn)){
			$url .= "&isbn=" . $this->isn;
		}
		if (!empty($this->upc)){
			$url .= "&upc=" . $this->upc;
		}
		if (!empty($this->issn)){
			$url .= "&issn=" . $this->issn;
		}
		$this->log("Syndetics url: $url", PEAR_LOG_DEBUG);
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
		if (!$id){
			$id = $this->configArray['Contentcafe']['id']; // alternate way to pass the content cafe id to this method.
		}
		$pw  = $this->configArray['Contentcafe']['pw'];
		$url = isset($this->configArray['Contentcafe']['url']) ? $this->configArray['Contentcafe']['url'] : 'http://contentcafe2.btol.com';
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
			$context   = stream_context_create(array(
				'http' => array(
					'header' => "User-Agent: {$userAgent}\r\n",
				),
			));

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

	// Stopped using amazon as a source a long time ago. Retaining just in case
//	function amazon($id){
//		if (is_null($this->isn)){
//			return false;
//		}
//		require_once ROOT_DIR . '/sys/Amazon.php';
//		require_once 'XML/Unserializer.php';
//
//		$params  = array('ResponseGroup' => 'Images', 'ItemId' => $this->isn);
//		$request = new AWS_Request($id, 'ItemLookup', $params);
//		$result  = $request->sendRequest();
//		if (!PEAR_Singleton::isError($result)){
//			$unxml = new XML_Unserializer();
//			$unxml->unserialize($result);
//			$data = $unxml->getUnserializedData();
//			if (PEAR_Singleton::isError($data)){
//				return false;
//			}
//			if (isset($data['Items']['Item']) && !$data['Items']['Item']['ASIN']){
//				$data['Items']['Item'] = $data['Items']['Item'][0];
//			}
//			if (isset($data['Items']['Item'])){
//				// Where in the XML can we find the URL we need?
//				switch ($this->size){
//					case 'small':
//						$imageIndex = 'SmallImage';
//						break;
//					case 'medium':
//						$imageIndex = 'MediumImage';
//						break;
//					case 'large':
//						$imageIndex = 'LargeImage';
//						break;
//					default:
//						$imageIndex = false;
//						break;
//				}
//
//				// Does a URL exist?
//				if ($imageIndex && isset($data['Items']['Item'][$imageIndex]['URL'])){
//					$imageUrl = $data['Items']['Item'][$imageIndex]['URL'];
//					return $this->processImageURL($imageUrl, false);
//				}
//			}
//		}
//
//		return false;
//	}

// Not used at all. Keeping in case it becomes handy in the future
//	function getCoverFromPublisher($folderToCheck){
//		if (!file_exists($folderToCheck)){
//			$this->log("No publisher directory, expected to find in $folderToCheck", PEAR_LOG_INFO);
//			return false;
//		}
//		//$this->log("Looking in folder $folderToCheck for cover image supplied by publisher.", PEAR_LOG_INFO);
//		//Check to see if the file exists in the folder
//
//		$matchingFiles10 = glob($folderToCheck . $this->isbn10 . "*.jpg");
//		$matchingFiles13 = glob($folderToCheck . $this->isbn13 . "*.jpg");
//		if (count($matchingFiles10) > 0){
//			//We found a match
//			$this->log("Found a publisher file by 10 digit ISBN " . $matchingFiles10[0], PEAR_LOG_INFO);
//			return $this->processImageURL($matchingFiles10[0], true);
//		}elseif (count($matchingFiles13) > 0){
//			//We found a match
//			$this->log("Found a publisher file by 13 digit ISBN " . $matchingFiles13[0], PEAR_LOG_INFO);
//			return $this->processImageURL($matchingFiles13[0], true);
//		}else{
//			//$this->log("Did not find match by isbn 10 or isbn 13, checking sub folders", PEAR_LOG_INFO);
//			//Check all subdirectories of the current folder
//			$subDirectories = array();
//			$dh             = opendir($folderToCheck);
//			if ($dh){
//				while (($file = readdir($dh)) !== false){
//
//					if (is_dir($folderToCheck . $file) && $file != '.' && $file != '..'){
//						//$this->log("Found file $file", PEAR_LOG_INFO);
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

	// These two methods aren't currently needed because of initialization done in bootstap.php
//	private function initDatabaseConnection(){
//		// Setup Local Database Connection
//		if (!defined('DB_DATAOBJECT_NO_OVERLOAD')){
//			define('DB_DATAOBJECT_NO_OVERLOAD', 0);
//		}
//		$options =& PEAR_Singleton::getStaticProperty('DB_DataObject', 'options');
//		$options = $this->configArray['Database'];
//		$this->logTime("Connect to database");
//		require_once ROOT_DIR . '/Drivers/marmot_inc/Library.php';
//	}
//
//	private function initMemcache(){
//		global $memCache;
//		if (!isset($memCache)){
//			// Set defaults if nothing set in config file.
//			$host    = isset($this->configArray['Caching']['memcache_host']) ? $this->configArray['Caching']['memcache_host'] : 'localhost';
//			$port    = isset($this->configArray['Caching']['memcache_port']) ? $this->configArray['Caching']['memcache_port'] : 11211;
//			$timeout = isset($this->configArray['Caching']['memcache_connection_timeout']) ? $this->configArray['Caching']['memcache_connection_timeout'] : 1;
//
//			// Connect to Memcache:
//			$memCache = new Memcache();
//			if (!$memCache->pconnect($host, $port, $timeout)){
//				PEAR_Singleton::raiseError(new PEAR_Error("Could not connect to Memcache (host = {$host}, port = {$port})."));
//			}
//			$this->logTime("Initialize Memcache");
//		}
//	}

//	private function makeIsbn10And13(){
//		if (!empty($this->isn) && strlen($this->isn) >= 10){
//			require_once ROOT_DIR . '/Drivers/marmot_inc/ISBNConverter.php';
//			if (strlen($this->isn) == 10){
//				//$this->log("Provided ISBN is 10 digits.", PEAR_LOG_INFO);
//				$this->isbn10 = $this->isn;
//				$this->isbn13 = ISBNConverter::convertISBN10to13($this->isbn10);
//			}elseif (strlen($this->isn) == 13){
//				//$this->log("Provided ISBN is 13 digits.", PEAR_LOG_INFO);
//				$this->isbn13 = $this->isn;
//				$this->isbn10 = ISBNConverter::convertISBN13to10($this->isbn13);
//			}
//			$this->log("Loaded isbn10 $this->isbn10 and isbn13 $this->isbn13.", PEAR_LOG_INFO);
//			$this->logTime("create isbn 10 and isbn 13");
//		}
//	}

}
