<?php

// $ php examples/02-validate.php < examples/users.csv

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$exit = 0;
$in = new React\Stream\ReadableResourceStream(STDIN);
$out = new React\Stream\WritableResourceStream(STDOUT);
$info = new React\Stream\WritableResourceStream(STDERR);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$csv = new Clue\React\Csv\Decoder($in, $delimiter);
$encoder = new Clue\React\Csv\Encoder($out, $delimiter);
$csv->pipe($encoder);

$csv->on('error', function (Exception $e) use ($info, &$exit) {
    $info->write('ERROR: ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('Valid CSV will be forwarded to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

Loop::run();

exit($exit);
