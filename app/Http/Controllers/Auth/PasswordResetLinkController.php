<?php

// PasswordResetLinkController.php — Guest forgot-password form and send reset link.
//
// exports: PasswordResetLinkController | create | store
// used_by: routes/web.php
// rules:   Uses Password::sendResetLink — same flow as invite for new members.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Forgot password Inertia routes.

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
