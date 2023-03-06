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
 * Updates related to list widgets
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:45 PM
 */

function getListWidgetUpdates(){
	return array(
		'list_widgets' => array(
			'title' => 'Setup Configurable List Widgets',
			'description' => 'Create list widgets tables',
			'sql' => array(
				"CREATE TABLE IF NOT EXISTS list_widgets (" .
				"`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"`name` VARCHAR(50) NOT NULL, " .
				"`description` TEXT, " .
				"`showTitleDescriptions` TINYINT DEFAULT 1, " .
				"`onSelectCallback` VARCHAR(255) DEFAULT '' " .
				") ENGINE = MYISAM COMMENT = 'A widget that can be displayed within VuFind or within other sites' ",
				"CREATE TABLE IF NOT EXISTS list_widget_lists (" .
				"`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"`listWidgetId` INT NOT NULL, " .
				"`weight` INT NOT NULL DEFAULT 0, " .
				"`displayFor` ENUM('all', 'loggedIn', 'notLoggedIn') NOT NULL DEFAULT 'all', " .
				"`name` VARCHAR(50) NOT NULL, " .
				"`source` VARCHAR(500) NOT NULL, " .
				"`fullListLink` VARCHAR(500) DEFAULT '' " .
				") ENGINE = MYISAM COMMENT = 'The lists that should appear within the widget' ",
			),
		),

		'list_widgets_update_1' => array(
			'title' => 'List Widget List Update 1',
			'description' => 'Add additional functionality to list widgets (auto rotate and single title view)',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `autoRotate` TINYINT NOT NULL DEFAULT '0'",
				"ALTER TABLE `list_widgets` ADD COLUMN `showMultipleTitles` TINYINT NOT NULL DEFAULT '1'",
			),
		),

		'list_widgets_update_2' => array(
			'title' => 'List Widget Update 2',
			'description' => 'Add library id to list widget',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `libraryId` INT(11) NOT NULL DEFAULT '-1'",
			),
		),

		'list_widgets_home' => array(
			'title' => 'List Widget Home',
			'description' => 'Create the default homepage widget',
			'sql' => array(
				"INSERT INTO list_widgets (name, description, showTitleDescriptions, onSelectCallback) VALUES ('home', 'Default example widget.', '1','')",
				"INSERT INTO list_widget_lists (listWidgetId, weight, source, name, displayFor) VALUES ('1', '1', 'highestRated', 'Highest Rated', 'all')",
				"INSERT INTO list_widget_lists (listWidgetId, weight, source, name, displayFor) VALUES ('1', '2', 'recentlyReviewed', 'Recently Reviewed', 'all')",
			),
		),

		'list_wdiget_list_update_1' => array(
			'title' => 'List Widget List Source Length Update',
			'description' => 'Update length of source field to accommodate search source type',
			'sql' => array(
				"ALTER TABLE `list_widget_lists` CHANGE `source` `source` VARCHAR( 500 ) NOT NULL "
			),
		),

		'list_wdiget_update_1' => array(
			'title' => 'Update List Widget 1',
			'description' => 'Update List Widget to allow custom css files to be included and allow lists do be displayed in dropdown rather than tabs',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `customCss` VARCHAR( 500 ) NOT NULL ",
				"ALTER TABLE `list_widgets` ADD COLUMN `listDisplayType` ENUM('tabs', 'dropdown') NOT NULL DEFAULT 'tabs'"
			),
		),

		'list_widget_update_2' => array(
			'title' => 'Update List Widget 2',
			'description' => 'Update List Widget to add vertical widgets',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `style` ENUM('vertical', 'horizontal', 'single') NOT NULL DEFAULT 'horizontal'",
				"UPDATE `list_widgets` SET `style` = 'single' WHERE showMultipleTitles = 0",
			),
		),

		'list_widget_update_3' => array(
			'title' => 'List Widget Update 3',
			'description' => 'New functionality for widgets - ratings, cover size, new display option',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `coverSize` ENUM('small', 'medium') NOT NULL DEFAULT 'small'",
				"ALTER TABLE `list_widgets` ADD COLUMN `showRatings` TINYINT NOT NULL DEFAULT '0'",
				"ALTER TABLE `list_widgets` CHANGE `style` `style` ENUM('vertical', 'horizontal', 'single', 'single-with-next') NOT NULL DEFAULT 'horizontal'",
				"ALTER TABLE `list_widgets` ADD COLUMN `showTitle` TINYINT NOT NULL DEFAULT '1'",
				"ALTER TABLE `list_widgets` ADD COLUMN `showAuthor` TINYINT NOT NULL DEFAULT '1'",
			),
		),

		'list_widget_update_4' => array(
			'title' => 'List Widget Update 4',
			'description' => 'Additional options for ',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `showViewMoreLink` TINYINT NOT NULL DEFAULT '0'",
				"ALTER TABLE `list_widgets` ADD COLUMN `viewMoreLinkMode` ENUM('covers', 'list') NOT NULL DEFAULT 'list'",
			),
		),

		'list_widget_style_update' => array(
			'title' => 'List Widget Style Update',
			'description' => 'Add Text-Only List as a style option.',
			'sql' => array(
				"ALTER TABLE `list_widgets` CHANGE `style` `style` ENUM('vertical', 'horizontal', 'single', 'single-with-next', 'text-list') NOT NULL DEFAULT 'horizontal'",
				"ALTER TABLE `list_widgets` COMMENT = 'A widget that can be displayed within Pika or within other sites'",
			),
		),

		'list_widget_update_5' => array(
			'title' => 'List Widget Update 5',
			'description' => 'Switch for displaying or not displaying a widget\'s title bar.',
			'sql' => array(
				"ALTER TABLE `list_widgets` ADD COLUMN `showListWidgetTitle` TINYINT NOT NULL DEFAULT '1'",
			),
		),

			'list_widget_num_results' => array(
					'title' => 'List Widget Number of titles to show',
					'description' => 'Add the ability to determine how many results should be shown for a list.',
					'sql' => array(
							"ALTER TABLE `list_widgets` ADD COLUMN `numTitlesToShow` INT NOT NULL DEFAULT '25'",
					),
			),

	);
}
