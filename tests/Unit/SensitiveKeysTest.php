<?php

namespace Tests\Unit;

use App\Support\SensitiveKeys;
use PHPUnit\Framework\TestCase;

class SensitiveKeysTest extends TestCase
{
    public function test_scrub_removes_sensitive_keys_at_any_depth(): void
    {
        $result = SensitiveKeys::scrub([
            'name' => 'Jane',
            'password' => 'secret',
            'profile' => ['token' => 'abc', 'city' => 'Houston'],
            'options' => [['control_id' => 'PR-1'], ['label' => 'kept']],
        ]);

        $this->assertSame('Jane', $result['name']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('token', $result['profile']);
        $this->assertSame('Houston', $result['profile']['city']);
        $this->assertArrayNotHasKey('control_id', $result['options'][0]);
        $this->assertSame('kept', $result['options'][1]['label']);
    }

    public function test_mask_replaces_sensitive_values_but_keeps_the_key_visible(): void
    {
        $result = SensitiveKeys::mask([
            'name' => 'Jane',
            'password' => 'secret',
            'profile' => ['token' => 'abc', 'city' => 'Houston'],
        ]);

        $this->assertSame('Jane', $result['name']);
        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['profile']['token']);
        $this->assertSame('Houston', $result['profile']['city']);
    }

    public function test_null_and_scalars_pass_through_unchanged(): void
    {
        $this->assertNull(SensitiveKeys::scrub(null));
        $this->assertSame('value', SensitiveKeys::scrub('value'));
        $this->assertNull(SensitiveKeys::mask(null));
    }
}
