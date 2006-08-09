<?php
require_once '../Smacs.class.php';
require_once 'PHPUnit2/Framework/TestCase.php';

class SliceTest extends PHPUnit2_Framework_TestCase
{

	public function testSlice()
	{
		$template = '
 ====top====
<!-- row -->
|{c1}|{c2}|{c3}|
<!-- row -->
 ====bot====';
 		$expected = '
|1,1|1,2|1,3|
|2,1|2,2|2,3|
|3,1|3,2|3,3|';
	  $page = new Smacs($template);
	  $rows = new Slice($page, "\n<!-- row -->");
		for($i=1; $i<4; $i++) {
			for($j=1; $j<4; $j++) {
				$kvs["{c$j}"] = "$i,$j";
			}
			$rows->mixAdd($kvs);
		}
		$this->assertEquals($expected, $rows->out());//accumulated changes in slice
		$this->assertEquals($template, $page->out());//page template unchanged
	}

	public function testSplice()
	{
		$template = '
 ====top====
<!-- row -->
|{c1}|{c2}|{c3}|
<!-- row -->
 ====bot====';
 		$expected = '
 ====top====
|1,1|1,2|1,3|
|2,1|2,2|2,3|
|3,1|3,2|3,3|
 ====bot====';
	  $page = new Smacs($template);
	  $rows = new Slice($page, "\n<!-- row -->");
		for($i=1; $i<4; $i++) {
			for($j=1; $j<4; $j++) {
				$kvs["{c$j}"] = "$i,$j";
			}
			$rows->mixAdd($kvs);
		}
		$rows->splice($page);
		$this->assertEquals($expected, $page->out());//page template changed
	}

}
?>