<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Auto-increment ids differ between nodes, so a row's foreign keys travel
 * as the parent's uuid under `_refs` and are swapped back to local ids on
 * receipt. Which columns are foreign keys is read from the schema, so
 * every declared constraint is covered without per-model configuration.
 *
 * ponytail: ids embedded in JSON columns (sale_transactions.details
 * line_items / subscription_id) and system_activities.subject_id are not
 * remapped; add a per-model hook here if cross-node voids need them.
 */
class ForeignKeys
{
    /** Polymorphic pointers the schema can't declare: column => type column whose (pluralised) value is the parent table. */
    private const POLYMORPHIC = ['cash_ledger_entries' => ['source_id' => 'source_type']];

    /** @var array<string, array<string, string>> table => [column => parent table] */
    private static array $columns = [];

    /**
     * Sender side: stamp each row with `_refs` (column => parent uuid).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function attach(string $table, array $rows): array
    {
        $wanted = [];

        foreach ($rows as $row) {
            foreach (self::parentsOf($table, $row) as $column => $parent) {
                if (! empty($row[$column])) {
                    $wanted[$parent][(int) $row[$column]] = true;
                }
            }
        }

        $uuids = [];

        foreach ($wanted as $parent => $ids) {
            $uuids[$parent] = DB::table($parent)->whereIn('id', array_keys($ids))->pluck('uuid', 'id')->all();
        }

        foreach ($rows as &$row) {
            $row['_refs'] = [];

            foreach (self::parentsOf($table, $row) as $column => $parent) {
                if (! empty($row[$column])) {
                    $row['_refs'][$column] = $uuids[$parent][(int) $row[$column]] ?? null;
                }
            }
        }

        return $rows;
    }

    /**
     * Receiver side: swap `_refs` back to local ids. A parent that hasn't
     * landed here yet throws, so the event errors and is retried later.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function resolve(string $table, array $payload): array
    {
        if (! array_key_exists('_refs', $payload)) {
            return $payload; // event from a sender that predates _refs
        }

        $refs = (array) $payload['_refs'];
        unset($payload['_refs']);

        foreach (self::parentsOf($table, $payload) as $column => $parent) {
            if (empty($payload[$column])) {
                continue;
            }

            $uuid = $refs[$column] ?? null;
            $id = $uuid ? DB::table($parent)->where('uuid', $uuid)->value('id') : null;

            if ($id === null) {
                throw new RuntimeException("{$table}.{$column} -> {$parent} ".($uuid ?? '(no uuid)').' is not on this node yet.');
            }

            $payload[$column] = (int) $id;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string> column => parent table
     */
    private static function parentsOf(string $table, array $row): array
    {
        $parents = self::$columns[$table] ??= collect(Schema::getForeignKeys($table))
            ->filter(fn (array $fk) => count($fk['columns']) === 1 && Schema::hasColumn($fk['foreign_table'], 'uuid'))
            ->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => $fk['foreign_table']])
            ->all();

        foreach (self::POLYMORPHIC[$table] ?? [] as $column => $typeColumn) {
            if (! empty($row[$typeColumn])) {
                $parents[$column] = Str::plural($row[$typeColumn]);
            }
        }

        return $parents;
    }
}
