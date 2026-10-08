<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /**
     * Register for an API key
     *
     * Register and receive a free API key. Send it as `Authorization: Bearer <key>`.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create($data); // password is hashed via the model cast

        $token = $user->createToken('api-key')->plainTextToken;

        return response()->json([
            'data' => [
                'api_key' => $token,
                'rate_tier' => $user->rate_tier,
                'user' => ['name' => $user->name, 'email' => $user->email],
            ],
        ], 201);
    }
}
