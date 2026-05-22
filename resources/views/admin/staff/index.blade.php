@extends('layouts.app')
@section('title', 'Personal y Roles')
@push('styles')
<style>
    .auth-toggle { display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.75rem; }
    .auth-toggle label {
        padding:0.4rem 0.85rem; border-radius:999px; border:1px solid var(--border);
        font-size:0.75rem; font-weight:600; cursor:pointer;
    }
    .auth-toggle input { display:none; }
    .auth-toggle input:checked + span { background:var(--accent); color:#fff; border-color:var(--accent); }
    .auth-fields { display:none; }
    .auth-fields.visible { display:block; }
    .pin-hint { font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem; }
</style>
@endpush
@section('content')
<section class="page-header">
    <section>
        <h1 class="page-title">👥 Personal y roles</h1>
        <p class="page-subtitle">Meseros, cajeros y cocineros — acceso por PIN o correo</p>
    </section>
</section>

<div class="grid grid-2" style="margin-bottom:1.5rem;align-items:start;">
    <section class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">Registrar personal</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" id="staff-create-form">
            @csrf
            <section class="form-group">
                <label class="form-label">Nombre completo *</label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" required>
            </section>
            <section class="form-group">
                <label class="form-label">Rol *</label>
                <select name="role" class="form-select" required>
                    @foreach($staffRoles as $key => $label)
                    <option value="{{ $key }}" @selected(old('role')===$key)>{{ $label }}</option>
                    @endforeach
                </select>
            </section>

            <label class="form-label">Tipo de acceso *</label>
            <section class="auth-toggle" data-auth-toggle>
                <label><input type="radio" name="auth_type" value="pin" @checked(old('auth_type','pin')==='pin')><span>PIN (4-6 dígitos)</span></label>
                <label><input type="radio" name="auth_type" value="email" @checked(old('auth_type')==='email')><span>Correo y contraseña</span></label>
                <label><input type="radio" name="auth_type" value="both" @checked(old('auth_type')==='both')><span>Ambos</span></label>
            </section>

            <section class="auth-fields" data-auth="pin">
                <section class="form-group">
                    <label class="form-label">PIN *</label>
                    <input type="text" name="pin_code" class="form-input" maxlength="6" pattern="\d{4,6}" inputmode="numeric" placeholder="Ej. 3344" value="{{ old('pin_code') }}">
                    <p class="pin-hint">Para tablets y terminales rápidas en el restaurante.</p>
                </section>
            </section>

            <section class="auth-fields" data-auth="email">
                <section class="form-group">
                    <label class="form-label">Correo *</label>
                    <input type="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="mesero@restaurante.com">
                </section>
                <section class="grid grid-2">
                    <section class="form-group">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="password" class="form-input" minlength="6" autocomplete="new-password">
                    </section>
                    <section class="form-group">
                        <label class="form-label">Confirmar *</label>
                        <input type="password" name="password_confirmation" class="form-input" minlength="6" autocomplete="new-password">
                    </section>
                </section>
            </section>

            <label style="display:flex;align-items:center;gap:0.5rem;margin:0.75rem 0;font-size:0.85rem;">
                <input type="checkbox" name="active" value="1" checked> Usuario activo
            </label>

            <button type="submit" class="btn btn-primary">Registrar</button>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title" style="margin-bottom:0.75rem;">Información</h2>
        <ul style="font-size:0.85rem;color:var(--text-secondary);line-height:1.6;padding-left:1.1rem;">
            <li><strong>Mesero:</strong> crea órdenes y envía comandas a cocina (queda registrado su nombre).</li>
            <li><strong>Cajero:</strong> cobra mesas y confirma inventario al cerrar venta.</li>
            <li><strong>Cocinero:</strong> consulta recetas e insumos por plato.</li>
            <li>El ticket de cocina imprime mesero y hora de envío.</li>
        </ul>
    </section>
</div>

<section class="card">
    <form method="GET" class="inv-toolbar" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;align-items:flex-end;">
        <section class="form-group" style="margin:0;">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-input" value="{{ request('q') }}" placeholder="Nombre, correo o PIN">
        </section>
        <section class="form-group" style="margin:0;">
            <label class="form-label">Rol</label>
            <select name="role" class="form-select">
                <option value="">Todos</option>
                @foreach($staffRoles as $key => $label)
                <option value="{{ $key }}" @selected($filterRole===$key)>{{ $label }}</option>
                @endforeach
            </select>
        </section>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Acceso</th>
                <th>Estado</th>
                <th>Órdenes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            <tr>
                <td><strong>{{ $u->name }}</strong></td>
                <td><span class="badge badge-blue">{{ $u->role_label }}</span></td>
                <td style="font-size:0.8rem;">
                    @if($u->pin_code)
                        PIN: <code>{{ $u->pin_code }}</code><br>
                    @endif
                    @if($u->email)
                        {{ $u->email }}
                    @endif
                    @if(!$u->pin_code && !$u->email)
                        <span style="color:var(--text-muted);">Sin acceso</span>
                    @endif
                </td>
                <td>
                    @if($u->active)
                        <span class="badge badge-green">Activo</span>
                    @else
                        <span class="badge badge-gray">Inactivo</span>
                    @endif
                </td>
                <td style="font-size:0.75rem;">
                    @if($u->role === 'waiter')
                        {{ $u->orders_as_waiter_count }} órdenes
                    @else
                        —
                    @endif
                </td>
                <td>
                    <details>
                        <summary class="btn btn-ghost btn-sm">Editar</summary>
                        <form method="POST" action="{{ route('admin.staff.update', $u) }}" style="margin-top:0.75rem;padding:0.75rem;background:var(--bg-input);border-radius:var(--radius-sm);min-width:280px;">
                            @csrf @method('PUT')
                            <section class="form-group">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="name" class="form-input" value="{{ $u->name }}" required>
                            </section>
                            <section class="form-group">
                                <label class="form-label">Rol</label>
                                <select name="role" class="form-select" required>
                                    @foreach($staffRoles as $key => $label)
                                    <option value="{{ $key }}" @selected($u->role===$key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </section>
                            @php
                                $editAuth = $u->pin_code && $u->email ? 'both' : ($u->email ? 'email' : 'pin');
                            @endphp
                            <section class="form-group">
                                <label class="form-label">Tipo acceso</label>
                                <select name="auth_type" class="form-select staff-auth-type">
                                    <option value="pin" @selected($editAuth==='pin')>PIN</option>
                                    <option value="email" @selected($editAuth==='email')>Correo</option>
                                    <option value="both" @selected($editAuth==='both')>Ambos</option>
                                </select>
                            </section>
                            <section class="form-group staff-pin-field">
                                <label class="form-label">PIN (vacío = no cambiar)</label>
                                <input type="text" name="pin_code" class="form-input" maxlength="6" pattern="\d{4,6}" placeholder="{{ $u->pin_code ? '••••' : 'Nuevo PIN' }}">
                            </section>
                            <section class="form-group staff-email-field">
                                <label class="form-label">Correo</label>
                                <input type="email" name="email" class="form-input" value="{{ $u->email }}">
                            </section>
                            <section class="form-group staff-pass-field">
                                <label class="form-label">Nueva contraseña</label>
                                <input type="password" name="password" class="form-input" minlength="6" placeholder="Opcional">
                            </section>
                            <section class="form-group staff-pass-field">
                                <label class="form-label">Confirmar</label>
                                <input type="password" name="password_confirmation" class="form-input" minlength="6">
                            </section>
                            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;font-size:0.8rem;">
                                <input type="checkbox" name="active" value="1" @checked($u->active)> Activo
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                        </form>
                        @if($u->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.staff.destroy', $u) }}" style="margin-top:0.5rem;" onsubmit="return confirm('¿Eliminar o desactivar a {{ $u->name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">Eliminar / desactivar</button>
                        </form>
                        @endif
                    </details>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Sin personal registrado.</td></tr>
            @endforelse
        </tbody>
    </table>
    <section style="margin-top:1rem;">{{ $users->links() }}</section>
</section>
@endsection

@push('scripts')
<script>
function syncAuthFields(scope) {
    const form = scope.closest('form') || document.getElementById('staff-create-form');
    const type = form.querySelector('[name="auth_type"]:checked, select[name="auth_type"]')?.value || 'pin';
    form.querySelectorAll('.auth-fields').forEach(el => {
        const show = el.dataset.auth === type || (type === 'both' && el.dataset.auth);
        if (type === 'both') {
            el.classList.toggle('visible', el.dataset.auth === 'pin' || el.dataset.auth === 'email');
        } else {
            el.classList.toggle('visible', el.dataset.auth === type);
        }
    });
    const pinInput = form.querySelector('[name="pin_code"]');
    const emailInput = form.querySelector('[name="email"]');
    const passInput = form.querySelector('[name="password"]');
    if (pinInput) pinInput.required = type === 'pin' || type === 'both';
    if (emailInput) emailInput.required = type === 'email' || type === 'both';
    if (passInput && form.id === 'staff-create-form') {
        passInput.required = type === 'email' || type === 'both';
    }
}

document.querySelectorAll('[data-auth-toggle] input, .staff-auth-type').forEach(el => {
    el.addEventListener('change', () => syncAuthFields(el));
});

document.querySelectorAll('form').forEach(f => {
    if (f.querySelector('[name="auth_type"]')) syncAuthFields(f.querySelector('[name="auth_type"]'));
});

document.querySelectorAll('.staff-auth-type').forEach(sel => {
    sel.addEventListener('change', function() {
        const form = this.closest('form');
        const t = this.value;
        form.querySelector('.staff-pin-field').style.display = (t === 'pin' || t === 'both') ? '' : 'none';
        form.querySelector('.staff-email-field').style.display = (t === 'email' || t === 'both') ? '' : 'none';
        form.querySelectorAll('.staff-pass-field').forEach(el => {
            el.style.display = (t === 'email' || t === 'both') ? '' : 'none';
        });
    });
    sel.dispatchEvent(new Event('change'));
});

syncAuthFields(document.getElementById('staff-create-form'));
</script>
@endpush
