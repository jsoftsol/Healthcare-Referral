<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Hospital;
use App\Enums\HospitalStatus;

class AuthenticateHospital
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Hospital-Api-Key') ?? $request->bearerToken();

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $hash     = hash('sha256', $apiKey);
        $hospital = Hospital::where('api_key_hash', $hash)->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($hospital->status !== HospitalStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => 'Hospital account is suspended.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('hospital', $hospital);

        return $next($request);
    }
}
