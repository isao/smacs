<?php
/**
 * Smacs - separate markup and code simply.
 *
 * @var $base    object class SmacsBase, represents the base/backing template
 * @var $pointer array of slice markers, used as keys in $nodes array
 * @var $nodes   array of SmacsSlice objects, indexed by slice marker strings
 * @var $filters array of callbacks to operate on replacement values
 *
 * @see SmacsFile  extends Smacs to populate base template from a file using
 *                 either explicit or implicit file or path references
 * @see SmacsOb    extends Smacs to populate base template from output buffer
 * @see SmacsBase  is an encapsulated representation of a base template
 * @see SmacsSlice extends SmacsBase to represent a template subsection
 */
class Smacs
{
	public $base;
	public $pointer;
	public $nodes;
	public $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->pointer = array();
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
		$this->_lastNode()->apply($keys, $vals);
	}

	public function append($str)
	{
		$this->_lastNode()->buffer .= $str;
	}

	public function filter(/* any number of arguments */)
	{
		$this->filters = func_get_args();
		return $this;
	}

	public function slice($mark)
	{
		if(!strlen($mark)) {
			throw new Exception('slice marker not specified', E_USER_ERROR);
		}
		if(!isset($this->nodes[$mark])) {
			$this->nodes[$mark] = new SmacsSlice($mark, $this->base);
		}
		$this->pointer[] = $mark;
		return $this;
	}
	
	public function absorb()
	{
		$slice = $this->_lastNode(true);
		$base  = $this->_lastNode();
		$base->absorb($slice);
	}

	public function delete()
	{
		$pointer = array_pop($this->pointer);
		$this->base->delete($this->nodes[$pointer]);
		unset($this->nodes[$pointer]);
	}

	public function __toString()
	{
		return $this->base->buffer;
	}
	
	protected function _lastNode($preserve_stack = false)
	{
		$pointer = array_pop($this->pointer);
		if(!$preserve_stack) $this->pointer = array();
		return $pointer
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

class SmacsOb extends Smacs
{
	public function __construct()
	{
	  ob_start();		
	}

	public function endOb()
	{
		parent::__construct(ob_get_flush());
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
		$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
		if(!$count) {
			trigger_error('apply() found no replacements', E_USER_WARNING);
		}
		return $count;
	}

	public function absorb(SmacsSlice $inner)
	{
		$this->buffer = preg_replace($inner->context, $inner->buffer, $this->buffer, 1, $ok);
		if(!$ok) {
			trigger_error('slice not absorbed', E_USER_WARNING);
		}
		$inner->buffer = '';
	}

	public function delete(SmacsBase $inner)
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
 * @var $context string regex that obtains $pattern from $base->buffer 
 * @var $pattern string text containing placeholders, used against each apply()
 */
class SmacsSlice extends SmacsBase
{
	public $context;//regex, used for SmacsBase::absorb()
	public $pattern;//read-only template used for SmacsSlice::apply()

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

	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		return $count;
	}

	protected function _regex($mark)
	{
		$mark = preg_quote($this->_checkString($mark));
		return "/$mark([\s\S]*)$mark/";
	}
}
