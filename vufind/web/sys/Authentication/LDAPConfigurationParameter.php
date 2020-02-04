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

require_once 'ConfigurationReader.php';

class LDAPConfigurationParameter {

	private $ldapParameter;

	public function __construct($configurationFilePath = '') {
		$this->configurationFilePath = $configurationFilePath;
	}

	public function getParameter() {
		$this->getFullSectionParameters();
		$this->checkIfMandatoryParametersAreSet();
		$this->convertParameterValuesToLowercase();
		return $this->ldapParameter;
	}

	private function getFullSectionParameters() {
		$configurationReader = new ConfigurationReader($this->configurationFilePath);
		$this->ldapParameter = $configurationReader->readConfiguration("LDAP");
	}

	private function checkIfMandatoryParametersAreSet() {
		if (empty($this->ldapParameter['host']) ||
			empty($this->ldapParameter['port']) ||
			empty($this->ldapParameter['basedn']) ||
			empty($this->ldapParameter['username'])
		) {
			throw new InvalidArgumentException("One or more LDAP parameter are missing. Check your config.ini!");
		}
	}

	private function convertParameterValuesToLowercase() {
		foreach ($this->ldapParameter as $index => $value) {
			// Don't lowercase the bind credentials -- they may be case sensitive!
			if ($index != 'bind_username' && $index != 'bind_password') {
				$this->ldapParameter[$index] = strtolower($value);
			}
		}
	}


}

?>
