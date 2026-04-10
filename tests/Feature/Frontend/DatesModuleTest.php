<?php

namespace Tests\Feature\Frontend;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class DatesModuleTest extends TestCase
{
    public function test_datetime_input_value_can_preserve_empty_inputs(): void
    {
        $script = <<<'NODE'
globalThis.JPrime = { timezone: 'Asia/Manila' };
const { toDateTimeInputValue } = await import('./resources/js/dates.js');

console.log(JSON.stringify({
    empty: toDateTimeInputValue(null, false),
    default_empty: toDateTimeInputValue(null),
    populated: toDateTimeInputValue('2026-03-20T10:00:00.000Z', false),
}));
NODE;

        $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
        $process->mustRun();

        /** @var array{empty:string,default_empty:string,populated:string} $result */
        $result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('', $result['empty']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $result['default_empty']);
        $this->assertSame('2026-03-20T18:00', $result['populated']);
    }
}
