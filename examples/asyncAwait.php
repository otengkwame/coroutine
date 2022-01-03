<?php

include 'vendor/autoload.php';

// will create closure function in `Co` static class namespace with supplied name as key
\async('childTask', function ($av = null) {
    $x = 0;
    while (true) {
        echo "Child task! $av\n";
        yield;
        $x++;
        if ($x === 6)
            break;
    }

    return 'finish';
});

function repeat()
{
    $counter = 0;
    while (true) {
        $counter++;
        \printf("..");
        yield;
    }
}

function parentTask()
{
    $rid = yield \away(\repeat());
    $tid = yield \current_task();
    $child = yield \await('childTask', 'using async() function');

    echo "child returned: " . $child . \EOL;

    for ($i = 1; $i <= 6; ++$i) {
        echo "Parent task $tid iteration $i.\n";
        yield;

        if ($i == 3) yield \cancel_task($rid);
    }
};

\coroutine_run(\parentTask());
