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

require_once ROOT_DIR . '/services/Admin/Admin.php';

class DataObjectUtil {

	/**
	 * Save the object to the database (and optionally solr) based on the structure of the object
	 * Takes care of determining whether or not the object is new or not.
	 *
	 * @param array $structure
	 * @param string $dataType class name of the object represented by $structure
	 * @return array
	 */
	static function saveObject($structure, $dataType){
		global $logger;
		//Check to see if we have a new object or an exiting object to update
		/** @var DB_DataObject $object */
		$object = new $dataType();
		DataObjectUtil::updateFromUI($object, $structure);
		// if primaryKeySet is true
		$primaryKeySet = false;
		foreach ($structure as $property){
			if(isset($property['primaryKey']) && $property['primaryKey'] == true){
				$primaryKeyField = $property['property'];
				if(!empty($object->$primaryKeyField)){
					$primaryKeySet = true;
					break;
				}
			}
		}
		$validationResults           = DataObjectUtil::validateObject($structure, $object);
		$validationResults['object'] = $object;

		if ($validationResults['validatedOk']){
			//Check to see if we need to insert or update the object.
			//We can tell which to do based on whether or not the primary key is set

			if ($primaryKeySet){
				$result                      = $object->update();
				$validationResults['saveOk'] = ($result == 1);
			}else{
				$result                      = $object->insert();
				$validationResults['saveOk'] = $result;
			}
			if (!$validationResults['saveOk']){
				//TODO: Display the PEAR error (in certain circumstances only?)
				$error = &PEAR_Singleton::getStaticProperty('DB_DataObject', 'lastError');
				if (isset($error)){
					$validationResults['errors'][] = 'Save failed ' . $error->getMessage();
				}else{
					$validationResults['errors'][] = 'Save failed';
				}
			}
		}
		return $validationResults;
	}

	/**
	 * Validate that the inputs for the data object are correct prior to saving the object.
	 *
	 * @param $structure array for Object Editor's object structure
	 * @param $object - The object to validate
	 * @return array
	 */
	static function validateObject($structure, $object){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		//Do the validation
		foreach ($structure as $property){
			if (isset($property['required']) && $property['required'] == true){
				$value = $_REQUEST[$property['property']] ?? null;
				if ($value == null || strlen($value) == 0){
					$validationResults['errors'][] = $property['property'] . ' is required.';
				}
			}
			//Check to see if there is a custom validation routine
			if (!empty($property['serverValidation'])){
				$serverValidation = $property['serverValidation'];
				$propValidation   = $object->$serverValidation();
				if ($propValidation['validatedOk'] == false){
					$validationResults['errors'] = array_merge($validationResults['errors'], $propValidation['errors']);
				}
			}
			if ($property['type'] == 'partialDate'){
				$dayField   = $property['propNameDay'];
				$monthField = $property['propNameMonth'];
				$yearField  = $property['propNameYear'];
				if (!empty($object->$dayField) && $object->$dayField > 31){
					$validationResults['errors'][] = $property['label'] . ' - Invalid day of month : ' . $object->$dayField;
				}
				if (!empty($object->$monthField) && $object->$monthField > 12){
					$validationResults['errors'][] = $property['label'] . ' - Invalid month : ' .$object->$monthField;
				}
				if (!empty($object->$yearField)){
					if (strlen($object->$yearField) != 4){
						$validationResults['errors'][] = $property['label'] . ' - Please provide a four-digit year : ' . $object->$yearField;
					}elseif ($object->$yearField > date("Y")){
							$validationResults['errors'][] = $property['label'] . ' - Invalid year (in future) : '. $object->$yearField;
					}elseif (!empty($object->$dayField) && !empty($object->$monthField)
						&& !checkdate($object->$monthField, $object->$dayField, $object->$yearField)){
						$validationResults['errors'][] = $property['label'] . " - Not a valid date: {$object->$monthField}-{$object->$dayField}-{$object->$yearField}";
					}
				}

			}
		}

		//Make sure there aren't errors
		if (count($validationResults['errors']) > 0){
			$validationResults['validatedOk'] = false;
		}
		return $validationResults;
	}

