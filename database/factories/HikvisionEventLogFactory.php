<?php

namespace Database\Factories;

use App\Models\HikvisionEventLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HikvisionEventLog>
 */
class HikvisionEventLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_serial' => 'TEST-SERIAL',
            'event_serial_no' => (string) fake()->unique()->numberBetween(1000, 999999),
            'event_type' => 'AccessControllerEvent',
            'employee_no' => str_pad((string) fake()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT),
            'attendance_id' => null,
            'payload' => ['eventType' => 'AccessControllerEvent'],
            'processed_at' => null,
        ];
    }
}
