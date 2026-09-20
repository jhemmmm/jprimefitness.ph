<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Password hashes are written raw: the `hashed` cast re-verifies a bcrypt hash's
 * cost against this node's BCRYPT_ROUNDS and throws when the sender's is higher.
 * A payload without a hash (older node, or a user who has none yet) never clears
 * the local one.
 */
class UserReceiver extends DefaultReceiver
{
    protected function fillModel(Model $model, array $payload): void
    {
        parent::fillModel($model, Arr::except($payload, 'password'));
    }

    protected function afterWrite(Model $model, array $payload, bool $created): void
    {
        if (! empty($payload['password'])) {
            DB::table($model->getTable())->where($model->getKeyName(), $model->getKey())->update(['password' => $payload['password']]);
        }
    }
}
