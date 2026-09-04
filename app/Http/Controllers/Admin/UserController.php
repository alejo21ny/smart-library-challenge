<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Events\UserRoleChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('loans')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => array_map(fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()], UserRole::cases()),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeRole', $user);

        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $oldRole = $user->role;
        $newRole = UserRole::from($data['role']);

        if ($oldRole !== $newRole) {
            $user->update(['role' => $newRole]);
            UserRoleChanged::dispatch($user, $oldRole, $newRole, $request->user());
        }

        return back()->with('success', "{$user->name}'s role updated to {$newRole->label()}.");
    }
}
