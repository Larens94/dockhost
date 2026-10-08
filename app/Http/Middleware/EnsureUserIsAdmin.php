<?php

// EnsureUserIsAdmin.php — Blocks non-admin panel users from admin-only routes.
//
// exports: EnsureUserIsAdmin | EnsureUserIsAdmin::handle(Request $request, Closure $next): Response
// used_by: bootstrap/app.php
// rules:   Return 404 (not 403) for non-admins — do not leak resource existence.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | Admin gate for infra and anagrafica.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(404);
        }

        return $next($request);
    }
}
