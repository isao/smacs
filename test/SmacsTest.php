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
		$so->filter('xmlencode')->apply($kv);
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

	public function testFilterKeys()
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
			'title' => 'Smacs',
			'body' => 'smacs is simple',
			'footer' => 'page 1');

		$so = new Smacs($tpl);
		$so->filter('keybraces')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testFilterKeysAndEncode()
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
			smacs is &quot;simple&quot; &lt;&gt;&amp;
			--------------
			page 1";

		$kv = array(
			'title' => 'Smacs',
			'body' => 'smacs is "simple" <>&',
			'footer' => 'page 1');

		$so = new Smacs($tpl);
		$so->filter('keybraces', 'xmlencode')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter('keyandenc')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter('keyandenc', 'keybraces', 'xmlencode')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testFilterUsingConstants()
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
			smacs is &quot;simple&quot; &lt;&gt;&amp;
			--------------
			page 1";

		$kv = array(
			'title' => 'Smacs',
			'body' => 'smacs is "simple" <>&',
			'footer' => 'page 1');

		$so = new Smacs($tpl);
		$so->filter(Smacs::KEYBRACES, Smacs::XMLENCODE)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter(Smacs::KEYANDENC)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter(Smacs::KEYANDENC, Smacs::KEYBRACES, Smacs::XMLENCODE)->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testFilterGetsResetAftereachApply()
	{
		$tpl = "
			
			head

			<!-- row -->{a} {b} {c}
			<!-- row -->
			foot";

		$expected = '
			
			head

			&quot;oh&quot; &quot;hai&quot; &lt;&amp;&gt;
			"oh" "hai" <&>
			
			foot';

		$so = new Smacs($tpl);

		$kv = array(
			'{a}' => '"oh"',
			'{b}' => '"hai"',
			'{c}' => '<&>',
		);

		$so->slice('<!-- row -->')->filter('xmlencode')->apply($kv);
		$so->slice('<!-- row -->')->apply($kv);
		$so->slice('<!-- row -->')->absorb();
		$this->assertEquals($expected, $so->__toString());
	}
	
	public function testXmlencodeQuoteFilter()
	{
		$tpl = "His name is {name} {and} they called him {nickname}";
		
		$kv = array(
			'{name}'     => "O'Hara",
			'{and}'      => '&',
			'{nickname}' => '"slim"',
		);
		
		$expected = "His name is O'Hara & they called him \"slim\"";
		$so = new Smacs($tpl);
		$so->apply($kv);//no filter
		$this->assertEquals($expected, $so->__toString());

		$expected = "His name is O&#039;Hara &amp; they called him &quot;slim&quot;";
		$so = new Smacs($tpl);
		$so->filter('xmlencode')->apply($kv);// <>&"' values quoted
		$this->assertEquals($expected, $so->__toString());		

		$expected = "His name is O'Hara &amp; they called him \"slim\"";
		$so = new Smacs($tpl);
		$so->filter('xmlencode', 'no_quotes')->apply($kv);// <>&"' values quoted
		$this->assertEquals($expected, $so->__toString());
	}
	
	public function testApplyTakesArbitraryArrayOrObjArguments()
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
		);
		
		$ob = (object) array(
			'{body}' => 'smacs is simple',
			'{footer}' => 'page 1');

		$so = new Smacs($tpl);
		$so->apply($kv, $ob);
		$this->assertEquals($expected, $so->__toString());
	}

}
