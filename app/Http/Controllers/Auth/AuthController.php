<?php

namespace App\Http\Controllers\Auth;

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request) 
    {
        $request->validate([
			'email' => 'required|string|email',
			'password' => 'required|string'
		]);

		$credentials = request(['email', 'password']);

		// print_r($credentials);die;
		if(!Auth::attempt($credentials))
			return response()->json(['message' => 'Unauthorized'],401);

		$user = $request->user();

		$tokenResult = $user->createToken('Personal Access Token');

		$token = $tokenResult->token;

		if ($request->remember_me)
		$token->expires_at = Carbon::now()->addWeeks(1);
		$token->save();

		return response()->json([
			'access_token' => $tokenResult->accessToken,
			'token_type' => 'Bearer',
			'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
		]);
   }
}
