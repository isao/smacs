<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework/TestCase.php';

class SmacsTest extends PHPUnit_Framework_TestCase
{	
	public function testNoChangesIfNothingApplied()
	{
		$tp = 'title: {title}, footer: {footer}';

		$so = new Smacs($tp);

		$expected = $tp;
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testApplyKvsToBase()
	{
		$tp = 'title:{title}, footer:{footer}';
		$kv = array('{title}' => 'mytitle', '{footer}' => 'myfooter');

		$so = new Smacs($tp);
		$so->apply($kv);

		$expected = 'title:mytitle, footer:myfooter';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testAppendToBase()
	{
		$tp = 'title:{title}, footer:{footer}';
		$kv = array('{title}' => 'mytitle', '{footer}' => 'myfooter');

		$so = new Smacs($tp);
		$so->apply($kv);
		$so->append(' -- additional footer');

		$expected = 'title:mytitle, footer:myfooter -- additional footer';
		$this->assertEquals($so->__toString(), $expected);	
	}
	
	public function testSliceOne()
	{
		$tp = 'title:{title} -row-[{a},{b},{c}]-row- footer:{footer}';
		$kv1['{title}'] = 'mytitle';
		$kv1['{footer}'] = 'myfooter';
		$kv2['{a}'] = 'a1';
		$kv2['{b}'] = 'b1';
		$kv2['{c}'] = 'c1';

		$so = new Smacs($tp);
		$so->apply($kv1);
		$so->slice('-row-')->apply($kv2);
		
		$expected = 'title:mytitle [a1,b1,c1] footer:myfooter';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testaNestedSlice()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		
		//the target slice is "sticky" now
		//reset it w/ slice() to apply kvs to base template
		$so->slice()->apply($kv1);

		$expected = 'mytitle Z:[ 1 2 3 ]';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testSlicesNested2Deep()
	{
		$tp = '{title} -row-{letter}:[-cell-{number}-cell-] -row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->apply(array('{letter}'=>'Y'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>4));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>5));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>6));

		$expected = 'mytitle Z:[123] Y:[456] ';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testAppendToNestedSlice()
	{
		$tp = '{title} -row-{letter}:[-cell-{number}-cell-] -row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->apply(array('{letter}'=>'Y'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>4));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>5));
		$so->slice('-row-')->slice('-cell-')->append('*');

		$expected = 'mytitle Z:[123] Y:[45*] ';
		$this->assertEquals($so->__toString(), $expected);	
	}
	
	public function testSlicesNested3Deep()
	{
		$tp = '{title} -row-{letter}:[-cell-{number}-cell-] -row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->apply(array('{letter}'=>'Y'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>4));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>5));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>6));
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->apply(array('{letter}'=>'X'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>7));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>8));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>9));

		$expected = 'mytitle Z:[123] Y:[456] X:[789] ';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testOneFilter()
	{
		$tp = 'title:{title}, footer:{footer}';
		$kv = array('{title}' => "o'title", '{footer}' => 'myfooter');

		$so = new Smacs($tp);
		$so->filter('addslashes')->apply($kv);

		$expected = "title:o\\'title, footer:myfooter";
		$this->assertEquals($so->__toString(), $expected);
	}	

	public function testTwoFilters()
	{
		$tp = 'title:{title}, footer:{footer}';
		$kv = array('{title}' => "<o'title>", '{footer}' => 'myfooter');

		$so = new Smacs($tp);
		$so->filter(array('addslashes','htmlentities'))->apply($kv);

		$expected = "title:&lt;o\\'title&gt;, footer:myfooter";
		$this->assertEquals($so->__toString(), $expected);
	}	

	public function testUnusedSliceGetsDeleted()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-');
		
		$expected = 'mytitle ';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testAllUnusedSlicesGetDeleted()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->slice('-cell-');
		
		$expected = 'mytitle ';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testUnusedReferencedSliceGetsDeleted()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->slice('-cell-');;
		$so->slice('-row-')->apply(array('{letter}'=>'Z'));

		$expected = 'mytitle Z:[ ]';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testDeleteNoApply()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->delete('-row-');
		
		$expected = 'mytitle ';
		$this->assertEquals($so->__toString(), $expected);	

	}

	public function testDelete()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number} -cell-]-row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->delete('-cell-');
		
		$expected = 'mytitle Z:[ ]';
		$this->assertEquals($so->__toString(), $expected);
	}

	public function testDelete2()
	{
		$tp = '{title} -row-{letter}:[ -cell-{number}-cell- ]-del-DELETEME-del- -row-';
		$kv1['{title}'] = 'mytitle';

		$so = new Smacs($tp);
		$so->apply($kv1);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->delete('-del-');
		
		$expected = 'mytitle Z:[ 123 ] ';
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testDelete3()
	{
		$tp = '-row-{letter}:-del-

		DELETEME

		-del-[ -cell-{number}-cell- ] -row-';

		$so = new Smacs($tp);

		$so->slice('-row-')->apply(array('{letter}'=>'Z'));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>1));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>2));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>3));
		$so->slice('-row-')->delete('-del-');
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>4));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>5));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>6));
		$so->slice('-row-')->apply(array('{letter}'=>'Y'));
		$so->slice('-row-')->delete('-del-');
		$so->slice('-row-')->splice('-cell-');

		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>7));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>8));
		$so->slice('-row-')->slice('-cell-')->apply(array('{number}'=>9));
		$so->slice('-row-')->apply(array('{letter}'=>'X'));
		$so->slice('-row-')->delete('-del-');
		$so->slice('-row-')->splice('-cell-');

		$expected = 'Z:[ 123 ] Y:[ 456 ] X:[ 789 ] ';
		$this->assertEquals($so->__toString(), $expected);	
	}
}
