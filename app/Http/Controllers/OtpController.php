<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();

        $otp = (string) random_int(100000, 999999);
        $expiresInMinutes = 10;

        $cacheKey = 'otp:' . $user->id;
        Cache::put($cacheKey, $otp, now()->addMinutes($expiresInMinutes));

        Mail::to($user->email)->send(new OtpMail($otp, $expiresInMinutes));

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->firstOrFail();

        $otp = (string) random_int(100000, 999999);
        $expiresInMinutes = 10;

        $cacheKey = 'password_reset_otp:' . $email;
        Cache::put($cacheKey, $otp, now()->addMinutes($expiresInMinutes));

        Mail::to($user->email)->send(new OtpMail($otp, $expiresInMinutes));

        return response()->json([
            'message' => 'OTP sent to your email address',
        ]);
    }

    public function verifyForgotPasswordOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $email = $request->input('email');
        $otp = $request->input('otp');

        $cacheKey = 'password_reset_otp:' . $email;
        $cachedOtp = Cache::get($cacheKey);

        if (!$cachedOtp || $cachedOtp !== $otp) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }
}
