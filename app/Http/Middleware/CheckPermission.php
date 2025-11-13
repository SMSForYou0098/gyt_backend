<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (!Auth::user()->hasPermissionTo($permission)) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to perform this action'
            ], 403);
        }

        return $next($request);
    }
}
