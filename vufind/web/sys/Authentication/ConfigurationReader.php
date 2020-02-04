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

require_once 'IOException.php';
require_once 'FileParseException.php';

class ConfigurationReader {

	private $pathToConfigurationFile;
	private $configurationFileContent;
	private $sectionName;

	public function __construct($pathToConfigurationFile = '') {
		$this->setPathOfConfigurationFileIfParameterIsEmpty($pathToConfigurationFile);
		$this->checkIfConfigurationFileExists();
	}

	private function setPathOfConfigurationFileIfParameterIsEmpty($pathToConfigurationFile) {
		if (empty($pathToConfigurationFile) || $pathToConfigurationFile == '') {
			$actualPath = dirname(__FILE__);
			// Handle forward and back slashes for Windows/Linux compatibility:
			$this->pathToConfigurationFile = str_replace(array("/sys/authn", "\sys\authn"),
				array("/conf/config.ini", "\conf\config.ini"), $actualPath);

		} else {
			$this->pathToConfigurationFile = $pathToConfigurationFile;
		}
	}

	private function checkIfConfigurationFileExists() {
		clearstatcache();
		if (!file_exists($this->pathToConfigurationFile)) {
			throw new IOException('Missing configuration file ' . $this->pathToConfigurationFile . '.', 1);
		}
	}

	public function readConfiguration($sectionName) {
		$this->sectionName = $sectionName;
		try {
			$this->configurationFileContent = parse_ini_file($this->pathToConfigurationFile, true);
		} catch (Exception $exception) {
			throw new FileParseException("Error during parsing file '" . $this->pathToConfigurationFile . "'", 2);
		}

		$this->checkIfParsingWasSuccesfull();
		$this->checkIfSectionExists();
		return $this->configurationFileContent[$this->sectionName];
	}

	private function checkIfParsingWasSuccesfull() {
		if (!is_array($this->configurationFileContent)) {
			throw new FileParseException ('Could not parse configuration file ' . $this->pathToConfigurationFile . '.', 3);
		}
	}

	private function checkIfSectionExists() {
		if (empty($this->configurationFileContent[$this->sectionName])) {
			throw new UnexpectedValueException ('Section ' . $this->sectionName . ' do not exists! Could not procede.');
		}
	}
}
