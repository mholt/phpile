<?php

/**
 *	By Matthew Holt
 * 	
 * 	A practical and efficient prefix tree structure which is never
 *	loaded into memory; all lookups are done by the filesystem.
 * 	
 *	Requires PHP 5.4+
**/


class FileTrie
{
	
	/** 	Functional Constants	**/


	// If using a real file extension, must include "."
	const DEFAULT_FILE_SUFFIX = ".json";

	// Default path to trie root; do NOT include trailing slash; relative to cwd
	const DEFAULT_ROOT = "./data";

	// Default maximum length of node names
	const DEFAULT_MAX_PIECE_LEN = 3;

	// Name of the serialized FileTrie object file
	const OBJECT_FILENAME = "filetrie";
	
	// Name of the directory/node name for the root of the trie; should be "root"
	const ROOT_NODE = "root";

	// Maximum length of a single file or directory name on most file systems
	const MAX_FILENAME_LEN = 255;

	// Maximum length of key used for file path; 0 for no limit
	const DEFAULT_MAX_KEY_LEN = 20;
	
	// Default random pool factor; if randomized results, the limit is multiplied
	// by this to determine the size of the pool from which to randomly draw results
	const DEFAULT_RANDOM_POOL_FACTOR = 10;




	/** 	Sorting Constants	**/

	const SORT_NONE			= 0;	// Returned in the order found during the trie traversal
	const SORT_RANDOM		= 1;	// Returns results in a randomized order according to the random pool factor
	const SORT_COUNT_ASC	= 2;	// Sorts by occurrences in the trie, lowest to highest
	const SORT_COUNT_DESC	= 3;	// Sorts by occurrences in the trie, highest to lowest
	const SORT_KEY_ASC		= 4;	// Sorts by key, ascending
	const SORT_KEY_DESC		= 5;	// Sorts by key, descending
	const SORT_VALUE_ASC	= 6;	// Sorts by value, ascending
	const SORT_VALUE_DESC	= 7;	// Sorts by value, descending
	



	/** 	Instance Fields		**/

	private $piecelen = self::DEFAULT_MAX_PIECE_LEN;	// Maximum length of each piece of the key on the filesystem
	private $suffix = self::DEFAULT_FILE_SUFFIX;		// File suffix or extension
	private $rootpath = self::DEFAULT_ROOT;				// Path to the directory of this FileTrie
	private $keycount = 0;								// Only counts unique, distinct keys
	public $data;										// Any arbitrary data you wish to attach to this trie
	public $randompoolfactor = self::DEFAULT_RANDOM_POOL_FACTOR;	// Usually 10-100; larger numbers make lookups slower but with more interesting results
	private $fskeylen = self::DEFAULT_MAX_KEY_LEN;		// Maximum length of sanitized key which is used on the filesystem
	private $internal;									// For other internal use; not to be serialized into the object file!
	



	
	/**
	 * Creates a new FileTrie if it doesn't already exist, or loads an existing one
	 * @var $rt 	The path to the root node (not including the "/root" part) - empty for default
	 * @var $len 	The key piece length - 0 for default
	 * @var $klen	The maximum key length as used on the filesystem - 0 for default
	 * @var $suf	File suffix or extension
	 **/
	public function __construct($rt = self::DEFAULT_ROOT,
			$len = self::DEFAULT_MAX_PIECE_LEN,
			$klen = self::DEFAULT_MAX_KEY_LEN,
			$suf = self::DEFAULT_FILE_SUFFIX)
	{
		if (!is_string($rt) || strlen(trim($rt)) < 2)
			$rt = self::DEFAULT_ROOT;
		
		$rt = trim($rt);
		
		if (!is_string($suf) || strlen($suf) > self::MAX_FILENAME_LEN
			|| strlen(trim($suf)) == 0)
			$suf = self::DEFAULT_FILE_SUFFIX;
		
		$suf = trim($suf);
		
		if (!is_int($len) || $len < 1
			|| $len + strlen($suf) > self::MAX_FILENAME_LEN)
			$len = self::DEFAULT_MAX_PIECE_LEN;

		if (!is_int($klen) || $klen < 1)
			$klen = self::DEFAULT_MAX_KEY_LEN;
		
		if ($rt[strlen($rt) - 1] == DIRECTORY_SEPARATOR)
			$rt = substr($rt, 0, strlen($rt) - 2);
		
		$this->internal = new stdClass;
		$this->internal->cwd = getcwd();

		$this->rootpath = $this->abspath($rt);	// First set this...
		$rootnode = $this->rtpath();			// ...then call this.

		if (self::valid($this->rootpath))
		{
			// Open an existing trie
			
			if (!is_dir($this->rootpath))
				throw new Exception("Specified path '{$this->rootpath}' exists, but is not a directory.");
			else if (!is_readable($this->rootpath))
				throw new Exception("Specified path '{$this->rootpath}' exists, but is not readable by PHP.");
			else if (!is_writable($this->rootpath))
				throw new Exception("Specified path '{$this->rootpath}' exists, but is not writable by PHP.");

			$objpath = $this->objpath();
			$json = file_get_contents($objpath);
		
			if ($json === false)
				throw new Exception("Failed to open FileTrie object file in '{$objpath}'.");
			
			$obj = json_decode($json);
			foreach ($obj as $prop => $value)
				$this->{$prop} = $value;
		}
		else
		{
			// Create a new trie; we're assuming the directories are clean and empty or don't exist
			if (!mkdir($this->rootpath, 0755, true) && !file_exists($this->rootpath))
				throw new Exception("Could not create directory '{$this->rootpath}' in which to store the trie.");
			
			if (!mkdir($rootnode, 0755, true) && !file_exists($rootnode))
				throw new Exception("Could not create root node '{$rootnode}.");

			$this->suffix = trim($suf);
			$this->piecelen = $len;
			$this->fskeylen = $klen;
			
			$this->save();
		}
	}


