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
require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/sys/Recommend/RecommendationFactory.php';

/**
 * AJAX recommendation module loader
 *
 * @category Pika
 * @package  Controller_AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class Recommend extends Action
{
	/**
	 * Process incoming parameters and display recommendations.
	 *
	 * @return void
	 * @access public
	 */
	public function launch()
	{
		global $interface;

		header('Content-type: text/html');
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		$moduleName = preg_replace('/[^\w]/', '', $_REQUEST['mod']);
		$module = RecommendationFactory::initRecommendation(
		$moduleName, null, $_REQUEST['params']
		);

		if ($module) {
			$module->init();
			$module->process();
			echo $interface->fetch($module->getTemplate());
		} else {
			echo translate('An error has occurred');
		}
	}
}
