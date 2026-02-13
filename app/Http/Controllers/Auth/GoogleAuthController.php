<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth provider
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Check if user exists with this Google ID
            $user = User::query()->where('google_id', $googleUser->id)->first();

            if ($user) {
                // Update tokens for existing Google user
                $user->update([
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'avatar' => $googleUser->avatar,
                ]);
            } else {
                // Check if user exists with same email (account linking)
                $user = User::query()->where('email', $googleUser->email)->first();

                if ($user) {
                    // Link Google account to existing user
                    $user->update([
                        'google_id' => $googleUser->id,
                        'google_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken,
                        'avatar' => $googleUser->avatar,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                } else {
                    // Create new user with Google data
                    $user = User::query()->create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'google_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken,
                        'avatar' => $googleUser->avatar,
                        'email_verified_at' => now(), // Trust Google's email verification
                        'password' => null, // Google-only user
                    ]);
                }
            }

            // Log the user in
            Auth::login($user, remember: true);

            return redirect()->intended(config('fortify.home'));
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Unable to login with Google. Please try again.']);
        }
    }
}
