<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework.php';

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

	public function testEmptyFilterIsFilterAll()
	{
		$tpl = "{title} {mixed>}";
		$kv = array('title' => 'hey', 'mixed>' => '<&"\'> not encoded');
		$expected = 'hey <&"\'> not encoded';
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
		$so->filter('filterall')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter()->apply($kv);
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

		//multiple parameter constants
		$so = new Smacs($tpl);
		$so->filter(Smacs::KEYBRACES, Smacs::XMLENCODE)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter(Smacs::FILTERALL)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		//constants combined in a single parameter (OR'd)
		$filters = Smacs::KEYBRACES | Smacs::XMLENCODE;
		$so = new Smacs($tpl);
		$so->filter($filters)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$filters = Smacs::KEYBRACES | Smacs::XMLENCODE | Smacs::SKIPANGLE;
		$so = new Smacs($tpl);
		$so->filter($filters)->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$filters = Smacs::FILTERALL;
		$so = new Smacs($tpl);
		$so->filter($filters)->apply($kv);
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
	
	public function testFilterXmlencodeNoQuotes()
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

		$expected = "His name is O'Hara &amp; they called him \"slim\"";
		$so = new Smacs($tpl);
		$filters = (Smacs::FILTERALL ^ Smacs::KEYBRACES) | Smacs::NO_QUOTES;
		$so->filter($filters)->apply($kv);// <>&"' values quoted
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

	public function testFilterSkipAngle()
	{
		$tpl = "

			{title}
			==============
			{<body>}
			--------------
			{footer}";

		$expected = "

			Smacs
			==============
			filter 'skipangle' skips value encoding <em>simple</em>
			--------------
			page &lt;1&gt;";

		$kv = array(
			'{title}' => 'Smacs',
			'{<body>}' => "filter 'skipangle' skips value encoding <em>simple</em>",
			'{footer}' => 'page <1>',
		);

		$so = new Smacs($tpl);
		$so->filter('xmlencode', 'skipangle')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$kv = array(
			'title' => 'Smacs',
			'<body>' => "filter 'skipangle' skips value encoding <em>simple</em>",
			'footer' => 'page <1>',
		);

		$so = new Smacs($tpl);
		$so->filter('keybraces', 'xmlencode', 'skipangle')->apply($kv);
		$this->assertEquals($expected, $so->__toString());

		$so = new Smacs($tpl);
		$so->filter('filterall')->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testFilterSkipAngleSlice()
	{
		$tpl = "
			head
			<!-- row -->{a}  {<b>}  {c}
			<!-- row -->foot";
		
		$expected = "
			head
			00&gt;  <0&0>  &lt;00
			01&gt;  <0&1>  &lt;01
			02&gt;  <0&2>  &lt;02
			10&gt;  <1&0>  &lt;10
			11&gt;  <1&1>  &lt;11
			12&gt;  <1&2>  &lt;12
			20&gt;  <2&0>  &lt;20
			21&gt;  <2&1>  &lt;21
			22&gt;  <2&2>  &lt;22
			foot";

		$filters = Smacs::KEYBRACES | Smacs::XMLENCODE | Smacs::SKIPANGLE;

		$so = new Smacs($tpl);
		for($i = 0; $i < 3; $i++) {
			for($j = 0; $j < 3; $j++) {
				$kv = array(
					'a' => "$i$j>",
					'<b>' => "<$i&$j>",//these values won't be encoded, unlike the others
					'c' => "<$i$j",
				);
				$so->slice('<!-- row -->')->filter($filters)->apply($kv);
			}
		}
		$so->slice('<!-- row -->')->absorb();
		
		$this->assertEquals($expected, $so->__toString());
	}

	public function testNullStringKeysIgnored()
	{
		$tpl = "hello {world}";
		$expected = 'hello whirled';
		
		$kv = array(
			'{world}' => 'whirled',
			'' => '?',//checking that a null string key won't do anything
		);
		
		$so = new Smacs($tpl);
		$so->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

	public function testNumericIndexArrayKeysIgnored()
	{
		$tpl = "hello {world}, who's number 1?";
		$expected = "hello whirled, who's number 1?";
		
		$kv = array(
			1 => 'numeric keys ignored',//checking we ignore numeric array indexes
			'{world}' => 'whirled',
		);
		
		$so = new Smacs($tpl);
		$so->apply($kv);
		$this->assertEquals($expected, $so->__toString());
	}

}
