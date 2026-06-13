<?php

namespace Tests\Feature;

class SurveyExpertValidationPublicLinkTest extends PublicValidationLinkUxTest
{
    public function test_survey_expert_validation_public_link_filter_covers_polished_page(): void
    {
        [$token] = $this->validationFixture();

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Validasi Ahli Instrumen')
            ->assertSeeText('Kirim Hasil Validasi');
    }
}
