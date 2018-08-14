<?php

// $ php examples/01-count.php < examples/users.csv

use Clue\React\Csv\AssocDecoder;
use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$exit = 0;
$in = new ReadableResourceStream(STDIN, $loop);
$info = new WritableResourceStream(STDERR, $loop);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$decoder = new AssocDecoder($in, $delimiter);

$count = 0;
$decoder->on('data', function () use (&$count) {
    ++$count;
});

$decoder->on('end', function () use (&$count) {
    echo $count . PHP_EOL;
});

$decoder->on('error', function (Exception $e) use (&$count, &$exit, $info) {
    $info->write('ERROR after record ' . $count . ': ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('The resulting number of records (rows minus header row) will be printed to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

$loop->run();

exit($exit);
