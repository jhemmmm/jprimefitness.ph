<?php

declare(strict_types=1);

namespace App\Services\Sync;

final class SyncOp
{
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const DELETE = 'delete';
    public const RESTORE = 'restore';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::CREATE, self::UPDATE, self::DELETE, self::RESTORE];
    }
}
