<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $packageData['package']->title }} - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page { size: A4; margin: 16mm; }
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .break-after-page { break-after: page; page-break-after: always; }
            .analysis-package-section { min-height: auto !important; }
        }
    </style>
</head>
<body class="bg-white text-slate-950 antialiased">
    <main class="mx-auto max-w-5xl px-6 py-8 print:p-0">
        <div class="no-print mb-6 flex justify-end">
            <button type="button" onclick="window.print()" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Print to PDF</button>
        </div>

        @include('surveys.admin.analysis-package._document', ['packageData' => $packageData, 'survey' => $survey])
    </main>
</body>
</html>
