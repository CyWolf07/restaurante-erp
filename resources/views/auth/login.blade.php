@extends('layouts.app')
@section('title', 'Iniciar Sesión')
@push('styles')
<style>
    .login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
    .login-card { width: 100%; max-width: 420px; }
    .login-brand { text-align: center; margin-bottom: 2.5rem; }
    .login-brand-icon {
        width: 72px; height: 72px; margin: 0 auto 1rem;
        background: linear-gradient(135deg, var(--accent), #a855f7);
        border-radius: 20px; display: flex; align-items: center; justify-content: center;
        font-size: 2rem; box-shadow: 0 8px 32px var(--accent-glow);
    }
    .login-brand h1 { font-size: 1.5rem; font-weight: 800; }
    .login-brand p { color: var(--text-muted); font-size: 0.85rem; }
    .divider { display: flex; align-items: center; gap: 1rem; margin: 1.5rem 0; color: var(--text-muted); font-size: 0.75rem; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    .pin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-top: 1rem; }
    .pin-btn {
        padding: 1rem; border-radius: var(--radius-sm);
        background: var(--bg-input); border: 1px solid var(--border);
        color: var(--text-primary); font-size: 1.5rem; font-weight: 700;
        cursor: pointer; transition: var(--transition); font-family: inherit;
    }
    .pin-btn:hover { background: var(--bg-card-hover); border-color: var(--accent); }
    .pin-btn:active { transform: scale(0.95); }
    .pin-display {
        text-align: center; font-size: 2rem; letter-spacing: 0.75rem;
        padding: 0.75rem; background: var(--bg-input); border: 1px solid var(--border);
        border-radius: var(--radius-sm); margin-bottom: 0.5rem; min-height: 3.5rem;
        display: flex; align-items: center; justify-content: center; color: var(--accent-hover);
        font-weight: 700;
    }
    .tab-btns { display: flex; gap: 0; margin-bottom: 1.5rem; background: var(--bg-input); border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border); }
    .tab-btn {
        flex: 1; padding: 0.6rem; text-align: center; font-size: 0.8rem; font-weight: 600;
        border: none; background: transparent; color: var(--text-muted); cursor: pointer;
        transition: var(--transition); font-family: inherit;
    }
    .tab-btn.active { background: var(--accent); color: white; }
</style>
@endpush
@section('content')
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">🍽️</div>
            <h1>{{ config('app.restaurant_name') }}</h1>
            <p>Sistema de Gestión ERP</p>
        </div>

        <div class="card">
            <div class="tab-btns">
                <button class="tab-btn active" onclick="showTab('pin')" id="tab-pin">PIN Rápido</button>
                <button class="tab-btn" onclick="showTab('email')" id="tab-email">Email / Contraseña</button>
            </div>

            {{-- Login por PIN --}}
            <div id="form-pin">
                <form action="{{ route('login.post') }}" method="POST" id="pin-form">
                    @csrf
                    <input type="hidden" name="pin_code" id="pin-input" value="">
                    <div class="pin-display" id="pin-display">_ _ _ _</div>
                    <div class="pin-grid">
                        @for($i = 1; $i <= 9; $i++)
                        <button type="button" class="pin-btn" onclick="addPin('{{ $i }}')">{{ $i }}</button>
                        @endfor
                        <button type="button" class="pin-btn" onclick="clearPin()" style="font-size:1rem;">⌫</button>
                        <button type="button" class="pin-btn" onclick="addPin('0')">0</button>
                        <button type="submit" class="pin-btn" style="background:var(--accent);color:white;border-color:var(--accent);">→</button>
                    </div>
                </form>
            </div>

            {{-- Login por Email --}}
            <div id="form-email" style="display:none;">
                <form action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-input" placeholder="usuario@restaurante.local" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:0.5rem;">
                        Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    let pin = '';
    function addPin(digit) {
        if (pin.length >= 6) return;
        pin += digit;
        document.getElementById('pin-input').value = pin;
        document.getElementById('pin-display').textContent = pin.split('').map(()=>'●').join(' ');
    }
    function clearPin() {
        pin = pin.slice(0, -1);
        document.getElementById('pin-input').value = pin;
        document.getElementById('pin-display').textContent = pin ? pin.split('').map(()=>'●').join(' ') : '_ _ _ _';
    }
    function showTab(tab) {
        document.getElementById('form-pin').style.display = tab === 'pin' ? 'block' : 'none';
        document.getElementById('form-email').style.display = tab === 'email' ? 'block' : 'none';
        document.getElementById('tab-pin').classList.toggle('active', tab === 'pin');
        document.getElementById('tab-email').classList.toggle('active', tab === 'email');
    }
</script>
@endpush
