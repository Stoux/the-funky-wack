<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'inviteCode' => $request->query('code'),
        ]);
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'invite_code' => ['required', 'string', 'size:8'],
        ]);

        // Find and validate invite code
        $inviteCode = InviteCode::where('code', strtoupper($validated['invite_code']))
            ->whereNull('used_by')
            ->first();

        if (! $inviteCode) {
            return back()->withErrors([
                'invite_code' => 'Invalid or already used invite code.',
            ])->withInput();
        }

        // Create user and mark invite as used atomically
        $user = DB::transaction(function () use ($validated, $inviteCode) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $inviteCode->update([
                'used_by' => $user->id,
                'used_at' => now(),
            ]);

            return $user;
        });

        // Log the user in
        Auth::login($user);

        return redirect()->route('user.profile');
    }
}
