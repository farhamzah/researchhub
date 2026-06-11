<?php

namespace App\Http\Controllers;

use App\Models\AnalysisResult;
use App\Modules\Analysis\Services\AnalysisExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAnalysisExportController extends Controller
{
    public function csv(AnalysisResult $analysisResult, Request $request, AnalysisExportService $exportService): Response
    {
        return $this->download($analysisResult, $request, $exportService, 'csv');
    }

    public function markdown(AnalysisResult $analysisResult, Request $request, AnalysisExportService $exportService): Response
    {
        return $this->download($analysisResult, $request, $exportService, 'markdown');
    }

    private function download(
        AnalysisResult $analysisResult,
        Request $request,
        AnalysisExportService $exportService,
        string $format,
    ): Response {
        $export = $exportService->export($analysisResult, $request->user(), $format, $request);
        $filename = str_replace('"', '', $export['filename']);

        return response($export['content'], Response::HTTP_OK, [
            'Content-Type' => $export['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
