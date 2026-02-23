<?php
// FILE: app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Support\RbacBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;
use Throwable;

class AuthController extends Controller
{
    // Show login form
    public function showLogin()
    {
        if (Auth::check()) return redirect('/dashboard');
        return view('auth.login');
    }

    // Handle login POST
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Auth::validate($credentials)) {
            return back()->withErrors(['email' => 'Wrong email or password.'])->withInput();
        }

        if ($user->two_factor_enabled) {
            $code = (string) random_int(100000, 999999);
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            $request->session()->put('auth.2fa.user_id', $user->id);
            $request->session()->put('auth.2fa.remember', $remember);
            $request->session()->put('auth.2fa.sent_at', now()->toDateTimeString());

            $this->sendTwoFactorCode($user, $code);

            return redirect()->route('2fa.form');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    // Show register form
    public function showRegister()
    {
        if (Auth::check()) return redirect('/dashboard');
        return view('auth.register');
    }

    // Handle register POST
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'terms'    => 'accepted',
        ]);

        $signupRole = app(RbacBootstrap::class)->signupRole();
        $email = strtolower(trim((string) $request->email));
        $rootEmail = strtolower(trim((string) config('haarray.root_admin_email', 'prateekbhujelpb@gmail.com')));
        if ($email !== '' && $email === $rootEmail) {
            $signupRole = 'super-admin';
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $email,
            'password' => Hash::make($request->password),
            'role' => $signupRole,
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('haarray.terms_version', '2026-02-23'),
        ]);
        $this->syncUserRole($user, $signupRole);

        Auth::login($user);
        return redirect('/dashboard');
    }

    public function showTwoFactor(Request $request)
    {
        if (!$request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $userId = $request->session()->get('auth.2fa.user_id');
        if (!$userId) {
            return redirect()->route('login')->withErrors(['email' => '2FA session expired. Please login again.']);
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['auth.2fa.user_id', 'auth.2fa.remember', 'auth.2fa.sent_at']);
            return redirect()->route('login')->withErrors(['email' => 'User session not found.']);
        }

        $validCode = $user->two_factor_code === $request->code
            && $user->two_factor_expires_at
            && Carbon::parse($user->two_factor_expires_at)->isFuture();

        if (!$validCode) {
            return back()->withErrors(['code' => 'Invalid or expired code.'])->withInput();
        }

        $remember = (bool) $request->session()->get('auth.2fa.remember', false);

        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        Auth::login($user, $remember);
        $request->session()->forget(['auth.2fa.user_id', 'auth.2fa.remember', 'auth.2fa.sent_at']);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id');
        if (!$userId) {
            return redirect()->route('login')->withErrors(['email' => '2FA session expired. Please login again.']);
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->withErrors(['email' => 'User session not found.']);
        }

        $code = (string) random_int(100000, 999999);
        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        $this->sendTwoFactorCode($user, $code);

        return back()->with('success', 'A new verification code has been sent.');
    }

    public function facebookRedirect(Request $request): RedirectResponse
    {
        $clientId = config('services.facebook.client_id');
        $redirectUri = config('services.facebook.redirect');

        if (!$clientId || !$redirectUri) {
            return redirect()->route('login')->withErrors(['email' => 'Facebook login is not configured yet.']);
        }

        $state = Str::random(40);
        $request->session()->put('oauth.facebook.state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'email,public_profile',
            'response_type' => 'code',
        ]);

        return redirect()->away('https://www.facebook.com/v19.0/dialog/oauth?' . $query);
    }

    public function facebookCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('oauth.facebook.state');
        $receivedState = (string) $request->query('state', '');

        if (!$expectedState || $expectedState !== $receivedState) {
            return redirect()->route('login')->withErrors(['email' => 'Invalid Facebook auth state.']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('login')->withErrors(['email' => 'Facebook login was cancelled or failed.']);
        }

        $tokenResponse = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri' => config('services.facebook.redirect'),
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            return redirect()->route('login')->withErrors(['email' => 'Unable to fetch Facebook access token.']);
        }

        $accessToken = (string) ($tokenResponse->json('access_token') ?? '');
        if ($accessToken === '') {
            return redirect()->route('login')->withErrors(['email' => 'Facebook token response is invalid.']);
        }

        $profileResponse = Http::get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if (!$profileResponse->successful()) {
            return redirect()->route('login')->withErrors(['email' => 'Unable to fetch Facebook profile.']);
        }

        $profile = $profileResponse->json();
        $facebookId = (string) ($profile['id'] ?? '');
        $email = (string) ($profile['email'] ?? '');
        $name = (string) ($profile['name'] ?? 'Facebook User');

        if ($facebookId === '') {
            return redirect()->route('login')->withErrors(['email' => 'Facebook profile is missing user ID.']);
        }

        if ($email === '') {
            $email = "facebook_{$facebookId}@facebook.local";
        }

        $user = User::where('facebook_id', $facebookId)
            ->orWhere('email', $email)
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'facebook_id' => $facebookId,
                'role' => app(RbacBootstrap::class)->signupRole(),
            ]);
        } else {
            $user->update([
                'name' => $user->name ?: $name,
                'facebook_id' => $facebookId,
            ]);
        }
        $this->syncUserRole($user, app(RbacBootstrap::class)->signupRole());

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    private function sendTwoFactorCode(User $user, string $code): void
    {
        Mail::raw(
            "Your HariLog verification code is: {$code}\n\nThis code expires in 10 minutes.",
            function ($message) use ($user) {
                $message->to($user->email)->subject('HariLog 2FA Verification Code');
            }
        );
    }

    private function syncUserRole(User $user, string $fallbackRole = 'user'): void
    {
        try {
            if (!Schema::hasTable('roles') || !Schema::hasTable('model_has_roles')) {
                return;
            }

            app(RbacBootstrap::class)->syncUserRole($user, $fallbackRole);
        } catch (Throwable $exception) {
            // Keep authentication flow working even if role tables are not ready.
        }
    }
}
