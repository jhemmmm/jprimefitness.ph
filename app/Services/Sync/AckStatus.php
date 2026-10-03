<?php

declare(strict_types=1);

namespace App\Services\Sync;

final class AckStatus
{
    public const OK = 'ok';
    public const CONFLICT = 'conflict';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';
}
