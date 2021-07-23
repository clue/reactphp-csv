<?php

// getting reasonable results requires a large data set:
// 1) download a large CSV data set, for example from https://github.com/fivethirtyeight/russian-troll-tweets
// $ curl -OL https://github.com/fivethirtyeight/russian-troll-tweets/raw/master/IRAhandle_tweets_1.csv
//
// 2) If your data set it not already in gzip format, compress it:
// $ gzip < IRAhandle_tweets_1.csv > IRAhandle_tweets_1.csv.gz
//
// 3) pipe compressed CSV into benchmark script:
// $ php examples/92-benchmark-count-gzip.php < IRAhandle_tweets_1.csv.gz

use Clue\React\Csv\AssocDecoder;
use React\ChildProcess\Process;
use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

// This benchmark example spawns the decompressor in a child `gunzip` process
// because parsing CSV files is already mostly CPU-bound and multi-processing
// is preferred here. If the input source is slower (such as an HTTP download)
// or if `gunzip` is not available (Windows), using a built-in decompressor
// such as https://github.com/clue/reactphp-zlib would be preferable.
$process = new Process('exec gunzip', null, null, array(
    0 => STDIN,
    1 => array('pipe', 'w'),
    STDERR
));
$process->start();
$decoder = new AssocDecoder($process->stdout);

$count = 0;
$decoder->on('data', function () use (&$count) {
    ++$count;
});

$start = microtime(true);
$report = Loop::addPeriodicTimer(0.05, function () use (&$count, $start) {
    printf("\r%d records in %0.3fs...", $count, microtime(true) - $start);
});

$decoder->on('close', function () use (&$count, $report, $start) {
    $now = microtime(true);
    Loop::cancelTimer($report);

    printf("\r%d records in %0.3fs => %d records/s\n", $count, $now - $start, $count / ($now - $start));
});
