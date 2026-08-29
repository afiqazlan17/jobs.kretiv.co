<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_id_is_auto_generated_sequentially(): void
    {
        $first = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret', 'role' => 'staff']);
        $second = User::create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'secret', 'role' => 'staff']);

        $this->assertSame('KCM001', $first->staff_id);
        $this->assertSame('KCM002', $second->staff_id);
    }
}
