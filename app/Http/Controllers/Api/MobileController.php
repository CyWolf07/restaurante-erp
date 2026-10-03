<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\PosOperationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255', 'device_name' => 'required|string|max:80']);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password) && $user->active && in_array($user->role, ['cook', 'waiter', 'cashier', 'administrator', 'programmer'], true), 401, 'Credenciales inválidas.');
        $expires = now()->addDay();

        return response()->json(['token' => $user->createToken($data['device_name'], ['mobile:access'], $expires)->plainTextToken, 'expires_at' => $expires->toIso8601String(), 'user' => $this->profile($user)])->header('Cache-Control', 'no-store');
    }

    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role, 'role_label' => $user->role_label, 'capabilities' => ['read_orders' => true, 'mark_ready' => in_array($user->role, ['cook', 'cashier', 'administrator', 'programmer'], true)]];
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function orders(Request $request)
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:50']);
        $query = Order::active()->with(['details.product', 'details.modifiers.modifier']);
        if ($request->user()->role === 'waiter') {
            $query->where('waiter_id', $request->user()->id);
        }
        if ($request->user()->role === 'cook') {
            $query->whereNotNull('kitchen_sent_at')->whereIn('status', ['in_kitchen', 'ready']);
        }
        $page = $query->orderBy('created_at')->orderBy('id')->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => $page->getCollection()->map(fn ($order) => [
            'id' => $order->id, 'table_number' => $order->table_number, 'status' => $order->status,
            'status_label' => $order->status_label,
            'total' => $request->user()->role === 'cook' ? null : $order->total,
            'items' => $order->details->filter(fn ($item) => $request->user()->role !== 'cook' || $item->kitchen_sent_at)->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->product?->name ?? 'Producto no disponible', 'quantity' => $item->quantity, 'comments' => $item->comments,
                'modifiers' => $item->modifiers->map(fn ($modifier) => ['name' => $modifier->modifier?->name ?? 'Modificador no disponible', 'quantity' => $modifier->quantity])->values(),
            ])->values(),
        ])->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function ready(Request $request, Order $order, PosOperationService $operations)
    {
        abort_unless(in_array($request->user()->role, ['cook', 'cashier', 'administrator', 'programmer'], true), 403);
        $operations->run(function () use ($order, $operations) {
            $locked = $operations->editableOrder($order->id);
            if (! $locked->isInKitchen() || ! $locked->kitchen_sent_at) {
                throw ValidationException::withMessages(['order' => 'La orden no está en cocina. Actualiza la pantalla.']);
            }
            $locked->update(['status' => 'ready']);
            $operations->record($locked, 'ready', ['source' => 'mobile']);
        });

        return response()->json(['message' => 'Orden lista.']);
    }
}
