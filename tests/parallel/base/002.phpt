--TEST--
Check parallel global scope
--SKIPIF--
<?php
if (!extension_loaded('parallel')) {
	echo 'skip';
}
?>
--FILE--
<?php
include 'vendor/autoload.php';

$parallel = new Async\Parallel\Runtime();

$parallel->run(function() {
	global $thing;

	$thing = 10;
});

$future = $parallel->run(function() {
	global $thing;

	var_dump($thing);

	return false;
});

var_dump($future->value(), @$thing);
?>
--EXPECTF--
int(10)
bool(false)
NULL
