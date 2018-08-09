<?php

use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Clue\React\Csv\Decoder;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$exit = 0;
$in = new ReadableResourceStream(STDIN, $loop);
$out = new WritableResourceStream(STDOUT, $loop);
$info = new WritableResourceStream(STDERR, $loop);

$delimiter = isset($argv[1]) ? $argv[1] : ',';

$decoder = new Decoder($in, $delimiter);

$headers = array();
$encoder = new ThroughStream(function ($data) use (&$headers) {
    return json_encode(array_combine($headers, $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
});
$encoder->pipe($out);

// first row from decoder will be used as header values, then start piping to encoder
$decoder->once('data', function ($data) use (&$headers, $decoder, $encoder) {
    $headers = $data;
    $decoder->pipe($encoder);
});

$decoder->on('error', function (Exception $e) use ($info, &$exit) {
    $info->write('ERROR: ' . $e->getMessage() . PHP_EOL);
    $exit = 1;
});

$info->write('You can pipe/write a valid CSV stream to STDIN' . PHP_EOL);
$info->write('Valid NDJSON (Newline-Delimited JSON) will be forwarded to STDOUT' . PHP_EOL);
$info->write('Invalid CSV will raise an error on STDERR and exit with code 1' . PHP_EOL);

$loop->run();

exit($exit);
