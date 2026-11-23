<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('department')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User,
            'roles' => UserRole::cases(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'department_id', 'password']));
        $user->role = $request->enum('role', UserRole::class);
        // The administrator knows this password: the user replaces it at the first login.
        $user->must_change_password = true;
        $user->save();

        Audit::record('account.created', ['by' => $request->user()->id, 'account' => $user->id, 'role' => $user->role->value]);

        return redirect()->route('admin.users.index')
            ->with('success', "Le compte de {$user->name} a été créé.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email', 'department_id']));
        $user->role = $request->enum('role', UserRole::class);
        $user->is_active = $request->boolean('is_active');

        if (filled($request->validated('password'))) {
            $user->password = $request->validated('password');
            $user->must_change_password = true;
        }

        $changes = array_keys($user->getDirty());
        // Saving also revokes the tokens of the mobile application when needed (see User::booted).
        $user->save();

        Audit::record('account.updated', ['by' => $request->user()->id, 'account' => $user->id, 'changed' => $changes]);

        return redirect()->route('admin.users.index')
            ->with('success', "Le compte de {$user->name} a été mis à jour.");
    }
}
