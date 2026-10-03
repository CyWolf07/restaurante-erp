<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\PosOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test', ['mobile:access'], now()->addDay())->plainTextToken;
    }

    public function test_login_redacts_secrets_and_logout_revokes_token(): void
    {
        $user = User::factory()->create(['role' => 'waiter', 'active' => true, 'password' => 'password-test']);
        $result = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password-test', 'device_name' => 'Android'])
            ->assertOk()->assertJsonPath('user.role', 'waiter')->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.pin_code');
        $token = $result->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_inactive_invalid_and_expired_credentials_are_rejected(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $user = User::factory()->create(['role' => 'cashier', 'active' => false]);
        $this->withToken($this->token($user))->getJson('/api/v1/me')->assertUnauthorized();
        $user->update(['active' => true]);
        $expired = $user->createToken('expired', ['mobile:access'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'test'])->assertUnauthorized();
    }

    public function test_waiter_scope_pagination_and_permissions(): void
    {
        $waiter = User::factory()->create(['role' => 'waiter', 'active' => true]);
        $other = User::factory()->create(['role' => 'waiter', 'active' => true]);
        $own = Order::create(['waiter_id' => $waiter->id, 'table_number' => 1, 'status' => 'pending', 'total' => 100]);
        Order::create(['waiter_id' => $other->id, 'table_number' => 2, 'status' => 'pending', 'total' => 200]);
        $this->withToken($this->token($waiter))->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/orders?per_page=51')->assertUnprocessable();
        $this->postJson("/api/v1/orders/{$own->id}/ready")->assertForbidden();
    }

    public function test_cook_transition_is_audited_and_cannot_repeat(): void
    {
        $cook = User::factory()->create(['role' => 'cook', 'active' => true]);
        $waiter = User::factory()->create(['role' => 'waiter', 'active' => true]);
        $order = Order::create(['waiter_id' => $waiter->id, 'table_number' => 1, 'status' => 'in_kitchen', 'kitchen_sent_at' => now(), 'total' => 100]);
        Order::create(['waiter_id' => $waiter->id, 'table_number' => 2, 'status' => 'pending', 'total' => 100]);
        $this->withToken($this->token($cook))->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total', null);
        $this->postJson("/api/v1/orders/{$order->id}/ready")->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready']);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'user_id' => $cook->id, 'action' => 'ready']);
        $this->postJson("/api/v1/orders/{$order->id}/ready")->assertUnprocessable();
    }

    public function test_bearer_ability_and_current_role_are_required(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $token = $user->createToken('other', ['read:other'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
        $token = $this->token($user);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $user->update(['role' => 'waiter']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.capabilities.mark_ready', false);
    }

    public function test_closed_cash_register_blocks_mobile_ready(): void
    {
        $cook = User::factory()->create(['role' => 'cook']);
        $order = Order::create(['waiter_id' => $cook->id, 'table_number' => 1, 'status' => 'in_kitchen', 'kitchen_sent_at' => now()]);
        $this->mock(PosOperationService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andThrow(ValidationException::withMessages(['order' => 'La caja está cerrada.']));
        });
        $this->withToken($this->token($cook))->postJson("/api/v1/orders/{$order->id}/ready")->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'in_kitchen']);
    }
}
