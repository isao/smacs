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
		$so->slice('<-mark->')->delete();
		$this->assertEquals($expected, $so->__toString());

	}

	public function testSliceIsSameAsDeleteIfNoReplacementsAreApplied()
	{
		$tpl = "

			--------------
			hello  <-mark-> sections can be removed. you can use this approach instead
			of if/then code in your template.<-mark->
			there
			--------------";

		$expected = "

			--------------
			hello  
			there
			--------------";

		//make the slice, but do not apply anything to it-- same as delete.
		//this makes sense if you consider that slices are hoisted sub-sections of
		//the main template. the buffer starts empty, and the hoisted section, or
		//"slice" is stored as a pattern that is applied with replacement key/vals
		//to the buffer.
		$so1 = new Smacs($tpl);
		$so1->slice('<-mark->');

		//delete destroys the slice object, so that anything in it's buffer will be 
		//lost, and subsequent references to it will generate exceptions/errors
		$so2 = new Smacs($tpl);
		$so2->slice('<-mark->')->delete();

		$this->assertEquals($expected, $so1->__toString());
		$this->assertEquals($expected, $so2->__toString());
		$this->assertEquals($so1, $so2);
	}

	public function testSlicesMakeCopies()
	{
		$tpl = "

			table
			--------------
			<-row->{c1}  {c2}  {c3}  {c4}
			<-row->--------------";

		$expected = "

			table
			--------------
			11  12  13  14
			21  22  23  24
			31  32  33  34
			--------------";

		$so = new Smacs($tpl);
		
		for($i = 1; $i < 4; $i++) {
			$kv = array();
			for($j = 1; $j < 5; $j++) {
				$kv["{c$j}"] = "$i$j";
			}
			$so->slice('<-row->')->apply($kv);
		}
		$this->assertEquals($expected, $so->__toString());
	}

	public function testSlicesCanBeNested()
	{
		$tpl = "

			table
			--------------
			<-row-><-cell->{cell}{br}<-cell-><-row->
			--------------";

		$expected = "

			table
			--------------
			11  12  13  14
			21  22  23  24
			31  32  33  34
			--------------";

		$so = new Smacs($tpl);
		for($i = 1; $i < 4; $i++) {
			$kv = array();
			for($j = 1; $j < 5; $j++) {
				$kv['{cell}'] = "$i$j";
				if($j% 4) {
					$kv['{br}'] = '  ';//intra cell space
				} elseif($i == 3) {
					$kv['{br}'] = '';//last row, no space or break
				} else {
					$kv['{br}'] = "\n\t\t\t";//eol
				}
				$so->slice('<-row->')->slice('<-cell->')->apply($kv);
				#@$so->slice('<-row->')->apply(array());//throws warnings
				$so->slice('<-row->')->splice();
			}
		}
		$this->assertEquals($expected, $so->__toString());
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
