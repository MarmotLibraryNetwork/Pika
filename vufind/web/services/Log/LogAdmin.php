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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/24/2020
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Pager.php';

abstract class Log_Admin extends Admin_Admin {

	public $pageTitle;
	public $logTemplate = 'logTable.tpl';
	public $filterLabel = 'Min Works Processed';
	public $columnToFilterBy;


	function launch(){
		global $interface;

		$logClass         = get_class($this);
		$logEntryClassName = $logClass . 'Entry';
		require_once ROOT_DIR . '/sys/Log/'. $logEntryClassName . '.php';
		$page              = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$pageSize          = isset($_REQUEST['pagesize']) ? $_REQUEST['pagesize'] : 30; // to adjust number of items listed on a page
		$filter            = (!empty($this->columnToFilterBy) && !empty($_REQUEST['filterCount']) && ctype_digit($_REQUEST['filterCount']))
			? $this->columnToFilterBy . ' >= ' . $_REQUEST['filterCount'] : false;


		/** @var DB_DataObject $logEntry */
		$logEntry = new $logEntryClassName();
		if ($filter){
			$logEntry->whereAdd($filter); // limits total count correctly
		}
		$total = $logEntry->count();

		$logEntry = new $logEntryClassName();
		if ($filter){
			$logEntry->whereAdd($filter);
		}
		$logEntry->orderBy('startTime DESC');
		$logEntry->limit(($page - 1) * $pageSize, $pageSize);
		$logEntries = $logEntry->fetchAll();

		$options = array(
			'totalItems' => $total,
			'fileName'   => '/Log/' . $logClass . '?page=%d' . (empty($_REQUEST['filterCount']) ? '' : '&filterCount=' . $_REQUEST['filterCount']) . (empty($_REQUEST['pagesize']) ? '' : '&pagesize=' . $_REQUEST['pagesize']),
			'perPage'    => $pageSize,
		);
		$pager   = new VuFindPager($options);


		$interface->assign('filterLabel', $this->filterLabel);
		$interface->assign('recordsPerPage', $pageSize);
		$interface->assign('page', $page);
		$interface->assign('logEntries', $logEntries);
		$interface->assign('pageLinks', $pager->getLinks());
		$interface->assign('logTable', 'Log\\' . $this->logTemplate);
		$interface->assign('logType', str_replace('Log', '', $logClass));

		$this->display('log.tpl', $this->pageTitle);
	}

	function getAllowableRoles(){
		return array('opacAdmin');
	}

}
