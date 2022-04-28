<?php

// simple usage:
// $ php examples/91-benchmark-count.php < examples/users.csv
//
// getting reasonable results requires a large data set:
// 1) download a large CSV data set, for example from https://github.com/fivethirtyeight/russian-troll-tweets
// $ curl -OL https://github.com/fivethirtyeight/russian-troll-tweets/raw/master/IRAhandle_tweets_1.csv
//
// 2) pipe CSV into benchmark script:
// $ php examples/91-benchmark-count.php < IRAhandle_tweets_1.csv

use Clue\React\Csv\AssocDecoder;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

$csv = new AssocDecoder(new ReadableResourceStream(STDIN));

$count = 0;
$csv->on('data', function () use (&$count) {
    ++$count;
});

$start = microtime(true);
$report = Loop::addPeriodicTimer(0.05, function () use (&$count, $start) {
    printf("\r%d records in %0.3fs...", $count, microtime(true) - $start);
});

$csv->on('close', function () use (&$count, $report, $start) {
    $now = microtime(true);
    Loop::cancelTimer($report);

    printf("\r%d records in %0.3fs => %d records/s\n", $count, $now - $start, $count / ($now - $start));
});
