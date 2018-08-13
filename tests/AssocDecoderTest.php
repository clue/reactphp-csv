<?php

use Clue\React\Csv\AssocDecoder;
use React\Stream\ReadableResourceStream;

class AssocDecoderTest extends TestCase
{
    private $input;
    private $decoder;

    public function setUp()
    {
        $stream = fopen('php://temp', 'r');
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->input = new ReadableResourceStream($stream, $loop);
        $this->decoder = new AssocDecoder($this->input);
    }

    public function testEmitDataWithoutNewlineWillNotForward()
    {
        $this->decoder->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("hello"));
    }

    public function testEmitDataOneLineWillBeSavedAsHeaderAndWillNotForward()
    {
        $this->decoder->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("hello,world\n"));
    }

    public function testEmitDataTwoLinesWillForwardOneRecord()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('name' => 'alice', 'partner' => 'bob')));

        $this->input->emit('data', array("name,partner\nalice,bob\n"));
    }

    public function testEmitDataTwoLinesWithoutTrailingNewlineWillNotForwardRecord()
    {
        $this->decoder->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("name,partner\nalice,bob"));
    }

    public function testEmitDataTwoLinesWithCustomSemicolonWillForwardOneRecord()
    {
        $this->decoder = new AssocDecoder($this->input, ';');
        $this->decoder->on('data', $this->expectCallableOnceWith(array('name' => 'alice', 'partner' => 'bob')));

        $this->input->emit('data', array("name;partner\nalice;bob\n"));
    }

    public function testEmitDataTwoLinesButWrongColumnCoundWillEmitErrorAndClose()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnceWith($this->isInstanceOf('UnexpectedValueException')));
        $this->decoder->on('close', $this->expectCallableOnce());


        $this->input->emit('data', array("name,partner\nalice\n"));
    }

    public function testEmitEndWithoutDataWillEmitErrorAndCloseDueToMissingHeader()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnceWith($this->isInstanceOf('UnderflowException')));
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->input->emit('end');
    }

    public function testEmitEndAfterDataWillNotEmitAnyDataOrErrorsAndClose()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableNever());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("hello\n"));
        $this->input->emit('end');
    }

    public function testEmitEmptyLineWillForwardErrorAndClose()
    {
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("\n"));
    }

    public function testEmitDataWithExactBufferSizeWillForward()
    {
        $this->decoder = new AssocDecoder($this->input, ',', '"', '\\', 5);
        $this->decoder->on('data', $this->expectCallableOnceWith(array('names' => 'hello')));

        $this->input->emit('data', array("names\nhello\n"));
    }

    public function testEmitDataWithBufferSizeOverflowWillForwardOverflowErrorAndClose()
    {
        $this->decoder = new AssocDecoder($this->input, ',', '"', '\\', 4);
        $this->decoder->on('data', $this->expectCallableNever());
        $this->decoder->on('error', $this->expectCallableOnceWith($this->isInstanceOf('OverflowException')));
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("hello\n"));
    }

    public function testEmitDataWithoutTrailingNewlineWillForwardOnEnd()
    {
        $this->decoder->on('data', $this->expectCallableOnceWith(array('name' => 'hello')));
        $this->decoder->on('end', $this->expectCallableOnce());

        $this->input->emit('data', array("name\nhello"));
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
        $this->decoder->on('data', $this->expectCallableOnceWith(array('name' => 'hello')));
        $this->decoder->on('data', array($this->decoder, 'close'));

        $this->decoder->on('end', $this->expectCallableNever());

        $this->input->emit('data', array("name\nhello"));
        $this->input->emit('end');
    }

    public function testUnreadableInputWillResultInUnreadableDecoder()
    {
        $this->input->close();
        $this->decoder = new AssocDecoder($this->input);

        $this->assertFalse($this->decoder->isReadable());
    }

    public function testUnreadableInputWillNotAddAnyEventListeners()
    {
        $this->input->close();
        $this->decoder = new AssocDecoder($this->input);

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

        $this->decoder = new AssocDecoder($this->input);
        $this->decoder->pause();
    }

    public function testForwardResumeToInput()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('resume');

        $this->decoder = new AssocDecoder($this->input);
        $this->decoder->resume();
    }
}
