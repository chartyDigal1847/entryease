<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntryEaseRole
{
    private function normalizeRole(?string $role): string
    {
        return match ($role) {
            'hr', 'registrar', 'admission_officer' => 'admission_officer',
            'admin' => 'admin',
            default => 'student',
        };
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $this->normalizeRole(session('sso_role')
            ?? data_get(session('user'), 'role')
            ?? 'student');

        $allowed = array_map(
            fn(string $allowedRole) => $this->normalizeRole($allowedRole),
            $roles
        );

        if (!in_array($role, $allowed, true)) {
            abort(403, 'You do not have access to this EntryEase area.');
        }

        return $next($request);
    }
}
