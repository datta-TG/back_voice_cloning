<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use mysql_xdevapi\Exception;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
        return response()->json([
            'message' => 'Successfully created user!'], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $credentials = request(['email', 'password']);
        if (Auth::attempt($credentials)) {
            $user = $request->user();
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            if ($request->remember_me) {
                $token->expires_at = Carbon::now()->addWeeks(1);
            }
            $token->save();
            return response()->json([
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString()
            ]);
        } else {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 401);
        }

    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'oldPassword' => 'required',
            'newPassword' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }

        if (Hash::check($request->oldPassword, $request->user()->password)) {
            $request->user()->fill([
                'password' => Hash::make($request->newPassword)
            ])->save();
            return response()->json(['message' =>
                'Successfully password change'], 200);
        } else {
            return response()->json(['message' =>
                'Old password does not match'], 400);
        }
    }
    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'newName' => 'required',
            'newEmail' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        try {
            $user = $request->user();
            $user->name = $request->newName;
            $user->email = $request->newEmail;
            $user->save();
            return response()->json(['message' =>
                'Successfully update user']);
        } catch (Exception $e) {
            return response()->json(['message' =>
                'Error'], 400);
        }
    }
}
