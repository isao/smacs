<?php
if(!defined('SMACS_ENCODE_HTML')) define('SMACS_ENCODE_HTML', 2);

class Smacs
{
	public $out; ///! (string)
	
	public function __construct($s)
	{
		$this->set($s);
	}
	
	public function set($s)
	{
		$this->out = $s; 
	}
	
	public function mixIn($kv, $encode=null)
	{
		$this->out = $this->mixOut($kv, $this->out, $encode); 
	}
	
	public function mixOut($kv, $s, $encode)
	{
		$k = array_keys($kv);
		$v = array_values($kv);
		if($encode == SMACS_ENCODE_HTML) {
			$v = array_map('htmlentities', array_values($kv));
		}
		return str_replace($k, $v, $s);
	}
	
	public function __toString() {
		return $this->out;
	}
}

class SmacsFile extends Smacs
{
	public static $tplext = '.html';
	public static $phpext = '.php';

	public function __construct($s='')
	{
	  if($s == '' or is_dir($s)) $s = $this->defaultfile($s);
		$this->set(file_get_contents($s));
	}

	public function defaultfile($p='')
	{
		return $p.basename($_SERVER['SCRIPT_NAME'], self::$phpext).self::$tplext;
	}
}

class SmacsBuffer extends Smacs
{
	public function __construct($f=null)
	{
		ob_start();
		$this->bufferFile($f);
	}

	public function bufferFile($f=null)
	{
		if(!is_null($f) and readfile($f) !== false) {
			$this->out .= ob_get_contents($f);
			ob_end_clean();
		}
	}
}

class Slice extends Smacs
{
	public    $context; ///! (object)
	protected $rgx;     ///! (string) regular expression
	protected $mold;    ///! (string) sub template

	public function __construct(Smacs &$context, $beg, $end=null)
	{
		$this->context = $context;
		$beg = preg_quote($beg);
		$end = $end ? preg_quote($end) : $beg;
		$this->rgx = "/$beg([\s\S]*?)$end/i";
		if(!preg_match($this->rgx, $this->context->out, $m)) {
			throw new Exception("slice pattern `$this->rgx` not found in template\n", E_USER_ERROR);
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
		$this->context->out = preg_replace($this->rgx, $this->out, $this->context->out);
	}
}
?>