	public function __destruct()
	{
		try
		{
			$this->save();
		}
		catch (Exception $e)
		{
		}
	}


	public function insert($key, $value = array())
	{
		if (!($clean = $this->sanitize($key)) || is_resource($value))
			return false;

		$p = $this->mkpath($clean);
		$records = $this->getfile($key, $p);
		
		if (is_null($records))
		{
			if (!mkdir($p['nodedir'], 0755, true))
				if (!file_exists($p['nodedir']))
					return false;

			$records = new stdClass;
			$records->{$key} = new stdClass;
			$records->{$key}->value = $value;
			$records->{$key}->count = 1;

			$this->keycount ++;
		}
		else
		{
			if (!property_exists($records, $key))
				$records->{$key} = new stdClass;

			$records->{$key}->value = $value;
			$records->{$key}->count ++;
		}

		return file_put_contents($p['fullpath'], json_encode($records)) !== false;
	}

	
	public function has($key)
	{
		return $this->rawget($key) != null;
	}


	public function count($key)
	{
		if (is_string($key))
		{
			$obj = $this->rawget($key);
			return is_object($obj) ? $obj->count : 0;
		}
		else if (is_array($key))
			return $key['count'];
		else if (is_object($key))
			return $key->count;
		else
			return 0;
	}


	public function prefixed($prefix, $limit = 0, $sorting = self::SORT_NONE, $userfunc = null)
	{
		// Reset instance fields for a new search. Keep in mind that $this->internal->limit
		// refers to the "scaled" limit, or the limit according to the random pool
		// factor, if any; but the local $limit will always, in this method, contain
		// the actual limit that the calling function wants.
		$this->internal->results = array();
		$this->internal->limit = $sorting == self::SORT_RANDOM ? $this->randompoolfactor * $limit : $limit;
		$this->internal->counter = 0;
		$this->internal->userfunc = $userfunc;

		$path = $this->mkpath($prefix);
		$prefixlen = strlen($path['nodename']);

		if (!file_exists($path['nodedir']))
			return $this->internal->results;

		$it = new DirectoryIterator($path['nodedir']);

		foreach ($it as $f)
		{
			if ($f->isDot())
				continue;
			else if (substr($f->getFilename(), 0, $prefixlen) == $path['nodename'])
			{
				$this->handleitem($f);

				if (!$this->belowlimit())
					break;
			}
		}
		
		if ($sorting == self::SORT_RANDOM)
			shuffle($this->internal->results);

		if ($limit && $this->internal->limit != $limit)
			array_splice($this->internal->results, $limit);		// Use actual limit; not "scaled" limit

		// Convert into an associative array
		$assoc = array();
		foreach ($this->internal->results as $result)
			$assoc[$result->key] = $result->value;

		// Sort, if requested
		if ($sorting == self::SORT_VALUE_ASC)
			asort($assoc);
		else if ($sorting == self::SORT_VALUE_DESC)
			arsort($assoc);
		else if ($sorting == self::SORT_KEY_ASC)
			ksort($assoc);
		else if ($sorting == self::SORT_KEY_DESC)
			krsort($assoc);
		else if ($sorting == self::SORT_COUNT_ASC
				|| $sorting == self::SORT_COUNT_DESC)
		{
			$counts = array();
			$sorted = array();

			foreach ($this->internal->results as $result)
				$counts[$result->key] = $result->count;
			
			if ($sorting == self::SORT_COUNT_ASC)
				asort($counts, SORT_NUMERIC);
			else if ($sorting == self::SORT_COUNT_DESC)
				arsort($counts, SORT_NUMERIC);

			foreach ($counts as $key => $count)
				$sorted[$key] = $assoc[$key];

			$assoc = $sorted;
		}

		return $assoc;
	}


