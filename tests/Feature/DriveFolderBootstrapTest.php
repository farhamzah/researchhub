<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DriveConnection;
use App\Models\DriveFolder;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\DriveIntegration\Actions\BootstrapResearchHubDriveFoldersAction;
use App\Modules\DriveIntegration\DTOs\DriveFolderData;
use App\Modules\DriveIntegration\Services\GoogleDriveFolderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DriveFolderBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_bootstrap_standard_drive_folder_metadata_after_successful_folder_creation(): void
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Dissertation Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $this->connectedDrive($owner);
        $this->fakeSuccessfulFolderCreation(13);

        $folders = app(BootstrapResearchHubDriveFoldersAction::class)->handle($owner, $project);

        $metadata = ActivityLog::where('action', 'drive.folders_bootstrapped')->firstOrFail()->metadata;

        $this->assertCount(13, $folders);
        $this->assertDatabaseHas('drive_folders', [
            'user_id' => $owner->id,
            'project_id' => null,
            'folder_type' => DriveFolder::TYPE_RESEARCHHUB_ROOT,
            'name' => 'MyRiset',
            'path' => 'MyRiset',
        ]);
        $this->assertDatabaseHas('drive_folders', [
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'folder_type' => DriveFolder::TYPE_PROJECT_ROOT,
            'name' => 'Dissertation Study',
            'path' => 'MyRiset/Dissertation Study',
        ]);
        $this->assertDatabaseHas('drive_folders', [
            'project_id' => $project->id,
            'folder_type' => DriveFolder::TYPE_APPENDIX,
            'name' => '11_Lampiran',
        ]);
        $this->assertSame(13, $metadata['folder_count']);
        $this->assertStringNotContainsString('plain-access-bootstrap', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('plain-refresh-bootstrap', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_folder_metadata_is_not_persisted_when_folder_creation_fails(): void
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Failure Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $this->connectedDrive($owner);

        $this->mock(GoogleDriveFolderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createFolder')
                ->once()
                ->andThrow(new RuntimeException('Safe test failure'));
        });

        try {
            app(BootstrapResearchHubDriveFoldersAction::class)->handle($owner, $project);
            $this->fail('Expected folder bootstrap to fail.');
        } catch (RuntimeException) {
            //
        }

        $metadata = ActivityLog::where('action', 'drive.folder_bootstrap_failed')->firstOrFail()->metadata;

        $this->assertDatabaseCount('drive_folders', 0);
        $this->assertSame('RuntimeException', $metadata['reason']);
        $this->assertStringNotContainsString('plain-access-bootstrap', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('plain-refresh-bootstrap', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_non_owner_cannot_bootstrap_project_drive_folders(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Protected Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $this->connectedDrive($outsider);

        $this->mock(GoogleDriveFolderService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createFolder');
        });

        $this->expectException(AuthorizationException::class);

        app(BootstrapResearchHubDriveFoldersAction::class)->handle($outsider, $project);
    }

    public function test_super_admin_can_bootstrap_project_drive_folders(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Supervised Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $this->connectedDrive($superAdmin);
        $this->fakeSuccessfulFolderCreation(13);

        $folders = app(BootstrapResearchHubDriveFoldersAction::class)->handle($superAdmin, $project);

        $this->assertCount(13, $folders);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'project_id' => $project->id,
            'action' => 'drive.folders_bootstrapped',
        ]);
    }

    private function connectedDrive(User $user): DriveConnection
    {
        return DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'researcher@example.test',
            'access_token' => 'plain-access-bootstrap',
            'refresh_token' => 'plain-refresh-bootstrap',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);
    }

    private function fakeSuccessfulFolderCreation(int $expectedCalls): void
    {
        $sequence = 0;

        $this->mock(GoogleDriveFolderService::class, function (MockInterface $mock) use (&$sequence, $expectedCalls): void {
            $mock->shouldReceive('createFolder')
                ->times($expectedCalls)
                ->andReturnUsing(function (DriveConnection $connection, string $name, ?string $parentFolderId = null) use (&$sequence): DriveFolderData {
                    $sequence++;

                    return new DriveFolderData(
                        driveFolderId: "drive-folder-{$sequence}",
                        name: $name,
                        webViewLink: "https://drive.example.test/folders/{$sequence}",
                    );
                });
        });
    }
}
