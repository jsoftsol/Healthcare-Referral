<?php
namespace App\Http\Middleware;

use App\Enums\StaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $staff = $request->user();

        if (! $staff) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $allowedRoles = array_map(
            fn(string $role) => StaffRole::from($role),
            $roles
        );

        if (! in_array($staff->role, $allowedRoles, strict: true)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
