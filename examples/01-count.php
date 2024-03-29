<?php

// $ php examples/01-count.php < examples/users.csv

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$exit = 0;
$in = new React\Stream\ReadableResourceStream(STDIN);
$info = new React\Stream\WritableResourceStream(STDERR);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$csv = new Clue\React\Csv\AssocDecoder($in, $delimiter);

$count = 0;
$csv->on('data', function () use (&$count) {
    ++$count;
});

$csv->on('end', function () use (&$count) {
    echo $count . PHP_EOL;
});

$csv->on('error', function (Exception $e) use (&$count, &$exit, $info) {
    $info->write('ERROR after record ' . $count . ': ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('The resulting number of records (rows minus header row) will be printed to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

Loop::run();

exit($exit);
