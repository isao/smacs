<?php
/**
 * Smacs
 *
 * @var $base SmacsBase object represents the base template
 * @var $pointer string is index of $buffers associative array
 * @var $buffers array of SmacsSlice objects
 * @var $filters array of callbacks to operate on replacement values
 */
class Smacs
{
	protected $base;
	protected $pointer;
	protected $buffers;
	protected $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->pointer = '';
		$this->buffers = array();
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

	public function filter(/* any number of arguments */)
	{
		$this->filters = func_get_args();
		return $this;
	}

	public function slice($mark)
	{
		if(!strlen($mark)) {
			throw new Exception('slice marker empty', E_USER_ERROR);
		}
		if(!isset($this->buffers[$mark])) {
			$this->buffers[$mark] = new SmacsSlice($mark, $this->base);
		}
		$this->pointer = $mark;
		return $this;
	}

	public function splice($mark)
	{
		$this->_buffer()->absorbSlice($this->buffers[$mark]);
	}

	public function delete($mark = '')
	{
		if(strlen($mark) && !isset($this->buffers[$mark])) {
			$this->slice($mark);
		}
		$this->base->deleteSlice($this->buffers[$this->pointer]);
		unset($this->buffers[$this->pointer]);
	}

	public function __toString()
	{
		while($this->buffers) {
			$inner = array_pop($this->buffers);//absorb latest slice
			if($outer = end($this->buffers)) {//into the next latest
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
		$this->pointer = '';//reset pointer, so it must be specified each time
		return strlen($pointer)
			? $this->buffers[$pointer]
			: $this->base;
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
	public function __construct($tpl='', $ext='.html')
	{
	  $ref = ($tpl=='' or is_dir($tpl)) ? $this->_impliedFile($tpl, $ext) : $tpl;
		if(is_readable($ref)) {
			parent::__construct(file_get_contents($ref));
		} else {
			throw new Exception(sprintf("could not load $ref"));
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
		return $dir.basename($_SERVER['SCRIPT_NAME'], '.php').$ext;
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
		$this->buffer = preg_replace($inner->context, $inner->buffer, $this->buffer, 1, $ok);
		if(!$ok) {
			trigger_error('splice failed', E_USER_WARNING);
		}
		$inner->buffer = '';
	}

	public function deleteSlice(SmacsBase $inner)
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

	public function __construct($mark, SmacsBase $base)
	{
		$this->context = $this->_regex($mark);
		if(preg_match($this->context, $base->buffer, $match)) {
			$this->pattern = $this->_checkString($match[1]);
			$this->buffer  = '';
		} else {
			trigger_error("slice '$mark' not found", E_USER_WARNING);
		}
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		return $this->_checkCount($count);
	}

	protected function _regex($mark)
	{
		$mark = '\s*'.preg_quote($this->_checkString($mark)).'\s*';
		return "/$mark([\s\S]+)$mark/";
	}
}
