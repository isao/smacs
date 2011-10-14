<?php
set_include_path(
	dirname(dirname(__FILE__)).
	PATH_SEPARATOR.
	get_include_path()
);

require_once 'Smacs.php';

class SmacsIncludeTest extends PHPUnit_Framework_TestCase
{	
	public function testInclude()
	{
		$files = array(
			'test/test-a.html',
			'test/test-b.html',
			'test/test-c.html'
		);
		
		$expected = 'this is test file test-a.html
this is test file test-b.html
this is test file test-c.html';
		
		$S = new SmacsInclude($files);
		$this->assertEquals($expected, $S->__toString());
	}

	public function testExtractVarsOverwrite()
	{
		$expected = 'hi there';
		$S = new SmacsInclude('test/test-vars.html', array('_file' => 'there'));
		$this->assertEquals($expected, $S->__toString());
	}
}
