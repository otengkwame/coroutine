--TEST--
ZEND_DECLARE_INHERITED_CLASS
--SKIPIF--
<?php if (((float) \phpversion() >= 8.0)) print "skip"; ?>
--FILE--
<?php
include 'vendor/autoload.php';

$parallel = new parallel\Runtime();

try {
	$parallel->run(function(){
		class Foo extends Bar {}
	});
} catch (Throwable $t) {
	var_dump($t->getMessage());
}
?>
--EXPECTF--
string(%d) "syntax error, unexpected '\' (T_NS_SEPARATOR), expecting identifier (T_STRING)

#0 [internal function]: Async\Closure\SerializableClosure->%S
#1 %S
#2 [internal function]: Async\Closure\SerializableClosure->%S
#3 %S
#4 %S
#5 {main}"
