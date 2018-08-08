<?php

use React\Stream\ReadableResourceStream;
use Clue\React\Csv\Decoder;

class DecoderTest extends TestCase
{
    private $input;
    private $decoder;

    public function setUp()
    {
        $stream = fopen('php://temp', 'r');
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->input = new ReadableResourceStream($stream, $loop);
        $this->decoder = new Decoder($this->input);
    }

    public function testEmitDataArrayWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello', 'world')));

        $this->input->emit('data', array("hello,world\n"));
    }

    public function testEmitDataArrayWillForwardWithWindowsCRLF()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello', 'world')));

        $this->input->emit('data', array("hello,world\r\n"));
    }

    public function testEmitDataArrayWillForwardWithCustomSemicolonDelimiter()
    {
        $this->decoder = new Decoder($this->input, ';');
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello', 'world')));

        $this->input->emit('data', array("hello;world\n"));
    }

    public function testEmitDataArrayWillForwardNumbersAsString()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('1', '2')));

        $this->input->emit('data', array("1,2\n"));
    }

    public function testEmitDataArrayWillForwardEmptyElements()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('1', '', '3', '')));

        $this->input->emit('data', array("1,,3,\n"));
    }

    public function testEmitDataStringWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello')));

        $this->input->emit('data', array("\"hello\"\n"));
    }

    public function testEmitDataStringWithNewlineWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array("hello" . "\n" . "world")));

        $this->input->emit('data', array("\"hello\nworld\"\n"));
    }

    public function testEmitDataStringWithMultiNewlineWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array("hello" . "\n\n" . "world")));

        $this->input->emit('data', array("\"hello\n\nworld\"\n"));
    }

    public function testEmitDataStringEndsWithNewlineWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array("hello" . "\n")));

        $this->input->emit('data', array("\"hello\n\"\n"));
    }

    public function testEmitDataStringOnlyNewlineWillForward()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array("\n")));

        $this->input->emit('data', array("\"\n\"\n"));
    }

    public function testEmitDataWithoutNewlineWillNotForward()
    {
        $this->decoder->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("hello"));
    }

    public function testEmitDataInMultipleChunksWillForwardOnNewline()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array("hello")));

        $this->input->emit('data', array("he"));
        $this->input->emit('data', array("llo"));
        $this->input->emit('data', array("\n"));
    }

    public function testEmitEmptyLineWillForwardError()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("\n"));
    }

    public function testEmitDataErrorWillForwardError()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("\"hello\\\"test\n"));
        $this->input->emit('end');
    }

    public function testEmitDataErrorInMultipleChunksWillForwardError()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("\"hello"));
        $this->input->emit('data', array("\\\"test\n"));
        $this->input->emit('end');
    }

    public function testEmitDataWithExactBufferSizeWillForward()
    {
        $this->decoder = new Decoder($this->input, ',', '"', '\\', 5);
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello')));

        $this->input->emit('data', array("hello\n"));
    }

    public function testEmitDataWithBufferSizeOverflowWillForwardOverflowError()
    {
        $this->decoder = new Decoder($this->input, ',', '"', '\\', 4);
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnceWith($this->isInstanceOf('OverflowException')));

        $this->input->emit('data', array("hello\n"));
    }

    public function testEmitEndWillForwardEnd()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('end', $this->expectCallableOnce());

        $this->input->emit('end');
    }

    public function testEmitDataWithoutNewlineWillForwardOnEnd()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello')));
        $this->decoder->on('end', $this->expectCallableOnce());

        $this->input->emit('data', array("hello"));
        $this->input->emit('end');
    }

    public function testEmitDataErrorWithoutNewlineWillForwardErrorOnEnd()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('end', $this->expectCallableNever());

        $this->input->emit('data', array("\"hello\\\"test"));
        $this->input->emit('end');
    }

    public function testClosingInputWillCloseDecoder()
    {
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->assertTrue($this->decoder->isReadable());

        $this->input->close();

        $this->assertFalse($this->decoder->isReadable());
    }

    public function testClosingInputWillRemoveAllDataListeners()
    {
        $this->input->close();

        $this->assertEquals(array(), $this->input->listeners('data'));
        $this->assertEquals(array(), $this->decoder->listeners('data'));
    }

    public function testClosingDecoderWillCloseInput()
    {
        $this->input->on('close', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->assertTrue($this->decoder->isReadable());

        $this->decoder->close();

        $this->assertFalse($this->decoder->isReadable());
    }

    public function testClosingDecoderWillRemoveAllDataListeners()
    {
        $this->decoder->close();

        $this->assertEquals(array(), $this->input->listeners('data'));
        $this->assertEquals(array(), $this->decoder->listeners('data'));
    }

    public function testClosingDecoderDuringFinalDataEventFromEndWillNotEmitEnd()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('hello')));
        $this->decoder->on('data', array($this->decoder, 'close'));

        $this->decoder->on('end', $this->expectCallableNever());

        $this->input->emit('data', array("hello"));
        $this->input->emit('end');
    }

    public function testUnreadableInputWillResultInUnreadableDecoder()
    {
        $this->input->close();
        $this->decoder = new Decoder($this->input);

        $this->assertFalse($this->decoder->isReadable());
    }

    public function testUnreadableInputWillNotAddAnyEventListeners()
    {
        $this->input->close();
        $this->decoder = new Decoder($this->input);

        $this->assertEquals(array(), $this->input->listeners('data'));
        $this->assertEquals(array(), $this->decoder->listeners('data'));
    }

    public function testEmitErrorEventWillForwardAndClose()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));
    }

    public function testPipeReturnsDestStream()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $this->decoder->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testForwardPauseToInput()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('pause');

        $this->decoder = new Decoder($this->input);
        $this->decoder->pause();
    }

    public function testForwardResumeToInput()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('resume');

        $this->decoder = new Decoder($this->input);
        $this->decoder->resume();
    }
}
