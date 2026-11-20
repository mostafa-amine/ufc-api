<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The signed-in account's page: its API keys, made and deleted here. */
class DashboardController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.dashboard', [
            'user' => $request->user(),
            'keys' => $request->user()->tokens()->latest('id')->get(),
            // Shown once, right after the key is made; only its hash is stored.
            'newKey' => $request->session()->get('new_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isBlocked(), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:60']], [
            'name.required' => 'Give the key a name, like "my app".',
        ]);

        if ($user->tokens()->count() >= User::MAX_KEYS) {
            throw ValidationException::withMessages([
                'name' => 'You have '.User::MAX_KEYS.' keys, the most allowed. Delete one to make another.',
            ]);
        }

        $token = $user->createToken($data['name']);

        return redirect()->route('dashboard')->with('new_key', [
            'id' => $token->accessToken->id,
            'name' => $data['name'],
            'value' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, int $key): RedirectResponse
    {
        // Only this account's own keys: someone else's id is simply not found.
        $request->user()->tokens()->whereKey($key)->firstOrFail()->delete();

        return redirect()->route('dashboard')->with('status', 'Key deleted. Anything still using it will stop working.');
    }
}