	static function updateFromUI($object, $structure){
		foreach ($structure as $property){
			DataObjectUtil::processProperty($object, $property);
		}
	}

	static function processProperty($object, $property){
		global $logger;
		$propertyName = $property['property'];
		switch ($property['type']){
			case 'section':
				foreach ($property['properties'] as $subProperty){
					DataObjectUtil::processProperty($object, $subProperty);
				}
				break;
			case 'text':
			case 'enum':
			case 'hidden':
			case 'url':
			case 'email':
			case 'multiemail':
				if (isset($_REQUEST[$propertyName])){
					$str             = strip_tags(trim($_REQUEST[$propertyName]));
					$object->$propertyName = $str;
				}
				break;
			case 'textarea':
			case 'html':
			case 'folder':
			case 'crSeparated':
				if (strlen(trim($_REQUEST[$propertyName])) == 0){
					$object->$propertyName = null;
				}else{
					$object->$propertyName = trim($_REQUEST[$propertyName]);
				}
				//Strip tags from the input to avoid problems
				if ($property['type'] == 'textarea' || $property['type'] == 'crSeparated'){
					$object->$propertyName = strip_tags($object->$propertyName);
				}else{
					$allowableTags         = $property['allowableTags'] ?? '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6>';

					$object->$propertyName = strip_tags($object->$propertyName, $allowableTags);
				}
				break;
			case 'integer':
				$object->$propertyName = ctype_digit(ltrim($_REQUEST[$propertyName], '-')) ? $_REQUEST[$propertyName] : 0;
				//the ltrim makes us allow for negative integers as well. eg. -1
				break;
			case 'currency':
				if (preg_match('/\\$?\\d*\\.?\\d*/', $_REQUEST[$propertyName])){
					if (substr($_REQUEST[$propertyName], 0, 1) == '$'){
						$object->$propertyName = substr($_REQUEST[$propertyName], 1);
					}else{
						$object->$propertyName = $_REQUEST[$propertyName];
					}
				}else{
					$object->$propertyName = 0;
				}

				break;
			case 'checkbox':
				$object->$propertyName = isset($_REQUEST[$propertyName]) && $_REQUEST[$propertyName] == 'on' ? 1 : 0;
				break;
			case 'multiSelect':
				$object->$propertyName = (isset($_REQUEST[$propertyName]) && is_array($_REQUEST[$propertyName])) ? $_REQUEST[$propertyName] : [];
				break;
			case 'date':
				if (empty($_REQUEST[$propertyName]) || $_REQUEST[$propertyName] == '0000-00-00'){
					$object->$propertyName = null;
				}else{
					$dateParts             = date_parse($_REQUEST[$propertyName]);
					$time                  = $dateParts['year'] . '-' . $dateParts['month'] . '-' . $dateParts['day'];
					$object->$propertyName = $time;
				}

				break;
			case 'partialDate':
				$dayField   = $property['propNameDay'];
				$monthField = $property['propNameMonth'];
				$yearField  = $property['propNameYear'];
				if (!empty($_REQUEST[$dayField])){
					$object->$dayField = $_REQUEST[$dayField];
				}
				if (!empty($_REQUEST[$monthField])){
					$object->$monthField = $_REQUEST[$monthField];
				}
				if (!empty($_REQUEST[$yearField])){
					$object->$yearField = $_REQUEST[$yearField];
				}

				// Set the actual corresponding dateField when the date is complete
				if (!empty($object->$yearField) && !empty($object->$monthField) && !empty($object->$dayField)){
					$object->$propertyName = $object->$yearField . '-' . $object->$monthField. '-' . $object->$dayField;
				} else {
					// Ensure it is empty when the date Parts are incomplete
					// (for the case when all the date parts were present previously, but a part was removed. eg. it was incorrect)
					$object->$propertyName = null;
				}

				break;
			case 'image':
				if (isset($_REQUEST["remove{$propertyName}"])){
					$object->$propertyName = '';

				}elseif (isset($_FILES[$propertyName])){
					if (isset($_FILES[$propertyName]['error']) && $_FILES[$propertyName]["error"] == 4){
						$logger->log("No file was uploaded for $propertyName", PEAR_LOG_DEBUG);
						//No image supplied, use the existing value
					}elseif (isset($_FILES[$propertyName]['error']) && $_FILES[$propertyName]["error"] > 0){
						//return an error to the browser
						$logger->log("Error in file upload for $propertyName", PEAR_LOG_ERR);
					}elseif (in_array($_FILES[$propertyName]['type'], ['image/gif', 'image/jpeg', 'image/png'])){
						//Make sure that the type is correct (jpg, png, or gif)
						$logger->log("Processing uploaded file for $propertyName", PEAR_LOG_DEBUG);
						//Copy the full image to the files directory
						//Filename is the name of the object + the original filename
						global $configArray;
						$destFileName = $propertyName . $_FILES[$propertyName]['name'];
						if($property['customName']){
							$destFileName = $_FILES[$propertyName]['name'];
							$extension    = pathinfo($destFileName, PATHINFO_EXTENSION);
							if (isset($_REQUEST['fileName']) && $_REQUEST['fileName'] != ""){
								$customFileName = $_REQUEST['fileName'];
								if (strpos($customFileName, $extension) !== false){
									$destFileName = $customFileName;
								}else{
									$destFileName = $customFileName . '.' . $extension;
								}
							}
						}
						$destFolder   = $property['storagePath'] ?? $configArray['Site']['local'] . '/files';
						$destFullPath = $destFolder . '/original/' . $destFileName;
						$pathToThumbs = $destFolder . '/thumbnail';
						$pathToMedium = $destFolder . '/medium';
						$copyResult   = copy($_FILES[$propertyName]["tmp_name"], $destFullPath);
						$logger->log("Copied file to $destFullPath", PEAR_LOG_DEBUG);

						if ($copyResult){
							$img    = imagecreatefromstring(file_get_contents($destFullPath));
							$width  = imagesx($img);
							$height = imagesy($img);

							if (isset($property['thumbWidth'])){
								$logger->log("Creating thumbnails for $propertyName", PEAR_LOG_DEBUG);
								//Create a thumbnail if needed
								$thumbWidth = $property['thumbWidth'];
								$new_width  = $thumbWidth;
								$new_height = floor($height * ($thumbWidth / $width));

								// create a new temporary image
								$tmp_img = imagecreatetruecolor($new_width, $new_height);

								// copy and resize old image into new image
								imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

								// save thumbnail into a file
								imagejpeg($tmp_img, "{$pathToThumbs}/{$destFileName}");
							}
							if (isset($property['mediumWidth'])){
								$logger->log("Creating medium sized image for $propertyName", PEAR_LOG_DEBUG);
								//Create a medium size if needed
								$thumbWidth = $property['mediumWidth'];
								$new_width  = $thumbWidth;
								$new_height = floor($height * ($thumbWidth / $width));

								// create a new temporary image
								$tmp_img = imagecreatetruecolor($new_width, $new_height);

								// copy and resize old image into new image
								imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

								// save thumbnail into a file
								imagejpeg($tmp_img, "{$pathToMedium}/{$destFileName}");
							}
						}

						//store the actual filename
						$object->$propertyName = $destFileName;
						$logger->log("Set $propertyName to $destFileName", PEAR_LOG_DEBUG);
					}
				}

				break;
			case 'file':
				//Make sure that the type is correct (jpg, png, or gif)
				if (isset($_REQUEST["remove{$propertyName}"])){
					$object->$propertyName = '';
				}elseif (isset($_REQUEST["{$propertyName}_existing"]) && $_FILES[$propertyName]['error'] == 4){
					$object->$propertyName = $_REQUEST["{$propertyName}_existing"];
				}elseif (isset($_FILES[$propertyName])){
					if ($_FILES[$propertyName]['error'] > 0){
						//return an error to the browser
					}elseif (true){ //TODO: validate the file type
						//Copy the full image to the correct location
						//Filename is the name of the object + the original filename
						$destFileName = $_FILES[$propertyName]['name'];
						$destFolder   = $property['path'];
						$destFullPath = $destFolder . '/' . $destFileName;
						$copyResult   = copy($_FILES[$propertyName]['tmp_name'], $destFullPath);
						if ($copyResult){
							$logger->log("Copied file from {$_FILES[$propertyName]['tmp_name']} to $destFullPath", PEAR_LOG_INFO);
						}else{
							$logger->log("Could not copy file from {$_FILES[$propertyName]['tmp_name']} to $destFullPath", PEAR_LOG_ERR);
							if (!file_exists($_FILES[$propertyName]['tmp_name'])){
								$logger->log('  Uploaded file did not exist', PEAR_LOG_ERR);
							}
							if (!is_writable($destFullPath)){
								$logger->log('  Destination is not writable', PEAR_LOG_ERR);
							}
						}
						//store the actual filename
						$object->$propertyName = $destFileName;
					}
				}
				break;
			case 'password':
				if (!empty($_REQUEST[$propertyName]) && ($_REQUEST[$propertyName] == $_REQUEST[$propertyName . 'Repeat'])){
					$object->$propertyName = md5($_REQUEST[$propertyName]);
				}
				break;
			case 'oneToMany':
				//Check for deleted associations
				$deletions = $_REQUEST[$propertyName . 'Deleted'] ?? [];
				//Check for changes to the sort order
				if ($property['sortable'] == true && isset($_REQUEST[$propertyName . 'Weight'])){
					$weights = $_REQUEST[$propertyName . 'Weight'];
				}
				$values = [];
				if (isset($_REQUEST[$propertyName . 'Id'])){
					$idsToSave      = $_REQUEST[$propertyName . 'Id'];
					$existingValues = $object->$propertyName;
					$subObjectType  = $property['subObjectType'];  // the PHP Class name
					$subStructure   = $property['structure'];
					foreach ($idsToSave as $key => $id){
						//Create the subObject
						if ($id < 0 || $id == ''){
							$subObject = new $subObjectType();
							$id        = $key;
						}else{
							$subObject = $existingValues[$id];
						}

						$deleted = $deletions[$id] ?? false;
						if ($deleted == 'true'){
							$subObject->deleteOnSave = true;
						}else{
							//Update properties of each associated object
							foreach ($subStructure as $subProperty){
								$requestKey      = $propertyName . '_' . $subProperty['property'];
								$subPropertyName = $subProperty['property'];
								if (in_array($subProperty['type'], array('text', 'enum', 'integer', 'numeric', 'number', 'textarea', 'html', 'multiSelect'))){
									$subObject->$subPropertyName = $_REQUEST[$requestKey][$id];
								}elseif (in_array($subProperty['type'], array('checkbox'))){
									$subObject->$subPropertyName = isset($_REQUEST[$requestKey][$id]) ? 1 : 0;
								}elseif ($subProperty['type'] == 'date'){
									if (strlen($_REQUEST[$requestKey][$id]) == 0 || $_REQUEST[$requestKey][$id] == '0000-00-00'){
										$subObject->$subPropertyName = null;
									}else{
										$dateParts                   = date_parse($_REQUEST[$requestKey][$id]);
										$time                        = $dateParts['year'] . '-' . $dateParts['month'] . '-' . $dateParts['day'];
										$subObject->$subPropertyName = $time;
									}
								}elseif (!in_array($subProperty['type'], ['label', 'foreignKey', 'oneToMany'])){
									//echo("Invalid Property Type " . $subProperty['type']);
								}
							}
						}
						if ($property['sortable'] == true && isset($weights)){
							$subObject->weight = $weights[$id];
						}

						//Update the values array
						$values[$id] = $subObject;
					}
				}

				$object->$propertyName = $values;
				break;
		}
	}

}
