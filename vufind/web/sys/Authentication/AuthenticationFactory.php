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

require_once 'UnknownAuthenticationMethodException.php';

class AuthenticationFactory {

	static function initAuthentication($authNHandler, $additionalInfo = array()){
		switch(strtoupper($authNHandler)){
			case "LDAP":
				require_once 'LDAPAuthentication.php';
				return new LDAPAuthentication($additionalInfo);
			case "DB":
				require_once 'DatabaseAuthentication.php';
				return new DatabaseAuthentication($additionalInfo);
			case "SIP2":
				require_once 'SIPAuthentication.php';
				return new SIPAuthentication($additionalInfo);
			case "ILS":
				require_once 'ILSAuthentication.php';
				return new ILSAuthentication($additionalInfo);
			default:
				throw new UnknownAuthenticationMethodException('Authentication handler ' + $authNHandler + 'does not exist!');
		}
	}
}
