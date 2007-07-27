<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';

$smacs = new SmacsFile();

//page data
$pagedata['{msg}'] = @$_REQUEST['msg'];
$pagedata['{title}'] = 'example smacs page';
$pagedata['{footer}'] = 'etherjar.com';
$pagedata['{table_name}'] = 'sums';

if(!empty($pagedata['{msg}'])) {
	$smacs->delete('<!-- special_message -->');
}

//row data
for($i = 0; $i < 4; $i++) {
	$slicedata = array('{rowno}' => $i);
	for($j = 0; $j < 4; $j++) {
		$slicedata["{cell$j}"] = $i + $j;
	}
	$smacs->slice('<!-- row -->')->apply($slicedata);
}

$smacs->filter('htmlentities')->apply($pagedata);
print $smacs;