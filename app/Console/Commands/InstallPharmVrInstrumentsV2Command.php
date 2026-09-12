<?php

namespace App\Console\Commands;

use App\Models\ResearchProject;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use Illuminate\Console\Command;

class InstallPharmVrInstrumentsV2Command extends Command
{
    protected $signature = 'researchhub:install-pharmvr-instruments-v2 {--project= : UUID atau slug proyek tujuan} {--contact= : Kontak peneliti; default email owner} {--dry-run : Tampilkan rencana tanpa menulis data}';

    protected $description = 'Membuat tiga draft instrumen PharmVR v2.0 secara idempotent untuk satu proyek.';

    public function handle(InstallPharmVrInstrumentsV2Action $installer): int
    {
        $identifier = trim((string) $this->option('project'));
        if ($identifier === '') {
            $this->error('Opsi --project wajib diisi.');

            return self::INVALID;
        }

        $project = ResearchProject::query()->with('owner')->whereKey($identifier)->orWhere('slug', $identifier)->first();
        if (! $project || ! $project->owner) {
            $this->error('Proyek atau owner proyek tidak ditemukan.');

            return self::FAILURE;
        }

        $contact = trim((string) $this->option('contact')) ?: (string) $project->owner->email;
        $rows = $installer->preview($project, $contact);
        $this->table(['Identifier', 'Nama', 'Tindakan'], collect($rows)->map(fn (array $row): array => [$row['identifier'], $row['title'], $row['action']])->all());

        if ($this->option('dry-run')) {
            $this->info('Dry-run selesai; tidak ada data yang ditulis.');

            return self::SUCCESS;
        }

        $results = $installer->handle($project->owner, $project, $contact);
        $created = collect($results)->where('created', true)->count();
        $this->info("Selesai: {$created} dibuat, ".(count($results) - $created).' sudah ada. Semua tetap draft dan nonpublik.');

        return self::SUCCESS;
    }
}
