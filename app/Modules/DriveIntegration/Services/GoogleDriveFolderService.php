<?php

namespace App\Modules\DriveIntegration\Services;

use App\Models\DriveConnection;
use App\Modules\DriveIntegration\DTOs\DriveFolderData;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveFolderService
{
    public const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public function createFolder(DriveConnection $connection, string $name, ?string $parentFolderId = null): DriveFolderData
    {
        if (blank($connection->access_token)) {
            throw new RuntimeException('Google Drive connection is missing an access token.');
        }

        $file = new DriveFile([
            'name' => $name,
            'mimeType' => self::FOLDER_MIME_TYPE,
        ]);

        if (filled($parentFolderId)) {
            $file->setParents([$parentFolderId]);
        }

        $created = $this->drive($connection)->files->create($file, [
            'fields' => 'id,name,webViewLink',
        ]);

        if (blank($created->getId())) {
            throw new RuntimeException('Google Drive did not return a folder ID.');
        }

        return new DriveFolderData(
            driveFolderId: $created->getId(),
            name: $created->getName() ?: $name,
            webViewLink: $created->getWebViewLink(),
        );
    }

    private function drive(DriveConnection $connection): Drive
    {
        $client = new Client;
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));
        $client->setScopes(config('google.drive_scopes', []));
        $client->setAccessToken([
            'access_token' => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
            'expires_in' => $connection->token_expires_at?->diffInSeconds(now(), true),
        ]);

        return new Drive($client);
    }
}
