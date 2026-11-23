<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Send each user to the screen that matches their role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.users.index');
        }

        return redirect()->route('tickets.index');
    }
}
