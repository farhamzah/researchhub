<?php

namespace App\Console\Commands;

use App\Models\ResearchProject;
use App\Modules\Surveys\Actions\FinalizePharmVrInstrumentsV2ForSupervisorReviewAction;
use Illuminate\Console\Command;

class FinalizePharmVrInstrumentsV2ForSupervisorReviewCommand extends Command
{
    protected $signature = 'researchhub:finalize-pharmvr-instruments-v2-review
        {--project= : UUID atau slug proyek tujuan}
        {--contact= : Kontak peneliti; default email owner}
        {--output= : Nama file JSON baru di storage/app/private}';

    protected $description = 'Memperbarui redaksi final draft PharmVR, mengganti snapshot belum dibuka, dan membuat tiga tautan Hub private.';

    public function handle(FinalizePharmVrInstrumentsV2ForSupervisorReviewAction $action): int
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

        $filename = trim((string) $this->option('output')) ?: 'pharmvr-supervisor-review-links-'.now()->format('Ymd-His').'.json';
        if (basename($filename) !== $filename || ! str_ends_with($filename, '.json')) {
            $this->error('Opsi --output hanya boleh berupa nama file JSON.');

            return self::INVALID;
        }

        $result = $action->handle(
            $project->owner,
            $project,
            trim((string) $this->option('contact')) ?: (string) $project->owner->email,
            now()->addDays(30),
            storage_path('app/private/'.$filename),
        );

        $this->info('Pembaruan final instrumen dan Reviewer Hub selesai.');
        $this->line('private_file='.$result['output_path']);
        $this->line('sha256='.$result['sha256']);
        $this->line('item_counts='.implode('/', array_values($result['questions'])));
        $this->line('old_hubs_revoked='.$result['old_hubs_revoked']);
        $this->line('new_hubs_created='.$result['new_hubs_created']);

        return self::SUCCESS;
    }
}
