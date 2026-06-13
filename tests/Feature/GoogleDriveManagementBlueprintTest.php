<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoogleDriveManagementBlueprintTest extends TestCase
{
    public function test_google_drive_management_blueprint_exists_and_documents_strategy(): void
    {
        $blueprint = $this->readProjectFile('docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md');

        foreach ([
            'MyRiset remains the source of truth',
            'Google Drive stores',
            'https://www.googleapis.com/auth/drive.file',
            'http://127.0.0.1:8001/auth/google/drive/callback',
            'https://myriset.net/auth/google/drive/callback',
            'http://127.0.0.1:8001/google/drive/callback',
            'https://myriset.net/google/drive/callback',
            '01_Documents',
            '05_Analysis',
            '07_Publication',
            '{project-slug}_{module}_{document-type}_{version}_{YYYY-MM-DD}',
            'drive_files',
            'Never render tokens',
            'No automatic public link creation',
            'Google Picker future design',
            'Google Docs future exports',
            'Google Sheets future exports',
            'redirect_uri_mismatch',
            'refresh_token_revoked',
        ] as $expected) {
            $this->assertStringContainsString($expected, $blueprint);
        }

        foreach ([
            'Google Docs export.',
            'Google Sheets export.',
            'Google Picker.',
        ] as $nonGoal) {
            $this->assertStringContainsString($nonGoal, $blueprint);
        }
    }

    public function test_google_drive_folder_mapping_exists_and_maps_modules_to_folders(): void
    {
        $mapping = $this->readProjectFile('docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md');

        foreach ([
            'Documents',
            'Survey Builder',
            'Expert Validation',
            'Supervision',
            'Analysis',
            'Report Generator',
            'Publication Manager',
            'Temporary exports',
            '01_Documents',
            '02_Surveys',
            '03_Validation',
            '04_Supervision',
            '05_Analysis',
            '06_Reports',
            '07_Publication',
            '99_Exports',
            'document_versions',
            'survey_validation_rounds',
            'analysis_results',
            'No automatic public link creation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $mapping);
        }
    }

    public function test_production_and_qa_docs_reference_drive_management_plan(): void
    {
        $deployment = $this->readProjectFile('docs/MYRISET_PRODUCTION_DEPLOYMENT_CHECKLIST.md');
        $envGuide = $this->readProjectFile('docs/MYRISET_PRODUCTION_ENV_GUIDE.md');
        $qa = $this->readProjectFile('docs/MYRISET_E2E_QA_CHECKLIST.md');

        foreach ([
            'Core MyRiset must still work when Google Drive is not configured.',
            'GOOGLE_DRIVE_CLIENT_ID=',
            'GOOGLE_DRIVE_CLIENT_SECRET=',
            'GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/google/drive/callback',
            'https://myriset.net/auth/google/drive/callback',
            'docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md',
            'docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md',
        ] as $expected) {
            $this->assertStringContainsString($expected, $deployment);
        }

        foreach ([
            'GOOGLE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback',
            'GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/google/drive/callback',
            'MyRiset remains the source of truth for workflow and metadata.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $envGuide);
        }

        foreach ([
            'Google Drive Management QA',
            'docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md',
            'docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md',
            'no client secret, access token, refresh token',
            '/auth/google/drive/callback',
            'MyRiset remains the source of workflow/metadata',
        ] as $expected) {
            $this->assertStringContainsString($expected, $qa);
        }
    }

    public function test_google_drive_docs_do_not_contain_real_secrets_or_tokens(): void
    {
        $combinedDocs = collect([
            'docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md',
            'docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md',
            'docs/MYRISET_PRODUCTION_ENV_GUIDE.md',
            'docs/MYRISET_PRODUCTION_DEPLOYMENT_CHECKLIST.md',
            'docs/MYRISET_E2E_QA_CHECKLIST.md',
        ])->map(fn (string $path): string => $this->readProjectFile($path))->join("\n");

        foreach ([
            'client_secret_',
            'ya29.',
            'refresh_token=',
            'access_token=',
            'BEGIN PRIVATE KEY',
            'BEGIN OPENSSH PRIVATE KEY',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $combinedDocs);
        }
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, $path.' should be readable.');

        return $contents;
    }
}
