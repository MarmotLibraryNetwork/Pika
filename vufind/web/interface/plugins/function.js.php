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

/**
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.js.php
 * Type:     function
 * Name:     js
 * Purpose:  Loads a JS file from the appropriate theme
 *           directory.  Supports one parameter:
 *              filename (required) - file to load from
 *                  interface/themes/[theme]/js/ folder.
 * -------------------------------------------------------------
 *
 * @param array  $params  Incoming parameter array
 * @param Smarty|UInterface|object &$smarty Smarty object
 *
 * @return string        <script> tag for including Javascript
 */ // @codingStandardsIgnoreStart
function smarty_function_js($params, &$smarty){
	// @codingStandardsIgnoreEnd
	// Extract details from the config file, Smarty interface and parameters
	// so we can find CSS files:
	global $configArray;

	$local = $configArray['Site']['local'];
	$themes = explode(',', $smarty->getVuFindTheme());
	$themes[] = 'default';
	$filename = $params['filename'];

	// Loop through the available themes looking for the requested JS file:
	$js = false;
	foreach ($themes as $theme) {
		$theme = trim($theme);

		// If the file exists on the local file system, set $js to the relative
		// path needed to link to it from the web interface.
		if (file_exists("{$local}/interface/themes/{$theme}/js/{$filename}")) {
			$js = "/interface/themes/{$theme}/js/{$filename}";
			break;
		}
	}

	// If we couldn't find the file, check the global Javascript area; if that
	// still doesn't help, we shouldn't try to link to it:
	if (!$js) {
		if (file_exists("{$local}/js/{$filename}")) {
			$js = "/js/{$filename}";
		} else {
			return '';
		}
	}

	// We found the file -- build the script tag:
//	global $interface;
	return "<script type=\"text/javascript\" src=\"{$js}?v=" . urlencode($smarty->getVariable('gitBranch')) . "\"></script>";
}
