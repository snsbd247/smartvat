<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Security\Http\Requests\LoginRequest;
use App\Shared\Audit\AuditLogger;
use App\Shared\Mail\MailConfigurator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate by email OR username and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => ['This account is not active.'],
            ]);
        }

        // Two-factor challenge, when enabled for this user.
        if ($user->two_factor_enabled) {
            $code = (string) $request->input('code', '');
            if ($code === '') {
                return response()->json(['two_factor_required' => true]);
            }
            if (! \App\Shared\Security\Totp::verify((string) $user->two_factor_secret, $code)) {
                throw ValidationException::withMessages(['code' => ['The authentication code is invalid.']]);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        AuditLogger::log('login', $user, companyId: $user->companies()->value('companies.id'));

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ],
            'companies' => $user->companies()
                ->get(['companies.id', 'companies.code', 'companies.name'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'is_default' => (bool) $c->pivot->is_default,
                ]),
        ]);
    }

    /**
     * Current authenticated user with role/permission info for the active company.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ]);
    }

    /**
     * Change the authenticated user's password after verifying the current one.
     * All other tokens are revoked so other sessions must re-authenticate.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Keep the current token, drop the rest.
        $currentId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentId)->delete();

        AuditLogger::log('password_changed', $user);

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Send a password-reset link. Always returns a neutral message so the
     * endpoint cannot be used to enumerate which emails exist.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Use the user's default company SMTP settings if configured.
        $user = User::where('email', $data['email'])->first();
        if ($user !== null) {
            MailConfigurator::apply($user->companies()->value('companies.id'));
            Password::sendResetLink(['email' => $data['email']]);
        }

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    /**
     * Complete a password reset with a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete(); // revoke all API tokens
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password has been reset. Please sign in.']);
    }

    /**
     * Begin two-factor setup: generate a secret and return the provisioning URI.
     * The secret is stored but 2FA is not active until confirmed with a valid code.
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = \App\Shared\Security\Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_enabled' => false])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => \App\Shared\Security\Totp::uri($secret, $user->email, config('app.name')),
        ]);
    }

    /** Confirm two-factor setup by verifying a code from the authenticator app. */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (empty($user->two_factor_secret) || ! \App\Shared\Security\Totp::verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['The authentication code is invalid.']]);
        }

        $user->forceFill(['two_factor_enabled' => true])->save();
        AuditLogger::log('two_factor_enabled', $user);

        return response()->json(['message' => 'Two-factor authentication enabled.']);
    }

    /** Disable two-factor after verifying the account password. */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_enabled' => false])->save();
        AuditLogger::log('two_factor_disabled', $user);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
