<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ListController extends Controller
{
    public function index()
    {
        // Editions and qualities are shared via HandleInertiaRequests::shareOnce()
        return Inertia::render('List');
    }
}
