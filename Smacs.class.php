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

	public function mixOut(&$kv, &$s, $encode=0)
	{
		$k = array_keys($kv);
		if($encode & SMACS_ADD_BRACES)  $k = $this->addBraces($k);

		$v = array_values($kv);
		if($encode & SMACS_ENCODE_HTML) $v = array_map('htmlentities', $v);
		if($encode & SMACS_ENCODE_SQL)  $v = array_map('addslashes',   $v);
		return str_replace($k, $v, $s);
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
	public static $tplext = '.tpl.html';
	public static $phpext = '.php';

	public function __construct($s='')
	{
		$this->load($s);
	}

	/**
	 * Append a file to the template; if a path or empty string is supplied, use a
	 * default filename.
	 * @param $s (string) file, path, or empty string.
	 */
	public function load($s='')
	{
	  if($s == '' or is_dir($s)) $s = $this->defaultFile($s);
		if(is_file($s)) $this->add(file_get_contents($s));
	}

	/**
	 * Gets the default template filename using an optional path parameter. If no
	 * path is provided, use the path to the calling php script.
	 * The default name is calculated taking the calling script filename, and
	 * swapping extensions/suffixes; i.e. self::$phpext for self::$tplext.
	 * @param $d (string) path to look for default filename
	 * @return (string) a defualt path and filename
	 */
	protected function defaultFile($d)
	{
		if($d == '') {
			$d = './';
		} elseif(substr($d, -1) != DIRECTORY_SEPARATOR) {
			$d.= DIRECTORY_SEPARATOR;
		}
		return $d.basename($_SERVER['SCRIPT_NAME'], self::$phpext).self::$tplext;
	}

}

class SmacsBuffer extends Smacs
{
	public $oblevel;

	public function __construct()
	{
		$this->oblevel = ob_get_level();
		$files = func_get_args();
		if(count($files)) {
			foreach($files as $f) $this->bufferFile($f);
		} else {
			$this->bufferStart();
		}
	}

	public function bufferStart()
	{
	  if($this->oblevel < ob_get_level()) ob_start();
	}

	public function bufferFile($f=null)
	{
		if(!is_null($f)) {
			$this->bufferStart();
			include($f);
			$this->bufferIn();
			ob_end_clean();
		}
	}

	public function bufferIn()
	{
		$this->out .= ob_get_contents();
	}

	public function bufferEnd()
	{
		while($this->oblevel < ob_get_level()) $this->out .= ob_end_clean();
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
			throw new Exception("slice pattern '$this->rgx' not found in template\n", E_USER_ERROR);
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