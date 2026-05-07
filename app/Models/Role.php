<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Project-local Role model. Adds `SyncsToOutbox` so role definitions
 * (name, guard_name, uuid) replicate between live and local through
 * the standard sync pipeline.
 */
class Role extends SpatieRole
{
    use SyncsToOutbox;
}
