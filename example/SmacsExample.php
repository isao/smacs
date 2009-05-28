<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';

$Page = new SmacsFile('SmacsExample.html');

$pagedata = array(
	'{title}'=>'example smacs page',
	'{footer}'=>'etherjar.com');

$rowdata[] = array(
	'{a}' => 'able',
	'{b}' => 'baker',
	'{c}' => 'charlie',
);
$rowdata[] = array(
	'{a}' => 'anteater',
	'{b}' => 'bugbear',
	'{c}' => 'cougar',
);
$rowdata[] = array(
	'{a}' => 'alpha',
	'{b}' => 'beta',
	'{c}' => 'gamma',
);

//page data
if(empty($_REQUEST['msg'])) {
	$Page->slice('<!message>')->delete();
} else {
	$pagedata['{msg}'] = $_REQUEST['msg'];
}
$Page->filter('xmlencode')->apply($pagedata);

//lowercase
$tabledata = array('{tablename}' => 'lowercase');
$Page->slice('<!-- main -->')->apply($tabledata);//need this before absorb()
foreach($rowdata as $row) {
	$Page->slice('<!-- main -->')->slice('<!-- row -->')->apply($row);
}
$Page->slice('<!-- main -->')->slice('<!-- row -->')->absorb();

//UPPERcase
$tabledata = array('{tablename}' => 'UPPERcase');
$Page->slice('<!-- main -->')->apply($tabledata);
foreach($rowdata as $row) {
	$row = array_map('strtoupper', $row);
	$Page->slice('<!-- main -->')->slice('<!-- row -->')->apply($row);
}
$Page->slice('<!-- main -->')->slice('<!-- row -->')->absorb();

$Page->slice('<!-- main -->')->absorb();
print $Page;
