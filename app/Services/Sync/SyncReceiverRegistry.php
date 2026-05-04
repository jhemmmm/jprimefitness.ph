<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Sync\Receivers\DefaultReceiver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class SyncReceiverRegistry
{
    public function __construct(private Container $container) {}

    public function receiverFor(string $entityType): DefaultReceiver
    {
        $entities = (array) config('sync.entities', []);

        if (! isset($entities[$entityType])) {
            throw new InvalidArgumentException("Unknown sync entity type: {$entityType}");
        }

        $class = $entities[$entityType]['receiver'] ?? DefaultReceiver::class;

        return $this->container->make($class);
    }

    /**
     * Entity types in dependency order. Snapshot bootstrap iterates
     * this list so parents land before children.
     *
     * @return array<int, string>
     */
    public function entityTypesInOrder(): array
    {
        return array_keys((array) config('sync.entities', []));
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelFor(string $entityType): string
    {
        $entities = (array) config('sync.entities', []);

        if (! isset($entities[$entityType]['model'])) {
            throw new InvalidArgumentException("Unknown sync entity type: {$entityType}");
        }

        return $entities[$entityType]['model'];
    }
}
