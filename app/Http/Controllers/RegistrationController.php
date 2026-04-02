<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Founders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class RegistrationController extends Controller
{
    /**
     * Authenticate user and return a token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Admin registers a new user.
     */
    public function adminRegister(Request $request)
    {
        $registrar = $request->user();
      Log::info('Registrar role: ' . $registrar->role);
     Log::info('Registrar ID: ' . $registrar->id);
     Log::info('Request role: ' . $request->input('role'));
     log::debug('registrar role: ' . $registrar->role);
        // Only superadmins can register admins
        if ($request->input('role') === 'admin' && $registrar->role !== 'superadmin') {
            return response()->json(['error' => 'Only superadmins can register new admins.'], 403);
        }
        
        // Cannot register superadmins
        if ($request->input('role') === 'superadmin') {
            return response()->json(['error' => 'Cannot register superadmins via this endpoint.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:' . implode(',', array_keys(User::ROLES)),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Public registration for investors.
     */
    public function investorRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'investors',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Redirect to OAuth provider.
     */
  public function redirectToProvider($provider)
{
    Log::channel('stack')->info('🔵 OAuth redirect started', [
        'provider' => $provider,
        'redirect_uri' => config('services.google.redirect'),
        'full_url' => url()->full(),
        'session_id' => session()->getId(),
    ]);
    
    return Socialite::driver($provider)->stateless()->redirect();
}
    /**
     * Handle OAuth provider callback.
     */
    public function handleProviderCallback($provider)
    {
         Log::channel('stack')->info('🟢 OAuth callback received', [
        'provider' => $provider,
        'full_url' => request()->fullUrl(),
        'all_query_params' => request()->query(),
        'has_code' => request()->has('code'),
        'has_error' => request()->has('error'),
        'error_param' => request()->get('error'),
        'session_id' => session()->getId(),
        'ip' => request()->ip(),
    ]);
        try {
            $oauthUser = Socialite::driver($provider)->stateless()->user();
            
            // Find or create user
            $user = User::where('email', $oauthUser->getEmail())->first();
            
            if (!$user) {
                // Create new user for OAuth signup
                $user = User::create([
                    'name' => $oauthUser->getName() ?? $oauthUser->getNickname(),
                    'email' => $oauthUser->getEmail(),
                    'password' => Hash::make(uniqid()), // Random password
                    'role' => 'investors', // Default role for OAuth users
                    'oauth_provider' => $provider,
                    'oauth_id' => $oauthUser->getId(),
                ]);
            } else {
                // Update OAuth info for existing user
                $user->update([
                    'oauth_provider' => $provider,
                    'oauth_id' => $oauthUser->getId(),
                ]);
            }

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect to frontend with token in query params
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $redirectUrl = $frontendUrl . '/oauth/callback?' . http_build_query([
                'access_token' => $token,
                'user' => json_encode($user),
                'provider' => $provider
            ]);

            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error('OAuth callback error: ' . $e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/login?error=oauth_failed');
        }
    }

    /**
     * OAuth login for existing users.
     */
    public function oauthLogin(Request $request, $provider)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            // Get user info from OAuth provider using access token
            $oauthUser = Socialite::driver($provider)->stateless()->userFromToken($request->access_token);
            
            // Find user by email
            $user = User::where('email', $oauthUser->getEmail())->first();
            
            if (!$user) {
                return response()->json(['error' => 'No account found with this email. Please sign up first.'], 404);
            }

            // Update OAuth info
            $user->update([
                'oauth_provider' => $provider,
                'oauth_id' => $oauthUser->getId(),
            ]);

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth login error: ' . $e->getMessage());
            return response()->json(['error' => 'OAuth authentication failed'], 401);
        }
    }

    /**
     * Logout user and revoke tokens.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'No authenticated user'], 401);
        }

        // Revoke all tokens for this user
        $user->tokens()->delete();

        // Invalidate the session
        Auth::guard('web')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
