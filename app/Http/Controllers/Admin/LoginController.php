<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminLoggedIn;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class LoginController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Login');
    }

    public function login(Request $request)
    {
        $v = $request->validate([
            'password' => [
                'string',
                'required',
            ]
        ]);

        if ($v['password'] !== config('app.tfw.super_secure_password')) {
            throw new BadRequestException('Nope');
        }

        session([AdminLoggedIn::SESSION_KEY => true]);

        return redirect()->route('admin.dashboard');
    }
}
