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
 * File:     function.img__ssign.php
 * Type:     function
 * Name:     css
 * Purpose:  Loads an image source from the appropriate theme
 *           directory. and assigns to a variable Supports two parameters:
 *              filename (required) - file to load from
 *                  interface/themes/[theme]/images/ folder.
 * -------------------------------------------------------------
 */
function smarty_function_img_assign($params, &$smarty)
{
	// Extract details from the config file and parameters so we can find CSS files:
	global $configArray;
	global $interface;
	$local = $configArray['Site']['local'];
	
	$themes = $interface->getThemes();
	$filename = $params['filename'];

	// Loop through the available themes looking for the requested CSS file:
	foreach ($themes as $theme) {
		$theme = trim($theme);
		
		// If the file exists on the local file system, set $css to the relative
		// path needed to link to it from the web interface.
		if (file_exists("{$local}/interface/themes/{$theme}/images/{$filename}")) {
			$smarty->assign($params['var'], "/interface/themes/{$theme}/images/{$filename}");
			return;
		}
	}
	
	//Didn't find a theme specific image, try the images directory
	if (file_exists("{$local}/images/{$filename}")) {
		$smarty->assign($params['var'], "/images/{$filename}");
		return;
	}

	// We couldn't find the file, return an empty value:
}
