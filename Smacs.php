<?php
class Smacs
{
	public $base;
	public $slices;
	protected $pointer;
	protected $k_encoders;
	protected $v_encoders;

	public function __construct($tpl)
	{
		$this->base       = new SmacsBase($tpl);
		$this->slices     = array();
		$this->pointer    = null;
		$this->k_encoders = array();
		$this->v_encoders = array();
	}

	public function filters($callbacks, $for_values = true)
	{
		if($for_values) {
			$this->k_encoders = (array) $callbacks;
		} else {
			$this->v_encoders = (array) $callbacks;
		}
		return $this;
	}

	public function slice($name = null)
	{
		$this->pointer = $name;
		if(!isset($this->slices[$name]) && !is_null($name)) {
			$this->slices[$name] = new SmacsSlice($name, $this->base);
		}
		return $this;
	}

	public function apply(array $kvs)
	{
		$this->_theBuffer()->apply(array_keys($kvs), array_values($kvs));
	}

	public function splice($name)
	{
		$this->_theBuffer()->splice($this->slices[$name]);
	}

	public function __toString()
	{
		$this->_spliceSlices();
		return $this->base->buffer;
	}
	
	protected function _theBuffer($name = false)
	{
		if($name === false) {
			$name = $this->pointer;
		}
		return is_null($name)
			? $this->base
			: $this->slices[$name];
	}
	
	protected function _spliceSlices()
	{
		while($this->slices) {
			$inner = array_pop($this->slices);
			if($outer = end($this->slices)) {
				$outer->splice($inner);
			} else {
				$this->base->splice($inner);
			}
		}
	}
	
	protected function _encode(array $arr, array $callbacks)
	{
		error_log('callback array:'.print_r($callbacks, 1));
		foreach($callbacks as $callback) {
			print "$callback, ".$arr['{table_name}'];
			$arr = array_map($callback, $arr);
		}
		return $arr;
	}
}

class SmacsBase
{
	public $buffer;
	
	public function __construct($str)
	{
		$this->buffer = $this->_stringCheck($str);
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer = str_replace($keys, $vals, $this->buffer);
	}

	public function splice(SmacsSlice $inner)
	{
		$this->buffer = preg_replace($inner->context, $inner->buffer, $this->buffer);
		$inner->buffer = '';
	}

	protected function _stringCheck($str)
	{
		if(!is_string($str)) {
			throw new Exception('expected string, not '.gettype($str));
		} elseif(!strlen($str)) {
			trigger_error('empty string', E_USER_NOTICE);
		}
		return $str;
	}
}

class SmacsSlice extends SmacsBase
{
	public $name;
	public $context;
	public $pattern;

	public function __construct($name, SmacsBase $base)
	{
		$this->name    = $this->_stringCheck($name);
		$this->context = $this->_regex($name);
		if(!preg_match($this->context, $base->buffer, $match)) {
			throw new Exception("slice '$name' not found using expression {$this->context}", E_USER_ERROR);
		}
		$this->pattern = $match[1];
		$this->buffer  = '';
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern);
	}

	protected function _regex($name)
	{
		$name = preg_quote($name);
		return "/$name([\s\S]+)$name/i";
	}
}

/**
 * Specify a Smacs template from the filesystem, either explicitly, or based on
 * the calling script's filename.
 *
 * @example load a template file explicitly
 *   //from script "foo.php", loads "./tpl/bar.html"
 *   $foo = new SmacsFile('./tpl/bar.html');
 *
 * @example load a template file implicitly
 *   //from script "foo.php", loads "./foo.html"
 *   $foo = new SmacsFile();
 *
 * @example load a template file implicitly, from a specified path
 *   //in script "foo.php", loads "./tpl/foo.html"
 *   $foo = new SmacsFile('./tpl');
 */
class SmacsFile extends Smacs
{
	const PHP_EXT = '.php';

	/**
	 * @param (string) template filepath, or path, or empty string for implied
	 * @param (string) if template location is implied, this is it's extension
	 */
	public function __construct($tpl='', $ext='.html')
	{
		parent::__construct($this->_getFileContents($tpl, $ext));
	}

	protected function _getFileContents($tpl, $ext)
	{
	  $ref = ($tpl=='' or is_dir($tpl)) ? $this->_impliedFile($tpl, $ext) : $tpl;
		if(is_readable($ref)) {
			return file_get_contents($ref);
		} else {
			throw new Exception("could not load file '$tpl' from '$ref'");
		}
	}

	protected function _impliedFile($dir, $ext)
	{
		if($dir == '') {
			$dir = './';
		} elseif(substr($dir, -1) != DIRECTORY_SEPARATOR) {
			$dir .= DIRECTORY_SEPARATOR;
		}
		return $dir.basename($_SERVER['SCRIPT_NAME'], SmacsFile::PHP_EXT).$ext;
	}
}
