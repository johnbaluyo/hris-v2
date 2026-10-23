<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountAccessController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdministrator(), 403);
        $data = $request->validate(['mode' => ['required', Rule::in(User::ACCESS_MODES)], 'user_id' => 'prohibited']);
        if ($user->accessMode() !== $data['mode']) {
            AuditEvent::create(['action' => 'account.access-mode.changed', 'subject_type' => User::class,
                'subject_id' => $user->id, 'actor_id' => $user->id,
                'before' => ['access_mode' => $user->accessMode()], 'after' => ['access_mode' => $data['mode']], 'created_at' => now()]);
        }
        $request->session()->put('account_access', ['user_id' => $user->id, 'mode' => $data['mode']]);

        return redirect()->route('dashboard');
    }
}
