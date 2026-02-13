<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    // Reset AUTH_MODE to default 'both' for each test
    Config::set('auth.auth_mode', 'both');
});

test('user can redirect to google oauth', function () {
    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
});

test('new user can register via google', function () {
    mockSocialiteCallback([
        'id' => '123456789',
        'email' => 'newuser@example.com',
        'name' => 'New User',
        'avatar' => 'https://example.com/avatar.jpg',
        'token' => 'mock-token',
        'refreshToken' => 'mock-refresh-token',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New User')
        ->and($user->google_id)->toBe('123456789')
        ->and($user->avatar)->toBe('https://example.com/avatar.jpg')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

test('existing user links google account on first google login', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    mockSocialiteCallback([
        'id' => '987654321',
        'email' => 'existing@example.com',
        'name' => 'Existing User',
        'avatar' => 'https://example.com/new-avatar.jpg',
        'token' => 'new-token',
        'refreshToken' => 'new-refresh-token',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($existingUser);

    $existingUser->refresh();
    expect($existingUser->google_id)->toBe('987654321')
        ->and($existingUser->avatar)->toBe('https://example.com/new-avatar.jpg')
        ->and($existingUser->email_verified_at)->not->toBeNull();
});

test('user with linked google account can login via google', function () {
    $user = User::factory()->create([
        'email' => 'linkeduser@example.com',
        'google_id' => '111222333',
        'google_token' => 'old-token',
        'google_refresh_token' => 'old-refresh-token',
        'avatar' => 'https://example.com/old-avatar.jpg',
    ]);

    mockSocialiteCallback([
        'id' => '111222333',
        'email' => 'linkeduser@example.com',
        'name' => 'Linked User',
        'avatar' => 'https://example.com/updated-avatar.jpg',
        'token' => 'updated-token',
        'refreshToken' => 'updated-refresh-token',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->google_token)->not->toBe('old-token')
        ->and($user->google_refresh_token)->not->toBe('old-refresh-token')
        ->and($user->avatar)->toBe('https://example.com/updated-avatar.jpg');
});

test('user with google only account cannot login with password', function () {
    $user = User::factory()->create([
        'email' => 'googleonly@example.com',
        'password' => null,
        'google_id' => '444555666',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'some-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('google oauth stores and updates tokens', function () {
    mockSocialiteCallback([
        'id' => '777888999',
        'email' => 'tokentest@example.com',
        'name' => 'Token Test',
        'avatar' => 'https://example.com/avatar.jpg',
        'token' => 'access-token-123',
        'refreshToken' => 'refresh-token-456',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'tokentest@example.com')->first();
    expect($user->google_token)->not->toBeNull()
        ->and($user->google_refresh_token)->not->toBeNull();
});

test('email is marked as verified for google users', function () {
    mockSocialiteCallback([
        'id' => '101112131',
        'email' => 'verified@example.com',
        'name' => 'Verified User',
        'avatar' => 'https://example.com/avatar.jpg',
        'token' => 'token',
        'refreshToken' => 'refresh-token',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'verified@example.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

test('avatar is saved from google profile', function () {
    mockSocialiteCallback([
        'id' => '141516171',
        'email' => 'avatar@example.com',
        'name' => 'Avatar User',
        'avatar' => 'https://lh3.googleusercontent.com/a/test-avatar',
        'token' => 'token',
        'refreshToken' => 'refresh-token',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'avatar@example.com')->first();
    expect($user->avatar)->toBe('https://lh3.googleusercontent.com/a/test-avatar');
});

test('google oauth callback handles exceptions gracefully', function () {
    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andThrow(new \Exception('OAuth failed'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('google oauth is blocked when auth mode is manual only', function () {
    Config::set('auth.auth_mode', 'manual_only');

    $response = $this->get(route('auth.google.redirect'));

    $response->assertNotFound();
});

test('google oauth callback is blocked when auth mode is manual only', function () {
    Config::set('auth.auth_mode', 'manual_only');

    mockSocialiteCallback([
        'id' => '999888777',
        'email' => 'test@example.com',
        'name' => 'Test',
        'avatar' => 'avatar.jpg',
        'token' => 'token',
        'refreshToken' => 'refresh',
    ]);

    $response = $this->get(route('auth.google.callback'));

    $response->assertNotFound();
});

test('user is redirected to google when auth mode is google only and accessing login', function () {
    Config::set('auth.auth_mode', 'google_only');

    $response = $this->get(route('login'));

    $response->assertRedirect(route('auth.google.redirect'));
});

test('user is redirected to google when auth mode is google only and accessing register', function () {
    Config::set('auth.auth_mode', 'google_only');

    $response = $this->get(route('register'));

    $response->assertRedirect(route('auth.google.redirect'));
});

// Helper function to mock Socialite callback
function mockSocialiteCallback(array $attributes): void
{
    $mockUser = Mockery::mock(SocialiteUser::class);
    $mockUser->shouldReceive('getId')->andReturn($attributes['id']);
    $mockUser->shouldReceive('getEmail')->andReturn($attributes['email']);
    $mockUser->shouldReceive('getName')->andReturn($attributes['name']);
    $mockUser->shouldReceive('getAvatar')->andReturn($attributes['avatar']);
    $mockUser->id = $attributes['id'];
    $mockUser->email = $attributes['email'];
    $mockUser->name = $attributes['name'];
    $mockUser->avatar = $attributes['avatar'];
    $mockUser->token = $attributes['token'];
    $mockUser->refreshToken = $attributes['refreshToken'] ?? null;

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($mockUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}