	private function handleitem(&$f)
	{
		if ($f->isFile())
		{
			$data = json_decode(file_get_contents($f->getPathname()));
			foreach ($data as $key => $entry)
			{
				if (!$this->belowlimit())
					break;

				if (is_callable($this->internal->userfunc)
					&& !call_user_func($this->internal->userfunc, $key, $entry->value, $entry->count))
					continue;
				
				$tmp = new stdClass;
				$tmp->key = $key;
				$tmp->value = $entry->value;
				$tmp->count = $entry->count;

				$this->internal->results[] = $tmp;
				$this->internal->counter ++;
			}
		}
		else if ($f->isDir())
			$this->recursively($f->getPathname());
	}


	private function recursively($path)
	{
		$it = new DirectoryIterator($path);

		foreach ($it as $f)
		{
			if ($f->isDot())
				continue;
			else if ($this->belowlimit())
				$this->handleitem($f);
			else
				break;
		}
	}


	public function remove($key)
	{
		$p = $this->mkpath($key);
		$records = $this->getfile($key, $p);
		$success = true;
		
		if (is_null($records) || !property_exists($records, $key))
			return false;
		else
		{
			unset($records->{$key});
			$success = file_put_contents($p['fullpath'], json_encode($records)) !== false;

			if (count(get_object_vars($records)) == 0)
			{
				// Delete the whole file since it's now empty
				if (!@unlink($p['fullpath']))
					return false;

				// Also delete all parent directories that aren't shared with another node.
				// (We compare against 3 because of "." and ".." items plus the subfolder.)
				$parent = $p['nodedir'];
				while (count(scandir($parent)) <= 3 && basename($parent) != "root")
				{
					if (!@rmdir($parent))
						return false;
					$parent = pathinfo($parent, PATHINFO_DIRNAME);
				}
			}
		}

		return $success;
	}


	public function clear($key)
	{
		// TODO?
	}


	public function get($key, $path = "")
	{
		$result = $this->rawget($key, $path);

		if (!is_null($result))
			return property_exists($result, 'value') ? $result->value : null;
		else
			return null;
	}


	private function rawget($key, $path = "")
	{
		$records = $this->getfile($key, $path);

		if (is_null($records))
			return null;
		else
			return property_exists($records, $key) ? $records->{$key} : null;
	}


	private function getfile($key, $p = "")
	{
		if (!$p)
		{
			$clean = $this->sanitize($key);

			if (!$clean)
				return false;

			$p = $this->mkpath($clean);
		}

		return file_exists($p['fullpath'])
				? json_decode(file_get_contents($p['fullpath']))
				: null;
	}


