<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Log\AsyncFileHandler;
use App\Log\AsyncStreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;

// Log level from environment (default: INFO for production performance)
$logLevel = match (getenv('LOG_LEVEL') ?: 'INFO') {
    'DEBUG' => Level::Debug,
    'INFO' => Level::Info,
    'WARNING' => Level::Warning,
    'ERROR' => Level::Error,
    default => Level::Info,
};

// Enable file logging from environment
$enableFileLog = filter_var(getenv('LOG_FILE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);

$handlers = [
    // Async console output (stdout) - non-blocking
    [
        'class' => AsyncStreamHandler::class,
        'constructor' => [
            'stream' => 'php://stdout',
            'level' => $logLevel,
            'bubble' => true,
            'bufferSize' => 1000,
            'flushInterval' => 100, // ms
        ],
        'formatter' => [
            'class' => LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %level_name%: %message% %context%\n",
                'dateFormat' => 'H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
];

// Add async file handler if enabled
if ($enableFileLog) {
    $handlers[] = [
        'class' => AsyncFileHandler::class,
        'constructor' => [
            'basePath' => BASE_PATH . '/runtime/logs/app',
            'level' => $logLevel,
            'bubble' => true,
            'bufferSize' => 2000,
            'maxFileSize' => 50 * 1024 * 1024, // 50MB
        ],
    ];
}

return [
    'default' => [
        'handlers' => $handlers,
        'processors' => [
            [
                'class' => PsrLogMessageProcessor::class,
            ],
        ],
    ],

    // Separate channel for email logs (async coroutine)
    'email' => [
        'handlers' => [
            [
                'class' => AsyncStreamHandler::class,
                'constructor' => [
                    'stream' => 'php://stdout',
                    'level' => $logLevel,
                    'bufferSize' => 500,
                    'flushInterval' => 200,
                ],
                'formatter' => [
                    'class' => LineFormatter::class,
                    'constructor' => [
                        'format' => "[%datetime%] EMAIL: %message% %context%\n",
                        'dateFormat' => 'H:i:s',
                        'allowInlineLineBreaks' => true,
                    ],
                ],
            ],
        ],
    ],
];
