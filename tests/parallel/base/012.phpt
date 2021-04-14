--TEST--
ZEND_BIND_STATIC (FAIL)
--SKIPIF--
<?php if (((float) \phpversion() >= 8.0)) print "skip"; ?>
--INI--
opcache.optimization_level=0
--FILE--
<?php
include 'vendor/autoload.php';

$parallel = new Async\Parallel\Runtime();
$var     = null; /* avoid undefined */

try {
	$parallel->run(function() use(&$var) {})->value();
} catch (Throwable $ex) {
	var_dump($ex->getMessage());
}
?>
--EXPECTF--
