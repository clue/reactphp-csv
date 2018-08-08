# clue/reactphp-csv [![Build Status](https://travis-ci.org/clue/reactphp-csv.svg?branch=master)](https://travis-ci.org/clue/reactphp-csv)

Streaming CSV (Comma-Separated Values or Character-Separated Values) parser and encoder for [ReactPHP](https://reactphp.org/).

CSV (Comma-Separated Values or less commonly Character-Separated Values) can be
used to store a large number of (uniform) records in simple text-based files,
such as a list of user records or log entries. CSV is not exactly a new format
and has been used in a large number of systems for decades. In particular, CSV
is often used for historical reasons and despite its shortcomings, it is still a
very common export format for a large number of tools to interface with
spreadsheet processors (such as Exel, Calc etc.). This library provides a simple
streaming API to process very large CSV files with thousands or even millions of
rows efficiently without having to load the whole file into memory at once.

* **Standard interfaces** -
  Allows easy integration with existing higher-level components by implementing
  ReactPHP's standard streaming interfaces.
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](https://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an [automated tests suite](#tests) and is regularly tested in the *real world*

**Table of contents**

* [CSV format](#csv-format)
* [Usage](#usage)
  * [Decoder](#decoder)
  * [Encoder](#encoder)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

## CSV format

CSV (Comma-Separated Values or less commonly Character-Separated Values) is a
very simple text-based format for storing a large number of (uniform) records,
such as a list of user records or log entries.

```
Alice,30
Bob,50
Carol,40
Dave,30
```

While this may look somewhat trivial, this simplicity comes at a price. CSV is
limited to untyped, two-dimensional data, so there's no standard way of storing
any nested structures or to differentiate a boolean value from a string or
integer.

CSV allows for optional field names. Whether field names are used is
application-dependant, so this library makes no attempt at *guessing* whether
the first line contains field names or field values. For many common use cases
it's a good idea to include them like this:

```
name,age
Alice,30
Bob,50
Carol,40
Dave,30
```

CSV allows handling field values that contain spaces, the delimiting comma or
even newline characters (think of URLs or user-provided descriptions) by
enclosing them with quotes like this:

```
name,comment
Alice,"Yes, I like cheese"
Bob,"Hello
World!"
```

> Note that these more advanced parsing rules are often handled inconsistently
  by other applications. Nowadays, these parsing rules are defined as part of
  [RFC 4180](https://tools.ietf.org/html/rfc4180), however many applications
  starting using some CSV-variant long before this standard was defined.

Some applications refer to CSV as Character-Separated Values, simply because
using another delimiter (such as semicolon or tab) is a rather common approach
to avoid the need to enclose common values in quotes. This is particularly
common for European systems that use a comma as decimal separator.

```
name;comment
Alice;Yes, I like cheese
Bob;Turn 22,5 degree clockwise
```

CSV files are often limited to only ASCII characters for best interoperability.
However, many legacy CSV files often use ISO 8859-1 encoding or some other
variant. Newer CSV files are usually best saved as UTF-8 and may thus also
contain special characters from the Unicode range. The text-encoding is usually
application-dependant, so your best bet would be to convert to (or assume) UTF-8
consistently.

Despite its shortcomings CSV is widely used and this is unlikely to change any
time soon. In particular, CSV is a very common export format for a lot of tools
to interface with spreadsheet processors (such as Exel, Calc etc.). This means
that CSV is often used for historial reasons and using CSV to store structured
application data is usually not a good idea nowadays – but exporting to CSV for
known applications is a very reasonable approach.

## Usage

### Decoder

The `Decoder` (parser) class can be used to make sure you only get back
complete, valid CSV elements when reading from a stream.
It wraps a given
[`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface)
and exposes its data through the same interface, but emits the CSV elements
as parsed values instead of just chunks of strings:

```
test,1,24
"hello world",2,48
```
```php
$stdin = new ReadableResourceStream(STDIN, $loop);

$stream = new Decoder($stdin);

$stream->on('data', function ($data) {
    // data is a parsed element from the CSV stream
    // line 1: $data = array('test', '1', '24');
    // line 2: $data = array('hello world', '2', '48');
    var_dump($data);
});
```

ReactPHP's streams emit chunks of data strings and make no assumption about their lengths.
These chunks do not necessarily represent complete CSV elements, as an
element may be broken up into multiple chunks.
This class reassembles these elements by buffering incomplete ones.

The `Decoder` supports the same optional parameters as the underlying
[`str_getcsv()`](http://php.net/str_getcsv) function.
This means that, by default, CSV fields will be delimited by a comma (`,`), will
use a quote enclosure character (`"`) and a backslash escape character (`\`).
This behavior can be controlled through the optional constructor parameters:

```php
$stream = new Decoder($stdin, ';');

$stream->on('data', function ($data) {
    // CSV fields will now be delimited by semicolon
});
```

Additionally, the `Decoder` limits the maximum buffer size (maximum line
length) to avoid buffer overflows due to malformed user input. Usually, there
should be no need to change this value, unless you know you're dealing with some
unreasonably long lines. It accepts an additional argument if you want to change
this from the default of 64 KiB:

```php
$stream = new Decoder($stdin, ',', '"', '\\', 64 * 1024);
```

If the underlying stream emits an `error` event or the plain stream contains
any data that does not represent a valid CSV stream,
it will emit an `error` event and then `close` the input stream:

```php
$stream->on('error', function (Exception $error) {
    // an error occured, stream will close next
});
```

If the underlying stream emits an `end` event, it will flush any incomplete
data from the buffer, thus either possibly emitting a final `data` event
followed by an `end` event on success or an `error` event for
incomplete/invalid CSV data as above:

```php
$stream->on('end', function () {
    // stream successfully ended, stream will close next
});
```

If either the underlying stream or the `Decoder` is closed, it will forward
the `close` event:

```php
$stream->on('close', function () {
    // stream closed
    // possibly after an "end" event or due to an "error" event
});
```

The `close(): void` method can be used to explicitly close the `Decoder` and
its underlying stream:

```php
$stream->close();
```

The `pipe(WritableStreamInterface $dest, array $options = array(): WritableStreamInterface`
method can be used to forward all data to the given destination stream.
Please note that the `Decoder` emits decoded/parsed data events, while many
(most?) writable streams expect only data chunks:

```php
$stream->pipe($logger);
```

For more details, see ReactPHP's
[`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface).

### Encoder

The `Encoder` (serializer) class can be used to make sure anything you write to
a stream ends up as valid CSV elements in the resulting CSV stream.
It wraps a given
[`WritableStreamInterface`](https://github.com/reactphp/stream#writablestreaminterface)
and accepts its data through the same interface, but handles any data as complete
CSV elements instead of just chunks of strings:

```php
$stdout = new WritableResourceStream(STDOUT, $loop);

$stream = new Encoder($stdout);

$stream->write(array('test', true, 24));
$stream->write(array('hello world', 2, 48));
```
```
test,1,24
"hello world",2,48
```

The `Encoder` supports the same optional parameters as the underlying
[`fputcsv()`](http://php.net/fputcsv) function.
This means that, by default, CSV fields will be delimited by a comma (`,`), will
use a quote enclosure character (`"`) and a backslash escape character (`\`).
This behavior can be controlled through the optional constructor parameters:

```php
$stream = new Encoder($stdout, ';');

$stream->write(array('hello', 'world'));
```
```
hello;world
```

If the underlying stream emits an `error` event or the given data contains
any data that can not be represented as a valid CSV stream,
it will emit an `error` event and then `close` the input stream:

```php
$stream->on('error', function (Exception $error) {
    // an error occured, stream will close next
});
```

If either the underlying stream or the `Encoder` is closed, it will forward
the `close` event:

```php
$stream->on('close', function () {
    // stream closed
    // possibly after an "end" event or due to an "error" event
});
```

The `end(mixed $data = null): void` method can be used to optionally emit
any final data and then soft-close the `Encoder` and its underlying stream:

```php
$stream->end();
```

The `close(): void` method can be used to explicitly close the `Encoder` and
its underlying stream:

```php
$stream->close();
```

For more details, see ReactPHP's
[`WritableStreamInterface`](https://github.com/reactphp/stream#writablestreaminterface).

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/reactphp-csv:dev-master
```

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.

## More

* If you want to learn more about processing streams of data, refer to the documentation of
  the underlying [react/stream](https://github.com/reactphp/stream) component.

* If you want to process compressed CSV files (`.csv.gz` file extension)
  you may want to use [clue/reactphp-zlib](https://github.com/clue/reactphp-zlib)
  on the compressed input stream before passing the decompressed stream to the CSV decoder.

* If you want to create compressed CSV files (`.csv.gz` file extension)
  you may want to use [clue/reactphp-zlib](https://github.com/clue/reactphp-zlib)
  on the resulting CSV encoder output stream before passing the compressed
  stream to the file output stream.

* If you want to concurrently process the records from your CSV stream,
  you may want to use [clue/reactphp-flux](https://github.com/clue/reactphp-flux)
  to concurrently process many (but not too many) records at once.
