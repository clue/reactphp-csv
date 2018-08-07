<?php

namespace Clue\React\Csv;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/**
 * The Decoder / Parser reads from a plain stream and emits data arrays for each CSV record
 */
class Decoder extends EventEmitter implements ReadableStreamInterface
{
    private $input;
    private $temp = false;

    private $delimiter;
    private $enclosure;
    private $escapeChar;
    private $maxlength;

    private $buffer = '';
    private $closed = false;

    public function __construct(ReadableStreamInterface $input, $delimiter = ',', $enclosure = '"', $escapeChar = '\\', $maxlength = 65536)
    {
        $this->input = $input;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escapeChar = $escapeChar;
        $this->maxlength = $maxlength;

        if (!$input->isReadable()) {
            return $this->close();
        }

        $this->temp = fopen('php://memory', 'r+');

        $this->input->on('data', array($this, 'handleData'));
        $this->input->on('end', array($this, 'handleEnd'));
        $this->input->on('error', array($this, 'handleError'));
        $this->input->on('close', array($this, 'close'));
    }

    public function isReadable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->buffer = '';

        if ($this->temp !== false) {
            fclose($this->temp);
            $this->temp = false;
        }

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    /** @internal */
    public function handleData($data)
    {
        $this->buffer .= $data;

        // keep parsing while a newline has been found
        while (($newline = strpos($this->buffer, "\n")) !== false && $newline <= $this->maxlength) {
            // read data up until newline and remove from buffer
            ftruncate($this->temp, 0);
            fwrite($this->temp, (string)substr($this->buffer, 0, $newline));
            rewind($this->temp);
            $this->buffer = (string)substr($this->buffer, $newline + 1);

            $data = fgetcsv($this->temp, 0, $this->delimiter, $this->enclosure, $this->escapeChar);

            // abort stream if decoding failed
            if ($data === false) {
                $this->handleError(new \RuntimeException('Unable to decode CSV'));
                return;
            }

            $this->emit('data', array($data));
        }

        if (isset($this->buffer[$this->maxlength])) {
            $this->handleError(new \OverflowException('Buffer size exceeded'));
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if ($this->buffer !== '') {
            $this->handleData("\n");
        }

        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }
}
