<?php
$start = microtime(true);

require_once "FileTrie.php";

$trie = new FileTrie("demodata");

$trie->insert("John Doe", "john@doe.com");
$trie->insert("JohnDoe", "aasdf@acme.com");
$trie->insert("John Smith", "j0hn@smith.com");
$trie->insert("John Smith", "j0hn@smith.com");
$trie->insert("Jane", "j4ne@acme.com");
$trie->insert("Ihave Novalue", null);
$trie->insert("Little-Bobby-Tables", "xkcd@bobbytables.com");
$trie->insert("Harry S. Truman", "trumanh@whitehouse.gov");


echo "Has 'Jane'? ".($trie->has("Jane") ? "Y" : "N")."<br>";

echo "Has 'jane'? ".($trie->has("jane") ? "Y" : "N")."<br>";

echo "Has 'Ihave Novalue'? ".($trie->has("Ihave Novalue") ? "Y" : "N")."<br><br>";

echo "Value of 'John Doe': ".$trie->get("John Doe")."<br>";
echo "Value of 'Ihave Novalue': ";
var_dump($trie->get("Ihave Novalue"));
echo "<br><br><br>";


echo "Entries starting with 'J':<br><pre>";
var_dump($trie->prefixed("J"));
echo "</pre><br><br>";


echo "Entries starting with 'john':<br><pre>";
var_dump($trie->prefixed("john"));
echo "</pre><br><br>";


echo "All entries, randomized:<br><pre>";
var_dump($trie->prefixed("", 0, FileTrie::SORT_RANDOM));
echo "</pre><br><br>";



echo "All entries in ascending key order:<br><pre>";
var_dump($trie->prefixed("", 0, FileTrie::SORT_KEY_ASC));
echo "</pre><br><br>";


echo "All entries in descending value order:<br><pre>";
var_dump($trie->prefixed("", 0, FileTrie::SORT_VALUE_DESC));
echo "</pre><br><br>";



echo "All entries in descending count order:<br><pre>";
var_dump($trie->prefixed("", 0, FileTrie::SORT_COUNT_DESC));
echo "</pre><br><br>";



echo "First 3 entries:<br><pre>";
var_dump($trie->prefixed("", 3));
echo "</pre><br><br>";




function filterFunc($key, $value, $count)
{
	return strpos($value, ".gov") !== false;
}

echo "Filtered (value must contain '.gov'):<br><pre>";
var_dump($trie->prefixed("", 0, FileTrie::SORT_NONE, 'filterFunc'));
echo "</pre><br><br>";




echo "John Smith appears: ".$trie->count("John Smith")." times<br>";
echo "Little-Bobby-Tables appears: ".$trie->count("Little-Bobby-Tables")." times<br>";
echo "LittleBobbyTables appears: ".$trie->count("LittleBobbyTables")." times<br>";
echo "Little Bobby Tables appears: ".$trie->count("Little Bobby Tables")." times<br>";
echo "jane appears: ".$trie->count("jane")." times<br>";
echo "Jane appears: ".$trie->count("Jane")." times<br><br>";


echo "TOTAL DISTINCT KEYS: ".$trie->getkeycount()."<br>";



$end = microtime(true) - $start;

echo "<br><br><b>DURATION:</b> {$end} seconds";

?>