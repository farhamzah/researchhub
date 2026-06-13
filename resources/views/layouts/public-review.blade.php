<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MyRiset Public Review')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="/" class="text-xl font-semibold text-slate-950">MyRiset</a>
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Secure academic review link
                    </span>
                </div>
                <p class="max-w-3xl text-sm leading-6 text-slate-600">
                    Halaman ini disediakan untuk peninjauan akademik terbatas. Link hanya berlaku untuk penugasan yang diberikan dan tidak menampilkan dashboard admin atau data responden.
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-5 text-sm text-slate-500 sm:px-6 lg:px-8">
                Powered by MyRiset. Jangan bagikan link ini kepada pihak lain.
            </div>
        </footer>
    </div>
</body>
</html>
