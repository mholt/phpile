FileTrie Schema
======

So as to allow FileTrie instances to be communicable between various platforms, languages,
and implementations, the following schema should be adopted in all cases.


Overview and Terminology
------

FileTries are never loaded entirely into memory. Storage and retrieval is facilitated by
the filesystem. Like a regular prefix tree, there are nodes. "Nodes" may either be
folders or files. A "leaf node", or entry, is a file, and an "internal node" is a
directory. Each actual entry in the structure is always a file (JSON).

A FileTrie structure exists at a certain directory, for example:

    /home/dev/data

An *instance* of a FileTrie is the presence of at least

1. an object file
2. a root folder

in the FileTrie's directory. An empty FileTrie contains these two things.

The *object file* is called "filetrie" (no extension). Using our example above, the
object file would be:

	/home/dev/data/filetrie

The object file defines various settings and metadata about that particular FileTrie.
Loading this file effectively "instantiates" a FileTrie object/structure.

The "root node" is synonymous with the "root directory" and refers to the parent
directory containing all other nodes, and is called *root*. The root node of our
FileTrie from above would be located at:

    /home/dev/data/root

This "root" folder is the *root node*.


Keys and values
------

Unlike simple prefix tries, FileTries act less like a simple set and more like a
map/dictionary/associative array. Values are stored in a FileTrie by a specific key,
which need not be equivalent to the value. Keys are relatively short and should be
unique. Multiple values can have the same key, but only one value for a key can be
stored. Storing multiple values to the same key is not allowed.

In other words, a key, as this schema currently stands, represents a value, but never
more than one value. That value may be null, or non-existent.

Values can be any type of data, as long as it can be serialized into a JSON string.
This includes strings, numbers, objects, and arrays. Binary keys and values should
be Base64-encoded and treated as strings.

If I were to use a FileTrie to store a simple contact list, I might have keys and
values like the following:
     
    KEYS				VALUES
    ---------------		---------------------
    John Doe			johndoe@yahoo.com
    John Smith			john@smith.com
    John				john@acme.com
    Larry				cableguy@hotmail.com

Keys must be formatted to be acceptable file and directory names. These keys, as they
are, have spaces and will not do. See "Storage and Retrieval."


Storage and Retrieval
------

Like a regular prefix tree, each internal node is a prefix to at least one entry.
In a FileTrie, the directories contain "pointers" (sub-directories) to child nodes
and, conveniently, the parent node. Folders can also contain leaf nodes, which are
files.

### Basic, 1-character key pieces

Our data from above would be stored as follows:

    root/j/o/h/n/d/o/e.txt
    root/j/o/h/n/s/m/i/t/h.txt
    root/j/o/h/n.txt
    root/l/a/r/r/y.txt

We've chosen to use `.txt` suffixes on files, though `.json` is also preferable.
Note that the directory `root/j/o/h` looks like:

    n
    n.txt

This is why file suffixes (or extensions) are required. The `n` subfolder contains:

    d
    s

The `d` folder is a parent for the entry about John Doe and the `s` folder is a parent
for John Smith's record.

**What about when you have millions of entries?** Well, then you have millions of files.
This can lead to, sometimes, billions of directories, if their keys have little or
nothing in common. To reduce this overhead, key pieces can be combined into more
characters, approaching the structure of a *radix tree*.

### Multi-character key pieces

We could reduce the number of internal nodes by concatenating some nodes, for example:

    root/joh/ndo/e.txt
    root/joh/nsm/ith.txt
    root/joh/n.txt
    root/lar/ry.txt

We've gone from 17 directories and files down to 10. The savings becomes more
significant with larger datasets.

The key piece length of the FileTrie is serialized into the object file.

Recommended key piece lengths are between 1 and 3 characters, inclusive. The most
immediate child nodes a single node could have with key piece length of 1 is about
80. With key piece length of 3, it's about 512,000, which is significantly slower when
the prefix search doesn't involve a prefix that's divisible by the key piece length.

### While we're at it, what about a radix tree?

We're quickly approaching the same idea as a radix tree, aren't we. **Implementing a
radix tree is under review.** However, it is already looking like a radix tree suffers
performance loss and must hit the filesystem multiple times for each lookup instead of once,
since the key pieces are of variable length and a direct path to a node cannot be constructed
in O(1).

### Retrieving a value

The same function used to build a path from a key (see "Translating keys into paths") can be
used to save and retrieve values, simply by writing and reading a file.


Translating keys into paths
------

**UNDER REVIEW: Restrict to just alphanumerics? (except for file suffix which can have dots and underscores, etc...)**

Given a string as a key, for example "John Doe", how do you get the node's file path?
Each of the following conventions should be followed:

- Trim whitespace
- Convert to lowercase
- Only allow the following characters:
    - Alphanumeric `[a-z0-9]`
    - Underscores `_`
    - A single dot `\.?`

Here's a sample, Perl-style regular expression which can be used. Matches should be
stripped entirely:

    /^\.|[^\w\d\-\.]|[\.]{2,}|\.$/

This process will turn `John Doe` into `johndoe` which, once broken into key
pieces based on the "key piece length," will make a suitable file path.

To finish, simply insert directory separators at the proper intervals, based on the *key
piece length*. Then append the file suffix:

    joh/ndo/e.txt

This is now the path to that node.

File Formats
------

All files should be in a plain-text JSON format.


### Object File Scheme


The object file, `filetrie`, should be a JSON object, for example:

	{
		"piecelen": 3,
		"suffix": ".txt",
		"rootpath": "./data",
		"nodecount": 247160,
		"keycount": 232006,
		"data": null,
		"randompoolfactor": 10,
		"fskeylen": 20
	}

Where:

- **piecelen** - *integer* - The key piece length
- **suffix** - *string* - The suffix or extension of files
- **rootpath** - *string* - Path to the directory containing "root"
- **nodecount** - *integer* - Total number of nodes in the FileTrie
- **keycount** - *integer* - Total number of keys (different values) stored
- **data** - *anything* - Any arbitrary data to store with the FileTrie
- **randompoolfactor** - *integer* - Size factor for randomized prefix search results
- **fskeylen** - *integer* - Maximum length of keys as used by the filesystem


### Entry File Scheme

Entry files should be a JSON object, with keys being the properties. This way,
files can handle collisions in the filesystem in the case two keys sanitize into
the same (maybe truncated) key value. Example:

**`root/joh/ndo/e.txt`** 

	{
		"John Doe": {
			"value": "johndoe@yahoo.com",
			"count": 1
		}
	}

A similar yet distinct key, such as `JohnDoe`, would translate into the same node file
path. The file could thus accommodate both:

**`root/joh/ndo/e.txt`** 

	{
		"John Doe": {
			"value": "johndoe@yahoo.com",
			"count": 1
		},
		"JohnDoe": {
			"value": "johndoe2@yahoo.com",
			"count": 1
		}
	}

As you might guess, the "count" value is to be incremented when that key is inserted
into the FileTrie again. The newly-inserted value should replace any previous value.

The `value` property can be an object, string, array, or number. The `value` property might
also be null or omitted entirely if it isn't needed.

Symlinks
-----

It is possible to directly reference a particular node from another. In effect, a
node can "point" to another one (either a leaf or internal node). This can be done using
symbolic links. **The implementation of this is under review.**