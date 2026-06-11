<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Modules\Surveys\Services\SurveyResponseCsvExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSurveyResponseExportController extends Controller
{
    public function __invoke(Survey $survey, Request $request, SurveyResponseCsvExporter $exporter): Response
    {
        $withIdentity = $request->boolean('with_identity');
        $csv = $exporter->toCsv($survey, $request->user(), $withIdentity, $request);
        $filename = str_replace('"', '', $exporter->filename($survey, $withIdentity));

        return response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
