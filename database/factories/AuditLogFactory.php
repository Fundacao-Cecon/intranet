<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['action' => 'FIXTURE', 'target_snapshot' => 'Dado sintético', 'outcome' => 'success', 'occurred_at' => now('UTC')];
    }
}
