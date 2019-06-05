<?php

// TO DO: copy pika config > [catalog|school] > basic display > additional css from galacto to production

// SYNTAX: path/to/php NashvilleAdHocOverdues.php $_SERVER['SERVER_NAME'], e.g., 
// $ sudo /opt/rh/php55/root/usr/bin/php NashvilleAdHocOverdues.php nashville.test

$_SERVER['SERVER_NAME'] = $argv[1];
if(is_null($_SERVER['SERVER_NAME'])) {
	echo 'SYNTAX: path/to/php NashvilleAdHocOverdues.php $_SERVER[\'SERVER_NAME\'], e.g., $ sudo /opt/rh/php55/root/usr/bin/php NashvilleAdHocOverdues.php nashville.test\n';
	exit();
}

global $errorHandlingEnabled;
$errorHandlingEnabled = true;

$startTime = microtime(true);
require_once '../web/sys/Logger.php';
require_once '../web/sys/PEAR_Singleton.php';
PEAR_Singleton::init();

require_once '../web/sys/ConfigArray.php';
require_once 'PEAR.php';

// Sets global error handler for PEAR errors
PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'utilErrorHandler');

// Read Config Pwd file
$configArray = readConfig();

$carlx_db_php = $configArray['Catalog']['carlx_db_php'];
$carlx_db_php_user = $configArray['Catalog']['carlx_db_php_user'];
$carlx_db_php_password = $configArray['Catalog']['carlx_db_php_password'];

$reportPath = preg_replace('/[^\/]$/','$0/',$configArray['Site']['reportPath']);

// delete old files
array_map('unlink', glob($reportPath . "*_school_report.csv"));

// connect to carlx oracle db
$conn = oci_connect($carlx_db_php_user, $carlx_db_php_password, $carlx_db_php);
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

// query school branch codes
$sql = <<<EOT
	select branch_v.branchcode
	from branch_v
	where branch_v.branchgroup = '2'
	order by branch_v.branchcode
EOT;
$stid = oci_parse($conn, $sql);
oci_execute($stid);
while (($row = oci_fetch_array ($stid, OCI_ASSOC)) != false) {
	$aSchool[] = $row['BRANCHCODE'];
}
oci_free_statement($stid);

// for each school branch, query overdues
foreach ($aSchool as $sSchool) {
	//echo $sSchool . "\n";

	$sql = <<<EOT
with
  i as (
    select
      transitem_v.patronid
      , branch_v.branchgroup as SYSTEM
      , item_v.cn as Call_#
      , bbibmap_v.title as Title
      , to_char(jts.todate(transitem_v.dueornotneededafterdate),'MM/DD/YYYY') as Due_Date
      , item_v.price as Owed
      , to_char(jts.todate(transitem_v.dueornotneededafterdate),'MM/DD/YYYY') as Due_Date_Dup
      , item_v.item as Item
      from transitem_v
      left join item_v on transitem_v.item = item_v.item
      left join bbibmap_v on item_v.bid = bbibmap_v.bid
      left join branch_v on item_v.owningbranch = branch_v.branchnumber
      where
        transitem_v.transcode in ('C','L','O')
  ), 
  p as (
    select
	    branch_v.branchcode as Home_Lib_Code
	    , branch_v.branchname as Home_Lib
	    , patron_v.bty as P_Type
	    , bty_v.btyname as Grd_Lvl
	    , ( case
	      when patron_v.sponsor is null and bty_v.btynumber in ('13','40') then (patron_v.lastname || ', ' || patron_v.firstname)
	      else patron_v.sponsor
	      end
	    ) as Home_Room
      , patron_v.name as Patron_Name
      , patron_v.patronid as P_Barcode
	    , ( case
	      when patron_v.sponsor is null and bty_v.btynumber in ('13','40') then (patron_v.patronid)
	      when patron_v.sponsor is not null then (patron_v.street2)
	      end
	    ) as sponsorid
	from patron_v
	inner join branch_v on patron_v.defaultbranch = branch_v.branchnumber
    inner join bty_v on patron_v.bty = bty_v.btynumber
	where 
	  branch_v.branchcode = '$sSchool'
	  and branch_v.branchgroup = 2
	  and patron_v.bty in ('13','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','40','42','46','47')
  ),
-- In order to sort LL no delivery PTYPES with their classmates and have homerooms sorted by grade for Elementary/Middle schools,
-- get a count of students per bty per sponsor, set the most common bty for the sponsor as the homeroom grade.
-- There has GOT to be a better way...
  g as (
    select
      sponsorid
      , P_Type
      , count(P_Barcode) as studentcount
    from p
    where p.P_Type between 21 and 34 
    group by
      sponsorid,
      P_Type
    order by
      sponsorid,
      P_Type      
  ), 
  homeroom_grade as (
    select
      sponsorid
      , min(P_Type) as P_Type
    from (
      select
        sponsorid
        , P_Type
        , studentcount
        , max(studentcount) over (partition by sponsorid) as rmax_studentcount
      from g
      order by
        sponsorid
        , studentcount
        , P_Type
    )
    where studentcount = rmax_studentcount
    group by sponsorid
    order by sponsorid
  )
select
  Home_Lib_Code
  , Home_Lib
  , p.P_Type
  , Grd_Lvl
  , Home_Room
  , Patron_Name
  , P_Barcode
  , SYSTEM
  , Call_#
  , Title
  , Due_Date
  , Owed
  , Due_Date_Dup
  , i.Item
from
  p inner join i on p.P_Barcode = i.patronid
  full outer join homeroom_grade on p.sponsorid = homeroom_grade.sponsorid
where 
  Home_Lib_Code is not null
order by
  homeroom_grade.P_Type
  , Home_Room
  , Patron_Name
EOT;

	$stid = oci_parse($conn, $sql);
	// consider using oci_set_prefetch to improve performance
	// oci_set_prefetch($stid, 1000);
	oci_execute($stid);
	// start a new file for the new school
	$df;
	$df = fopen($reportPath . $sSchool . "_school_report.csv", 'w');
	$header = false;
	while (($row = oci_fetch_array ($stid, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
		if ($header == false) {
			$header = array_keys($row);;
			fputcsv($df, $header);
		} 
		// CSV OUTPUT
		fputcsv($df, $row);
	}
	fclose($df);
	//echo $sSchool . " overdue report written\n";
}
oci_free_statement($stid);
oci_close($conn);
?>
