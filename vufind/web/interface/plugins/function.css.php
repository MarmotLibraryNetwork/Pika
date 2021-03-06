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

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.css.php
 * Type:     function
 * Name:     css
 * Purpose:  Loads a CSS file from the appropriate theme
 *           directory.  Supports two parameters:
 *              filename (required) - file to load from
 *                  interface/themes/[theme]/css/ folder.
 *              media (optional) - media attribute to
 *                  pass into <link> tag.
 * -------------------------------------------------------------
 */
function smarty_function_css($params, &$smarty)
{
	// Extract details from the config file and parameters so we can find CSS files:
	global $configArray;
	global $interface;
	$local = $configArray['Site']['local'];
	$themes = $interface->getThemes();
	$filename = $params['filename'];

	// Loop through the available themes looking for the requested CSS file:
	$css = false;
	foreach ($themes as $theme) {
		$theme = trim($theme);

		// If the file exists on the local file system, set $css to the relative
		// path needed to link to it from the web interface.
		if (file_exists("{$local}/interface/themes/{$theme}/css/{$filename}")) {
			$css = "/interface/themes/{$theme}/css/{$filename}";
			break;
		}
	}

	// If we couldn't find the file, we shouldn't try to link to it:
	if (!$css) {
		return '';
	}

	// We found the file -- build the link tag:
	$media = isset($params['media']) ? " media=\"{$params['media']}\"" : '';
	return "<link rel=\"stylesheet\" type=\"text/css\"{$media} href=\"{$css}?v=" . urlencode($interface->getVariable('gitBranch')) . "\">";
}
