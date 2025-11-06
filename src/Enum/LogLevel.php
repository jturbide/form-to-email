<?php

declare(strict_types=1);

namespace FormToEmail\Enum;

/**
 * Enum LogLevel
 *
 * Internal, dependency-free log levels.
 */
enum LogLevel: string
{
    case Debug = 'debug';
    case Info  = 'info';
    case Error = 'error';
}
