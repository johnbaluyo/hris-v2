<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAccountAccessMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isAdministrator()) {
            $selection = $request->session()->get('account_access');
            $mode = 'admin';
            if (is_array($selection) && ($selection['user_id'] ?? null) === $user->id) {
                $mode = in_array($selection['mode'] ?? null, User::ACCESS_MODES, true) ? $selection['mode'] : 'staff';
            }
            $user->useAccessMode($mode);
        } else {
            $request->session()->forget('account_access');
        }

        return $next($request);
    }
}
