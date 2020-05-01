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
use Clue\React\Zlib\Decompressor;
use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

$loop = Factory::create();
$input = new ReadableResourceStream(STDIN, $loop);
$decompressor = new Decompressor(ZLIB_ENCODING_GZIP);
$input->pipe($decompressor);
$decoder = new AssocDecoder($decompressor);

$decompressor->on('error', function (Exception $e) {
    printf("\nDecompression error: " . $e->getMessage() . "\n");
});

$count = 0;
$decoder->on('data', function () use (&$count) {
    ++$count;
});

$start = microtime(true);
$report = $loop->addPeriodicTimer(0.05, function () use (&$count, $start) {
    printf("\r%d records in %0.3fs...", $count, microtime(true) - $start);
});

$decoder->on('close', function () use (&$count, $report, $loop, $start) {
    $now = microtime(true);
    $loop->cancelTimer($report);

    printf("\r%d records in %0.3fs => %d records/s\n", $count, $now - $start, $count / ($now - $start));
});

$loop->run();
