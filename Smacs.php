<?php
class Smacs
{
	public $base;
	public $slices;
	protected $pointer;
	protected $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->slices  = array();
		$this->pointer = null;
		$this->filters = array();
	}

	public function apply(array $kvs, $addbraces = false)
	{
		$keys = $addbraces ? $this->_addBraces($kvs) : array_keys($kvs);
		$vals = array_values($kvs);
		foreach($this->filters as $callback) {
			$vals = array_map($callback, $vals);
		}			
		$this->_theBuffer()->apply($keys, $vals);
	}

	public function slice($name = null)
	{
		$this->pointer = $name;
		if(!is_null($name) && !isset($this->slices[$name])) {
			$this->slices[$name] = new SmacsSlice($name, $this->base);
		}
		return $this;
	}

	public function filter($filters)
	{
		$this->filters = (array) $filters;
		return $this;
	}

	public function splice($name)
	{
		$this->_theBuffer()->splice($this->slices[$name]);
	}

	public function __toString()
	{
		$this->_spliceAll();
		return $this->base->buffer;
	}
	
	protected function _theBuffer()
	{
		return is_null($this->pointer)
			? $this->base
			: $this->slices[$this->pointer];
	}
	
	protected function _spliceAll()
	{
		while($this->slices) {
			$inner = array_pop($this->slices);//splice latest slice
			if($outer = end($this->slices)) {//with next latest
				$outer->splice($inner);
			} else {
				$this->base->splice($inner);//or base
			}
		}
	}

	protected function _addBraces($kvs)
	{
		foreach($kvs as $k => $v) {
			$keys[] = '{'.$k.'}';
		}
		return $keys;
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
	  $ref = ($tpl=='' or is_dir($tpl)) ? $this->_impliedFile($tpl, $ext) : $tpl;
		if(is_readable($ref)) {
			parent::__construct(file_get_contents($ref));
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

class SmacsBase
{
	public $buffer;
	
	public function __construct($str)
	{
		$this->buffer = $this->_stringCheck($str);
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
		$this->_applyCheck($count);
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
	
	protected function _applyCheck($count)
	{
		if(!$count) {
			trigger_error('apply() failed, no replacements found', E_USER_WARNING);
		}
	}
}

class SmacsSlice extends SmacsBase
{
	public $context;
	public $pattern;

	public function __construct($name, SmacsBase $base)
	{
		$this->context = $this->_regex($name);
		if(!preg_match($this->context, $base->buffer, $match)) {
			throw new Exception("slice '$name' not found using expression {$this->context}", E_USER_ERROR);
		}
		$this->pattern = $match[1];
		$this->buffer  = '';
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		$this->_applyCheck($count);
	}

	protected function _regex($name)
	{
		$name = preg_quote($this->_stringCheck($name));
		return "/$name([\s\S]+)$name/i";
	}
}
