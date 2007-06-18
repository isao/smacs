<?php
require_once dirname(dirname(__FILE__)).'/Smacs.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Extensions/ExceptionTestCase.php';

class SmacsFileTest extends PHPUnit_Extensions_ExceptionTestCase
{	
	public function testExplicitRef()
	{
		$file = dirname(__FILE__).DIRECTORY_SEPARATOR.'SmacsFileTest.html';
		$so = new SmacsFile($file);
		
		$expected = file_get_contents($file);
		$this->assertEquals($so->__toString(), $expected);	
	}

	public function testExplicitRefException()
	{
		$this->setExpectedException('Exception');

		$file = dirname(__FILE__).DIRECTORY_SEPARATOR.'__NONESUCH__';
		$so = new SmacsFile($file);
	}
	
	public function testImpliedFilenameCwd()
	{
		$_SERVER['SCRIPT_NAME'] = __FILE__;
		$so = new SmacsFile();
		
		$expected = file_get_contents(
			dirname(__FILE__).DIRECTORY_SEPARATOR.'SmacsFileTest.html');
		$this->assertEquals($so->__toString(), $expected);	
	}
	
	
}