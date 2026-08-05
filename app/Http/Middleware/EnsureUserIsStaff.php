<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel is staff-only.
 *
 * This shop has no customer accounts at all, so every authenticated user is staff by
 * definition — but the check is explicit anyway, because "there are no customers yet"
 * is exactly the assumption that stops being true without anyone revisiting the guard.
 */
final class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        abort_unless(in_array($user->role->value, ['admin', 'staff'], true), 403);

        return $next($request);
    }
}
