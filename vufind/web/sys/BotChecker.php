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
class BotChecker{

	static $isBot = null;
	/**
	 *
	 * Determines if the current request appears to be from a bot
	 */
	public static function isRequestFromBot(){
		if (BotChecker::$isBot == null){
			global $pikaLogger;
			global $timer;
			global $memCache;
			global $configArray;
			if (isset($_SERVER['HTTP_USER_AGENT'])){
				$userAgent = $_SERVER['HTTP_USER_AGENT'];
			}else{
				//No user agent passed, assume it is a bot
				return true;
			}

			$isBot = $memCache->get("bot_by_user_agent_" . $userAgent);
			if ($isBot === FALSE){
				global $serverName;
				if (file_exists('../../sites/' . $serverName . '/conf/bots.ini')){
					$fhnd = fopen('../../sites/' . $serverName . '/conf/bots.ini', 'r');
				}elseif (file_exists('../../sites/default/conf/bots.ini')){
					$fhnd = fopen('../../sites/default/conf/bots.ini', 'r');
				}else{
					$pikaLogger->error("Did not find bots.ini file, cannot detect bots");
					return false;
				}

				$isBot = false;
				while (($curAgent = fgets($fhnd, 4096)) !== false) {
					//Remove line separators
					$curAgent = str_replace("\r", '', $curAgent);
					$curAgent = str_replace("\n", '', $curAgent);
					if (strcasecmp($userAgent, $curAgent) == 0 ){
						$isBot = true;
						break;
					}
				}
				fclose($fhnd);

				$memCache->set("bot_by_user_agent_" . $userAgent, ($isBot ? 'TRUE' : 'FALSE'), 0, $configArray['Caching']['bot_by_user_agent']);
				if ($isBot){
					$pikaLogger->debug("$userAgent is a bot");
				}else{
					$pikaLogger->debug("$userAgent is not a bot");
				}
				BotChecker::$isBot = $isBot;
			}else{
				//$pikaLogger->debug("Got bot info from memcache $isBot");
				BotChecker::$isBot = ($isBot === 'TRUE');
			}

			$timer->logTime("Checking isRequestFromBot");
		}
		return BotChecker::$isBot;
	}
}
