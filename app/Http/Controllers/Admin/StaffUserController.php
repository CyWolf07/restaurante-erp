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
                $b->where('name', 'ilike', "%{$q}%")
                  ->orWhere('email', 'ilike', "%{$q}%")
                  ->orWhere('pin_code', 'like', "%{$q}%");
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
        $data = $this->validateStaff($request);

        User::create($data);

        return back()->with('success', "{$data['name']} registrado como " . config("restaurant.staff_roles.{$data['role']}") . '.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureStaffUser($user);

        $data = $this->validateStaff($request, $user);

        $user->update($data);

        return back()->with('success', "Usuario {$user->name} actualizado.");
    }

    public function destroy(User $user)
    {
        $this->ensureStaffUser($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        if ($user->ordersAsWaiter()->exists() || $user->ordersAsCashier()->exists()) {
            $user->update(['active' => false, 'pin_code' => null]);
            return back()->with('success', "Usuario {$user->name} desactivado (tiene historial de órdenes).");
        }

        $user->delete();

        return back()->with('success', "Usuario {$user->name} eliminado.");
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
            $pinRules = ['string', 'regex:/^\d{4,6}$/', Rule::unique('users', 'pin_code')->ignore($user?->id)];
            array_unshift($pinRules, $user ? 'nullable' : 'required');
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
            $rules['password'] = $user
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
