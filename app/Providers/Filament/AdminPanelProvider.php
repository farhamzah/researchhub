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
                    }

                    .fi-body,
                    .fi-layout,
                    .fi-main,
                    .fi-main-ctn,
                    .fi-page,
                    .fi-page-content {
                        background: #f8fafc !important;
                        color: #0f172a !important;
                    }

                    .fi-topbar,
                    .fi-sidebar,
                    .fi-main-sidebar {
                        background: #ffffff !important;
                        border-color: #e2e8f0 !important;
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
                        color: #0f172a !important;
                    }

                    .fi-sidebar-group-label {
                        color: #64748b !important;
                        font-weight: 700 !important;
                    }

                    .fi-sidebar-item a,
                    .fi-sidebar-item button {
                        color: #334155 !important;
                    }

                    .fi-sidebar-item-active a,
                    .fi-sidebar-item-active button,
                    .fi-sidebar-item a:hover,
                    .fi-sidebar-item button:hover {
                        background: #eff6ff !important;
                        color: #1d4ed8 !important;
                    }

                    .fi-section,
                    .fi-ta,
                    .fi-fo-component-ctn,
                    .fi-modal-window,
                    .fi-dropdown-panel {
                        background: #ffffff !important;
                        border-color: #e2e8f0 !important;
                    }

                    .fi-ta-header,
                    .fi-ta-content,
                    .fi-ta-table,
                    .fi-ta-row,
                    .fi-ta-cell,
                    .fi-ta-header-cell {
                        background: #ffffff !important;
                        color: #0f172a !important;
                    }

                    .fi-ta-row:hover,
                    .fi-ta-empty-state,
                    .fi-input-wrp {
                        background: #f8fafc !important;
                    }

                    .fi-input,
                    .fi-select-input,
                    .fi-textarea {
                        color: #0f172a !important;
                    }
                </style>
                HTML))
            ->navigationGroups([
                NavigationGroup::make('Workspace'),
                NavigationGroup::make('Research Resources'),
                NavigationGroup::make('Projects'),
                NavigationGroup::make('Research Documents'),
                NavigationGroup::make('Survey & Analysis'),
                NavigationGroup::make('Integrations'),
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
