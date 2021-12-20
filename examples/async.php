<?php
include 'vendor/autoload.php';

// will create closure function in `Co` static class namespace with supplied name as key
\async('childTask', function ($av = null) {
    $tid = yield \get_task();
    while (true) {
        echo "Child task $tid still alive! $av\n";
        yield;
    }
});

function parentTask()
{
    $tid = yield \get_task();
    // have `away` access the created async closure functions
    $childTid = yield \away('childTask', 'using async() function');

    for ($i = 1; $i <= 6; ++$i) {
        echo "Parent task $tid iteration $i.\n";
        yield;

        if ($i == 3) yield \cancel_task($childTid);
    }
};

\coroutine_run(\parentTask());
