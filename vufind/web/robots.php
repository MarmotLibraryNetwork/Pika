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

/**
 * Dynamic implementation of robots.txt to prevent indexing of non-production sites
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/9/14
 * Time: 11:19 AM
 */

require_once 'bootstrap.php';
global $configArray;
global $library;
if ($configArray['Site']['isProduction']){
	echo @file_get_contents('robots.txt');
	$url  = empty($library->catalogUrl) ? $configArray['Site']['url'] : $_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl;

	if (!empty($library->subdomain)){
		$subdomain = $library->subdomain;
		$fileName  = $subdomain . '.xml';

		/*
		 * sitemap: <sitemap_url>
		 * */

		echo <<<BLOCK

Sitemap: $url/sitemaps/$fileName
sitemap: $url/sitemaps/$fileName


BLOCK;
		//Google may want this with a lower case sitemap even though they specify capitalized.  Provide both.

	}
}else{
	//echo "User-agent: *\r\nDisallow: /\r\n";
}
