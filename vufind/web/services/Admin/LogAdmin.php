<?php
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
	public $logTemplate;
	public $columnToFilterBy;

	function launch(){
		global $interface;

		$logClass         = get_class($this);
		$logEntryClassName = $logClass . 'Entry';
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
			'fileName'   => '/Admin/' . $logClass . '?page=%d' . (empty($_REQUEST['filterCount']) ? '' : '&filterCount=' . $_REQUEST['filterCount']) . (empty($_REQUEST['pagesize']) ? '' : '&pagesize=' . $_REQUEST['pagesize']),
			'perPage'    => $pageSize,
		);
		$pager   = new VuFindPager($options);


		$interface->assign('recordsPerPage', $pageSize);
		$interface->assign('page', $page);
		$interface->assign('logEntries', $logEntries);
		$interface->assign('pageLinks', $pager->getLinks());

		$this->display($this->logTemplate, $this->pageTitle);
	}

}