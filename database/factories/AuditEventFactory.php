<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => null,
            'action' => 'demo.record.created',
            'subject_type' => null,
            'subject_id' => null,
            'before_state' => null,
            'after_state' => null,
            'reason' => null,
            'correlation_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory Seeder',
            'occurred_at' => now(),
        ];
    }
}
