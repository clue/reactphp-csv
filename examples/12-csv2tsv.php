<?php

// $ php examples/12-csv2tsv.php < examples/users.csv > examples/users.tsv
// see also https://github.com/clue/reactphp-tsv

use Clue\React\Csv\Decoder;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$exit = 0;
$in = new ReadableResourceStream(STDIN);
$out = new WritableResourceStream(STDOUT);
$info = new WritableResourceStream(STDERR);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$csv = new Decoder($in, $delimiter);

$encoder = new ThroughStream(function ($data) {
    $data = \array_map(function ($value) {
        return \addcslashes($value, "\0..\37");
    }, $data);

    return \implode("\t", $data) . "\n";
});

$csv->pipe($encoder)->pipe($out);

$csv->on('error', function (Exception $e) use ($info, &$exit) {
    $info->write('ERROR: ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

// TSV files MUST include a header line, so complain if CSV input ends without a single line
$csv->on('end', $empty = function () use ($info, &$exit) {
    $info->write('ERROR: Empty CSV input' . PHP_EOL);
    $exit = 1;
});
$csv->once('data', function () use ($csv, $empty) {
    $csv->removeListener('end', $empty);
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('Valid TSV (Tab-Separated Values) will be forwarded to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

Loop::run();

exit($exit);
