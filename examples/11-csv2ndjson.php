<?php

// $ php examples/11-csv2ndjson.php < examples/users.csv > examples/users.ndjson
// see also https://github.com/clue/reactphp-ndjson

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$exit = 0;
$in = new React\Stream\ReadableResourceStream(STDIN);
$out = new React\Stream\WritableResourceStream(STDOUT);
$info = new React\Stream\WritableResourceStream(STDERR);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$csv = new Clue\React\Csv\AssocDecoder($in, $delimiter);

$encoder = new React\Stream\ThroughStream(function ($data) {
    $data = \array_filter($data, function ($one) {
        return ($one !== '');
    });

    return \json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
});

$csv->pipe($encoder)->pipe($out);

$csv->on('error', function (Exception $e) use ($info, &$exit) {
    $info->write('ERROR: ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('Valid NDJSON (Newline-Delimited JSON) will be forwarded to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

Loop::run();

exit($exit);
