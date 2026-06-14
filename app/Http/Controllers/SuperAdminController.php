<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class SuperAdminController extends Controller
{
    /**
     * Update super admin profile (email and name)
     */
    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'passcode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Require passcode if it exists
        if (!is_null($user->passcode_hash)) {
            if (!$request->has('passcode')) {
                return response()->json(['passcode' => ['Passcode is required']], 422);
            }
            
            // Check rate limit
            if ($this->isRateLimited($user)) {
                return response()->json(['error' => 'Too many attempts. Please try again later.'], 429);
            }
            
            if (!Hash::check($request->passcode, $user->passcode_hash)) {
                $this->incrementFailedAttempts($user);
                return response()->json(['passcode' => ['Invalid passcode']], 422);
            }
            
            // Reset failed attempts on success
            $user->failed_passcode_attempts = 0;
            $user->passcode_attempts_at = null;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Update super admin password
     */
    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
            'passcode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['current_password' => ['Current password is incorrect']], 422);
        }

        // Require passcode if it exists
        if (!is_null($user->passcode_hash)) {
            if (!$request->has('passcode')) {
                return response()->json(['passcode' => ['Passcode is required']], 422);
            }
            
            // Check rate limit
            if ($this->isRateLimited($user)) {
                return response()->json(['error' => 'Too many attempts. Please try again later.'], 429);
            }
            
            if (!Hash::check($request->passcode, $user->passcode_hash)) {
                $this->incrementFailedAttempts($user);
                return response()->json(['passcode' => ['Invalid passcode']], 422);
            }
            
            // Reset failed attempts on success
            $user->failed_passcode_attempts = 0;
            $user->passcode_attempts_at = null;
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    /**
     * Check if passcode exists
     */
    public function hasPasscode()
    {
        /** @var User $user */
        $user = Auth::user();
        return response()->json(['has_passcode' => !is_null($user->passcode_hash)]);
    }

    /**
     * Create or update passcode
     */
    public function updatePasscode(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Check rate limit
        if ($this->isRateLimited($user)) {
            return response()->json(['error' => 'Too many attempts. Please try again later.'], 429);
        }

        $rules = [
            'passcode' => 'required|string|min:4|max:10|confirmed',
        ];

        if (!is_null($user->passcode_hash)) {
            $rules['current_passcode'] = 'required';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Verify current passcode if it exists
        if (!is_null($user->passcode_hash)) {
            if (!Hash::check($request->current_passcode, $user->passcode_hash)) {
                $this->incrementFailedAttempts($user);
                return response()->json(['current_passcode' => ['Current passcode is incorrect']], 422);
            }
        }

        // Reset failed attempts
        $user->failed_passcode_attempts = 0;
        $user->passcode_attempts_at = null;
        
        $user->passcode_hash = Hash::make($request->passcode);
        $user->save();

        return response()->json(['message' => 'Passcode updated successfully']);
    }

    /**
     * Verify passcode
     */
    public function verifyPasscode(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Check rate limit
        if ($this->isRateLimited($user)) {
            return response()->json(['error' => 'Too many attempts. Please try again later.'], 429);
        }

        $validator = Validator::make($request->all(), [
            'passcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (is_null($user->passcode_hash)) {
            return response()->json(['error' => 'Passcode not set'], 400);
        }

        if (!Hash::check($request->passcode, $user->passcode_hash)) {
            $this->incrementFailedAttempts($user);
            return response()->json(['passcode' => ['Incorrect passcode']], 422);
        }

        // Reset failed attempts
        $user->failed_passcode_attempts = 0;
        $user->passcode_attempts_at = null;
        $user->save();

        return response()->json(['message' => 'Passcode verified successfully']);
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(User $user): bool
    {
        // Check if we need to reset the count
        if ($user->passcode_attempts_at && $user->passcode_attempts_at->lt(now()->subMinutes(15))) {
            $user->failed_passcode_attempts = 0;
            $user->passcode_attempts_at = null;
            $user->save();
        }

        return $user->failed_passcode_attempts >= 5;
    }

    /**
     * Increment failed attempts
     */
    private function incrementFailedAttempts(User $user): void
    {
        $user->failed_passcode_attempts = $user->failed_passcode_attempts + 1;
        $user->passcode_attempts_at = now();
        $user->save();
    }
}