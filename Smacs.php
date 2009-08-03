<?php
/**
 * SmacsFile - get contents of a text file to create a Smacs template
 */
class SmacsFile extends Smacs
{
	/**
	 * @param (string) path to file to read
	 * @param (int) optional flags, like FILE_USE_INCLUDE_PATH
	 * @param (resource) optional stream_context_create() resource
	 * @see http://php.net/file_get_contents
	 * @see http://php.net/stream_context_create
	 */
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
	/**
	 * @param (string) path to a file to include
	 * @param (array) optional array to extract() to included file variable scope
	 */
	public function __construct($file, array $vars = array())
	{
		ob_start();
		extract($vars);
		$files = (array) $file;
		foreach($files as $f) require $f;
		parent::__construct(ob_get_clean());
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
 * @var $filters  (int) bitmask for matching XMLENCODE, KEYANDENC, etc.
 */
class Smacs
{
	const KEYBRACES = 1;
	const ADDBRACES = 1;
	const XMLENCODE = 2;
	const SKIPANGLE = 4;
	const KEYANDENC = 7;
	const FILTERALL = 7;
	const NO_QUOTES = 8;

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

	/**
	 * Apply key/value pairs to template, modifying them depending on filter(s)
	 * @see $this->filter()
	 * @param (mixed) arrays or objects having keys/values to replace in template
	 */
	public function apply(/* array(s) and/or object(s) */)
	{
		$quoteflag = $this->filters & self::NO_QUOTES ? ENT_NOQUOTES : ENT_QUOTES;
		foreach(func_get_args() as $kvs) {
			foreach($kvs as $k => $v) {
				$keys[] = $this->filters & self::ADDBRACES ? '{'.$k.'}' : $k;
				$vals[] = $this->filters & self::XMLENCODE
					&& (($this->filters ^ self::SKIPANGLE) && !strpos($k, '>'))
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

	/**
	 * Set flags to use to manipulate the keys and/or values provided by the next
	 *  $this->apply() call, before they are applied to the slice or template
	 *
	 * @param (mixed) string(s) or int(s) corresponing to this class's constants
	 *
	 * @example
	 *   $this->filter('keybraces');
	 *   $this->filter('keybraces', 'xmlencode');
	 *   $this->filter(Smacs::FILTERALL, Smacs::NO_QUOTES);
	 *   $this->filter(Smacs::FILTERALL| Smacs::NO_QUOTES);
	 *   $this->filter(Smacs::FILTERALL^ Smacs::SKIPANGLE);
	 * 
	 * @note filter behaviors
	 *    keybraces - add { and } to keys
	 *    addbraces - add { and } to keys
	 *    xmlencode - replace <&'"> with xml/html entities in values
	 *    skipangle - if xmlencode flag is set, and key contains a '>' DO NOT
	 *                replace <&'"> with xml/html entities in values
	 *    keyandenc - same as 'keybraces', 'xmlencode', 'skipangle'
	 *    filterall - same as 'keybraces', 'xmlencode', 'skipangle'
	 *    no_quotes - if xmlencode flag is set, encode only <&> characters in
	 *                values; DO NOT encode single or double quotes
	 *
	 * @note encode replacement values if 'xmlencode' flag is set, but NOT if the
	 *  key contains a '>' and the 'skipangle' flag is set
	 */
	public function filter(/* filter string(s) or int(s) */)
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
	 * @return (int) number of keys in template that were replaced
	 */
	public function apply(array $keys, array $vals)
	{
		$this->buffer = str_replace($keys, $vals, $this->buffer, $count);
		if(!$count) {
			trigger_error('apply() found no replacements', E_USER_WARNING);
		}
		return $count;
	}

	/**
	 * absorb a slice's buffer back into it's original template position
	 */
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
	protected $need2cp;//for nested slice absorb() w/out apply() first

	public function __construct($mark, SmacsBase $base)
	{
		$mark = preg_quote($this->_checkString($mark));
		$this->context = "/$mark([\s\S]*)$mark/";
		$this->need2cp = false;

		if(preg_match($this->context, $base->buffer, $match)) {
			$this->pattern = $match[1];
			$this->buffer = '';
		} else {
			throw new Exception("slice '$mark' not found", E_USER_ERROR);
		}
	}

	/**
	 * applies key/value replacements to the original slice pattern, and appends
	 * the result to the slice buffer.
	 * @param (array) strings to look for in slice pattern
	 * @param (array) strings to replace keys with in slice pattern
	 * @return (int) number of keys in slice pattern that were replaced
	 */
	public function apply(array $keys, array $vals)
	{
		$this->buffer .= str_replace($keys, $vals, $this->pattern, $count);
		if(!$count) {
			trigger_error('apply() found no replacements', E_USER_WARNING);
		}
		return $count;
	}
	
	/**
	 * absorb a slice into another slice. this is tricky because the backing slice
	 * buffer may be empty if apply() was never called. so let's set a flag if
	 * this is the case, rather than checking for a context in the buffer every
	 * time.
	 */
	public function absorb(SmacsSlice $inner)
	{
		if(!strlen($this->buffer)) {
			$this->need2cp = true;
		}
		if($this->need2cp) {
			$this->buffer .= $this->pattern;
		}
		parent::absorb($inner);
	}
}