	private function mkpath($key)
	{
		$clean = $this->sanitize($key);
		$len = strlen($clean);
		$path = $this->rtpath();

		if ($len > $this->fskeylen && $this->fskeylen > 0)
		{
			$clean = substr($clean, 0, $this->fskeylen);
			$len = $this->fskeylen;
		}

		$pieces = str_split($clean, $this->piecelen);

		foreach ($pieces as $piece)
			$path .= DIRECTORY_SEPARATOR.$piece;

		$filename = $pieces[count($pieces) - 1];
		$parentdir = substr($path, 0, strrpos($path, DIRECTORY_SEPARATOR));

		return array(
			"nodename" => $filename,
			"nodefile" => $filename.$this->suffix,
			"nodedir" => $parentdir,
			"nodepath" => $path,
			"fullpath" => $path.$this->suffix
		);
	}


	private static function valid($absrt)
	{
		$objpath = $absrt.DIRECTORY_SEPARATOR.self::OBJECT_FILENAME;
		$rtpath = $absrt.DIRECTORY_SEPARATOR.self::ROOT_NODE;
		return (is_dir($absrt) && is_file($objpath) && is_dir($rtpath));
	}


	private function save()
	{
		$rootpath = $this->rootpath;

		if (substr($rootpath, 0, 2) == ".".DIRECTORY_SEPARATOR)
		{
			// An old 'bug' in PHP, related to the SAPI, changes cwd to "/" in __destruct
			// http://stackoverflow.com/questions/14678947/why-does-getcwd-returns-in-destruct
			// https://bugs.php.net/bug.php?id=30957
			// ... so, we set the cwd member variable to persist the correct value here.
			$rootpath = $this->internal->cwd.DIRECTORY_SEPARATOR.substr($rootpath, 2);
		}
		
		$outfile = $this->objpath();
		$obj = new stdClass;

		foreach ($this as $prop => $val)
		{
			if ($prop == "internal")
				continue;
			$obj->{$prop} = $val;
		}

		if (@file_put_contents($outfile, json_encode($obj)."\n") === false)
			throw new Exception("Failed to save FileTrie object to '{$outfile}'. (cwd=".$this->internal->cwd.")");

		return true;
	}


	private function sanitize($key)
	{
		$key = strval($key);
		
		if (!is_string($key))
			return false;

		return strtolower(preg_replace("/^\.|[^[:alnum:]]|[\.]{2,}|\.$/", '', $key));
	}


	private function belowlimit()
	{
		return !$this->internal->limit || $this->internal->counter < $this->internal->limit;
	}


	private function objpath()
	{
		return self::makeobjpath($this->rootpath);
	}


	private function rtpath()
	{
		return self::makertpath($this->rootpath);
	}


	private static function makeobjpath($absrt)
	{
		return $absrt.DIRECTORY_SEPARATOR.self::OBJECT_FILENAME;
	}


	private static function makertpath($absrt)
	{
		return $absrt.DIRECTORY_SEPARATOR.self::ROOT_NODE;
	}


	private function abspath($path)
	{
		if ($path[0] == "." && $path[1] == DIRECTORY_SEPARATOR)
			$path = getcwd().DIRECTORY_SEPARATOR.substr($path, 2);
		else if ($path[0] == "~")
		{
			$path = str_replace("~",
				isset($_SERVER['HOME'])
					? $_SERVER['HOME'].DIRECTORY_SEPARATOR
					: $_SERVER['HOMEDRIVE'].":".DIRECTORY_SEPARATOR.$_SERVER['HOMEPATH'],
				$path);
		}

		if ($path[0] != DIRECTORY_SEPARATOR && $path[0] != "~" && $path[1] != ":")
			$path = getcwd().DIRECTORY_SEPARATOR.$path;

		return preg_replace("/\/{2,}/", "/", $path);
	}


	public function getkeycount()
	{
		return $this->keycount;
	}
}