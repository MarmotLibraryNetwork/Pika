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
require_once 'Pager/Pager.php';

/**
 * VuFind Pager Class
 *
 * This is a wrapper class around the PEAR Pager mechanism to make it easier
 * to modify default settings shared by a variety of VuFind modules.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class VuFindPager {
	/** @var Pager_Sliding $pager */
	var $pager;

	/**
	 * Constructor
	 *
	 * Initialize the PEAR pager object.
	 *
	 * @param   array $options        The Pager options to override.
	 * @access  public
	 */
	public function __construct($options = array()){
		// Set default Pager options:
		$finalOptions = [
			'mode'                  => 'sliding',
			'path'                  => '',
			'delta'                 => 2,
			'perPage'               => 20,
			'nextImg'               => translate('Next') . ' <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>',
			'prevImg'               => '<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> ' . translate('Prev'),
			'separator'             => '',
			'spacesBeforeSeparator' => 0,
			'spacesAfterSeparator'  => 0,
			'append'                => false,
			'clearIfVoid'           => true,
			'urlVar'                => 'page',
			'curTag'                => 'li',
			'linkContainer'         => 'li',
			'curPageSpanPre'        => '<span>',
			'curPageSpanPost'       => '</span>',
			'curPageLinkClassName'  => 'active',
		];

		// Override defaults with user-provided values:
		foreach ($options as $optionName => $optionValue){
			$finalOptions[$optionName] = $optionValue;
		}

		// Create the pager object:
		$this->pager =& Pager::factory($finalOptions);
	}

	/**
	 * Generate the pager HTML using the options passed to the constructor.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getLinks(){
		$links        = $this->pager->getLinks();
		$allLinks     = $links['all'];
		$links['all'] = (strlen($allLinks)) ? '<ul class="pagination">' . $allLinks . '</ul>' : null;
		return $links;
	}

	public function isLastPage(){
		$currentPage = $this->pager->_currentPage;
		$totalPages  = $this->pager->_totalPages;
		return $currentPage == $totalPages;
	}

//	public function getNumRecordsOnPage() {
//		if (!$this->isLastPage()) {
//			return $this->pager->_perPage;
//		}
//		return $this->pager->_totalItems - ($this->pager->_perPage * ($this->pager->_currentPage - 1));
//	}
}
