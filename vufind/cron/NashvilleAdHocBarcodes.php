<?php

// TO DO: maybe include teachers with their homerooms
// TO DO: interfile opted-outs with homeroom

// SYNTAX: path/to/php NashvilleAdHocBarcodes.php $_SERVER['SERVER_NAME'], e.g., 
// $ sudo /opt/rh/php55/root/usr/bin/php NashvilleAdHocBarcodes.php nashville.test

$_SERVER['SERVER_NAME'] = $argv[1];
if(is_null($_SERVER['SERVER_NAME'])) {
	echo 'SYNTAX: path/to/php NashvilleAdHocBarcodes.php $_SERVER[\'SERVER_NAME\'], e.g., $ sudo /opt/rh/php55/root/usr/bin/php NashvilleAdHocBarcodes.php nashville.test\n';
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
array_map('unlink', glob($reportPath . "*_school_barcodes.csv"));

// connect to carlx oracle db
$conn = oci_connect($carlx_db_php_user, $carlx_db_php_password, $carlx_db_php);
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

// query school branch codes and homerooms
$sql = <<<EOT
select 
  branchcode
  , homeroomid
  , min(homeroomname) as homeroomname
  , case
    when min(grade) < max(grade)
      then replace(replace(trim(to_char(min(grade),'00')),'-01','PK'),'00','KI') || '-' || replace(replace(trim(to_char(max(grade),'00')),'-01','PK'),'00','KI')
      else replace(replace(trim(to_char(min(grade),'00')),'-01','PK'),'00','KI') || '___'
  end as grade
from (
  select distinct
    b.branchcode
    , s.street2 as homeroomid
    , nvl(regexp_replace(upper(h.name),'[^A-Z]','_'),'_NULL_') as homeroomname
    , s.bty-22 as grade
  from
    branch_v b
      left join patron_v s on b.branchnumber = s.defaultbranch
      left join patron_v h on s.street2 = h.patronid
  where
    b.branchgroup = '2'
    and s.street2 is not null
    and s.bty >= 21
    and s.bty <= 34 -- excludes non-delivery, which conceivably could cause a mess...
--and b.branchnumber < 33 -- TEST LIMIT TO Amqui, Margaret Allen, Antioch High
  order by
    b.branchcode
    , homeroomname
) a
group by branchcode, homeroomid
order by branchcode, homeroomname
EOT;
$stid = oci_parse($conn, $sql);
oci_execute($stid);
while (($row = oci_fetch_array ($stid, OCI_ASSOC)) != false) {
	$aSchoolHomeroom[] = $row;
}
oci_free_statement($stid);

// for each school branch, query patrons
foreach ($aSchoolHomeroom as $sSchoolHomeroom) {
	$sSchool = $sSchoolHomeroom['BRANCHCODE'];
	$sHomeroom = $sSchoolHomeroom['HOMEROOMID'];
	$sHomeroomName = $sSchoolHomeroom['HOMEROOMNAME'];
	$sHomeroomGrade = $sSchoolHomeroom['GRADE'];
	$sql = <<<EOT
		select
			patronbranch.branchcode
			, patronbranch.branchname
			, bty_v.btynumber AS bty
			, bty_v.btyname as grade
			, case 
					when bty = 13 
					then patron_v.name
					else patron_v.sponsor
				end as homeroom
			, case 
					when bty = 13 
					then patron_v.patronid
					else patron_v.street2
				end as homeroomid
			, patron_v.name AS patronname
			, patron_v.patronid
			, patron_v.lastname
			, patron_v.firstname
			, patron_v.middlename
			, patron_v.suffixname
		from
			branch_v patronbranch
			, bty_v
			, patron_v
		where
			patron_v.bty = bty_v.btynumber
			and patronbranch.branchnumber = patron_v.defaultbranch
			and (
				(
					patron_v.bty >= 21
					and patron_v.bty <= 37
					and patronbranch.branchcode = '$sSchool'
					and patron_v.street2 = '$sHomeroom'
				) or (
					patron_v.bty = 13
					and patron_v.patronid = '$sHomeroom'
				)
			)
		order by
			patronbranch.branchcode
			, case
				when patron_v.bty = 13 then 0
				else 1
			end
			, patron_v.sponsor
			, patron_v.name
EOT;

	$stid = oci_parse($conn, $sql);
	// consider using oci_set_prefetch to improve performance
	// oci_set_prefetch($stid, 1000);
	oci_execute($stid);
	// start a new file for the new school
	$df;
	$df = fopen($reportPath . $sSchoolHomeroom['BRANCHCODE'] . "_" . $sSchoolHomeroom['GRADE'] . "_" . $sSchoolHomeroom['HOMEROOMNAME'] . "_school_barcodes.csv", 'w');
	while (($row = oci_fetch_array ($stid, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
		// CSV OUTPUT
		fputcsv($df, $row);
	}
	fclose($df);
	//echo $sSchoolHomeroom['BRANCHCODE'] . " barcode reports written\n";
}
oci_free_statement($stid);
oci_close($conn);
?>
