<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\SupervisionSessionResource;
use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use Illuminate\Database\Seeder;

class MyRisetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DocumentCategorySeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@researchhub.test'],
            [
                'name' => 'MyRiset Demo Admin',
                'password' => 'password',
            ],
        );
        $admin->assignRole('super_admin');

        $project = ResearchProject::query()->updateOrCreate(
            ['slug' => 'disertasi-pharmvr'],
            [
                'owner_id' => $admin->id,
                'title' => 'Disertasi PharmVR',
                'description' => 'Pengembangan dan evaluasi modul pembelajaran Virtual Reality berbasis CPOB/GMP untuk pendidikan farmasi industri.',
                'research_type' => 'dissertation',
                'institution' => 'Program Doktor Farmasi',
                'status' => ResearchProject::STATUS_ACTIVE,
                'started_at' => today()->subDays(30),
                'target_finished_at' => today()->addDays(180),
            ],
        );

        $milestones = $this->seedTimeline($admin, $project);
        $documents = $this->seedDocuments($admin, $project);
        $links = $this->seedResearchLinks($admin, $project);
        [$survey, $questions] = $this->seedSurvey($admin, $project);
        [$validators, $round, $assignments] = $this->seedValidation($admin, $project, $survey, $questions);
        $this->seedSupervision($admin, $project, $documents, $links, $survey, $round);
    }

    /**
     * @return array<string, ProjectMilestone>
     */
    private function seedTimeline(User $admin, ResearchProject $project): array
    {
        $milestoneRows = [
            ['title' => 'Penyusunan Proposal', 'status' => ProjectMilestone::STATUS_COMPLETED, 'sort_order' => 1, 'start' => -30, 'end' => -5],
            ['title' => 'Pengembangan Instrumen', 'status' => ProjectMilestone::STATUS_IN_PROGRESS, 'sort_order' => 2, 'start' => -7, 'end' => 14],
            ['title' => 'Validasi Ahli', 'status' => ProjectMilestone::STATUS_IN_PROGRESS, 'sort_order' => 3, 'start' => 7, 'end' => 30],
            ['title' => 'Uji Coba PharmVR', 'status' => ProjectMilestone::STATUS_NOT_STARTED, 'sort_order' => 4, 'start' => 31, 'end' => 75],
            ['title' => 'Analisis dan Publikasi', 'status' => ProjectMilestone::STATUS_NOT_STARTED, 'sort_order' => 5, 'start' => 76, 'end' => 160],
        ];

        $milestones = [];

        foreach ($milestoneRows as $row) {
            $milestones[$row['title']] = ProjectMilestone::query()->updateOrCreate(
                [
                    'research_project_id' => $project->id,
                    'title' => $row['title'],
                ],
                [
                    'description' => 'Demo milestone untuk alur kerja Disertasi PharmVR.',
                    'planned_start_date' => today()->addDays($row['start']),
                    'planned_end_date' => today()->addDays($row['end']),
                    'status' => $row['status'],
                    'sort_order' => $row['sort_order'],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        $taskRows = [
            ['milestone' => 'Penyusunan Proposal', 'title' => 'Finalisasi BAB I', 'status' => ProjectMilestone::STATUS_COMPLETED, 'progress' => 100, 'start' => -25, 'end' => -10, 'sort' => 1],
            ['milestone' => 'Pengembangan Instrumen', 'title' => 'Revisi instrumen validasi ahli', 'status' => ProjectMilestone::STATUS_IN_PROGRESS, 'progress' => 45, 'start' => -5, 'end' => -1, 'sort' => 2],
            ['milestone' => 'Uji Coba PharmVR', 'title' => 'Persiapan uji coba mahasiswa', 'status' => ProjectMilestone::STATUS_NOT_STARTED, 'progress' => 0, 'start' => 8, 'end' => 12, 'sort' => 3],
            ['milestone' => 'Analisis dan Publikasi', 'title' => 'Analisis hasil pre-test dan post-test', 'status' => ProjectMilestone::STATUS_NOT_STARTED, 'progress' => 0, 'start' => 45, 'end' => 60, 'sort' => 4],
            ['milestone' => 'Analisis dan Publikasi', 'title' => 'Draft artikel BMC Medical Education', 'status' => ProjectMilestone::STATUS_IN_PROGRESS, 'progress' => 25, 'start' => 20, 'end' => 90, 'sort' => 5],
        ];

        foreach ($taskRows as $row) {
            ProjectTimelineTask::query()->updateOrCreate(
                [
                    'research_project_id' => $project->id,
                    'title' => $row['title'],
                ],
                [
                    'project_milestone_id' => $milestones[$row['milestone']]->id,
                    'description' => 'Demo timeline task untuk Action Center MyRiset.',
                    'planned_start_date' => today()->addDays($row['start']),
                    'planned_end_date' => today()->addDays($row['end']),
                    'actual_start_date' => $row['progress'] > 0 ? today()->addDays($row['start']) : null,
                    'actual_end_date' => $row['progress'] === 100 ? today()->addDays($row['end']) : null,
                    'status' => $row['status'],
                    'progress_percentage' => $row['progress'],
                    'weight' => 1,
                    'sort_order' => $row['sort'],
                    'assigned_to' => $admin->id,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $milestones;
    }

    /**
     * @return array<string, Document>
     */
    private function seedDocuments(User $admin, ResearchProject $project): array
    {
        $category = DocumentCategory::query()->firstOrCreate(
            ['slug' => 'demo-pharmvr'],
            [
                'name' => 'Demo PharmVR',
                'description' => 'Kategori dokumen demo MyRiset.',
                'sort_order' => 99,
                'is_default' => false,
            ],
        );

        $documents = [];

        foreach ([
            'Proposal Disertasi PharmVR',
            'BAB I Pendahuluan',
            'BAB III Metodologi Penelitian',
            'Draft Artikel BMC Medical Education',
            'Instrumen Validasi Ahli',
        ] as $index => $title) {
            $documents[$title] = Document::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'slug' => str($title)->slug()->toString(),
                ],
                [
                    'category_id' => $category->id,
                    'owner_id' => $admin->id,
                    'title' => $title,
                    'description' => 'Metadata-only demo document untuk pengujian MyRiset.',
                    'status' => $index === 0 ? Document::STATUS_UNDER_REVIEW : Document::STATUS_DRAFT,
                    'visibility' => Document::VISIBILITY_PROJECT,
                    'tags' => ['demo', 'pharmvr'],
                ],
            );
        }

        return $documents;
    }

    /**
     * @return array<string, ResearchLink>
     */
    private function seedResearchLinks(User $admin, ResearchProject $project): array
    {
        $rows = [
            ['title' => 'BPOM CPOB 2024', 'url' => 'https://www.pom.go.id/', 'category' => ResearchLink::CATEGORY_REGULATION, 'sort' => 1],
            ['title' => 'Google Scholar', 'url' => 'https://scholar.google.com/', 'category' => ResearchLink::CATEGORY_REFERENCE, 'sort' => 2],
            ['title' => 'BMC Medical Education', 'url' => 'https://bmcmededuc.biomedcentral.com/', 'category' => ResearchLink::CATEGORY_JOURNAL, 'sort' => 3],
            ['title' => 'Scopus', 'url' => 'https://www.scopus.com/', 'category' => ResearchLink::CATEGORY_DATASET, 'sort' => 4],
            ['title' => 'Research Methods Resource', 'url' => 'https://methods.sagepub.com/', 'category' => ResearchLink::CATEGORY_METHODOLOGY, 'sort' => 5],
        ];

        $links = [];

        foreach ($rows as $row) {
            $links[$row['title']] = ResearchLink::query()->updateOrCreate(
                [
                    'research_project_id' => $project->id,
                    'title' => $row['title'],
                ],
                [
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                    'url' => $row['url'],
                    'description' => 'Demo research link untuk pengujian MyRiset.',
                    'category' => $row['category'],
                    'is_pinned' => true,
                    'is_active' => true,
                    'sort_order' => $row['sort'],
                ],
            );
        }

        return $links;
    }

    /**
     * @return array{0: Survey, 1: array<string, SurveyQuestion>}
     */
    private function seedSurvey(User $admin, ResearchProject $project): array
    {
        $survey = Survey::query()->updateOrCreate(
            ['slug' => 'angket-evaluasi-pembelajaran-pharmvr'],
            [
                'project_id' => $project->id,
                'created_by' => $admin->id,
                'title' => 'Angket Evaluasi Pembelajaran PharmVR',
                'description' => 'Demo survey untuk evaluasi pembelajaran PharmVR pada pendidikan farmasi industri.',
                'status' => Survey::STATUS_DRAFT,
                'identity_mode' => Survey::IDENTITY_ANONYMOUS,
                'is_public' => false,
            ],
        );

        $page = SurveyPage::query()->updateOrCreate(
            [
                'survey_id' => $survey->id,
                'sort_order' => 1,
            ],
            [
                'title' => 'Evaluasi Pembelajaran PharmVR',
                'description' => 'Isilah sesuai pengalaman menggunakan modul PharmVR.',
            ],
        );

        $likertOptions = [
            ['value' => 1, 'label' => 'Sangat tidak setuju'],
            ['value' => 2, 'label' => 'Tidak setuju'],
            ['value' => 3, 'label' => 'Setuju'],
            ['value' => 4, 'label' => 'Sangat setuju'],
        ];

        $questionRows = [
            ['key' => 'pharmvr_cpob_understanding', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'PharmVR membantu saya memahami alur produksi sesuai prinsip CPOB.', 'sort' => 1],
            ['key' => 'pharmvr_visual_clarity', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'Tampilan visual PharmVR mudah dipahami.', 'sort' => 2],
            ['key' => 'pharmvr_learning_engagement', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'Interaksi dalam PharmVR meningkatkan keterlibatan belajar.', 'sort' => 3],
            ['key' => 'pharmvr_material_relevance', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'Materi yang disajikan relevan dengan pembelajaran farmasi industri.', 'sort' => 4],
            ['key' => 'pharmvr_helpful_part', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Bagian apa yang paling membantu dalam pembelajaran menggunakan PharmVR?', 'sort' => 5],
        ];

        $questions = [];

        foreach ($questionRows as $row) {
            $questions[$row['key']] = SurveyQuestion::query()->updateOrCreate(
                [
                    'survey_id' => $survey->id,
                    'question_key' => $row['key'],
                ],
                [
                    'page_id' => $page->id,
                    'type' => $row['type'],
                    'label' => $row['label'],
                    'help_text' => $row['type'] === SurveyQuestion::TYPE_LIKERT ? 'Gunakan skala 1 sampai 4.' : null,
                    'options' => $row['type'] === SurveyQuestion::TYPE_LIKERT ? $likertOptions : null,
                    'settings' => $row['type'] === SurveyQuestion::TYPE_LIKERT ? ['scale_min' => 1, 'scale_max' => 4] : null,
                    'is_required' => true,
                    'sort_order' => $row['sort'],
                ],
            );
        }

        return [$survey, $questions];
    }

    /**
     * @param  array<string, SurveyQuestion>  $questions
     * @return array{0: array<string, ExpertValidator>, 1: SurveyValidationRound, 2: array<string, SurveyValidationAssignment>}
     */
    private function seedValidation(User $admin, ResearchProject $project, Survey $survey, array $questions): array
    {
        $validatorRows = [
            ['key' => 'materi', 'name' => 'Dr. Validator Materi CPOB', 'email' => 'validator.materi@example.test', 'role' => ExpertValidatorProject::ROLE_CONTENT, 'scope' => 'cpob_gmp_expert', 'institution' => 'Fakultas Farmasi'],
            ['key' => 'media', 'name' => 'Dr. Validator Media Pembelajaran', 'email' => 'validator.media@example.test', 'role' => ExpertValidatorProject::ROLE_CONTENT, 'scope' => 'education_expert', 'institution' => 'Teknologi Pendidikan'],
            ['key' => 'instrumen', 'name' => 'Dr. Validator Instrumen', 'email' => 'validator.instrumen@example.test', 'role' => ExpertValidatorProject::ROLE_INSTRUMENT, 'scope' => 'methodology_expert', 'institution' => 'Metodologi Penelitian'],
        ];

        $validators = [];

        foreach ($validatorRows as $row) {
            $validators[$row['key']] = ExpertValidator::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'created_by' => $admin->id,
                    'name' => $row['name'],
                    'institution' => $row['institution'],
                    'position' => 'Demo Expert Validator',
                    'expertise_areas' => [$row['scope']],
                    'notes' => 'Demo validator; bukan data pribadi nyata.',
                    'is_active' => true,
                    'is_global' => false,
                ],
            );

            ExpertValidatorProject::query()->updateOrCreate(
                [
                    'research_project_id' => $project->id,
                    'expert_validator_id' => $validators[$row['key']]->id,
                    'role' => $row['role'],
                ],
                [
                    'expertise_scope' => $row['scope'],
                    'status' => ExpertValidatorProject::STATUS_ACTIVE,
                    'invited_at' => now()->subDays(10),
                    'accepted_at' => now()->subDays(9),
                    'notes' => 'Demo assignment untuk validasi PharmVR.',
                    'created_by' => $admin->id,
                ],
            );
        }

        $round = SurveyValidationRound::query()->updateOrCreate(
            [
                'survey_id' => $survey->id,
                'title' => 'Validasi Instrumen Angket Evaluasi PharmVR',
            ],
            [
                'research_project_id' => $project->id,
                'created_by' => $admin->id,
                'description' => 'Demo validation round untuk data Aiken/CVI.',
                'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
                'rating_scale_min' => 1,
                'rating_scale_max' => 4,
                'status' => SurveyValidationRound::STATUS_OPEN,
                'instructions' => 'Berikan skor 1-4 untuk relevansi, kejelasan, bahasa, dan kesesuaian.',
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addDays(14),
            ],
        );

        $assignments = [];

        foreach ($validators as $key => $validator) {
            $status = $key === 'instrumen'
                ? SurveyValidationAssignment::STATUS_PENDING
                : SurveyValidationAssignment::STATUS_SUBMITTED;

            $assignments[$key] = SurveyValidationAssignment::query()->updateOrCreate(
                [
                    'survey_validation_round_id' => $round->id,
                    'expert_validator_id' => $validator->id,
                ],
                [
                    'role' => $key === 'instrumen' ? ExpertValidatorProject::ROLE_INSTRUMENT : ExpertValidatorProject::ROLE_CONTENT,
                    'status' => $status,
                    'token_hash' => null,
                    'token_created_at' => null,
                    'opened_at' => $status === SurveyValidationAssignment::STATUS_SUBMITTED ? now()->subDays(5) : null,
                    'submitted_at' => $status === SurveyValidationAssignment::STATUS_SUBMITTED ? now()->subDays($key === 'materi' ? 3 : 2) : null,
                    'revoked_at' => null,
                    'expires_at' => null,
                    'created_by' => $admin->id,
                ],
            );
        }

        foreach (['materi', 'media'] as $validatorKey) {
            foreach (array_values($questions) as $index => $question) {
                $needsRevision = $question->question_key === 'pharmvr_learning_engagement';

                SurveyValidationScore::query()->updateOrCreate(
                    [
                        'survey_validation_assignment_id' => $assignments[$validatorKey]->id,
                        'survey_question_id' => $question->id,
                    ],
                    [
                        'relevance_score' => $needsRevision && $validatorKey === 'media' ? 2 : 4,
                        'clarity_score' => $needsRevision ? 2 : 3 + ($index % 2),
                        'language_score' => $needsRevision ? 3 : 4,
                        'appropriateness_score' => $needsRevision && $validatorKey === 'media' ? 2 : 4,
                        'comment' => $needsRevision
                            ? 'Perlu memperjelas indikator keterlibatan belajar agar tidak multitafsir.'
                            : 'Butir sudah sesuai untuk konteks evaluasi PharmVR.',
                        'recommendation' => $needsRevision
                            ? SurveyValidationScore::RECOMMENDATION_MINOR_REVISION
                            : SurveyValidationScore::RECOMMENDATION_ACCEPTED,
                    ],
                );
            }
        }

        return [$validators, $round, $assignments];
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, ResearchLink>  $links
     */
    private function seedSupervision(
        User $admin,
        ResearchProject $project,
        array $documents,
        array $links,
        Survey $survey,
        SurveyValidationRound $round,
    ): void {
        $session = SupervisionSession::query()->updateOrCreate(
            [
                'research_project_id' => $project->id,
                'title' => 'Bimbingan Proposal dan Validasi Instrumen PharmVR',
            ],
            [
                'created_by' => $admin->id,
                'meeting_type' => SupervisionSession::MEETING_INSTRUMENT_REVIEW,
                'status' => SupervisionSession::STATUS_REVISION_NEEDED,
                'agenda' => 'Pembahasan progres proposal, instrumen, dan rencana validasi ahli.',
                'progress_report' => 'Proposal BAB I-III sudah disusun, survey builder sudah dibuat, dan validator ahli sudah disiapkan.',
                'questions' => 'Apakah indikator angket sudah sesuai dengan tujuan penelitian PharmVR?',
                'requested_feedback' => 'Mohon masukan terhadap struktur instrumen dan rencana uji coba.',
                'next_plan' => 'Revisi instrumen berdasarkan validasi ahli dan persiapan uji coba terbatas.',
                'notes' => 'Demo supervision session untuk QA MyRiset.',
                'target_date' => today()->addDays(5),
                'submitted_at' => now()->subDay(),
            ],
        );

        $reviewLink = SupervisionReviewLink::query()->updateOrCreate(
            [
                'supervision_session_id' => $session->id,
                'recipient_name' => 'Demo Supervisor',
            ],
            [
                'created_by' => $admin->id,
                'recipient_role' => 'Promotor',
                'status' => SupervisionReviewLink::STATUS_SUBMITTED,
                'token_hash' => null,
                'token_created_at' => null,
                'opened_at' => now()->subDays(2),
                'submitted_at' => now()->subDay(),
                'revoked_at' => null,
                'expires_at' => null,
            ],
        );

        SupervisionFeedback::query()->updateOrCreate(
            ['supervision_review_link_id' => $reviewLink->id],
            [
                'supervision_session_id' => $session->id,
                'decision' => SupervisionFeedback::DECISION_MINOR_REVISION,
                'general_feedback' => 'Struktur instrumen sudah baik, tetapi beberapa butir perlu diperjelas agar tidak menimbulkan interpretasi ganda.',
                'revision_notes' => 'Perjelas indikator keterlibatan belajar dan usability.',
                'recommended_next_steps' => 'Revisi butir, lakukan validasi ahli, lalu lanjut uji coba terbatas.',
                'supervisor_note' => null,
            ],
        );

        $resourceRows = [
            ['type' => SupervisionSessionResource::TYPE_DOCUMENT, 'id' => $documents['Proposal Disertasi PharmVR']->id, 'title' => 'Proposal Disertasi PharmVR', 'sort' => 1, 'visible' => true],
            ['type' => SupervisionSessionResource::TYPE_DOCUMENT, 'id' => $documents['Instrumen Validasi Ahli']->id, 'title' => 'Instrumen Validasi Ahli', 'sort' => 2, 'visible' => true],
            ['type' => SupervisionSessionResource::TYPE_SURVEY, 'id' => $survey->id, 'title' => 'Angket Evaluasi Pembelajaran PharmVR', 'sort' => 3, 'visible' => true],
            ['type' => SupervisionSessionResource::TYPE_VALIDATION_ROUND, 'id' => $round->id, 'title' => 'Validasi Instrumen Angket Evaluasi PharmVR', 'sort' => 4, 'visible' => true],
            ['type' => SupervisionSessionResource::TYPE_RESEARCH_LINK, 'id' => $links['Google Scholar']->id, 'title' => 'Google Scholar', 'sort' => 5, 'visible' => true],
            ['type' => SupervisionSessionResource::TYPE_MANUAL_NOTE, 'id' => null, 'title' => 'Fokus bimbingan pada kesiapan instrumen dan desain validasi ahli', 'sort' => 6, 'visible' => true],
        ];

        foreach ($resourceRows as $row) {
            SupervisionSessionResource::query()->updateOrCreate(
                [
                    'supervision_session_id' => $session->id,
                    'resource_type' => $row['type'],
                    'title' => $row['title'],
                ],
                [
                    'created_by' => $admin->id,
                    'resource_id' => $row['id'],
                    'url' => null,
                    'description' => 'Demo resource untuk bimbingan PharmVR.',
                    'notes' => $row['type'] === SupervisionSessionResource::TYPE_MANUAL_NOTE ? 'Bahas kesiapan instrumen sebelum validasi ahli.' : null,
                    'sort_order' => $row['sort'],
                    'is_visible_to_supervisor' => $row['visible'],
                ],
            );
        }

        $followUps = [
            ['title' => 'Revisi butir angket tentang keterlibatan belajar', 'status' => SupervisionFollowUpItem::STATUS_TODO, 'priority' => SupervisionFollowUpItem::PRIORITY_HIGH, 'due' => today()->addDays(7), 'completed' => null, 'note' => null],
            ['title' => 'Tambahkan penjelasan indikator usability', 'status' => SupervisionFollowUpItem::STATUS_IN_PROGRESS, 'priority' => SupervisionFollowUpItem::PRIORITY_NORMAL, 'due' => today()->addDays(10), 'completed' => null, 'note' => null],
            ['title' => 'Siapkan draft instrumen untuk validator ahli', 'status' => SupervisionFollowUpItem::STATUS_COMPLETED, 'priority' => SupervisionFollowUpItem::PRIORITY_NORMAL, 'due' => today()->subDays(2), 'completed' => now()->subDay(), 'note' => 'Draft instrumen sudah disiapkan untuk proses validasi ahli.'],
        ];

        foreach ($followUps as $row) {
            SupervisionFollowUpItem::query()->updateOrCreate(
                [
                    'supervision_session_id' => $session->id,
                    'title' => $row['title'],
                ],
                [
                    'created_by' => $admin->id,
                    'assigned_to' => $admin->id,
                    'description' => 'Demo follow-up untuk QA bimbingan PharmVR.',
                    'source' => SupervisionFollowUpItem::SOURCE_SUPERVISOR_FEEDBACK,
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'due_date' => $row['due'],
                    'completed_at' => $row['completed'],
                    'completion_note' => $row['note'],
                ],
            );
        }
    }
}
