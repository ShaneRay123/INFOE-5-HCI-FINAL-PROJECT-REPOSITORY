<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');
        
        // Allow Vercel preview deployments and main domain
        if ($origin && (
            str_ends_with($origin, '.vercel.app') ||
            str_contains($origin, 'localhost')
        )) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true');
        }
        
        return $next($request);
    }
}
