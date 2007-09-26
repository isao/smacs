<?php
/**
 * Smacs - aggregates encapsulated template objects
 *
 * @var $base    object class SmacsBase, represents the base/backing template
 * @var $pointer string slice marker, the key of current node in $nodes array
 * @var $nodes   array of SmacsSlice objects, indexed by slice marker strings
 * @var $filters array of callbacks to operate on replacement values
 * @see SmacsFile extends this class to accept explicit or implicit template 
 *  file references to use as the base template backing buffer.
 */
class Smacs
{
	protected $base;
	protected $pointer;
	public $nodes;
	protected $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->pointer = '';
		$this->nodes   = array();
		$this->filters = array();
	}

	public function apply(array $kvs)
	{
		$keys = array_keys($kvs);
		$vals = array_values($kvs);
		while($callback = array_pop($this->filters)) {
			$vals = array_map($callback, $vals);
		}
		$this->_buffer()->apply($keys, $vals);
	}

	public function append($str)
	{
		$this->_buffer()->buffer .= $str;
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
		if(!isset($this->nodes[$mark])) {
			$this->nodes[$mark] = new SmacsSlice($mark, $this->base);
			$this->nodes[$mark]->container($this->pointer);
		}
		$this->pointer = $mark;
		return $this;
	}
	
	public function splice($str = '')
	{
		$this->_buffer()->splice($str);
	}

	public function delete()
	{
		$this->base->prune($this->nodes[$this->pointer]);
		unset($this->nodes[$this->pointer]);
		$this->pointer = '';
	}

	public function __toString()
	{
		while($this->nodes) {
			$inner = array_pop($this->nodes);//absorb latest slice
			if($outer = end($this->nodes)) {//into the next latest
				$outer->absorb($inner);
			} else {
				$this->base->absorb($inner);//or base
			}
		}
		return $this->base->buffer;
	}

	protected function _buffer()
	{
		$pointer = $this->pointer; 
		$this->pointer = '';//reset pointer, so it must be specified each time
		return strlen($pointer)
			? $this->nodes[$pointer]
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
 * @example load a template file implicitly
 *   //from script "foo.php", loads "./foo.html"
 *   $foo = new SmacsFile();
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
			throw new Exception("could not load file <$ref>");
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
 *
 * @var $buffer string template containing placeholders and markers
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
		if(!$keys || !$vals) {
			trigger_error('apply() key/values empty', E_USER_WARNING);
			$count = 0;
		} else {
			$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
			if(!$count) {
				trigger_error('apply() found no replacements', E_USER_WARNING);
			}
		}
		return $count;
	}

	public function absorb(SmacsSlice $inner)
	{
		$this->buffer = preg_replace($inner->context, $inner->buffer, $this->buffer, 1, $ok);
		if(!$ok) {
			trigger_error('slice operation failed', E_USER_WARNING);
		}
		$inner->buffer = '';
	}

	public function prune(SmacsBase $inner)
	{
		$inner->buffer = '';
		$this->absorb($inner);
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
}

/**
 * Helper object to represent sub-sections of templates that repeat (like rows).
 * 
 * @var $context string regex that obtains $pattern from $base 
 * @var $pattern string text containing placeholders, used against each apply()
 */
class SmacsSlice extends SmacsBase
{
	public $context;
	public $pattern;

	public function __construct($mark, SmacsBase $base)
	{
		$this->context = $this->_regex($mark);
		if(preg_match($this->context, $base->buffer, $match)) {
			$this->pattern = $match[1];
			$this->buffer = '';
		} else {
			throw new Exception("slice '$mark' not found", E_USER_WARNING);
		}
	}
	
	public function container($mark)
	{
	  $this->containermark = $mark;
	}

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		return $count;
	}

	public function splice($str)
	{
		$this->buffer .= $this->pattern.$str;
	}

	protected function _regex($mark)
	{
		$mark = preg_quote($this->_checkString($mark), '/');
		return "/$mark([\s\S]+)$mark/";
	}
}

