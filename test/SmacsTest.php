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

		$this->assertEquals($so->__toString(), $expected);
	}

	public function testAppendToBase()
	{
		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => 'Smacs*',
			'{body}' => 'smacs is simple',
			'{footer}' => 'page 1');

		$expected = <<<TPL

			Smacs*
			==============
			smacs is simple
			--------------
			page 1

			* Seperate Code And Markup Simply
TPL;

		$so = new Smacs($tpl);
		$so->apply($kv);
		$so->append("\n\t\t\t* Seperate Code And Markup Simply");

		$this->assertEquals($so->__toString(), $expected);
	}

	public function testSingleFilter()
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

		$this->assertEquals($so->__toString(), $expected);
	}

	public function testMultipleFilters()
	{

		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		$kv = array(
			'{title}' => "prevent \\'XSS\\'",
			'{body}' => 'encode \\"html entities\\" like <&>',
			'{footer}' => '\\\\page 1\\\\');

		$filters = array('stripslashes', 'htmlentities');

		$expected = <<<TPL

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			\page 1\

TPL;

		$so = new Smacs($tpl);
		$so->filter($filters)->apply($kv);

		$this->assertEquals($so->__toString(), $expected);
	}

	public function testUserFunctionFilter()
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

			.prevent 'XSS'
			==============
			.encode "html entities" like &lt;&amp;&gt; in markup
			--------------
			.page 1

TPL;

		$so = new Smacs($tpl);
		$so->filter('my_filter')->apply($kv);

		$this->assertEquals($so->__toString(), $expected);
	}

	public function testAddBracesToKeys()
	{
		$tpl = <<<TPL

			{title}
			==============
			{body}
			--------------
			{footer}

TPL;

		//no braces in keys
		$kv = array(
			'title' => 'Smacs',
			'body' => 'smacs is simple',
			'footer' => 'page 1');

		$expected = <<<TPL

			Smacs
			==============
			smacs is simple
			--------------
			page 1

TPL;

		$so = new Smacs($tpl);
		$so->apply($kv, true);//add braces to each key in supplied assoc array

		$this->assertEquals($so->__toString(), $expected);
	}

}


function my_filter($val)
{
	return '.'.htmlentities($val, ENT_NOQUOTES);
}



