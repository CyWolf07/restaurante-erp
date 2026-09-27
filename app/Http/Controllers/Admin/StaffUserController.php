<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffUserController extends Controller
{
    private array $staffRoles;

    public function __construct()
    {
        $this->staffRoles = array_keys(config('restaurant.staff_roles', []));
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->withCount('ordersAsWaiter')
            ->whereIn('role', $this->staffRoles)
            ->orderBy('role')
            ->orderBy('name');

        if ($request->filled('role') && in_array($request->role, $this->staffRoles, true)) {
            $query->where('role', $request->role);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->whereLike('name', "%{$q}%")
                  ->orWhereLike('email', "%{$q}%");
            });
        }

        return view('admin.staff.index', [
            'users'      => $query->paginate(20)->withQueryString(),
            'staffRoles' => config('restaurant.staff_roles'),
            'filterRole' => $request->role,
        ]);
    }

    public function store(Request $request)
    {
        $data = app(\App\Services\PosOperationService::class)->run(function () use ($request) {
            $data = $this->validateStaff($request);
            $created = User::create($data);
            app(\App\Services\AuditService::class)->record('user', $created->id, 'staff_created', ['name' => $created->name, 'role' => $created->role]);
            return $data;
        }, false);

        return back()->with('success', "{$data['name']} registrado como " . config("restaurant.staff_roles.{$data['role']}") . '.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureStaffUser($user);

        app(\App\Services\PosOperationService::class)->run(function () use ($request, $user) {
            $user->refresh();
            $before = $user->only(['name', 'email', 'role', 'active']);
            $data = $this->validateStaff($request, $user);
            $user->update($data);
            app(\App\Services\AuditService::class)->record('user', $user->id, 'staff_updated', [
                'before' => $before, 'after' => $user->only(['name', 'email', 'role', 'active']),
                'credentials_changed' => array_key_exists('pin_code', $data) || array_key_exists('password', $data),
            ]);
        }, false);

        return back()->with('success', "Usuario {$user->name} actualizado.");
    }

    public function destroy(User $user)
    {
        $this->ensureStaffUser($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        app(\App\Services\PosOperationService::class)->run(function () use ($user) {
            $user->forceFill(['active' => false, 'pin_code' => null, 'remember_token' => null])->save();
            app(\App\Services\AuditService::class)->record('user', $user->id, 'staff_deactivated', ['name' => $user->name]);
        }, false);
        return back()->with('success', "Usuario {$user->name} desactivado. Su historial se conserva.");
    }

    private function ensureStaffUser(User $user): void
    {
        if (!in_array($user->role, $this->staffRoles, true)) {
            abort(404);
        }
    }

    private function validateStaff(Request $request, ?User $user = null): array
    {
        $staffRoles = $this->staffRoles;

        $rules = [
            'name'       => 'required|string|max:255',
            'role'       => ['required', Rule::in($staffRoles)],
            'active'     => 'sometimes|boolean',
            'auth_type'  => 'required|in:pin,email,both',
        ];

        $authType = $request->input('auth_type', 'pin');

        if (in_array($authType, ['pin', 'both'], true)) {
            $pinRules = ['string', 'regex:/^\d{4,6}$/'];
            array_unshift($pinRules, $user?->hasPin() ? 'nullable' : 'required');
            $rules['pin_code'] = $pinRules;
        } else {
            $rules['pin_code'] = 'nullable';
        }

        if (in_array($authType, ['email', 'both'], true)) {
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ];
            $rules['password'] = $user?->password
                ? 'nullable|string|min:6|confirmed'
                : 'required|string|min:6|confirmed';
        } else {
            $rules['email'] = 'nullable|email|max:255';
            $rules['password'] = 'nullable';
        }

        $validated = $request->validate($rules);

        $data = [
            'name'   => $validated['name'],
            'role'   => $validated['role'],
            'active' => $request->boolean('active', true),
        ];

        if (in_array($authType, ['pin', 'both'], true)) {
            if (!empty($validated['pin_code'] ?? null)) {
                $data['pin_code'] = $validated['pin_code'];
            }
        } else {
            $data['pin_code'] = null;
        }

        if (in_array($authType, ['email', 'both'], true)) {
            $data['email'] = $validated['email'];
            if (!empty($validated['password'] ?? null)) {
                $data['password'] = $validated['password'];
            }
        } else {
            $data['email'] = null;
            $data['password'] = null;
        }

        return $data;
    }
}
