<?php

declare(strict_types=1);

namespace FormToEmail\Enum;

/**
 * Enum LogFormat
 *
 * Controls output representation for the Logger.
 */
enum LogFormat: string
{
    case Text = 'text';
    case Json = 'json';
}
