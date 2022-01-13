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

    private $delimiter;
    private $enclosure;
    private $escapeChar;
    private $maxlength;

    private $buffer = '';
    private $offset = 0;
    private $closed = false;

    /**
     * @param ReadableStreamInterface $input
     * @param string                  $delimiter
     * @param string                  $enclosure
     * @param string                  $escapeChar
     * @param int                     $maxlength
     */
    public function __construct(ReadableStreamInterface $input, $delimiter = ',', $enclosure = '"', $escapeChar = '\\', $maxlength = 65536)
    {
        $this->input = $input;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escapeChar = $escapeChar;
        $this->maxlength = $maxlength;

        if (!$input->isReadable()) {
            $this->close();
            return;
        }

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
        if (!\is_string($data)) {
            $this->handleError(new \UnexpectedValueException('Expected stream to emit string, but got ' . \gettype($data)));
            return;
        }

        $this->buffer .= $data;

        // keep parsing while a newline has been found
        while (($newline = \strpos($this->buffer, "\n", $this->offset)) !== false && $newline <= $this->maxlength) {
            // read data up until newline and try to parse
            $data = \str_getcsv(
                \substr($this->buffer, 0, $newline + 1),
                $this->delimiter,
                $this->enclosure,
                $this->escapeChar
            );

            // unable to decode? abort
            if ($data === false || \end($data) === null) {
                $this->handleError(new \RuntimeException('Unable to decode CSV'));
                return;
            }

            // the last parsed cell value ends with a newline and the buffer does not end with end quote?
            // this looks like a multiline value, so only remember offset and wait for next newline
            $last = \substr(\end($data), -1);
            \reset($data);
            $edgeCase = \substr($this->buffer, $newline - 2, 3);
            if ($last === "\n" && ($newline === 1 || $this->buffer[$newline - 1] !== $this->enclosure || $edgeCase === $this->delimiter . $this->enclosure . "\n")) {
                $this->offset = $newline + 1;
                continue;
            }

            // parsing successful => remove from buffer and emit
            $this->buffer = (string)\substr($this->buffer, $newline + 1);
            $this->offset = 0;

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

        if ($this->buffer !== '') {
            $this->handleError(new \RuntimeException('Unable to decode CSV'));
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
