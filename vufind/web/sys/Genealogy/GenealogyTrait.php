<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 3/2/2020
 *
 */
trait GenealogyTrait {

	function formatPartialDate($day, $month, $year){
		$months        = [
			1  => 'January',
			2  => 'February',
			3  => 'March',
			4  => 'April',
			5  => 'May',
			6  => 'June',
			7  => 'July',
			8  => 'August',
			9  => 'September',
			10 => 'October',
			11 => 'November',
			12 => 'December'
		];
		$formattedDate = '';
		if ($month > 0){
			$formattedDate = $months[$month];
		}
		if ($day > 0){
			if (strlen($formattedDate) > 0){
				$formattedDate .= ' ';
			}
			$formattedDate .= $day;

		}
		if ($year > 0){
			if (strlen($formattedDate) > 0 && $day > 0){
				$formattedDate .= ',';
			}
			$formattedDate .= ' ' . $year;
		}
		return $formattedDate;
	}

	function getImageUrl($size = 'small'){
		return $this->picture ? '/genealogyImage.php?image=' . $this->picture . '&size=' . $size : '/interface/themes/default/images/person.png';
	}

}