<?php

// DomainMemberController.php — Grant, revoke, and change domain-scoped panel access.
//
// exports: DomainMemberController | store | update | destroy
// used_by: routes/web.php
// rules:   Never invite users to shared Dokploy project via API — no per-app member API on x-api-key.
//          New users get random password + Italian invite mail with reset link — never accept invite password from form.
//          Owner cannot remove/demote last owner. Cannot set is_admin.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | domain_user pivot CRUD.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Invite email, roles, owner IAM.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | DomainAccessMailer invite + access granted emails.

namespace App\Http\Controllers;

use App\Enums\DomainMemberRole;
use App\Http\Requests\StoreDomainMemberRequest;
use App\Http\Requests\UpdateDomainMemberRequest;
use App\Models\Domain;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Panel\DomainAccessMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DomainMemberController extends Controller
{
    public function store(
        StoreDomainMemberRequest $request,
        Domain $domain,
        AuditLogger $audit,
        DomainAccessMailer $accessMailer,
    ): RedirectResponse {
        $validated = $request->validated();
        $role = isset($validated['role'])
            ? DomainMemberRole::from($validated['role'])
            : DomainMemberRole::Developer;

        $inviter = $request->user();
        abort_unless($inviter instanceof User, 403);

        if (isset($validated['user_id'])) {
            $member = User::query()->findOrFail($validated['user_id']);
            $created = false;
        } else {
            $member = User::query()->where('email', $validated['email'])->first();
            $created = false;

            if ($member === null) {
                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                ]);

                $member = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make(Str::password(32)),
                    'is_admin' => false,
                ]);
                $created = true;
            }
        }

        if ($member->isAdmin()) {
            return back()->withErrors([
                'email' => __('panel.members.admins_see_all'),
            ]);
        }

        if ($domain->members()->whereKey($member->id)->exists()) {
            return back()->withErrors([
                'email' => __('panel.members.already'),
            ]);
        }

        $domain->members()->attach($member->id, ['role' => $role->value]);

        $audit->record('domain.member.invite', $domain, [
            'fqdn' => $domain->fqdn,
            'member_email' => $member->email,
            'role' => $role->value,
        ]);

        if ($created) {
            $accessMailer->sendMemberInvite($member, $domain, $inviter, $role);
        } else {
            $accessMailer->sendMemberAccessGranted($member, $domain, $inviter, $role);
        }

        $roleLabel = $role->label();
        $message = __('panel.members.granted', [
            'email' => $member->email,
            'role' => $roleLabel,
            'fqdn' => $domain->fqdn,
        ]);

        $message .= ' '.($created
            ? __('panel.members.invite_sent')
            : __('panel.members.notice_sent'));

        return back()->with('success', $message);
    }

    public function update(UpdateDomainMemberRequest $request, Domain $domain, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->isAdmin()) {
            abort(404);
        }

        if (! $domain->members()->whereKey($user->id)->exists()) {
            abort(404);
        }

        $newRole = DomainMemberRole::from($request->validated('role'));
        $currentRole = $user->domainMemberRole($domain);

        if ($currentRole === DomainMemberRole::Owner && $newRole !== DomainMemberRole::Owner) {
            if ($this->isLastOwner($domain, $user)) {
                return back()->withErrors([
                    'role' => __('panel.members.last_owner'),
                ]);
            }
        }

        $domain->members()->updateExistingPivot($user->id, ['role' => $newRole->value]);

        $audit->record('domain.member.role_change', $domain, [
            'fqdn' => $domain->fqdn,
            'member_email' => $user->email,
            'from_role' => $currentRole?->value,
            'to_role' => $newRole->value,
        ]);

        return back()->with('success', __('panel.members.role_updated', [
            'email' => $user->email,
            'role' => $newRole->label(),
        ]));
    }

    public function destroy(Domain $domain, User $user, AuditLogger $audit): RedirectResponse
    {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);

        if (! $actor->canManageDomainMembers($domain)) {
            abort(403);
        }

        if ($user->isAdmin()) {
            abort(404);
        }

        if (! $domain->members()->whereKey($user->id)->exists()) {
            abort(404);
        }

        if ($user->domainMemberRole($domain) === DomainMemberRole::Owner && $this->isLastOwner($domain, $user)) {
            return back()->withErrors([
                'role' => __('panel.members.last_owner'),
            ]);
        }

        $domain->members()->detach($user->id);

        $audit->record('domain.member.revoke', $domain, [
            'fqdn' => $domain->fqdn,
            'member_email' => $user->email,
        ]);

        return back()->with('success', __('panel.members.access_removed', ['email' => $user->email]));
    }

    private function isLastOwner(Domain $domain, User $user): bool
    {
        return $user->domainMemberRole($domain) === DomainMemberRole::Owner && $domain->ownerCount() <= 1;
    }
}
