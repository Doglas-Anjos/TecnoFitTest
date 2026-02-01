<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Async file log handler using Swoole coroutine channels.
 *
 * Writes logs to a rotating file asynchronously with JSON format,
 * ideal for log aggregation systems.
 */
class AsyncFileHandler extends AbstractProcessingHandler
{
    private ?Channel $channel = null;
    private bool $consumerRunning = false;
    private mixed $stream = null;
    private string $currentFile;

    public function __construct(
        private string $basePath,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        private int $bufferSize = 2000,
        private int $maxFileSize = 50 * 1024 * 1024, // 50MB
    ) {
        parent::__construct($level, $bubble);
        $this->currentFile = $this->getFilePath();
        $this->setFormatter(new JsonFormatter());
    }

    protected function write(LogRecord $record): void
    {
        $this->ensureConsumerRunning();

        $formatted = $this->getFormatter()->format($record);

        // Non-blocking push to channel
        if ($this->channel && !$this->channel->isFull()) {
            $this->channel->push($formatted, 0.001);
        }
    }

    private function ensureConsumerRunning(): void
    {
        if ($this->consumerRunning || !Coroutine::getCid()) {
            return;
        }

        $this->channel = new Channel($this->bufferSize);
        $this->consumerRunning = true;

        Coroutine::create(function () {
            $buffer = [];
            $lastFlush = microtime(true) * 1000;
            $bytesWritten = 0;

            while (true) {
                $log = $this->channel->pop(0.1); // 100ms timeout

                if ($log !== false) {
                    $buffer[] = $log;
                    $bytesWritten += strlen($log);
                }

                $now = microtime(true) * 1000;
                $shouldFlush = count($buffer) >= 100
                    || ($now - $lastFlush >= 200 && count($buffer) > 0); // 200ms

                if ($shouldFlush) {
                    $this->flushBuffer($buffer);
                    $buffer = [];
                    $lastFlush = $now;

                    // Check for rotation
                    if ($bytesWritten > $this->maxFileSize) {
                        $this->rotateFile();
                        $bytesWritten = 0;
                    }
                }

                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED && $this->channel->isEmpty()) {
                    break;
                }
            }

            if (count($buffer) > 0) {
                $this->flushBuffer($buffer);
            }

            $this->closeStream();
        });
    }

    private function flushBuffer(array $buffer): void
    {
        if (empty($buffer)) {
            return;
        }

        $this->ensureStream();

        if ($this->stream) {
            $output = implode('', $buffer);
            fwrite($this->stream, $output);
        }
    }

    private function ensureStream(): void
    {
        if ($this->stream) {
            return;
        }

        $dir = dirname($this->currentFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->stream = fopen($this->currentFile, 'a');
    }

    private function closeStream(): void
    {
        if ($this->stream) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    private function rotateFile(): void
    {
        $this->closeStream();

        // Rename current file with timestamp
        if (file_exists($this->currentFile)) {
            $rotatedFile = $this->basePath . '.' . date('Y-m-d-His') . '.log';
            rename($this->currentFile, $rotatedFile);

            // Clean old files (keep last 7)
            $this->cleanOldFiles();
        }

        $this->currentFile = $this->getFilePath();
    }

    private function getFilePath(): string
    {
        return $this->basePath . '.log';
    }

    private function cleanOldFiles(): void
    {
        $pattern = $this->basePath . '.*.log';
        $files = glob($pattern);

        if ($files && count($files) > 7) {
            // Sort by modification time, oldest first
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

            // Remove oldest files
            $toDelete = array_slice($files, 0, count($files) - 7);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
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
