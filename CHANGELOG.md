# Changelog

## 1.2.0 (2022-05-13)

*   Feature: Support custom EOL character when encoding CSV.
    (#26 by @clue)

    ```php
    $csv = new Clue\React\Csv\Encoder($stdout, ',', '"', '\\', "\r\n");
    ```

*   Feature: Add `headers` event to `AssocDecoder` class.
    (#29 by @SimonFrings)

    ```php
    $csv->on('headers', function (array $headers) {
        var_dump($headers); // e.g. $headers = ['name','age'];
    });
    ```

*   Feature: Check type of incoming `data` before trying to decode CSV.
    (#27 by @clue)

*   Feature: Support parsing multiline values starting with quoted newline.
    (#25 by @KamilBalwierz)

*   Improve documentation and examples.
    (#30 and #28 by @SimonFrings, #22 and #24 by @clue and #23 by @PaulRotmann)

## 1.1.0 (2020-12-10)

*   Feature: Add decoding benchmark plus benchmark for GZIP-compressed CSV files.
    (#15 by @clue)

*   Improve test suite and add `.gitattributes` to exclude dev files from exports.
    Add PHP 8 support, update to PHPUnit 9 and simplify test setup.
    (#13 and #14 by @clue and #16, #18, #19 and #20 by @SimonFrings)

*   Improve documentation wording/typos and examples.
    (#9 by @loilo and #10 by @clue)

## 1.0.0 (2018-08-14)

*   First stable release, following SemVer
