<?php
if(!defined('SMACS_ENCODE_HTML')) define('SMACS_ENCODE_HTML', 2);
if(!defined('SMACS_ENCODE_SQL'))  define('SMACS_ENCODE_SQL', 4);
if(!defined('SMACS_ADD_BRACES'))  define('SMACS_ADD_BRACES', 8);

class Smacs
{
	public static $keyprefix = '{';
	public static $keysuffix = '}';
	protected $out; ///!@var (string) template data

	public function __construct($s)
	{
		$this->set($s);
	}

	public function set($s)
	{
		$this->out = $s;
	}

	public function add($s)
	{
		$this->out .= $s;
	}

	public function mixIn($kv, $encode=0)
	{
		$this->out = $this->mixOut($kv, $this->out, $encode);
	}

	public function mixOut($kv, $s, $encode=0)
	{
		$k = array_keys($kv);
		if($encode & SMACS_ADD_BRACES)  $k = $this->addBraces($k);

		$v = array_values($kv);
		if($encode & SMACS_ENCODE_HTML) $v = array_map('htmlentities', $v);
		if($encode & SMACS_ENCODE_SQL)  $v = array_map('addslashes',   $v);
		return str_replace($k, $v, $s);
	}

	public function filter($kv)
	{
	  require_once dirname(__FILE__).'/Sfilter.class.php';
	  //@todo filter $kv using static methods in Sfilter class
	}

	public function addBraces($a)
	{
		foreach($a as $i) $b[] = self::$keyprefix.$i.self::$keysuffix;
		return $b;
	}

	public function out()
	{
		return $this->out;
	}

	public function __toString() {
		return $this->out();
	}

}

class SmacsFile extends Smacs
{
	public function __construct($s='', $tplext = '.tpl.html', $phpext = '.php')
	{
		$this->load($s, $tplext, $phpext);
	}

	public function load($s='', $tplext = '.tpl.html', $phpext = '.php')
	{
	  if($s == '' or is_dir($s)) $s = $this->defaultFile($s, $tplext, $phpext);
		if(is_file($s)) $this->add(file_get_contents($s));
	}

	/**
	 * Returns the default template filename using an optional path parameter.
	 *
	 * The default filename is calculated taking the calling script filename, and
	 * swapping the calling script's php extention for the tamplate extension.
	 * i.e. strip $phpext and append $tplext
	 * 
	 * If no path is provided, use the current working directory at runtime (not
	 * the path to this file).
	 *
	 * @param $d (string) path to look for default filename
	 * @param $tplext (string) extension of default template names
	 * @param $phpext (string) extension of default calling php script names
	 * @return (string) a defualt path and filename
	 */
	protected function defaultFile($d='', $tplext = '.tpl.html', $phpext = '.php')
	{
		if($d == '') {
			$d = './';
		} elseif(substr($d, -1) != DIRECTORY_SEPARATOR) {
			$d.= DIRECTORY_SEPARATOR;
		}
		return $d.basename($_SERVER['SCRIPT_NAME'], $phpext).$tplext;
	}

}

class SmacsBuffer extends Smacs
{
	public function __construct()
	{
		$files = func_get_args();
		if(count($files)) {
			foreach($files as $f) $this->bufferFile($f);
		} else {
			$this->bufferStart();
		}
	}

	public function bufferStart()
	{
	  ob_start();
	}

	public function bufferFile($f=null)
	{
		if(!is_null($f)) {
			$this->bufferStart();
			include($f);
			$this->bufferEnd();
		}
	}

	public function bufferAdd()
	{
		$this->out .= ob_get_contents();
		ob_clean();
	}

	public function bufferEnd()
	{
		while (ob_level()) {
			$this->bufferAdd();
			ob_end_clean();
		}
	}

}

class Slice extends Smacs
{
	protected $context; ///!@var (object)
	protected $rgx;     ///!@var (string) regular expression
	protected $mold;    ///!@var (string) sub template, stays clean

	public function __construct(Smacs &$context, $beg, $end=null)
	{
		$this->context = $context;
		$beg = preg_quote($beg);
		$end = $end ? preg_quote($end) : $beg;
		$this->rgx = "/$beg([\s\S]*?)$end/";
		if(!preg_match($this->rgx, $this->context->out(), $m)) {
			throw new Exception("slice pattern '$this->rgx' not found", E_USER_ERROR);
		}
		$this->mold = $m[1];
		$this->out = '';
	}

	public function mixAdd($kv, $entities=false)
	{
		$this->out.= $this->mixOut($kv, $this->mold, $entities);
	}

	public function splice()
	{
		$this->context->set(
			preg_replace($this->rgx, $this->out(), $this->context->out())
		);
	}

}
?>