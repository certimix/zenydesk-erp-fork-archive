@php
    $isRtl = app()->getLocale() === 'ar';
    $visibleNavigationItems = isset($navigationItems) ? $navigationItems->filter(fn ($item) => $item->isVisible()) : collect();
    $authUser = \Illuminate\Support\Facades\Auth::guard('web')->user() ?? filament()->auth()->user();
@endphp

<div style="display: flex; align-items: center; gap: 12px; {{ $isRtl ? 'flex-direction: row-reverse;' : '' }}">
    <!-- Botão de Ação Direta para Checkout de Assinatura (100% Visível) -->
    <a
        href="{{ url('/assinar') }}"
        style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; font-size: 13px; font-weight: 700; color: #ffffff !important; background-color: #155dfc !important; border-radius: 8px; text-decoration: none !important; box-shadow: 0 2px 6px rgba(21, 93, 252, 0.35); transition: all 0.2s; white-space: nowrap; cursor: pointer;"
        onmouseover="this.style.backgroundColor='#0c4ad4'; this.style.transform='translateY(-1px)'"
        onmouseout="this.style.backgroundColor='#155dfc'; this.style.transform='none'"
    >
        <svg style="width: 14px; height: 14px; fill: none; stroke: currentColor;" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
        </svg>
        <span>Assinar ERP</span>
    </a>

    @if ($authUser)
        @php
            $tenant = \Illuminate\Support\Facades\DB::table('zeny_erp_tenants')
                ->where('admin_user_id', $authUser->id)
                ->orWhereRaw('LOWER(email) = ?', [strtolower(trim($authUser->email))])
                ->first();
            $panelUrl = ($tenant && !empty($tenant->subdominio) && $tenant->subdominio !== 'erp')
                ? "https://{$tenant->subdominio}.erp.zenydesk.com/admin"
                : url('/admin');
        @endphp
        <div style="display: flex; align-items: center; gap: 8px;">
            <a
                href="{{ $panelUrl }}"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; font-weight: 600; color: #1e293b !important; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none !important; transition: all 0.2s;"
                onmouseover="this.style.backgroundColor='#e2e8f0'"
                onmouseout="this.style.backgroundColor='#f1f5f9'"
            >
                <svg style="width: 14px; height: 14px; fill: none; stroke: currentColor;" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Painel ERP ({{ $authUser->name }})</span>
            </a>
            <a
                href="{{ url('/admin/logout') }}"
                onclick="event.preventDefault(); document.getElementById('header-logout-form').submit();"
                style="display: inline-flex; align-items: center; padding: 6px 12px; font-size: 13px; font-weight: 600; color: #ef4444 !important; text-decoration: none !important;"
            >
                Sair
            </a>
            <form id="header-logout-form" action="{{ url('/admin/logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    @else
        {{-- Desktop Links: Entrar / Cadastrar (apontando diretamente para /assinar para ninguém criar conta sem pagar) --}}
        <div style="display: flex; align-items: center; gap: 8px;">
            @foreach ($visibleNavigationItems as $item)
                @php
                    $isRegister = str_contains(strtolower($item->getLabel()), 'cadastr') || str_contains(strtolower($item->getUrl()), 'register');
                @endphp

                @if ($isRegister)
                    <a
                        href="{{ url('/assinar') }}"
                        style="display: inline-flex; align-items: center; padding: 6px 14px; font-size: 13px; font-weight: 600; color: #1e293b !important; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none !important; transition: all 0.2s;"
                        onmouseover="this.style.backgroundColor='#e2e8f0'"
                        onmouseout="this.style.backgroundColor='#f1f5f9'"
                    >
                        {{ $item->getLabel() }}
                    </a>
                @else
                    <a
                        href="{{ $item->getUrl() }}"
                        style="display: inline-flex; align-items: center; padding: 6px 12px; font-size: 13px; font-weight: 600; color: #475569 !important; text-decoration: none !important; transition: color 0.2s;"
                        onmouseover="this.style.color='#155dfc'"
                        onmouseout="this.style.color='#475569'"
                    >
                        {{ $item->getLabel() }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</div>
