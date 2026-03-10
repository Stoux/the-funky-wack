<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function users(): \Inertia\Response
    {
        $users = User::query()
            ->withCount('inviteCodes')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    public function newUser(): \Inertia\Response
    {
        return Inertia::render('Admin/User', [
            'user' => null,
        ]);
    }

    public function storeUser(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($validated['is_admin'] ?? false) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return redirect()->route('admin.users.view', $user)->with('success', 'User created successfully.');
    }

    public function viewUser(User $user): \Inertia\Response
    {
        return Inertia::render('Admin/User', [
            'user' => $user,
        ]);
    }

    public function updateUser(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_admin' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        $user->forceFill(['is_admin' => $validated['is_admin'] ?? false])->save();

        return redirect()->route('admin.users.view', $user)->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user): \Illuminate\Http\RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }
}
