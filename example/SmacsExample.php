<pre><?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';

$smacs = new SmacsFile();

//page data
if(empty($_REQUEST['msg'])) {
	$smacs->slice('<!-- special_message -->')->delete();
} else {
	$pagedata['{msg}'] = $_REQUEST['msg'];
}
$pagedata['{title}'] = 'example smacs page';
$pagedata['{footer}'] = 'etherjar.com';
$pagedata['{table_name}'] = 'sums';

// if(empty($pagedata['{msg}'])) {
// 	$smacs->slice('<!-- special_message -->')->delete();
// }

//row data
for($i = 0; $i < 4; $i++) {
	$slicedata = array('{rowno}' => "row$i");
	for($j = 0; $j < 4; $j++) {
		$slicedata["{cell$j}"] = $i + $j;
	}
	$smacs->slice('<!-- table -->')->slice('<!-- row -->')->apply($slicedata);
	$smacs->slice('<!-- table -->')->apply(array('{tablemsg}'=>'hi'));
}

$smacs->filter('htmlentities')->apply($pagedata);

//print $smacs;

foreach($smacs->nodes as $k => $v)
{
	print '<hr>.'.$v->containermark.'.<br>';
	
	//print htmlentities(print_r($v, 1));
}