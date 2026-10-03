<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Project-local Permission model. Adds `SyncsToOutbox` so permission
 * definitions replicate between live and local through the standard
 * sync pipeline.
 */
class Permission extends SpatiePermission
{
    use SyncsToOutbox;
}
