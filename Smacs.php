<?php
class Smacs
{
	protected $base;
	protected $slices;
	protected $pointer;
	protected $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->slices  = array();
		$this->pointer = null;
		$this->filters = array();
	}

	public function apply(array $kvs)
	{
		$keys = array_keys($kvs);
		$vals = array_values($kvs);
		foreach($this->filters as $callback) {
			$vals = array_map($callback, $vals);
		}
		$this->_buffer()->apply($keys, $vals);
		$this->filters = array();
	}

	public function slice($name = null)
	{
		$this->pointer = $name;
		if(!is_null($name) && !isset($this->slices[$name])) {
			$this->slices[$name] = new SmacsSlice($name, $this->base);
		}
		return $this;
	}

	public function filter($filters = null)
	{
		$this->filters = (array) $filters;
		return $this;
	}

	public function splice($name)
	{
		$this->_buffer()->absorbSlice($this->slices[$name]);
	}

	public function delete($name)
	{
		if(!isset($this->slices[$name])) {
			$pointer = $this->pointer;
			$this->slice($name);
			$this->pointer = $pointer;
		}
		$this->_buffer()->deleteSlice($this->slices[$name]);
		unset($this->slices[$name]);
	}

	public function __toString()
	{
		while($this->slices) {
			$inner = array_pop($this->slices);//absorb latest slice
			if($outer = end($this->slices)) {//into the next latest
				$outer->absorbSlice($inner);
			} else {
				$this->base->absorbSlice($inner);//or base
			}
		}
		return $this->base->buffer;
	}

	protected function _buffer()
	{
		$pointer = $this->pointer; 
		$this->pointer = null;
		return is_null($pointer)
			? $this->base
			: $this->slices[$pointer];
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
 *   $foo = new SmacsFile('./tpl/');
 */
class SmacsFile extends Smacs
{
	const PHP_EXT = '.php';

	public function __construct($tpl='', $ext='.html')
	{
	  $ref = ($tpl=='' or is_dir($tpl)) ? $this->_impliedFile($tpl, $ext) : $tpl;
		if(is_readable($ref)) {
			parent::__construct(file_get_contents($ref));
		} else {
			throw new Exception(sprintf("could not load <%s> at <%s>", $tpl == '' ? 'implied' : $tpl, $ref));
		}
	}

	protected function _impliedFile($dir, $ext)
	{
		if($dir == '') {
			$dir = getcwd();
		}
		if(substr($dir, -1) != DIRECTORY_SEPARATOR) {
			$dir .= DIRECTORY_SEPARATOR;
		}
		return $dir.basename($_SERVER['SCRIPT_NAME'], SmacsFile::PHP_EXT).$ext;
	}
}

/**
 * Helper object to represent simple, non-repeating, template data. Completely
 * encapsulated within Smacs and SmacsSlice objects
 */
class SmacsBase
{
	public $buffer;

	public function __construct($str)
	{
		$this->buffer = $this->_checkString($str);
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
		return $this->_checkCount($count);
	}

	public function absorbSlice(SmacsSlice $inner)
	{
		$this->buffer = preg_replace($inner->context, $inner->buffer, $this->buffer, 1);
		$inner->buffer = '';
	}

	public function deleteSlice(SmacsSlice $inner)
	{
		$inner->buffer = '';
		$this->absorbSlice($inner);
	}

	protected function _checkString($str)
	{
		if(!is_string($str)) {
			throw new Exception('expected string, not '.gettype($str));
		} elseif(!strlen($str)) {
			trigger_error('empty string', E_USER_NOTICE);
		}
		return $str;
	}

	protected function _checkCount($count)
	{
		if(!$count) {
			trigger_error('apply() failed, no replacements made', E_USER_WARNING);
		}
		return (int) $count;
	}
}

/**
 * Helper object to represent sub-sections of templates that repeat (like rows).
 */
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
		$this->pattern = $this->_checkString($match[1]);
		$this->buffer  = '';
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		return $this->_checkCount($count);
	}

	protected function _regex($name)
	{
		$name = preg_quote($this->_checkString($name));
		return "/$name([\s\S]+)$name/";
	}
}
