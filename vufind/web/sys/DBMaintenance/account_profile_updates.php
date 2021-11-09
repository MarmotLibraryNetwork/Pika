<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2021  Marmot Library Network
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
 * account_profile_updates.php
 *
 * @category Pika
 * @package
 * @author   Chris Froese
 *
 */
 
 
function getAccountProfileUpdates() {
	return array(
		'login_method_2021.04' => [
			'title'           => 'Add \"Library Based\" to Login Configuration options.',
			'description'     => 'Add \"Library Based\" to Login Configuration options.',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE account_profiles ' .
				"CHANGE COLUMN loginConfiguration loginConfiguration ENUM('barcode_pin', 'name_barcode', 'library_based') NOT NULL DEFAULT 'name_barcode';",
			],
		],
	);
}