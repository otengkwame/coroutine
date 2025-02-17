<?php
include 'vendor/autoload.php';

use function Async\Path\{file_uri, file_meta, file_file, file_close};

/**
 * Converted example of https://github.com/jimmysong/asyncio-examples from:
 * @see https://youtu.be/qfY2cqjJMdw
 */
function get_statuses($websites)
{
    $statuses = ['200' => 0, '400' => 0, '405' => 0];
    $tasks = [];
    foreach ($websites as $website) {
        $tasks[] = yield \away(\get_website_status($website));
    }

    $taskStatus = yield \gather($tasks);
    foreach ($taskStatus as  $id => $status) {
        if (!$status)
            $statuses[$status] = 0;
        else
            $statuses[$status] += 1;
    }

    return \json_encode($statuses);
}

function get_website_status($url)
{
    $id = yield \current_task();
    $fd = yield file_uri($url);
    $status = file_meta($fd, 'status');
    yield file_close($fd);
    //[$meta, $status, $retry] = yield \head_uri($url);
    print "task: $id, url: $url code: $status" . EOL;
    return yield $status;
}

function lapse()
{
    $i = 0;
    while (true) {
        echo '.';
        $i++;
        if ($i == 800) {
            yield \shutdown();
        }

        yield;
    }
}

function main()
{
    yield \away(\lapse());
    $websites = yield file_file(__DIR__ . \DS . 'list_many.txt');
    if ($websites !== false) {
        \timer_for();
        $data = yield get_statuses($websites);
        $t1 = \timer_for();
        print $data . EOL;
        print("getting website statuses took " . $t1 . " seconds");
    }
}

\coroutine_run(\main());
