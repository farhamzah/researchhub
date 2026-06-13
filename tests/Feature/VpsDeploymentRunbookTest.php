<?php

namespace Tests\Feature;

use Tests\TestCase;

class VpsDeploymentRunbookTest extends TestCase
{
    public function test_vps_deployment_runbook_and_examples_exist_and_cover_safe_flow(): void
    {
        $runbook = $this->readProjectFile('docs/MYRISET_VPS_DEPLOYMENT_RUNBOOK.md');
        $nginx = $this->readProjectFile('docs/MYRISET_NGINX_EXAMPLE.conf');
        $deployScript = $this->readProjectFile('scripts/deploy-myriset.sh.example');
        $healthScript = $this->readProjectFile('scripts/health-check-myriset.sh.example');

        $this->assertStringContainsString('Step 3 - Prepare GitHub deploy key', $runbook);
        $this->assertStringContainsString('Step 4 - Clone MyRiset repository', $runbook);
        $this->assertStringContainsString('git clone git@github.com:farhamzah/researchhub.git /var/www/myriset', $runbook);
        $this->assertStringContainsString('git pull origin main', $runbook);
        $this->assertStringContainsString('APP_DEBUG=false', $runbook);
        $this->assertStringContainsString('php artisan db:seed --class=RolePermissionSeeder --force', $runbook);
        $this->assertStringContainsString('php artisan myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"', $runbook);
        $this->assertStringContainsString('php artisan db:seed --class=MyRisetDemoSeeder', $runbook);
        $this->assertStringContainsString('php artisan migrate:fresh', $runbook);
        $this->assertStringContainsString('php artisan db:wipe', $runbook);
        $this->assertStringContainsString('Drop database objects', $runbook);
        $this->assertStringContainsString('sudo certbot --nginx -d myriset.net -d www.myriset.net', $runbook);
        $this->assertStringContainsString('Rollback Notes', $runbook);

        $this->assertStringContainsString('server_name myriset.net www.myriset.net;', $nginx);
        $this->assertStringContainsString('root /var/www/myriset/public;', $nginx);
        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $nginx);
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.3-fpm.sock;', $nginx);
        $this->assertStringContainsString('location ~ /\\.(?!well-known).*', $nginx);

        $this->assertStringContainsString('APP_DIR="/var/www/myriset"', $deployScript);
        $this->assertStringContainsString('git pull origin main', $deployScript);
        $this->assertStringContainsString('php artisan migrate --force', $deployScript);
        $this->assertStringContainsString('php artisan myriset:production-check', $deployScript);

        $this->assertStringContainsString('curl -I "$APP_URL"', $healthScript);
        $this->assertStringContainsString('curl -I "$APP_URL/admin/login"', $healthScript);
        $this->assertStringContainsString('php artisan myriset:production-check', $healthScript);
    }

    public function test_vps_example_scripts_do_not_contain_secrets_or_destructive_commands(): void
    {
        $scriptText = $this->readProjectFile('scripts/deploy-myriset.sh.example')
            ."\n".$this->readProjectFile('scripts/health-check-myriset.sh.example');

        foreach ([
            'migrate:fresh',
            'db:wipe',
            'dropdb',
            'DROP DATABASE',
            'MyRisetDemoSeeder',
            'CHANGE_STRONG_PASSWORD_ON_SERVER',
            'GOOGLE_CLIENT_SECRET=',
            'GOOGLE_DRIVE_CLIENT_SECRET=',
            'PRIVATE KEY',
            'BEGIN OPENSSH PRIVATE KEY',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $scriptText);
        }
    }

    public function test_existing_production_and_qa_docs_link_vps_git_pull_deployment(): void
    {
        $deploymentChecklist = $this->readProjectFile('docs/MYRISET_PRODUCTION_DEPLOYMENT_CHECKLIST.md');
        $qaChecklist = $this->readProjectFile('docs/MYRISET_E2E_QA_CHECKLIST.md');

        foreach ([
            'VPS Git Pull Deployment',
            'git clone git@github.com:farhamzah/researchhub.git /var/www/myriset',
            'git pull origin main',
            'Nginx root must be `/var/www/myriset/public`',
            'Certbot',
            'php artisan myriset:create-admin',
            'Smoke test',
            'Rollback',
        ] as $expected) {
            $this->assertStringContainsString($expected, $deploymentChecklist);
        }

        $this->assertStringContainsString('VPS Git Pull Deployment QA', $qaChecklist);
        $this->assertStringContainsString('docs/MYRISET_VPS_DEPLOYMENT_RUNBOOK.md', $qaChecklist);
        $this->assertStringContainsString('docs/MYRISET_NGINX_EXAMPLE.conf', $qaChecklist);
        $this->assertStringContainsString('scripts/deploy-myriset.sh.example', $qaChecklist);
        $this->assertStringContainsString('scripts/health-check-myriset.sh.example', $qaChecklist);
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, $path.' should be readable.');

        return $contents;
    }
}
