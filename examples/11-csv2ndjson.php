<?php

// $ php examples/11-csv2ndjson.php < examples/users.csv > examples/users.ndjson
// see also https://github.com/clue/reactphp-ndjson

use Clue\React\Csv\AssocDecoder;
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

$decoder = new AssocDecoder($in, $delimiter);

$encoder = new ThroughStream(function ($data) {
    $data = \array_filter($data, function ($one) {
        return ($one !== '');
    });

    return \json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
});

$decoder->pipe($encoder)->pipe($out);

$decoder->on('error', function (Exception $e) use ($info, &$exit) {
    $info->write('ERROR: ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('Valid NDJSON (Newline-Delimited JSON) will be forwarded to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

Loop::run();

exit($exit);
