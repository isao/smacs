<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework/TestCase.php';

class SmacsFileTest extends PHPUnit_Framework_TestCase
{	
	public function testExplicitRef()
	{
		$file = dirname(__FILE__).'/SmacsFileTest.html';
		$so = new SmacsFile($file);
		
		$expected = file_get_contents($file);
		$this->assertEquals($expected, $so->__toString());	
	}

	public function testExplicitRefException()
	{
		$this->setExpectedException('Exception');
		$file = dirname(__FILE__).'/__NONESUCH__';
		$so = new SmacsFile($file);//exception thrown
	}
	
	public function testImpliedFileFromCwd()
	{
		$_SERVER['SCRIPT_NAME'] = __FILE__;//pretend this file is invoking Smacs

		$so = new SmacsFile();
		$expected = file_get_contents(dirname(__FILE__).'/SmacsFileTest.html');
		$this->assertEquals($expected, $so->__toString());
	}

	public function testImpliedFileFromPath()
	{
		$_SERVER['SCRIPT_NAME'] = __FILE__;//pretend this file is invoking Smacs

		$so = new SmacsFile(dirname(__FILE__));
		$expected = file_get_contents(dirname(__FILE__).'/SmacsFileTest.html');
		$this->assertEquals($expected, $so->__toString());
	}
}