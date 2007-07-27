<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework/TestCase.php';

class SmacsTest extends PHPUnit_Framework_TestCase
{
	public function testApplyKvsToBase()
	{
		$tpl = "

			{title}
			==============
			{body}
			--------------
			{footer}";

		$kv = array(
			'{title}' => 'Smacs',
			'{body}' => 'smacs is simple',
			'{footer}' => 'page 1');

		$expected = "

			Smacs
			==============
			smacs is simple
			--------------
			page 1";

		$so = new Smacs($tpl);
		$so->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testFiltersProccessReplacementValues()
	{

		$tpl = "

			{title}
			==============
			{body}
			--------------
			{footer}";

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&>',
			'{footer}' => 'page 1');

		$expected = "

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1";

		$so = new Smacs($tpl);
		$so->filter('htmlentities')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testMultipleFiltersCanBeSpecified()
	{

		$tpl = "

			{title}
			==============
			{body}
			--------------
			{footer}";

		$kv = array(
			'{title}' => "prevent 'XSS'       ",
			'{body}' => '  encode "html entities" like <&>',
			'{footer}' => '    page 1   ');

		$expected = "

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1";

		$so = new Smacs($tpl);
		$so->filter('trim', 'htmlentities')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testUseYourOwnFilterFunctionOrStaticMethod()
	{

		$tpl = "

			{title}
			==============
			{body}
			--------------
			{footer}";

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&> in markup',
			'{footer}' => 'page 1');

		$expected = "

			CESAGXFF
			==============
			RAXGGZYAGGFYXAZEXC
			--------------
			CW";

		$so = new Smacs($tpl);
		$so->filter('my_filter_function')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter(array('MyFilterClass', 'staticFunc'))->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testDelete()
	{
		$tpl = "

			--------------
			hello
			<-mark->

			sections can be removed. you can use this approach instead of if/then
			code in your template.

			<-mark->
			there
			--------------";

		$expected = "

			--------------
			hello
			there
			--------------";

		$so = new Smacs($tpl);
		$so->delete('<-mark->');
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->slice('<-mark->')->delete();
		$this->assertEquals($expected, $so->__toString());

	}

	public function testSliceDelete()
	{

	}

	public function testSplicingAnEmptySliceIsSameAsDelete()
	{

	}

}


function my_filter_function($val)
{
	return str_rot13(metaphone($val));
}

class MyFilterClass
{
	//using non-static functions in a callback throws E_STRICT notices
	public function filter($val)
	{
		return my_filter_function($val);
	}

	static public function staticFunc($val)
	{
		return my_filter_function($val);
	}
}
