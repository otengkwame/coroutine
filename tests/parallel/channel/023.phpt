--TEST--
Check serialize Channel
--SKIPIF--
<?php
if (((float) \phpversion() >= 8.0)) print "skip"; ?>
--FILE--
<?php
include 'vendor/autoload.php';
use \parallel\Channel;

try {
    print("No! Serialization of 'parallel\Channel' is not allowed, all good.\n");
    $data = serialize(new Channel);
    var_dump(unserialize($data));
} catch (\Exception $ex) {
    var_dump($ex->getMessage());
}
?>
--EXPECTF--
No! Serialization of 'parallel\Channel' is not allowed, all good.
object(parallel\Channel)#%d (15) {
  ["name":protected]=>
  string(%d) "%s[1]"
  ["index":protected]=>
  int(1)
  ["capacity":protected]=>
  int(-1)
  ["type":protected]=>
  string(10) "unbuffered"
  ["buffered":protected]=>
  object(SplQueue)#%d (2) {
    ["flags":"SplDoublyLinkedList":private]=>
    int(4)
    ["dllist":"SplDoublyLinkedList":private]=>
    array(0) {
    }
  }
  ["whenDrained":"Async\Spawn\Channeled":private]=>
  NULL
  ["input":"Async\Spawn\Channeled":private]=>
  array(0) {
  }
  ["open":protected]=>
  bool(true)
  ["state":protected]=>
  string(5) "libuv"
  ["future":protected]=>
  NULL
  ["process":protected]=>
  NULL
  ["paralleling":protected]=>
  NULL
  ["futureInput":protected]=>
  int(0)
  ["futureOutput":protected]=>
  int(0)
  ["futureError":protected]=>
  int(0)
}
