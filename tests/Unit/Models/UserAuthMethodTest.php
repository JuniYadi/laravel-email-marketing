<?php

use App\Models\User;

test('has google account returns true when google id exists', function () {
    $user = new User([
        'google_id' => '123456789',
    ]);

    expect($user->hasGoogleAccount())->toBeTrue();
});

test('has google account returns false when google id is null', function () {
    $user = new User([
        'google_id' => null,
    ]);

    expect($user->hasGoogleAccount())->toBeFalse();
});

test('has password returns true when password exists', function () {
    $user = new User;
    $user->setRawAttributes(['password' => 'hashed-password']);

    expect($user->hasPassword())->toBeTrue();
});

test('has password returns false when password is null', function () {
    $user = new User;
    $user->setRawAttributes(['password' => null]);

    expect($user->hasPassword())->toBeFalse();
});

test('authentication method returns google when only google id exists', function () {
    $user = new User([
        'google_id' => '123456789',
        'password' => null,
    ]);

    expect($user->authenticationMethod())->toBe('google');
});

test('authentication method returns password when only password exists', function () {
    $user = new User;
    $user->setRawAttributes([
        'google_id' => null,
        'password' => 'hashed-password',
    ]);

    expect($user->authenticationMethod())->toBe('password');
});

test('authentication method returns both when google id and password exist', function () {
    $user = new User;
    $user->setRawAttributes([
        'google_id' => '123456789',
        'password' => 'hashed-password',
    ]);

    expect($user->authenticationMethod())->toBe('both');
});

test('authentication method returns none when neither google id nor password exist', function () {
    $user = new User([
        'google_id' => null,
        'password' => null,
    ]);

    expect($user->authenticationMethod())->toBe('none');
});
