<?php

namespace Clue\React\Csv;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/**
 * The AssocDecoder / Parser reads from a plain stream and emits assoc data arrays for each CSV record
 */
class AssocDecoder extends EventEmitter implements ReadableStreamInterface
{
    private $input;
    private $expected;
    private $headers = array();
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
        $this->input = new Decoder($input, $delimiter, $enclosure, $escapeChar, $maxlength);

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
        if ($this->expected === null) {
            $this->headers = $data;
            $this->expected = \count($data);
            $this->emit('headers', array($data));
        } else {
            if (\count($data) !== $this->expected) {
                $this->handleError(new \UnexpectedValueException(
                    'Expected record with ' . $this->expected . ' columns, but got ' . \count($data) . ' instead')
                );
                return;
            }

            $this->emit('data', array(
                \array_combine($this->headers, $data)
            ));
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if ($this->headers === array()) {
            $this->handleError(new \UnderflowException('Stream ended without headers'));
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
