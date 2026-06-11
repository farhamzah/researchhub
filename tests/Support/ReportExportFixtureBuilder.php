<?php

namespace Tests\Support;

use App\Models\AnalysisResult;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Analysis\Actions\RunSurveyDescriptiveAnalysisAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;

class ReportExportFixtureBuilder
{
    public const SAFE_SURVEY_TITLE = 'Safe Visible Export Survey / Draft: QA';

    public const SAFE_NARRATIVE = 'Narasi aman menggambarkan distribusi respons berdasarkan analisis deskriptif dan perlu diverifikasi lebih lanjut.';

    public const SAFE_TABLE_TITLE = 'Safe QA Table';

    public const SAFE_TABLE_ROW = 'safe_visible_row';

    public const RESPONDENT_NAME = 'Private QA Respondent';

    public const RESPONDENT_EMAIL = 'private.qa@example.test';

    public const RESPONDENT_IDENTIFIER = 'PRIVATE-QA-ID-001';

    public const HIDDEN_ANSWER = 'QA-HIDDEN-ANSWER-DO-NOT-EXPORT';

    public const RAW_PAYLOAD_MARKER = 'QA_RAW_PAYLOAD_DO_NOT_EXPORT';

    /**
     * @return array<int, string>
     */
    public static function forbiddenTerms(): array
    {
        return [
            'p-value',
            'p value',
            'signifikan secara statistik',
            'statistically significant',
            'hipotesis diterima',
            'hipotesis ditolak',
            'kausal',
            'causal',
            'efektif secara signifikan',
            'effectiveness',
            'terbukti efektif',
        ];
    }

    public function create(): ReportExportFixture
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Report Export QA Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => self::SAFE_SURVEY_TITLE,
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);

        $likert = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'safe_visible_metric',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Safe visible metric',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);
        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_security_payload',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden security payload',
        ]);

        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => self::RESPONDENT_NAME,
            'email' => self::RESPONDENT_EMAIL,
            'identifier' => self::RESPONDENT_IDENTIFIER,
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'metadata' => [
                'raw_payload_marker' => self::RAW_PAYLOAD_MARKER,
                'forbidden_terms' => self::forbiddenTerms(),
            ],
        ]);

        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $likert->id,
            'question_key' => 'safe_visible_metric',
            'answer_value' => 5,
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_security_payload',
            'answer_value' => self::HIDDEN_ANSWER.' '.implode(' ', self::forbiddenTerms()),
        ]);

        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);
        $this->addSafeReportArtifacts($result);

        return new ReportExportFixture(
            owner: $owner,
            project: $project,
            survey: $survey,
            result: $result->fresh(['job', 'project', 'survey', 'tables', 'narratives']),
            safeStrings: [
                self::SAFE_SURVEY_TITLE,
                self::SAFE_NARRATIVE,
                self::SAFE_TABLE_TITLE,
                self::SAFE_TABLE_ROW,
                'safe_visible_metric',
            ],
            sensitiveStrings: [
                self::RESPONDENT_NAME,
                self::RESPONDENT_EMAIL,
                self::RESPONDENT_IDENTIFIER,
                self::HIDDEN_ANSWER,
            ],
            forbiddenTerms: self::forbiddenTerms(),
            rawPayloadMarker: self::RAW_PAYLOAD_MARKER,
        );
    }

    private function addSafeReportArtifacts(AnalysisResult $result): void
    {
        $result->update([
            'result_payload' => [
                ...$result->result_payload,
                'raw_answer_payload_sample' => [
                    self::RAW_PAYLOAD_MARKER,
                    self::RESPONDENT_NAME,
                    self::RESPONDENT_EMAIL,
                    self::HIDDEN_ANSWER,
                    ...self::forbiddenTerms(),
                ],
            ],
        ]);

        $result->narratives()->update([
            'narrative' => self::SAFE_NARRATIVE,
        ]);

        $result->tables()->create([
            'title' => self::SAFE_TABLE_TITLE,
            'table_key' => 'safe_qa_table',
            'columns' => [
                'metric',
                'value',
                'percentage',
                'question_key',
                'question_type',
            ],
            'rows' => [[
                'metric' => self::SAFE_TABLE_ROW,
                'value' => '42',
                'percentage' => '100',
                'question_key' => 'safe_visible_metric',
                'question_type' => SurveyQuestion::TYPE_LIKERT,
            ]],
        ]);
    }
}
