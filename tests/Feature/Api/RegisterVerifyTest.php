<?php

use App\Models\User;
use App\Services\BusinessCentralService;
use App\Services\SmsService;

beforeEach(function () {
    $this->mock(SmsService::class, function ($mock) {
        $mock->shouldReceive('generateOtp')->andReturn('123456');
        $mock->shouldReceive('sendOtp')->andReturn(['success' => true, 'message' => 'sent']);
    });

    $this->postJson('/api/v1/register', [
        'user_type' => 'individual',
        'tax_id' => '12345678901',
        'name' => 'Test',
        'lastname' => 'User',
        'phone_country' => 'GE',
        'phone' => '555123456',
        'address' => 'Tbilisi, Rustaveli 1',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertOk();

    $this->verify = ['phone' => '555123456', 'phone_country' => 'GE', 'otp' => '123456'];
});

it('saves no user and accepts the same code again when the BC sync fails', function () {
    $this->mock(BusinessCentralService::class, function ($mock) {
        $mock->shouldReceive('getCustomer')->twice()->andReturnUsing(
            fn () => throw new RuntimeException('BC is down'),
            fn () => ['value' => [['No' => 'C100']]],
        );
    });

    $this->postJson('/api/v1/register/verify-phone', $this->verify)->assertServerError();
    $this->assertDatabaseMissing('users', ['tax_id' => '12345678901']);

    $this->postJson('/api/v1/register/verify-phone', $this->verify)->assertCreated();
    expect(User::where('tax_id', '12345678901')->first()->bc_customer_no)->toBe('C100');
});

it('returns a validation error instead of crashing when the phone was registered before verification', function () {
    User::factory()->create(['phone' => '+995555123456', 'phone_country' => 'GE']);

    $this->postJson('/api/v1/register/verify-phone', $this->verify)
        ->assertUnprocessable()
        ->assertJsonPath('message', __('This phone number is already registered.'));

    expect(User::where('phone', '+995555123456')->count())->toBe(1);
});
