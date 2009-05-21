<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';

$so = new SmacsFile('SmacsExample.html');

$pagedata = array(
	'{title}'=>'example smacs page',
	'{footer}'=>'etherjar.com');

//page data
if(empty($_REQUEST['msg'])) {
	$so->slice('<!message>')->delete();
} else {
	$pagedata['{msg}'] = $_REQUEST['msg'];
}

$so->filter('xmlencode')->apply($pagedata);

$so->slice('<!main>')->apply(array('{tablename}'=>'numbers'));
$so->slice('<!main>')->slice('<!row>')->apply(array('{cell0}'=>'one', '{cell1}'=>'two', '{cell2}'=>'three'));
$so->slice('<!main>')->slice('<!row>')->apply(array('{cell0}'=>'four', '{cell1}'=>'five', '{cell2}'=>'six'));
$so->slice('<!main>')->slice('<!row>')->absorb();


$so->slice('<!main>')->apply(array('{tablename}'=>'letters'));
$so->slice('<!main>')->slice('<!row>')->apply(array('{cell0}'=>'alpha', '{cell1}'=>'beta', '{cell2}'=>'gamma'));
$so->slice('<!main>')->slice('<!row>')->apply(array('{cell0}'=>'delta', '{cell1}'=>'epsilon', '{cell2}'=>'fuego'));
$so->slice('<!main>')->slice('<!row>')->absorb();

$so->slice('<!main>')->absorb();
print $so;
