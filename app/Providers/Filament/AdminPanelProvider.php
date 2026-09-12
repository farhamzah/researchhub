<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('MyRiset')
            ->defaultThemeMode(ThemeMode::Light)
            ->darkMode(false)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): HtmlString => new HtmlString(<<<'HTML'
                <style id="researchhub-panel-light-theme">
                    :root {
                        color-scheme: light;
                        --myriset-bg: #f6f8fb;
                        --myriset-surface: #ffffff;
                        --myriset-surface-soft: #f8fafc;
                        --myriset-border: #dbe4ee;
                        --myriset-border-strong: #cbd5e1;
                        --myriset-text: #0f172a;
                        --myriset-muted: #64748b;
                        --myriset-primary: #1d4ed8;
                        --myriset-primary-soft: #eff6ff;
                        --myriset-success: #047857;
                        --myriset-warning: #b45309;
                        --myriset-shadow: 0 18px 45px rgba(15, 23, 42, 0.07);
                        --myriset-shadow-soft: 0 10px 28px rgba(15, 23, 42, 0.05);
                    }

                    .fi-body,
                    .fi-layout,
                    .fi-main,
                    .fi-main-ctn,
                    .fi-page,
                    .fi-page-content {
                        background: var(--myriset-bg) !important;
                        color: var(--myriset-text) !important;
                    }

                    .fi-topbar,
                    .fi-sidebar,
                    .fi-main-sidebar {
                        background: rgba(255, 255, 255, 0.96) !important;
                        border-color: var(--myriset-border) !important;
                        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04) !important;
                        backdrop-filter: blur(18px);
                    }

                    .fi-sidebar-header,
                    .fi-sidebar-nav,
                    .fi-sidebar-group,
                    .fi-sidebar-item,
                    .fi-topbar nav {
                        background: transparent !important;
                    }

                    .fi-logo,
                    .fi-sidebar-group-label,
                    .fi-sidebar-item-label,
                    .fi-sidebar-item-icon,
                    .fi-topbar button,
                    .fi-page-heading {
                        color: var(--myriset-text) !important;
                    }

                    .fi-sidebar-group-label {
                        color: var(--myriset-muted) !important;
                        font-weight: 700 !important;
                        letter-spacing: 0 !important;
                    }

                    .fi-sidebar-item a,
                    .fi-sidebar-item button {
                        color: #334155 !important;
                        border-radius: 8px !important;
                        min-height: 2.35rem;
                        transition: background-color 160ms ease, color 160ms ease, box-shadow 160ms ease !important;
                    }

                    .fi-sidebar-item-active a,
                    .fi-sidebar-item-active button {
                        background: var(--myriset-primary-soft) !important;
                        color: var(--myriset-primary) !important;
                        box-shadow: inset 3px 0 0 var(--myriset-primary) !important;
                    }

                    .fi-sidebar-item a:hover,
                    .fi-sidebar-item button:hover {
                        background: var(--myriset-primary-soft) !important;
                        color: var(--myriset-primary) !important;
                    }

                    .fi-section,
                    .fi-ta,
                    .fi-fo-component-ctn,
                    .fi-modal-window,
                    .fi-dropdown-panel {
                        background: var(--myriset-surface) !important;
                        border-color: var(--myriset-border) !important;
                        border-radius: 8px !important;
                        box-shadow: var(--myriset-shadow-soft) !important;
                    }

                    .fi-ta-header,
                    .fi-ta-content,
                    .fi-ta-table,
                    .fi-ta-row,
                    .fi-ta-cell,
                    .fi-ta-header-cell {
                        background: var(--myriset-surface) !important;
                        color: var(--myriset-text) !important;
                    }

                    .fi-ta-header-cell {
                        color: #475569 !important;
                        font-size: 0.75rem !important;
                        font-weight: 700 !important;
                    }

                    .fi-ta-row:hover {
                        background: #f8fbff !important;
                    }

                    .fi-ta-empty-state {
                        background: var(--myriset-surface-soft) !important;
                    }

                    .fi-input-wrp,
                    .fi-select-input,
                    .fi-textarea {
                        background: #ffffff !important;
                        border-color: var(--myriset-border-strong) !important;
                        border-radius: 8px !important;
                        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) !important;
                    }

                    .fi-input,
                    .fi-select-input,
                    .fi-textarea {
                        color: var(--myriset-text) !important;
                    }

                    .fi-btn,
                    .fi-ac-btn-action,
                    .fi-link {
                        border-radius: 8px !important;
                        font-weight: 700 !important;
                    }

                    .fi-btn:focus-visible,
                    .fi-link:focus-visible,
                    .fi-sidebar-item a:focus-visible,
                    .fi-sidebar-item button:focus-visible,
                    .fi-input:focus,
                    .fi-select-input:focus,
                    .fi-textarea:focus {
                        outline: 2px solid var(--myriset-primary) !important;
                        outline-offset: 2px !important;
                    }

                    .fi-badge {
                        border-radius: 999px !important;
                        font-weight: 700 !important;
                    }
                </style>
                HTML))
            ->navigationGroups([
                NavigationGroup::make('Beranda'),
                NavigationGroup::make('Penelitian'),
                NavigationGroup::make('Instrumen & Survei'),
                NavigationGroup::make('Dokumen & Referensi'),
                NavigationGroup::make('Pengaturan'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
