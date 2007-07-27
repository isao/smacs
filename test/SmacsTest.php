<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework/TestCase.php';

class SmacsTest extends PHPUnit_Framework_TestCase
{
	public function testApplyKvsToBase()
	{
		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => 'Smacs',
			'{body}' => 'smacs is simple',
			'{footer}' => 'page 1');

		$expected = <<<TPL

			Smacs
			==============
			smacs is simple
			--------------
			page 1

TPL;

		$so = new Smacs($tpl);
		$so->apply($kv);

		$this->assertEquals($expected, $so->__toString());
	}

	public function testFilterProccessesReplacementValues()
	{

		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&>',
			'{footer}' => 'page 1');

		$expected = <<<TPL

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1

TPL;

		$so = new Smacs($tpl);
		$so->filter('htmlentities')->apply($kv);

		$this->assertEquals($expected, $so->__toString());
	}

	public function testMultipleFiltersCanBeSpecified()
	{

		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => "prevent 'XSS'       ",
			'{body}' => '  encode "html entities" like <&>',
			'{footer}' => '    page 1   ');

		$expected = <<<TPL

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1

TPL;

		$so = new Smacs($tpl);
		$so->filter('trim', 'htmlentities')->apply($kv);

		$this->assertEquals($expected, $so->__toString());
	}

	public function testUseYourOwnFilterFunctionOrStaticMethod()
	{

		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&> in markup',
			'{footer}' => 'page 1');

		$expected = <<<TPL

			CESAGXFF
			==============
			RAXGGZYAGGFYXAZEXC
			--------------
			CW

TPL;

		$so = new Smacs($tpl);
		$so->filter('my_filter')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter(array('MyFilter', 'staticFilter'))->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

}


function my_filter($val)
{
	return str_rot13(metaphone($val));
}

class MyFilter
{
	//using non-static functions in a callback throws E_STRICT notices
	public function filter($val)
	{
		return str_rot13(metaphone($val));
	}

	static public function staticFilter($val)
	{
		return str_rot13(metaphone($val));
	}
}
