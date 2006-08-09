<?php

//phpunit SmacsTest SmacsTest.php

require_once '../Smacs.class.php';
require_once 'PHPUnit2/Framework/TestCase.php';

class SmacsTest extends PHPUnit2_Framework_TestCase
{
	public $so;
	public $tp = '
title: {title}
<!-- row -->{c1}, {c2}, {c3}
<!-- row -->
footer: {foot}
';
	public $kvs = array('{title}'=>'t1 & t2', '{foot}'=>'<footer>');
	
	public function setUp()
	{
		$this->so = new Smacs($this->tp);
	}
	
	public function testConstructor()
	{
		$this->assertEquals($this->tp, $this->so->out);
	}
	
	public function testSet()
	{
		$newtp = "new stuff\n".$this->tp."\nmore new stuff";
	  $this->so->set($newtp);
		$this->assertEquals($newtp, $this->so->out);
	}
	
	public function testMixIn()
	{
	  $kvs = array('{title}'=>'t1 & t2', '{foot}'=>'<footer>');

		$this->assertEquals($this->tp, $this->so->out);

	  $this->so->mixIn($kvs);

		$this->assertNotEquals($this->tp, $this->so->out);

		$expected = '
title: t1 & t2
<!-- row -->{c1}, {c2}, {c3}
<!-- row -->
footer: <footer>
';
		$this->assertEquals($expected, $this->so->out);
	}
	
	public function testMixInWithEncoding()
	{
	  $kvs = array('{title}'=>'t1 & t2', '{foot}'=>'<footer>');

		$this->assertEquals($this->tp, $this->so->out);

	  $this->so->mixIn($kvs, SMACS_ENCODE_HTML);

		$this->assertNotEquals($this->tp, $this->so->out);

		$expected = '
title: t1 &amp; t2
<!-- row -->{c1}, {c2}, {c3}
<!-- row -->
footer: &lt;footer&gt;
';
		$this->assertEquals($expected, $this->so->out);
	}

	public function toString()
	{
	  $this->assertEquals($this->tp, $this->so);
	  $this->assertEquals($this->tp, "{$this->so}");
	}

}

/*$page =& new Smac("
title {title}
<!-- row -->{c1}, {c2}, {c3}
<!-- row -->
foot {foot}");
$page->mixIn(array('{title}'=>'mytitle', '{foot}'=>'myfoot'));

$rows =& new Slice($page, '<!-- row -->');
for($i=0; $i<3; $i++) {
	for($j=0; $j<4; $j++) {
		$rows->mixAdd(array('{c1}'=>"($i,$j)", '{c2}'=>"($i,$j)", '{c3}'=>"($i,$j)"));
	}
	$rows->splice();
}
$rows->splice();

print '<pre>';
print htmlentities($page->__toString());
*/

?>