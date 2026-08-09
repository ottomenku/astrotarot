<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('template')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('email', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['role' => Role::User]),
            'templates' => Template::query()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        User::query()->create($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('admin.users.saved'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'templates' => Template::query()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validated($request, $user);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->fill($validated);
        $user->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', __('admin.users.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                Password::defaults(),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
        ]);
    }
}
