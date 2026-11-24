<?php

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->mock(SmsService::class, function ($mock) {
        $mock->shouldReceive('generateOtp')->andReturn('123456');
        $mock->shouldReceive('sendOtp')->andReturn(['success' => true, 'message' => 'sent']);
    });

    $this->user = User::factory()->create(['phone' => '+995555123456', 'phone_country' => 'GE']);
    $this->phone = ['phone' => '555123456', 'phone_country' => 'GE'];
});

function apiResetToken(): string
{
    test()->postJson('/api/v1/forgot-password/send-code', test()->phone)->assertOk();

    return test()->postJson('/api/v1/forgot-password/verify-code', [...test()->phone, 'otp' => '123456'])
        ->assertOk()
        ->json('reset_token');
}

it('resets the password through the mobile flow', function () {
    $this->postJson('/api/v1/forgot-password/reset', [
        'reset_token' => apiResetToken(),
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertOk();

    expect(Hash::check('new-secret', $this->user->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', ['phone' => '+995555123456']);
});

it('lets the same phone request a reset token twice', function () {
    apiResetToken();
    apiResetToken();

    $this->assertDatabaseCount('password_reset_tokens', 1);
});

it('rejects an expired reset token', function () {
    $token = apiResetToken();

    $this->travel(16)->minutes();

    $this->postJson('/api/v1/forgot-password/reset', [
        'reset_token' => $token,
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertUnprocessable();
});
