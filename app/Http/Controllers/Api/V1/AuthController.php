<?php
namespace App\Http\Controllers\Api\V1;


use App\Http\Requests\Staff\LoginRequest;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $staff = Staff::where('email', $request->email)->first();

        if (! $staff || ! Hash::check($request->password, $staff->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        $staff->tokens()->delete();

        $expiresAt     = now()->addMinutes(config('referral.token_expiry_minutes', 60));
        $refreshExpiry = now()->addDays(config('referral.refresh_token_expiry_days', 30));

        $accessToken  = $staff->createToken('access-token', ['*'], $expiresAt);
        $refreshToken = $staff->createToken('refresh-token', ['refresh'], $refreshExpiry);

        return $this->success([
            'staff'         => [
                'id'         => $staff->id,
                'name'       => $staff->name,
                'email'      => $staff->email,
                'role'       => $staff->role->value,
                'department' => $staff->department,
            ],
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'token_type'    => 'Bearer',
            'expires_at'    => $expiresAt->toIso8601String(),
        ], 'Login successful.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $staff     = $request->user();
        $expiresAt = now()->addMinutes(config('referral.token_expiry_minutes', 60));

        $staff->tokens()->where('name', 'access-token')->delete();

        $token = $staff->createToken('access-token', ['*'], $expiresAt);

        return $this->success([
            'access_token' => $token->plainTextToken,
            'expires_at'   => $expiresAt->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logged out.');
    }
}
