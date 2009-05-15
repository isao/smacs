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

		$expected = "

			Smacs
			==============
			smacs is simple
			--------------
			page 1";

		$kv = array(
			'{title}' => 'Smacs',
			'{body}' => 'smacs is simple',
			'{footer}' => 'page 1');

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

		$expected = "

			prevent &#039;XSS&#039;
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1";

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&>',
			'{footer}' => 'page 1');

		$so = new Smacs($tpl);
		$so->filter('htmlspecialchars')->apply($kv);
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

		$expected = "

			prevent 'XSS'
			==============
			encode &quot;html entities&quot; like &lt;&amp;&gt;
			--------------
			page 1";

		$kv = array(
			'{title}' => "prevent 'XSS'       ",
			'{body}' => '  encode "html entities" like <&>',
			'{footer}' => '    page 1   ');

		$so = new Smacs($tpl);
		$so->filter('trim', 'htmlentities')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testEmptyFilterIsNop()
	{
		$tpl = "{title}";
		$kv = array('{title}' => 'hey');
		$expected = "hey";
		$so = new Smacs($tpl);
		$so->filter()->apply($kv);
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

		$expected = "

			CESAGXFF
			==============
			RAXGGZYAGGFYXAZEXC
			--------------
			CW";

		$kv = array(
			'{title}' => "prevent 'XSS'",
			'{body}' => 'encode "html entities" like <&> in markup',
			'{footer}' => 'page 1');

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
			<!mark>

			sections can be removed. you can use this approach instead of if/then
			code in your template.

			<!mark>
			there
			--------------";

		$expected = "

			--------------
			hello
			
			there
			--------------";

		$so = new Smacs($tpl);
		$so->slice('<!mark>')->delete();
		$this->assertEquals($expected, $so->__toString());
	}
	
	public function testAbsorbingEmptySliceIsDeleting()
	{
		$tpl = "

			--------------
			hello
			<!mark>

			sections can be removed. you can use this approach instead of if/then
			code in your template.

			<!mark>
			there
			--------------";

		$expected = "

			--------------
			hello
			
			there
			--------------";

		$so = new Smacs($tpl);
		$so->slice('<!mark>')->absorb();
		$this->assertEquals($expected, $so->__toString());
	}

	public function testAppend()
	{		
		$tpl = "

			{owner}'s pet names
			<!pets>
			--{type}--
			<!names>{name} <!names>
			<!pets>
			====";
		
		$expected = "

			joni's pet names
			
			--dogs--
			snoopy lassie 
			
			--cats--
			garfield sammy *sammy was a cool cat
			
			--fish--
			nemo 
			
			==== *blah blah blah";
		
		$so = new Smacs($tpl);
		$so->slice('<!pets>')->apply(array('{type}'=>'dogs'));

		$so->slice('<!pets>')->slice('<!names>')->apply(array('{name}'=>'snoopy'));
		$so->slice('<!pets>')->slice('<!names>')->apply(array('{name}'=>'lassie'));
		$so->slice('<!pets>')->slice('<!names>')->absorb();

		$so->slice('<!pets>')->apply(array('{type}'=>'cats'));
		$so->slice('<!pets>')->slice('<!names>')->apply(array('{name}'=>'garfield'));
		$so->slice('<!pets>')->slice('<!names>')->apply(array('{name}'=>'sammy'));

		$so->slice('<!pets>')->slice('<!names>')->append("*sammy was a cool cat");

		$so->slice('<!pets>')->slice('<!names>')->absorb();

		$so->slice('<!pets>')->apply(array('{type}'=>'fish'));
		$so->slice('<!pets>')->slice('<!names>')->apply(array('{name}'=>'nemo'));
		$so->slice('<!pets>')->slice('<!names>')->absorb();

		$so->slice('<!pets>')->absorb();

		$so->apply(array('{owner}'=>'joni'));
		
		$so->append(' *blah blah blah');

		$this->assertEquals($expected, $so->__toString());
	}
	public function testSliceAbsorb()
	{		
		$tpl = "

			head
			
			dogs: <!d>{dog} <!d>
			cats: <!c>{cat} <!c>
			fish: <!f>{fish} <!f>
			
			foot";
		
		$expected = "

			head
			
			dogs: snoopy lassie 
			cats: garfield sammy 
			fish: nemo 
			
			foot";
		
		$so = new Smacs($tpl);
		$so->slice('<!d>')->apply(array('{dog}'=>'snoopy'));
		$so->slice('<!d>')->apply(array('{dog}'=>'lassie'));
		$so->slice('<!d>')->absorb();

		$so->slice('<!c>')->apply(array('{cat}'=>'garfield'));
		$so->slice('<!c>')->apply(array('{cat}'=>'sammy'));
		$so->slice('<!c>')->absorb();

		$so->slice('<!f>')->apply(array('{fish}'=>'nemo'));
		$so->slice('<!f>')->absorb();

		$this->assertEquals($expected, $so->__toString());
	}


	public function testSliceSplice()
	{		
		$tpl = "

			{owner}'s pet names
			<!pets>
			--{type}--
			<!petname>{name} <!petname>
			<!pets>
			====";
		
		$expected = "

			joni's pet names
			
			--dogs--
			snoopy lassie 
			
			--cats--
			garfield sammy 
			
			--fish--
			nemo 
			
			====";
		
		$so = new Smacs($tpl);
		$so->slice('<!pets>')->apply(array('{type}'=>'dogs'));
		$so->slice('<!pets>')->slice('<!petname>')->apply(array('{name}'=>'snoopy'));
		$so->slice('<!pets>')->slice('<!petname>')->apply(array('{name}'=>'lassie'));
		$so->slice('<!pets>')->slice('<!petname>')->absorb();

		$so->slice('<!pets>')->apply(array('{type}'=>'cats'));
		$so->slice('<!pets>')->slice('<!petname>')->apply(array('{name}'=>'garfield'));
		$so->slice('<!pets>')->slice('<!petname>')->apply(array('{name}'=>'sammy'));
		$so->slice('<!pets>')->slice('<!petname>')->absorb();

		$so->slice('<!pets>')->apply(array('{type}'=>'fish'));
		$so->slice('<!pets>')->slice('<!petname>')->apply(array('{name}'=>'nemo'));
		$so->slice('<!pets>')->slice('<!petname>')->absorb();

		$so->slice('<!pets>')->absorb();

		$so->apply(array('{owner}'=>'joni'));

		$this->assertEquals($expected, $so->__toString());
	}

	public function testSliceFilterApply()
	{
		$tpl = "

			head
			
			dogs: <!d>{dog} <!d>
			cats: <!c>{cat} <!c>
			fish: <!f>{fish} <!f>
			
			foot";
		
		$expected = "

			head
			
			dogs: SNOOPY LASSIE 
			cats: GARFIELD FLUFFY BOOTSIE CALVIN 
			fish: NEMO 
			
			foot";
		
		$so = new Smacs($tpl);
		$so->slice('<!d>')->filter('strtoupper')->apply(array('{dog}'=>'snoopy'));
		$so->slice('<!d>')->filter('strtoupper')->apply(array('{dog}'=>'lassie'));
		$so->slice('<!d>')->absorb();

		$so->slice('<!c>')->filter('strtoupper')->apply(array('{cat}'=>'garfield'));
		$so->slice('<!c>')->filter('strtoupper')->apply(array('{cat}'=>'fluffy'));
		$so->slice('<!c>')->filter('strtoupper')->apply(array('{cat}'=>'bootsie'));
		$so->slice('<!c>')->filter('strtoupper')->apply(array('{cat}'=>'calvin'));
		$so->slice('<!c>')->absorb();

		$so->slice('<!f>')->filter('strtoupper')->apply(array('{fish}'=>'nemo'));
		$so->slice('<!f>')->absorb();

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
