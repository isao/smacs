<?php
/**
 * SmacsFile - use a text file to create a Smacs template
 */
class SmacsFile extends Smacs
{
	public function __construct($file, $flags = 0, $context = null)
	{
		$tpl = file_get_contents($file, $flags, $context);
		if(false === $tpl) {
			throw new Exception("file $file could not be processed");
		}
		parent::__construct($tpl);
	}
}

/**
 * SmacsInclude - let PHP process a file, and use the output as a Smacs template
 */
class SmacsInclude extends Smacs
{
	public function __construct($file)
	{
		ob_start();
		require $file;
		parent::__construct(ob_get_flush());
	}
}

/**
 * Smacs - separate markup and code simply.
 *
 * @var $base     (SmacsBase object), represents the base/backing template
 * @var $nodes    (array) SmacsSlice objects, indexed by slice marker strings;
 *                method calls after a slice() call are invoked on the node
 *                element referenced by $this->pointer
 * @var $pointer  (array) queue of slice markers, used as keys in $nodes array
 * @var $filters  (int) bitmask
 */
class Smacs
{
	const KEYBRACES = 1;
	const XMLENCODE = 2;
	const KEYANDENC = 3;
	const NO_QUOTES = 4;

	protected $base;
	protected $nodes;
	protected $pointer;
	protected $filters;

	public function __construct($tpl)
	{
		$this->base    = new SmacsBase($tpl);
		$this->nodes   = array();
		$this->pointer = array();
		$this->filters = 0;
	}

	public function apply(/* arrays or objects */)
	{
		$quoteflag = $this->filters & self::NO_QUOTES ? ENT_NOQUOTES : ENT_QUOTES;
		foreach(func_get_args() as $kvs) {
			foreach($kvs as $k => $v) {
				$keys[] = $this->filters & self::KEYBRACES ? '{'.$k.'}' : $k;
				$vals[] = $this->filters & self::XMLENCODE
					? htmlspecialchars($v, $quoteflag, 'UTF-8')
					: $v;
			}
		}
		$this->filters = 0;
		$this->_lastNode()->apply($keys, $vals);
	}

	public function append($str)
	{
		$this->_lastNode()->buffer .= $str;
	}

	public function filter(/* filter strings or ints */)
	{
		foreach(func_get_args() as $arg) {
			if(is_int($arg)) {
				$this->filters |= $arg;
			} else {
				$arg = strtoupper($arg);
				if(defined(__CLASS__.'::'.$arg)) {
					$this->filters |= constant(__CLASS__.'::'.$arg);
				}
			}
		}
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
	
	/**
	 * return the object representing the most recently specified slice()
	 * @param (bool)
	 * @return (SmacsBase or SmacsSlice object)
	 */
	protected function _lastNode($preserve_stack = false)
	{
		$pointer = array_pop($this->pointer);
		if(!$preserve_stack) {
			$this->pointer = array();
		}
		return $pointer
			? $this->nodes[$pointer]
			: $this->base;
	}
}

/**
 * Helper object to represent simple, non-repeating, template data. Completely
 * encapsulated within Smacs and SmacsSlice objects
 *
 * @var $buffer (string) template containing placeholders and markers
 */
class SmacsBase
{
	public $buffer;

	public function __construct($str)
	{
		$this->buffer = $this->_checkString($str);
	}

	/**
	 * @param (array) strings to look for in template
	 * @param (array) strings to replace keys with in template
	 * @param (string) if set, template to use (and re-use) for slices
	 * @return (int) number of keys in template that were replaced
	 */
	public function apply(array $keys, array $vals, $pattern = null)
	{
		if(is_null($pattern)) {
			//modify base/backing buffer
			$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
		} else {
			//$this is a SmacsSlice: append to buffer, not overwrite
			$this->buffer.= str_replace($keys, $vals, $pattern, $count);
		}
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
 * @var $context (string) regex that obtains $pattern from $base->buffer 
 * @var $pattern (string) read-only sub-template, used with each apply()
 */
class SmacsSlice extends SmacsBase
{
	protected $context;//regex, used for SmacsBase::absorb()
	protected $pattern;//read-only template used for SmacsSlice::apply()

	public function __construct($mark, SmacsBase $base)
	{
		$mark = preg_quote($this->_checkString($mark));
		$this->context = "/$mark([\s\S]*)$mark/";
		if(preg_match($this->context, $base->buffer, $match)) {
			$this->pattern = $match[1];
			$this->buffer = '';
		} else {
			throw new Exception("slice '$mark' not found", E_USER_WARNING);
		}
	}

	public function apply(array $keys, array $vals, $ignored = null)
	{
		return parent::apply($keys, $vals, $this->pattern);
	}
}
