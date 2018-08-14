<?php

namespace Clue\React\Csv;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;

/**
 * The Encoder / Serializer can be used to write any array, encode it as a CSV record and forward it to an output stream
 */
class Encoder extends EventEmitter implements WritableStreamInterface
{
    private $output;
    private $temp = false;
    private $closed = false;

    private $delimiter;
    private $enclosure;
    private $escapeChar;

    /**
     * @param WritableStreamInterface $output
     * @param string                  $delimiter
     * @param string                  $enclosure
     * @param string                  $escapeChar
     * @throws \BadMethodCallException
     */
    public function __construct(WritableStreamInterface $output, $delimiter = ',', $enclosure = '"', $escapeChar = '\\')
    {
        // @codeCoverageIgnoreStart
        if ($escapeChar !== '\\' && PHP_VERSION_ID < 50504) {
            throw new \BadMethodCallException('Custom escape character only supported on PHP 5.5.4+');
        }
        // @codeCoverageIgnoreEnd

        $this->output = $output;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escapeChar = $escapeChar;

        if (!$output->isWritable()) {
            $this->close();
            return;
        }

        $this->temp = fopen('php://memory', 'r+');

        $this->output->on('drain', array($this, 'handleDrain'));
        $this->output->on('error', array($this, 'handleError'));
        $this->output->on('close', array($this, 'close'));
    }

    public function write($data)
    {
        if ($this->closed) {
            return false;
        }

        $written = false;
        if (is_array($data)) {
            // custom escape character requires PHP 5.5.4+ (see constructor check)
            // @codeCoverageIgnoreStart
            if ($this->escapeChar === '\\') {
                $written = fputcsv($this->temp, $data, $this->delimiter, $this->enclosure);
            } else {
                $written = fputcsv($this->temp, $data, $this->delimiter, $this->enclosure, $this->escapeChar);
            }
            // @codeCoverageIgnoreEnd
        }

        if ($written === false) {
            $this->handleError(new \RuntimeException('Unable to encode CSV'));
            return false;
        }

        rewind($this->temp);
        $data = stream_get_contents($this->temp);
        ftruncate($this->temp, 0);

        return $this->output->write($data);
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->output->end();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->output->close();

        if ($this->temp !== false) {
            fclose($this->temp);
            $this->temp = false;
        }

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleDrain()
    {
        $this->emit('drain');
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }
}
