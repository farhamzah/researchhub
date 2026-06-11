<?php

namespace App\Modules\DriveIntegration\Actions;

use App\Models\DriveConnection;
use App\Models\DriveFolder;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\DriveIntegration\DTOs\DriveFolderData;
use App\Modules\DriveIntegration\Services\GoogleDriveFolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

class BootstrapResearchHubDriveFoldersAction
{
    public function __construct(
        private readonly GoogleDriveFolderService $folderService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array<int, DriveFolder>
     */
    public function handle(User $user, ResearchProject $project, ?Request $request = null): array
    {
        Gate::forUser($user)->authorize('bootstrapDriveFolders', $project);

        try {
            $folders = DB::transaction(function () use ($user, $project): array {
                $connection = $this->connectedGoogleDrive($user);

                $rootFolder = $this->existingOrCreateRootFolder($user, $connection);
                $projectFolder = $this->existingOrCreateProjectFolder($user, $project, $connection, $rootFolder);
                $folders = [$rootFolder, $projectFolder];

                foreach (config('researchhub_drive.project_folders', []) as $folderDefinition) {
                    $folders[] = $this->existingOrCreateProjectSubfolder(
                        $user,
                        $project,
                        $connection,
                        $projectFolder,
                        (string) $folderDefinition['type'],
                        (string) $folderDefinition['name'],
                    );
                }

                return $folders;
            });

            $this->activityLogger->log(
                'drive.folders_bootstrapped',
                $user,
                $project,
                $project,
                [
                    'provider' => DriveConnection::PROVIDER_GOOGLE,
                    'folder_count' => count($folders),
                ],
                $request,
            );

            return $folders;
        } catch (Throwable $exception) {
            $this->activityLogger->log(
                'drive.folder_bootstrap_failed',
                $user,
                $project,
                $project,
                [
                    'provider' => DriveConnection::PROVIDER_GOOGLE,
                    'reason' => class_basename($exception),
                ],
                $request,
            );

            throw $exception;
        }
    }

    private function connectedGoogleDrive(User $user): DriveConnection
    {
        $connection = $user->googleDriveConnection()->first();

        if ($connection?->status !== DriveConnection::STATUS_CONNECTED) {
            throw new RuntimeException('Google Drive is not connected.');
        }

        return $connection;
    }

    private function existingOrCreateRootFolder(User $user, DriveConnection $connection): DriveFolder
    {
        $existing = DriveFolder::query()
            ->where('user_id', $user->getKey())
            ->whereNull('project_id')
            ->where('folder_type', DriveFolder::TYPE_RESEARCHHUB_ROOT)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $folder = $this->folderService->createFolder($connection, (string) config('researchhub_drive.root_folder_name', 'ResearchHub'));

        return $this->persistFolder($user, null, DriveFolder::TYPE_RESEARCHHUB_ROOT, $folder, $folder->name);
    }

    private function existingOrCreateProjectFolder(
        User $user,
        ResearchProject $project,
        DriveConnection $connection,
        DriveFolder $rootFolder,
    ): DriveFolder {
        return $this->existingOrCreateProjectFolderByType(
            $user,
            $project,
            $connection,
            DriveFolder::TYPE_PROJECT_ROOT,
            $project->title,
            $rootFolder->drive_folder_id,
            $rootFolder->path.'/'.$project->title,
        );
    }

    private function existingOrCreateProjectSubfolder(
        User $user,
        ResearchProject $project,
        DriveConnection $connection,
        DriveFolder $projectFolder,
        string $folderType,
        string $folderName,
    ): DriveFolder {
        return $this->existingOrCreateProjectFolderByType(
            $user,
            $project,
            $connection,
            $folderType,
            $folderName,
            $projectFolder->drive_folder_id,
            $projectFolder->path.'/'.$folderName,
        );
    }

    private function existingOrCreateProjectFolderByType(
        User $user,
        ResearchProject $project,
        DriveConnection $connection,
        string $folderType,
        string $folderName,
        string $parentFolderId,
        string $path,
    ): DriveFolder {
        $existing = DriveFolder::query()
            ->where('user_id', $user->getKey())
            ->where('project_id', $project->getKey())
            ->where('folder_type', $folderType)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $folder = $this->folderService->createFolder($connection, $folderName, $parentFolderId);

        return $this->persistFolder($user, $project, $folderType, $folder, $path);
    }

    private function persistFolder(
        User $user,
        ?ResearchProject $project,
        string $folderType,
        DriveFolderData $folder,
        string $path,
    ): DriveFolder {
        return DriveFolder::create([
            'user_id' => $user->getKey(),
            'project_id' => $project?->getKey(),
            'folder_type' => $folderType,
            'drive_folder_id' => $folder->driveFolderId,
            'name' => $folder->name,
            'path' => $path,
            'web_view_link' => $folder->webViewLink,
        ]);
    }
}
