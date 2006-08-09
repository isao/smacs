<?php
require_once '../Smacs.class.php';
require_once 'PHPUnit2/Framework/TestCase.php';

class SmacsTest extends PHPUnit2_Framework_TestCase
{
	public $so;
	public $tp;
	public $kv;
	
	public function setUp()
	{
		$this->tp = 'title: {title}, footer: {footer}';//template
		$this->kv = array("{title}"=>"t1's & t2's", "{footer}"=>"<footer>");//template data
		$this->so = new Smacs($this->tp);
	}
	
	public function testConstructor()
	{
		$this->assertEquals($this->tp, $this->so->out());
	}
	
	public function testSet()
	{
		$newtp = "new stuff\n".$this->tp."\nmore new stuff";
	  $this->so->set($newtp);
		$this->assertEquals($newtp, $this->so->out());

		$newtp = '';
	  $this->so->set($newtp);
		$this->assertEquals($newtp, $this->so->out());
	}
	
	public function testMixIn()
	{
		$expected = "title: t1's & t2's, footer: <footer>";
	  $this->so->mixIn($this->kv);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function testMixIn_SMACS_ENCODE_HTML()
	{
		$expected = "title: t1's &amp; t2's, footer: &lt;footer&gt;";
	  $this->so->mixIn($this->kv, SMACS_ENCODE_HTML);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function testMixIn_SMACS_ENCODE_SQL()
	{
		$expected = "title: t1\'s & t2\'s, footer: <footer>";
	  $this->so->mixIn($this->kv, SMACS_ENCODE_SQL);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function testMixIn_SMACS_ADD_BRACES()
	{
		$expected = "title: t1's & t2's, footer: <footer>";
		$kv = array("title"=>"t1's & t2's", "footer"=>"<footer>");//no key braces
	  $this->so->mixIn($kv, SMACS_ADD_BRACES);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function testMixIn_SMACS_ENCODE_HTML_and_SQL()
	{
		$expected = "title: t1\'s &amp; t2\'s, footer: &lt;footer&gt;";
	  $this->so->mixIn($this->kv, SMACS_ENCODE_SQL + SMACS_ENCODE_HTML);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function testMixIn_SMACS_ENCODE_ALL()
	{
		$expected = "title: t1\'s &amp; t2\'s, footer: &lt;footer&gt;";
		$kv = array("title"=>"t1's & t2's", "footer"=>"<footer>");//no key braces
		$encoders = SMACS_ADD_BRACES + SMACS_ENCODE_HTML + SMACS_ENCODE_SQL;
	  $this->so->mixIn($kv, $encoders);
		$this->assertNotEquals($this->tp, $this->so->out());
		$this->assertEquals($expected, $this->so->out());
	}

	public function textMixOut()
	{
		$expected = "title: t1's & t2's, footer: <footer>";
	  $out = $this->so->mixOut($kv);
		$this->assertNotEquals($this->tp, $out);
		$this->assertEquals($expected, $out);	
	}

	public function testToString()
	{
	  $this->assertEquals($this->tp, $this->so->__toString());
	}

}
?>