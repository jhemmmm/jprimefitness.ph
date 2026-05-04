<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

class SystemActivityReceiver extends DefaultReceiver
{
    use AppendOnlyReceiver;
}
