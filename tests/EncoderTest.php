<?php

use React\Stream\WritableResourceStream;
use Clue\React\Csv\Encoder;

class EncoderTest extends TestCase
{
    private $output;
    private $encoder;

    public function setUp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->output = new WritableResourceStream($stream, $loop);
        $this->encoder = new Encoder($this->output);
    }

    public function testWriteArray()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with("hello,world\n");

        $this->encoder->write(array('hello', 'world'));
    }

    public function testWriteArrayWithCustomDelimiter()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output, ';');

        $this->output->expects($this->once())->method('write')->with("hello;world\n");

        $this->encoder->write(array('hello', 'world'));
    }

    public function testWriteArrayTwiceWillSeparateWithNewlineAfterEachWrite()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->exactly(2))->method('write')->withConsecutive(array("hello\n"), array("world\n"));

        $this->encoder->write(array('hello'));
        $this->encoder->write(array('world'));
    }

    public function testWriteArrayWithStringWithSpacesUsesEnclosing()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with("\"hello world\"\n");

        $this->encoder->write(array('hello world'));
    }

    public function testWriteArrayWithSpecialStringUsesEnclosing()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with("\"this is a \"\"test\"\"\"\n");

        $this->encoder->write(array('this is a "test"'));
    }

    public function testWriteArrayWithUnicodeStringAsIs()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with("ünicode\n");

        $this->encoder->write(array('ünicode'));
    }

    public function testWriteArrayWithIntAndTrueLooksLikeString()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with("1,1,1\n");

        $this->encoder->write(array(1, true, '1'));
    }

    public function testWriteArrayWithNullAndFalseLooksLikeEmptyString()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with(",,\n");

        $this->encoder->write(array(null, false, ''));
    }

    public function testWriteStringWillEmitErrorAndClose()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->never())->method('write');

        $this->encoder->on('error', $this->expectCallableOnce());
        $this->encoder->on('close', $this->expectCallableOnce());

        $ret = $this->encoder->write('hello');
        $this->assertFalse($ret);

        $this->assertFalse($this->encoder->isWritable());
    }

    public function testEndWithoutDataWillEndOutputWithoutData()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->never())->method('write');
        $this->output->expects($this->once())->method('end')->with($this->equalTo(null));

        $this->encoder->end();
    }

    public function testEndWithDataWillForwardDataAndEndOutputWithoutData()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(true);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->once())->method('write')->with($this->equalTo("hello\n"));
        $this->output->expects($this->once())->method('end')->with($this->equalTo(null));

        $this->encoder->end(array('hello'));
    }

    public function testClosingEncoderClosesOutput()
    {
        $this->encoder->on('close', $this->expectCallableOnce());
        $this->output->on('close', $this->expectCallableOnce());

        $this->encoder->close();
    }

    public function testClosingOutputClosesEncoder()
    {
        $this->encoder->on('close', $this->expectCallableOnce());
        $this->output->on('close', $this->expectCallableOnce());

        $this->output->close();
    }

    public function testPassingClosedStreamToEncoderWillCloseImmediately()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(false);
        $this->encoder = new Encoder($this->output);

        $this->assertFalse($this->encoder->isWritable());
    }

    public function testWritingToClosedStreamWillNotForwardData()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('isWritable')->willReturn(false);
        $this->encoder = new Encoder($this->output);

        $this->output->expects($this->never())->method('write');

        $this->encoder->write("discarded");
    }

    public function testErrorEventWillForwardAndClose()
    {
        $this->encoder->on('error', $this->expectCallableOnce());
        $this->encoder->on('close', $this->expectCallableOnce());

        $this->output->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->output->isWritable());
    }

    public function testDrainEventWillForward()
    {
        $this->encoder->on('drain', $this->expectCallableOnce());

        $this->output->emit('drain');
    }
}
