<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="author" content="{{ config('app.author_name') }} - {{ config('app.development_company') }}">
    <title>@yield('title', 'ERP Restaurante') — {{ config('app.restaurant_name') }}</title>
    <meta name="description" content="Sistema ERP de gestión integral para restaurante — {{ config('app.restaurant_name') }}">
    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    @livewireStyles
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-primary: #0f172a; --bg-secondary: #1e293b; --bg-card: #1e293b;
            --bg-card-hover: #334155; --bg-input: #0f172a;
            --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-muted: #64748b;
            --accent: #6366f1; --accent-hover: #818cf8; --accent-glow: rgba(99,102,241,0.3);
            --success: #22c55e; --warning: #f59e0b; --danger: #ef4444;
            --success-bg: rgba(34,197,94,0.15); --warning-bg: rgba(245,158,11,0.15); --danger-bg: rgba(239,68,68,0.15);
            --border: #334155; --border-light: #475569;
            --radius: 12px; --radius-sm: 8px; --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -2px rgba(0,0,0,0.2);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.4), 0 4px 6px -4px rgba(0,0,0,0.3);
            --transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
        }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg-primary); color: var(--text-primary);
            line-height: 1.6; min-height: 100vh;
        }

        /* ── Layout ── */
        .app-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px; background: var(--bg-secondary); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 50; transition: var(--transition);
        }
        .sidebar-brand {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 0.75rem;
        }
        .sidebar-brand-icon {
            width: 40px; height: 40px; background: linear-gradient(135deg, var(--accent), #a855f7);
            border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; font-weight: 800; color: white;
        }
        .sidebar-brand h1 { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
        .sidebar-brand small { font-size: 0.7rem; color: var(--text-muted); }

        .sidebar-nav { flex: 1; padding: 1rem 0.75rem; overflow-y: auto; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-title {
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            color: var(--text-muted); letter-spacing: 0.1em; padding: 0 0.75rem; margin-bottom: 0.5rem;
        }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem;
            border-radius: var(--radius-sm); color: var(--text-secondary);
            text-decoration: none; font-size: 0.875rem; font-weight: 500;
            transition: var(--transition); margin-bottom: 2px;
        }
        .nav-link:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .nav-link.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.1));
            color: var(--accent-hover); border: 1px solid rgba(99,102,241,0.3);
        }
        .nav-link .icon { font-size: 1.1rem; width: 1.5rem; text-align: center; }

        .sidebar-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.8rem; color: white;
        }
        .user-name { font-size: 0.8rem; font-weight: 600; }
        .user-role { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .app-credit { margin-top: 0.75rem; font-size: 0.65rem; line-height: 1.35; color: var(--text-muted); }

        .main-content { flex: 1; margin-left: 260px; padding: 2rem; min-height: 100vh; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border);
        }
        .page-title { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; }
        .page-subtitle { font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem; }

        /* ── Cards ── */
        .card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); transition: var(--transition);
        }
        .card:hover { border-color: var(--border-light); box-shadow: var(--shadow-lg); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .card-title { font-size: 1rem; font-weight: 700; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.25rem; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 0.8rem; font-weight: 600;
            border: none; cursor: pointer; transition: var(--transition);
            text-decoration: none; white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            color: white; box-shadow: 0 2px 8px var(--accent-glow);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px var(--accent-glow); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-warning { background: var(--warning); color: #1e293b; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.75rem; }
        .btn-lg { padding: 0.8rem 1.75rem; font-size: 0.9rem; }

        /* ── Inputs ── */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.375rem; }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 0.6rem 0.875rem;
            background: var(--bg-input); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text-primary);
            font-family: inherit; font-size: 0.875rem; transition: var(--transition);
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow);
        }

        /* ── Tables ── */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            padding: 0.75rem 1rem; text-align: left;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            color: var(--text-muted); letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2);
        }
        .data-table tbody td {
            padding: 0.75rem 1rem; font-size: 0.85rem;
            border-bottom: 1px solid rgba(51,65,85,0.5);
        }
        .data-table tbody tr:hover { background: rgba(99,102,241,0.05); }

        /* ── Badges ── */
        .badge {
            display: inline-flex; align-items: center; padding: 0.2rem 0.6rem;
            border-radius: 999px; font-size: 0.7rem; font-weight: 600;
        }
        .badge-yellow { background: var(--warning-bg); color: var(--warning); }
        .badge-red { background: #dc2626; color: #fff; font-weight: 700; }
        .badge-green { background: #16a34a; color: #fff; font-weight: 700; }
        .badge-blue { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge-gray { background: rgba(100,116,139,0.2); color: var(--text-muted); }

        /* ── Paginación ERP ── */
        .erp-pagination {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }
        .erp-pagination-info { font-size: 0.8rem; color: var(--text-muted); margin: 0; }
        .erp-pagination-info strong { color: var(--text-primary); }
        .erp-pagination-controls { display: flex; align-items: center; gap: 0.35rem; }
        .erp-page-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border); background: var(--bg-input);
            color: #f8fafc; text-decoration: none; transition: var(--transition);
            flex-shrink: 0;
        }
        .erp-page-btn:hover:not(.disabled) {
            background: var(--accent); border-color: var(--accent); color: #fff;
        }
        .erp-page-btn.disabled { opacity: 0.35; cursor: not-allowed; color: var(--text-muted); }
        .erp-page-numbers { display: flex; align-items: center; gap: 0.2rem; }
        .erp-page-num {
            min-width: 1.75rem; height: 1.75rem; padding: 0 0.35rem;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 600; border-radius: var(--radius-sm);
            color: var(--text-secondary); text-decoration: none; border: 1px solid transparent;
        }
        .erp-page-num:hover { color: var(--text-primary); border-color: var(--border); background: var(--bg-card-hover); }
        .erp-page-num.active {
            background: var(--accent); color: #fff; border-color: var(--accent);
        }
        .erp-page-dots { padding: 0 0.25rem; color: var(--text-muted); font-size: 0.75rem; }

        /* ── Grid ── */
        .grid { display: grid; gap: 1.25rem; }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }

        /* ── Alerts / Flash Messages ── */
        .alert {
            padding: 0.875rem 1.25rem; border-radius: var(--radius-sm);
            font-size: 0.85rem; font-weight: 500; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.5rem;
            animation: slideDown 0.3s ease-out;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(34,197,94,0.3); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(245,158,11,0.3); }

        /* ── Stats Cards ── */
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1.25rem; transition: var(--transition);
        }
        .stat-card:hover { border-color: var(--accent); }
        .stat-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px); z-index: 100; display: flex;
            align-items: center; justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }
        .modal-content {
            background: var(--bg-secondary); border: 1px solid var(--border);
            border-radius: var(--radius-lg); max-width: 90vw; max-height: 90vh;
            overflow-y: auto; box-shadow: var(--shadow-lg);
            animation: scaleIn 0.2s ease-out;
        }
        .modal-full { width: 95vw; height: 95vh; }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-body { padding: 1.5rem; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; padding: 0.25rem; }
        .modal-close:hover { color: var(--text-primary); }

        /* ── Animations ── */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse-danger { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .pulse-danger { animation: pulse-danger 1.5s ease-in-out infinite; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .sidebar { width: 72px; }
            .sidebar-brand h1, .sidebar-brand small, .nav-section-title, .nav-link span,
            .user-name, .user-role { display: none; }
            .sidebar-brand { padding: 1rem; justify-content: center; }
            .nav-link { justify-content: center; padding: 0.75rem; }
            .main-content { margin-left: 72px; }
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--border-light); }
    </style>
    @stack('styles')
