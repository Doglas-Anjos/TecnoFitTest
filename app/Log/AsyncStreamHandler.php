<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Async log handler using Swoole coroutine channels.
 *
 * Logs are queued in a channel and written asynchronously,
 * avoiding blocking the main request processing.
 */
class AsyncStreamHandler extends AbstractProcessingHandler
{
    private ?Channel $channel = null;
    private bool $consumerRunning = false;
    private mixed $stream;
    private string $streamPath;

    public function __construct(
        string $stream = 'php://stdout',
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        private int $bufferSize = 1000,
        private int $flushInterval = 100, // ms
    ) {
        parent::__construct($level, $bubble);
        $this->streamPath = $stream;
    }

    protected function write(LogRecord $record): void
    {
        $this->ensureConsumerRunning();

        $formatted = $this->getFormatter()->format($record);

        // Non-blocking push to channel
        // If channel is full, drop the log (better than blocking)
        if ($this->channel && !$this->channel->isFull()) {
            $this->channel->push($formatted, 0.001); // 1ms timeout
        }
    }

    private function ensureConsumerRunning(): void
    {
        // Only start consumer in coroutine context
        if ($this->consumerRunning || !Coroutine::getCid()) {
            return;
        }

        $this->channel = new Channel($this->bufferSize);
        $this->consumerRunning = true;

        // Spawn consumer coroutine
        Coroutine::create(function () {
            $this->openStream();
            $buffer = [];
            $lastFlush = microtime(true) * 1000;

            while (true) {
                // Try to pop with short timeout
                $log = $this->channel->pop(0.05); // 50ms timeout

                if ($log !== false) {
                    $buffer[] = $log;
                }

                $now = microtime(true) * 1000;
                $shouldFlush = count($buffer) >= 50 // Batch size
                    || ($now - $lastFlush >= $this->flushInterval && count($buffer) > 0);

                if ($shouldFlush) {
                    $this->flushBuffer($buffer);
                    $buffer = [];
                    $lastFlush = $now;
                }

                // Check if channel is closed and empty
                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED && $this->channel->isEmpty()) {
                    break;
                }
            }

            // Final flush
            if (count($buffer) > 0) {
                $this->flushBuffer($buffer);
            }

            $this->closeStream();
        });
    }

    private function flushBuffer(array $buffer): void
    {
        if (empty($buffer) || !$this->stream) {
            return;
        }

        $output = implode('', $buffer);
        fwrite($this->stream, $output);
    }

    private function openStream(): void
    {
        $this->stream = fopen($this->streamPath, 'a');
        if ($this->stream) {
            stream_set_blocking($this->stream, false);
        }
    }

    private function closeStream(): void
    {
        if ($this->stream && $this->streamPath !== 'php://stdout' && $this->streamPath !== 'php://stderr') {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    public function close(): void
    {
        if ($this->channel) {
            $this->channel->close();
        }
        $this->consumerRunning = false;
        parent::close();
    }
}
