<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GoogleDriveSettings;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\ExpertValidators\ExpertValidatorResource;
use App\Filament\Resources\Projects\ResearchProjectResource;
use App\Filament\Resources\ResearchLinks\ResearchLinkResource;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RhUx001PresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_uses_exactly_five_workspace_groups_without_removing_legacy_routes(): void
    {
        $groups = [
            Dashboard::getNavigationGroup(),
            ResearchProjectResource::getNavigationGroup(),
            SurveyResource::getNavigationGroup(),
            ExpertValidatorResource::getNavigationGroup(),
            DocumentResource::getNavigationGroup(),
            ResearchLinkResource::getNavigationGroup(),
            GoogleDriveSettings::getNavigationGroup(),
        ];

        $this->assertSame([
            'Beranda',
            'Penelitian',
            'Instrumen & Survei',
            'Dokumen & Referensi',
            'Pengaturan',
        ], array_values(array_unique($groups)));
        $this->assertSame('Beranda', Dashboard::getNavigationLabel());
        $this->assertSame('Survei', SurveyResource::getNavigationLabel());
        $this->assertSame('Tautan Riset', ResearchLinkResource::getNavigationLabel());

        foreach ([
            'filament.admin.resources.projects.research-projects.index',
            'admin.projects.journey.show',
            'filament.admin.resources.surveys.index',
            'filament.admin.resources.expert-validators.index',
            'filament.admin.resources.documents.index',
            'filament.admin.resources.research-links.index',
            'filament.admin.pages.settings.google-drive',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route {$routeName} harus tetap tersedia.");
        }
    }

    public function test_dashboard_without_project_has_one_primary_action_and_at_most_three_follow_ups(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rh01-empty@example.test');

        $response = $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSeeText('Belum ada proyek yang dapat diakses')
            ->assertSeeText('Data contoh tidak ditampilkan sebagai data nyata')
            ->assertSeeText('Buat Proyek Riset')
            ->assertSeeText('Lihat semua rincian')
            ->assertSee('data-dashboard-details', false);

        $html = $response->getContent();

        $this->assertSame(1, preg_match_all('/\sdata-primary-action(?:\s|>)/', $html));
        $this->assertLessThanOrEqual(3, preg_match_all('/\sdata-initial-follow-up(?:\s|>)/', $html));
    }

    public function test_dashboard_keeps_multiple_projects_separate_and_wraps_long_titles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rh01-multiple@example.test');
        $longTitle = 'Evaluasi Implementasi Pembelajaran Virtual Reality untuk Penguatan Kompetensi Mahasiswa pada Lingkungan Akademik dengan Judul yang Sangat Panjang';

        $this->project($admin, $longTitle);
        $this->project($admin, 'Proyek Penelitian Dosen yang Terpisah');

        $response = $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('2 proyek dapat diakses')
            ->assertSeeText($longTitle)
            ->assertSeeText('Proyek Penelitian Dosen yang Terpisah')
            ->assertSeeText('Pilih proyek yang akan dilanjutkan')
            ->assertSee('overflow-wrap: anywhere;', false)
            ->assertSee('data-project-context-item', false);

        $html = $response->getContent();

        $this->assertSame(1, preg_match_all('/\sdata-primary-action(?:\s|>)/', $html));
        $this->assertLessThanOrEqual(3, preg_match_all('/\sdata-initial-follow-up(?:\s|>)/', $html));
    }

    public function test_journey_keeps_all_eleven_steps_in_keyboard_accessible_disclosures(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rh01-journey@example.test');
        $project = $this->project(
            $admin,
            'Judul Alur Riset yang Panjang untuk Memastikan Pembungkusan Teks Tetap Terbaca pada Layar Mobile',
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Langkah berikutnya')
            ->assertSeeText('Kelengkapan Alur Aplikasi')
            ->assertSeeText('bukan ukuran kelulusan disertasi, validitas ilmiah, atau izin pengumpulan data')
            ->assertSeeText('Sebelas langkah Alur Riset')
            ->assertSeeText('Kembali ke Proyek')
            ->assertSeeText('Keluar')
            ->assertSee('data-journey-secondary-summary', false)
            ->assertSee('focus-visible:outline', false)
            ->assertSee('break-words', false)
            ->assertSee(route('filament.admin.auth.logout'), false);

        $html = $response->getContent();

        $this->assertSame(11, preg_match_all('/\sdata-journey-step\s/', $html));
        $this->assertSame(11, preg_match_all('/\sdata-journey-step-details(?:\s|>)/', $html));
    }

    private function adminUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    private function project(User $owner, string $title): ResearchProject
    {
        return ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
            'target_finished_at' => today()->addMonths(3),
        ]);
    }
}