</head>
<body>
    <div class="app-layout">
        @auth
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">🍽️</div>
                <div>
                    <h1>{{ config('app.restaurant_name') }}</h1>
                    <small>ERP v1.0</small>
                </div>
            </div>
            <nav class="sidebar-nav">
                @php $role = auth()->user()->role; @endphp

                @if(in_array($role, ['cook','administrator','programmer']))
                <div class="nav-section">
                    <div class="nav-section-title">Cocina</div>
                    <a href="{{ route('production.index') }}" class="nav-link {{ request()->routeIs('production.*') ? 'active' : '' }}">
                        <span class="icon">🍲</span><span>Producción por lotes</span>
                    </a>
                    <a href="{{ route('cook.recipes') }}" class="nav-link {{ request()->routeIs('cook.*') ? 'active' : '' }}">
                        <span class="icon">📖</span><span>Fichas Técnicas</span>
                    </a>
                </div>
                @endif

                @if(in_array($role, ['waiter','administrator','programmer']))
                <div class="nav-section">
                    <div class="nav-section-title">Mesero</div>
                    <a href="{{ route('waiter.orders') }}" class="nav-link {{ request()->routeIs('waiter.orders') ? 'active' : '' }}">
                        <span class="icon">📋</span><span>Mis Órdenes</span>
                    </a>
                    <a href="{{ route('waiter.create-order') }}" class="nav-link {{ request()->routeIs('waiter.create-order') ? 'active' : '' }}">
                        <span class="icon">➕</span><span>Nueva Orden</span>
                    </a>
                </div>
                @endif

                @if(in_array($role, ['cashier','administrator','programmer']))
                <div class="nav-section">
                    <div class="nav-section-title">Cajero</div>
                    <a href="{{ route('cashier.pos') }}" class="nav-link {{ request()->routeIs('cashier.pos', 'cashier.table-detail', 'cashier.report-z', 'cashier.cash-closure*', 'cashier.order-details.*', 'cashier.send-kitchen', 'cashier.mark-ready', 'cashier.pay-order', 'cashier.cancel-order') ? 'active' : '' }}">
                        <span class="icon">💳</span><span>Punto de Venta</span>
                    </a>
                    <a href="{{ route('cashier.cash-count') }}" class="nav-link {{ request()->routeIs('cashier.cash-count*') ? 'active' : '' }}">
                        <span class="icon">#</span><span>Arqueo de caja</span>
                    </a>
                    <a href="{{ route('cashier.delivery.create') }}" class="nav-link {{ request()->routeIs('cashier.delivery*') ? 'active' : '' }}">
                        <span class="icon">@</span><span>Domicilios</span>
                    </a>
                </div>
                @endif

                @if(in_array($role, ['administrator','programmer']))
                <div class="nav-section">
                    <div class="nav-section-title">Administración</div>
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <span class="icon">📊</span><span>Dashboard</span>
                    </a>
                    <a href="{{ route('admin.inventory.index') }}" class="nav-link {{ request()->routeIs('admin.inventory*') ? 'active' : '' }}">
                        <span class="icon">📦</span><span>Inventario Comercial</span>
                    </a>
                    <a href="{{ route('admin.supplies') }}" class="nav-link {{ request()->routeIs('admin.supplies') ? 'active' : '' }}">
                        <span class="icon">🧪</span><span>Insumos / Recetas</span>
                    </a>
                    <a href="{{ route('admin.physical-inventory') }}" class="nav-link {{ request()->routeIs('admin.physical*') ? 'active' : '' }}">
                        <span class="icon">🔍</span><span>Conteo Físico</span>
                    </a>
                    <a href="{{ route('admin.staff.index') }}" class="nav-link {{ request()->routeIs('admin.staff*') ? 'active' : '' }}">
                        <span class="icon">👥</span><span>Personal / Roles</span>
                    </a>
                    <a href="{{ route('admin.audit') }}" class="nav-link {{ request()->routeIs('admin.audit') ? 'active' : '' }}">
                        <span class="icon">📋</span><span>Historial de cambios</span>
                    </a>
                    <a href="{{ route('admin.tables') }}" class="nav-link {{ request()->routeIs('admin.tables*') ? 'active' : '' }}">
                        <span class="icon">🪑</span><span>Mesas</span>
                    </a>
                    <a href="{{ route('admin.products') }}" class="nav-link {{ request()->routeIs('admin.products*') ? 'active' : '' }}">
                        <span class="icon">🍽️</span><span>Platos</span>
                    </a>
                    <a href="{{ route('admin.modifiers.index') }}" class="nav-link {{ request()->routeIs('admin.modifiers*') ? 'active' : '' }}">
                        <span class="icon">🧩</span><span>Opciones de Platos</span>
                    </a>
                    <a href="{{ route('admin.printers') }}" class="nav-link {{ request()->routeIs('admin.printers*') ? 'active' : '' }}">
                        <span class="icon">🖨️</span><span>Impresoras</span>
                    </a>
                    <a href="{{ route('admin.network') }}" class="nav-link {{ request()->routeIs('admin.network') ? 'active' : '' }}">
                        <span class="icon">📡</span><span>Red / Tablets</span>
                    </a>
                </div>
                @endif

                @if($role === 'programmer')
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <a href="{{ route('programmer.panel') }}" class="nav-link {{ request()->routeIs('programmer.panel') ? 'active' : '' }}">
                        <span class="icon">⚙️</span><span>Panel Técnico</span>
                    </a>
                    <a href="{{ route('programmer.inventory-import') }}" class="nav-link {{ request()->routeIs('programmer.inventory-import*') ? 'active' : '' }}">
                        <span class="icon">📥</span><span>Importar CSV</span>
                    </a>
                    <a href="{{ route('programmer.print-templates.index') }}" class="nav-link {{ request()->routeIs('programmer.print-templates*') ? 'active' : '' }}">
                        <span class="icon">🖨️</span><span>Formatos ticket</span>
                    </a>
                </div>
                @endif
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                    <div>
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">{{ auth()->user()->role_label }}</div>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;">
                        🚪 Cerrar Sesión
                    </button>
                </form>
                <div class="app-credit">
                    Creado por {{ config('app.author_name') }}<br>
                    {{ config('app.development_company') }}
                </div>
            </div>
        </aside>
        @endauth

        <main class="main-content" @guest style="margin-left:0" @endguest>
            @if(session('success'))
                <div class="alert alert-success">✅ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">❌ {{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">⚠️ {{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul style="margin:0;padding-left:1.25rem;">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
