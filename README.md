phpile
======

A PHP implementation of FileTrie, which is a file-system-based prefix tree ("trie") data structure that's persistent, scalable, portable, and super-fast


How to use FileTrie
======

First, see the [FileTrie schema](https://github.com/mholt/phpile/blob/master/schema.md) which defines the FileTrie abstract.

But what we've got here is an implemented FileTrie, not an abstract FileTrie. This implementation is done by a PHP class called `FileTrie`. It requires PHP 5.4+.

The following is a quick tutorial.


Creating a new FileTrie structure
------

**Create structure in default location (./data):**

	<?php
	require_once "FileTrie.php";
	$trie = new FileTrie();
	?>

This creates `./data` (the main directory), `./data/root` (the root node), and `./data/filetrie` (the object file).

**Create structure in custom location:**

	<?php
	require_once "FileTrie.php";
	$trie = new FileTrie("../mytrie");
	?>

Paths can be relative or absolute.


**More customizations:**

	<?php
	require_once "FileTrie.php";
	$trie = new FileTrie("../mytrie", 2, 15, ".txt");
	?>

This creates a FileTrie in `../mytrie`, with a key piece length of 2, a maximum key length (on the filesystem) of 15, and a file suffix of `.txt`. The default key piece length (node length) is 3, the default maximum key length (the length of all the nodes combined), is 20, and the default file suffix is `.json`. However, these defaults depend on individual implementations.

**From here on, we will assume the code is wrapped in `<?php` and `?>` tags, and that the `FileTrie.php` file has been included or required.**

Use an existing FileTrie structure
------

You "load" (or "open") a FileTrie the same way you create one. If the path is already a valid FileTrie, it will use the existing structure instead of creating a new one (and will ignore any extra arguments if you pass them in, because it will use the settings defined in the already-existing FileTrie).

**Use an existing FileTrie in the default location:**

	$trie = new FileTrie();


**Use an existing FileTrie in a custom location:**

	$trie = new FileTrie("/path/to/my/trie");


Inserting elements
------

You can add entries to the FileTrie by specifying a key. The value is optional, but must be something serializable into a string with `json_encode()`.

	$trie = new FileTrie();
	$trie->insert("key", "value");

or

	$trie = new FileTrie();
	$trie->insert("This can be any string!", $myArray);

or

	$trie = new FileTrie();
	$trie->insert("Make sure it's a string...", $stdClassObject);

Your values should probably have a consistent type throughout the whole trie, but exactly how you structure your values is up to you.

**From here on, we will assume that the FileTrie object has been constructed and is called `$trie`.**

Checking for existence
------

	$trie->has("Some key");

This function returns `true` or `false` if it can or can't, respectively, find the key in the trie.

Counting occurrences
------

A key can be inserted into the trie more than once, but it can only have one value associated with it. The number of times the key exists in the trie is indicated by the `count()` function:

	$trie->count("some key");

This function hits the filesystem when you give it a string key. If you pass in an object or an array, however, it assumes that the count is to be had in the `count` property or array element so that it can avoid a slower hit to the filesystem.


Searching (prefix searches)
------

Prefix searches are the only type of searches supported by this FileTrie class, as that is what the structure is for and is most efficient.

Searches are performed by *key*, not value. They are case-insensitive.

**Example 1: Find all entries with keys starting with "Jo":**

	$results = $trie->prefixed("Jo");

The `$result` variable now contains an associative array, with matching keys and their values:

	foreach ($results as $key => $val)
		echo "{$key}: {$val}";

This search is highly inefficient for large datasets. We can drastically improve performance by specifying a search cap: stop after finding *X* results.

**Example 2: Perform a prefix search with a result cap:**

	$results = $trie->prefixed("Jo", 10);

Autocompletes would seldom need caps larger than 5 or 10, which are blazing fast compared to searching the entire trie.

**Example 3: Ordering the results:**

	$results = $trie->prefixed("Jo", 10, FileTrie::SORT_COUNT_DESC);

The third argument can be any sorting constant, as defined near the top of the FileTrie class. Possible options are:

	SORT_NONE
	SORT_RANDOM
	SORT_COUNT_ASC
	SORT_COUNT_DESC
	SORT_KEY_ASC
	SORT_KEY_DESC
	SORT_VALUE_ASC

Sorting can be very slow without a search results limit (or a very high one), but won't make much of a difference with smaller limits.

**Example 4: Randomized results:**

Using the `SORT_RANDOM` constant will cause that prefix search to return the results in a random order. While unhelpful when some results are weighted and should be more relevant than others, this can provide more interesting autocomplete results so that each keystroke shows different things.

Prefix searches with randomized results typically go beyond the limit specified in the second argument. This is done so that the results have a larger pool of values from which to choose the final results. The size of this random pool is defined by an internal variable, `randompoolfactor`. You can set this variable to adjust random results appear:

	$trie->randompoolfactor = 25;
	$results = $trie->prefixed("Jo", 5, FileTrie::SORT_RANDOM);

It is advised to keep the pool factor between about 10 and 100. The search above will keep searching until it finds 125 matching entries, then pick 5 randomly from that set. This is because the actual depth of the search is determined by the product of the random pool factor and the search limit in the second argument.

A search like the following will take a little more time:

	$trie->randompoolfactor = 1000;
	$results = $trie->prefixed("Jo", 20, FileTrie::SORT_RANDOM);

It will find 20,000 results before it stops and only will choose 20 randomly from that entire pool to return as results. A more balanced and practical prefix search would be:

	$trie->randompoolfactor = 5;
	$results = $trie->prefixed("Jo", 10, FileTrie::SORT_RANDOM);

This will stop after finding 50 matches and randomly choose 10 to return as results.

The default random pool factor is 50.

**Example 5: Filtered results:**

You can optionally filter results as the search progresses by specifying a filtering function:

	$results = $trie->prefixed("Jo", 10, FileTrie::SORT_NONE, "myFilter");

The FileTrie will call the function `myFilter` like so:

	call_user_func('myFilter', $key, $value, $count);

With `myFilter` being replaced by the name of the function you pass in. Your function will receive both the *key* and the *value* of the entry being considered as well as the count (number of occurrences). Return *true* to accept the entry, or *false* to reject it and skip over it.


Examples
------

See the [demo.php](https://github.com/mholt/phpile/blob/master/demo.php) file for some working examples.