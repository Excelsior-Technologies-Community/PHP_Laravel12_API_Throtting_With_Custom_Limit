<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login API.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where(
            'email',
            $request->email
        )->first();

        if (
            !$user ||
            !Hash::check(
                $request->password,
                $user->password
            )
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user
            ->createToken('api-token')
            ->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'token' => $token,

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'plan' => $user->plan ?? 'free',
            ],
        ]);
    }

    /**
     * 1. List all API tokens.
     */
    public function tokens(Request $request)
    {
        $tokens = $request->user()
            ->tokens()
            ->latest()
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'API tokens retrieved successfully.',
            'count' => $tokens->count(),
            'tokens' => $tokens,
        ]);
    }

    /**
     * 2. Revoke one API token.
     */
    public function revokeToken(
        Request $request,
        int $id
    ) {
        $token = $request->user()
            ->tokens()
            ->where('id', $id)
            ->first();

        if (!$token) {
            return response()->json([
                'status' => false,
                'message' => 'Token not found.',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'status' => true,
            'message' => 'API token revoked successfully.',
            'token_id' => $id,
        ]);
    }

    /**
     * 3. Revoke all API tokens.
     */
    public function revokeAllTokens(Request $request)
    {
        $user = $request->user();

        $count = $user
            ->tokens()
            ->count();

        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'All API tokens revoked successfully.',
            'revoked_tokens' => $count,
        ]);
    }
}