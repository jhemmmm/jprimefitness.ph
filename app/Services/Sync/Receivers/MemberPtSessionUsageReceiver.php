<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

class MemberPtSessionUsageReceiver extends DefaultReceiver
{
    use AppendOnlyReceiver;
}
