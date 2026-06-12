<?php

namespace App\Modules\DriveIntegration\Actions;

use App\Models\DriveConnection;
use App\Models\DriveFolder;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\DriveIntegration\DTOs\DriveFolderBootstrapResult;
use App\Modules\DriveIntegration\DTOs\DriveFolderData;
use App\Modules\DriveIntegration\Services\GoogleDriveFolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BootstrapMyRisetDriveFoldersAction
{
    public function __construct(
        private readonly GoogleDriveFolderService $folderService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, ?Request $request = null): DriveFolderBootstrapResult
    {
        $this->activityLogger->log(
            'drive_folders.bootstrap_started',
            $user,
            null,
            null,
            ['provider' => DriveConnection::PROVIDER_GOOGLE],
            $request,
        );

        try {
            $result = DB::transaction(function () use ($user): DriveFolderBootstrapResult {
                $connection = $this->connectedGoogleDrive($user);
                $folders = [];
                $createdKeys = [];
                $reusedKeys = [];

                $rootFolder = $this->existingFindOrCreateGlobalFolder(
                    $user,
                    $connection,
                    DriveFolder::TYPE_RESEARCHHUB_ROOT,
                    (string) config('researchhub_drive.root_folder_name', 'MyRiset'),
                    null,
                    (string) config('researchhub_drive.root_folder_name', 'MyRiset'),
                    $createdKeys,
                    $reusedKeys,
                );
                $folders[] = $rootFolder;

                foreach (config('researchhub_drive.global_folders', []) as $folderDefinition) {
                    $folders[] = $this->existingFindOrCreateGlobalFolder(
                        $user,
                        $connection,
                        (string) $folderDefinition['type'],
                        (string) $folderDefinition['name'],
                        $rootFolder,
                        $rootFolder->path.'/'.(string) $folderDefinition['name'],
                        $createdKeys,
                        $reusedKeys,
                    );
                }

                return new DriveFolderBootstrapResult($folders, $createdKeys, $reusedKeys);
            });

            $this->activityLogger->log(
                'drive_folders.bootstrap_completed',
                $user,
                null,
                null,
                [
                    'provider' => DriveConnection::PROVIDER_GOOGLE,
                    'folder_count' => count($result->folders),
                    'folder_keys_created' => $result->createdKeys,
                    'folder_keys_reused' => $result->reusedKeys,
                ],
                $request,
            );

            return $result;
        } catch (Throwable $exception) {
            $this->activityLogger->log(
                'drive_folders.bootstrap_failed',
                $user,
                null,
                null,
                [
                    'provider' => DriveConnection::PROVIDER_GOOGLE,
                    'reason' => class_basename($exception),
                ],
                $request,
            );

            throw $exception;
        }
    }

    public function projectsFolder(User $user): ?DriveFolder
    {
        return DriveFolder::query()
            ->where('user_id', $user->getKey())
            ->whereNull('project_id')
            ->where('folder_type', DriveFolder::TYPE_PROJECTS_ROOT)
            ->first();
    }

    private function connectedGoogleDrive(User $user): DriveConnection
    {
        $connection = $user->googleDriveConnection()->first();

        if ($connection?->status !== DriveConnection::STATUS_CONNECTED) {
            throw new RuntimeException('Google Drive is not connected.');
        }

        if ($connection->token_expires_at?->isPast()) {
            throw new RuntimeException('Google Drive token has expired. Reconnect Google Drive first.');
        }

        return $connection;
    }

    /**
     * @param  array<int, string>  $createdKeys
     * @param  array<int, string>  $reusedKeys
     */
    private function existingFindOrCreateGlobalFolder(
        User $user,
        DriveConnection $connection,
        string $folderType,
        string $folderName,
        ?DriveFolder $parentFolder,
        string $path,
        array &$createdKeys,
        array &$reusedKeys,
    ): DriveFolder {
        $existing = DriveFolder::query()
            ->where('user_id', $user->getKey())
            ->whereNull('project_id')
            ->where('folder_type', $folderType)
            ->first();

        if ($existing !== null) {
            $reusedKeys[] = $folderType;

            return $existing;
        }

        $folder = $this->folderService->findFolder($connection, $folderName, $parentFolder?->drive_folder_id);
        $wasCreated = false;

        if ($folder === null) {
            $folder = $this->folderService->createFolder($connection, $folderName, $parentFolder?->drive_folder_id);
            $wasCreated = true;
        }

        $driveFolder = $this->persistFolder($user, $folderType, $folder, $path);

        if ($wasCreated) {
            $createdKeys[] = $folderType;
        } else {
            $reusedKeys[] = $folderType;
        }

        return $driveFolder;
    }

    private function persistFolder(
        User $user,
        string $folderType,
        DriveFolderData $folder,
        string $path,
    ): DriveFolder {
        return DriveFolder::create([
            'user_id' => $user->getKey(),
            'project_id' => null,
            'folder_type' => $folderType,
            'drive_folder_id' => $folder->driveFolderId,
            'name' => $folder->name,
            'path' => $path,
            'web_view_link' => $folder->webViewLink,
        ]);
    }
}
