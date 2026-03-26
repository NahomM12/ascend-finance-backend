<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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
}

