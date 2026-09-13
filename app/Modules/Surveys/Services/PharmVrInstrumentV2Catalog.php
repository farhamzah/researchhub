<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;

class PharmVrInstrumentV2Catalog
{
    public const VERSION = '2.0';

    public const STUDENT = 'S01-STUDENT-NEEDS';

    public const LECTURER = 'S02-LECTURER-NEEDS';

    public const PRACTITIONER = 'S03-PRACTITIONER-INTERVIEW';

    public function instruments(string $researcherContact): array
    {
        return [
            $this->student($researcherContact),
            $this->lecturer($researcherContact),
            $this->practitioner($researcherContact),
        ];
    }

    private function student(string $contact): array
    {
        $difficulty = $this->scale(['1' => 'Tidak sulit', '2' => 'Sedikit sulit', '3' => 'Cukup sulit', '4' => 'Sulit', '5' => 'Sangat sulit', 'NA' => 'Belum mempelajari']);
        $need = $this->scale(['1' => 'Tidak membutuhkan', '2' => 'Kurang membutuhkan', '3' => 'Cukup membutuhkan', '4' => 'Membutuhkan', '5' => 'Sangat membutuhkan']);
        $agreement = $this->scale(['1' => 'Sangat tidak setuju', '2' => 'Tidak setuju', '3' => 'Netral', '4' => 'Setuju', '5' => 'Sangat setuju']);

        return [
            'code' => self::STUDENT,
            'identifier' => self::STUDENT.'-v'.self::VERSION,
            'title' => 'Kuesioner Analisis Kebutuhan Mahasiswa PharmVR',
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'target' => 'Mahasiswa S1 Farmasi yang sudah/sedang memperoleh materi Farmasi Industri/CPOB sesuai protokol.',
            'duration' => '10–15 menit',
            'analysis' => 'A/B deskriptif; C difficulty; D need; E acceptance/readiness; F frekuensi/ranking; G tematik. Jangan dijumlahkan menjadi satu skor total.',
            'intro' => $this->intro('Kuesioner ini bertujuan memetakan pengalaman belajar, kesulitan, kebutuhan bantuan pembelajaran, kesiapan VR, dan prioritas konten PharmVR.', '10–15 menit', $contact),
            'pages' => [
                $this->page('A. Persetujuan dan Kelayakan', [
                    $this->q('S01-A01', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Apakah Anda bersedia berpartisipasi dalam penelitian ini?', ['Bersedia', 'Tidak bersedia'], ['terminate_on' => 'Tidak bersedia'], true),
                    $this->q('S01-A02', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Program pendidikan yang sedang Anda tempuh:', ['S1 Farmasi', 'Lainnya']),
                    $this->q('S01-A03', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Semester yang sedang Anda tempuh:', ['1', '2', '3', '4', '5', '6', '7', '8', '>8']),
                    $this->q('S01-A04', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Status Anda terhadap mata kuliah/materi Farmasi Industri yang membahas CPOB:', ['Sedang menempuh', 'Sudah menempuh', 'Belum menempuh', 'Tidak yakin']),
                    $this->q('S01-A05', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pengalaman apa yang pernah Anda miliki terkait lingkungan industri farmasi?', ['Belum pernah', 'Kunjungan industri', 'Praktikum atau simulasi fasilitas industri di kampus', 'Magang/PKPA/kerja praktik di industri', 'Video/virtual tour', 'Pengalaman lain'], ['exclusive_values' => ['Belum pernah']]),
                ]),
                $this->page('B. Pengalaman Belajar Saat Ini', [
                    $this->q('S01-B01', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Media/metode yang pernah digunakan:', ['Ceramah/diskusi', 'Buku/modul/regulasi', 'Slide', 'Video', 'Praktikum', 'Studi kasus', 'Simulasi komputer', 'VR/AR', 'Kunjungan industri', 'Lainnya']),
                    $this->q('S01-B02', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Frekuensi pengalaman VR:', ['Belum pernah', 'Pernah 1–2 kali', 'Kadang-kadang', 'Sering']),
                    $this->q('S01-B03', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Perangkat yang dapat diakses:', ['Smartphone', 'Laptop/komputer', 'Tablet', 'Headset VR', 'Tidak ada akses rutin'], ['exclusive_values' => ['Tidak ada akses rutin']]),
                    $this->q('S01-B04', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Akses internet:', ['Sangat terbatas', 'Terbatas', 'Cukup', 'Baik', 'Sangat baik']),
                ]),
                $this->page('C. Kesulitan Belajar CPOB', $this->scaled('S01-C', [
                    '01' => 'Tata letak dan fungsi area produksi.', '02' => 'Alur personel.', '03' => 'Alur material/bahan.', '04' => 'Higiene personel, pakaian kerja, dan prosedur masuk area produksi.', '05' => 'Pencegahan kontaminasi/kontaminasi silang dan line clearance.', '06' => 'Urutan proses produksi tablet non-steril.', '07' => 'Hubungan fungsi Produksi, QA, dan QC.', '08' => 'Dokumentasi produksi, batch record, dan penanganan penyimpangan.',
                ], $difficulty), 'Berdasarkan pembelajaran yang sudah Anda ikuti, seberapa sulit hal berikut dipahami? Pilih ‘Belum mempelajari’ bila Anda belum pernah mempelajarinya.'),
                $this->page('D. Kebutuhan Bantuan Pembelajaran', array_merge($this->scaled('S01-D', [
                    '01' => 'Visualisasi tata letak fasilitas dan zona kerja.', '02' => 'Visualisasi alur personel dan material.', '03' => 'Demonstrasi langkah prosedural secara berurutan.', '04' => 'Latihan pengambilan keputusan pada situasi/penyimpangan CPOB.', '05' => 'Umpan balik langsung saat melakukan langkah yang kurang tepat.', '06' => 'Kesempatan mengulang latihan secara mandiri.',
                ], $need), [
                    $this->q('S01-D07', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Media yang membantu:', ['Penjelasan dosen tambahan', 'Buku/modul', 'Video', 'Praktikum', 'Kunjungan industri', 'Simulasi komputer', 'Simulasi VR', 'Studi kasus/diskusi', 'Lainnya']),
                ]), 'Seberapa besar Anda membutuhkan bantuan pembelajaran tambahan untuk hal berikut?'),
                $this->page('E. Penerimaan dan Kesiapan VR', array_merge($this->scaled('S01-E', [
                    '01' => 'Jika tersedia dan mendapat petunjuk penggunaan, saya bersedia mencoba simulasi VR untuk pembelajaran CPOB.', '02' => 'Saya bersedia mengikuti sesi VR sebagai bagian dari kegiatan pembelajaran terjadwal.', '03' => 'Saya memerlukan orientasi/pelatihan singkat sebelum menggunakan headset VR.', '04' => 'Saya khawatir mengalami ketidaknyamanan seperti pusing, mual, atau kelelahan saat menggunakan VR.',
                ], $agreement), [
                    $this->q('S01-E05', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Durasi sesi nyaman:', ['<10 menit', '10–20 menit', '21–30 menit', '31–45 menit', '>45 menit', 'Belum dapat memperkirakan']),
                ]), 'Pernyataan berikut mengukur kesiapan dan harapan Anda terhadap penggunaan VR, bukan membuktikan efektivitas VR.'),
                $this->page('F. Prioritas Konten', [
                    $this->q('S01-F01', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 area/skenario pembelajaran prioritas:', $this->scenes(), ['max_selections' => 3]),
                    $this->q('S01-F02', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 fitur prioritas:', $this->features(), ['max_selections' => 3]),
                ]),
                $this->page('G. Terbuka', [
                    $this->q('S01-G01', SurveyQuestion::TYPE_LONG_TEXT, 'Bagian CPOB/proses industri apa yang paling sulit dipahami?'),
                    $this->q('S01-G02', SurveyQuestion::TYPE_LONG_TEXT, 'Kekhawatiran/hambatan penggunaan VR?'),
                    $this->q('S01-G03', SurveyQuestion::TYPE_LONG_TEXT, 'Saran agar PharmVR membantu pembelajaran Farmasi Industri?'),
                ]),
            ],
        ];
    }

    private function lecturer(string $contact): array
    {
        $frequency = $this->scale(['1' => 'Tidak pernah', '2' => 'Jarang', '3' => 'Kadang-kadang', '4' => 'Sering', '5' => 'Sangat sering', 'NA' => 'Tidak dapat menilai']);
        $importance = $this->scale(['1' => 'Tidak penting', '2' => 'Kurang penting', '3' => 'Cukup penting', '4' => 'Penting', '5' => 'Sangat penting']);
        $agreement = $this->scale(['1' => 'Sangat tidak setuju', '2' => 'Tidak setuju', '3' => 'Netral', '4' => 'Setuju', '5' => 'Sangat setuju']);

        return [
            'code' => self::LECTURER, 'identifier' => self::LECTURER.'-v'.self::VERSION,
            'title' => 'Kuesioner Analisis Kebutuhan Dosen PharmVR', 'instrument_type' => Survey::INSTRUMENT_ANALYSIS_LECTURER,
            'target' => 'Dosen/pengampu Farmasi Industri/CPOB.', 'duration' => '10–15 menit',
            'analysis' => 'Profil deskriptif; B/C/E terpisah; D/G tematik; F frekuensi. Jangan menyebut TPACK scale.',
            'intro' => $this->intro('Kuesioner ini bertujuan memetakan pandangan dosen tentang kesulitan mahasiswa, kebutuhan pengalaman belajar, asesmen, kelayakan implementasi, dan prioritas PharmVR.', '10–15 menit', $contact),
            'pages' => [
                $this->page('A. Profil', [
                    $this->q('S02-A01', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Apakah Anda bersedia berpartisipasi dalam penelitian ini?', ['Bersedia', 'Tidak bersedia'], ['terminate_on' => 'Tidak bersedia'], true),
                    $this->q('S02-A02', SurveyQuestion::TYPE_SHORT_TEXT, 'Bidang keahlian utama.'),
                    $this->q('S02-A03', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Lama mengajar:', ['<1 tahun', '1–3 tahun', '4–6 tahun', '7–10 tahun', '>10 tahun']),
                    $this->q('S02-A04', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Metode pembelajaran yang digunakan saat ini:', ['Ceramah/diskusi', 'Regulasi/buku', 'Video', 'Studi kasus', 'Praktikum', 'Kunjungan industri', 'Simulasi digital', 'VR/AR', 'Lainnya']),
                    $this->q('S02-A05', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Akses institusi ke fasilitas industri:', ['Sangat terbatas', 'Terbatas', 'Cukup', 'Baik', 'Sangat baik']),
                ]),
                $this->page('B. Kesulitan Mahasiswa menurut Dosen', $this->scaled('S02-B', [
                    '01' => 'Tata letak dan fungsi area produksi.', '02' => 'Alur personel.', '03' => 'Alur material.', '04' => 'Higiene/gowning/masuk area.', '05' => 'Kontaminasi silang dan line clearance.', '06' => 'Urutan proses tablet non-steril.', '07' => 'Hubungan Produksi–QA–QC.', '08' => 'Dokumentasi batch dan penyimpangan.',
                ], $frequency), 'Berdasarkan pengalaman mengajar terbaru, seberapa sering mahasiswa menunjukkan kesulitan pada aspek berikut? Pilih ‘Tidak dapat menilai’ bila Anda tidak memiliki dasar penilaian.'),
                $this->page('C. Kebutuhan Pengalaman Belajar', array_merge($this->scaled('S02-C', [
                    '01' => 'Visualisasi ruang dan alur fasilitas.', '02' => 'Demonstrasi prosedur langkah demi langkah.', '03' => 'Latihan berulang tanpa risiko nyata.', '04' => 'Latihan mengenali/merespons kesalahan atau penyimpangan.', '05' => 'Umpan balik langsung.', '06' => 'Refleksi/debrief setelah simulasi.',
                ], $importance), [
                    $this->q('S02-C07', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Media atau metode pembelajaran apa yang menurut Bapak/Ibu paling realistis diterapkan di institusi untuk membantu mengatasi kesenjangan tersebut?', ['Kelas', 'Video', 'Praktikum', 'Kunjungan', 'Simulasi desktop', 'VR', 'Studi kasus', 'Kombinasi', 'Lainnya']),
                ]), 'Seberapa penting mahasiswa memperoleh pengalaman belajar tambahan berikut?'),
                $this->page('D. Keselarasan Pembelajaran dan Asesmen', [
                    $this->q('S02-D01', SurveyQuestion::TYPE_LONG_TEXT, 'Kompetensi CPOB terpenting untuk mahasiswa.'),
                    $this->q('S02-D02', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Bentuk asesmen yang digunakan saat ini:', ['Tes tertulis', 'Tugas/studi kasus', 'Observasi praktikum', 'Presentasi', 'OSCE/unjuk kerja', 'Proyek', 'Lainnya']),
                    $this->q('S02-D03', SurveyQuestion::TYPE_LONG_TEXT, 'Kemampuan yang sulit dinilai saat ini.'),
                    $this->q('S02-D04', SurveyQuestion::TYPE_LONG_TEXT, 'Aktivitas/kinerja yang sebaiknya dapat diamati bila simulasi digunakan.'),
                ]),
                $this->page('E. Kelayakan Implementasi', [
                    $this->q('S02-E01', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Ketersediaan ruang aman VR:', ['Belum ada', 'Mungkin tersedia', 'Tersedia']),
                    $this->q('S02-E02', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Ketersediaan headset:', ['Tidak ada', '1', '2–5', '>5', 'Tidak tahu']),
                    $this->q('S02-E03', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Kualitas internet:', ['Sangat terbatas', 'Terbatas', 'Cukup', 'Baik', 'Sangat baik', 'Tidak tahu']),
                    $this->q('S02-E04', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Waktu realistis:', ['<10 menit', '10–20 menit', '21–30 menit', '31–45 menit', '>45 menit']),
                    $this->q('S02-E05', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Dukungan yang diperlukan:', ['Perangkat', 'Teknisi', 'Panduan dosen', 'Jadwal/lab', 'Pelatihan', 'LMS/database', 'SOP keselamatan', 'Lainnya']),
                    $this->q('S02-E06', SurveyQuestion::TYPE_LIKERT, 'Saya bersedia mencoba integrasi PharmVR bila konten, perangkat, waktu, dan dukungan memadai.', null, $agreement),
                ], 'Jawab berdasarkan kondisi institusi yang Anda ketahui saat ini.'),
                $this->page('F. Prioritas', [
                    $this->q('S02-F01', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 area/skenario pembelajaran prioritas:', $this->scenes(), ['max_selections' => 3]),
                    $this->q('S02-F02', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 fitur prioritas:', $this->features(), ['max_selections' => 3]),
                ]),
                $this->page('G. Terbuka', [
                    $this->q('S02-G01', SurveyQuestion::TYPE_LONG_TEXT, 'Keterbatasan utama pembelajaran CPOB yang belum teratasi.'),
                    $this->q('S02-G02', SurveyQuestion::TYPE_LONG_TEXT, 'Risiko/kelemahan yang harus dihindari dalam penggunaan VR.'),
                    $this->q('S02-G03', SurveyQuestion::TYPE_LONG_TEXT, 'Saran integrasi PharmVR ke RPS/kegiatan pembelajaran dan asesmen.'),
                ]),
            ],
        ];
    }

    private function practitioner(string $contact): array
    {
        return [
            'code' => self::PRACTITIONER, 'identifier' => self::PRACTITIONER.'-v'.self::VERSION,
            'title' => 'Pedoman Wawancara Praktisi/Ahli CPOB PharmVR', 'instrument_type' => Survey::INSTRUMENT_PRACTITIONER_INTERVIEW,
            'target' => 'Praktisi/ahli CPOB/farmasi industri.', 'duration' => '20–30 menit',
            'analysis' => 'Wawancara coding tematik dengan audit trail; F01/F02 hanya frekuensi prioritas; jangan membuat skor total.',
            'intro' => $this->intro('Pedoman wawancara semi-terstruktur, interviewer-administered, untuk memetakan kompetensi, kesenjangan, konten wajib, realisme, feedback, asesmen, prioritas, dan kelayakan PharmVR.', '20–30 menit', $contact),
            'pages' => [
                $this->page('A. Profil', [
                    $this->q('S03-A01', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Apakah Anda bersedia berpartisipasi dalam penelitian ini?', ['Bersedia', 'Tidak bersedia'], ['terminate_on' => 'Tidak bersedia'], true),
                    $this->q('S03-A02', SurveyQuestion::TYPE_SHORT_TEXT, 'Apa bidang keahlian dan jabatan Bapak/Ibu saat ini?'),
                    $this->q('S03-A03', SurveyQuestion::TYPE_SHORT_TEXT, 'Berapa lama pengalaman Bapak/Ibu di industri farmasi atau dalam bidang CPOB?'),
                    $this->q('S03-A04', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Area pengalaman:', ['Produksi', 'QA', 'QC', 'Engineering', 'Validation', 'Regulatory', 'Lainnya']),
                ], $this->practitionerInstruction()),
                $this->page('B. Kompetensi dan Kesenjangan', $this->open('S03-B', [
                    '01' => 'Menurut Bapak/Ibu, kompetensi CPOB apa yang paling penting dimiliki mahasiswa atau lulusan baru sebelum memasuki industri farmasi?', '02' => 'Menurut Bapak/Ibu, kesenjangan apa yang paling sering ditemukan antara pemahaman lulusan baru dan praktik di industri farmasi?', '03' => 'Menurut Bapak/Ibu, kesalahan atau miskonsepsi apa yang paling sering terjadi pada pemula dalam memahami atau menerapkan CPOB?', '04' => 'Menurut Bapak/Ibu, aspek CPOB apa yang sulit dipahami tanpa melihat atau mengalami lingkungan industri secara langsung?',
                ], [
                    '01' => 'Gali aspek pengetahuan, prosedur, dokumentasi, dan perilaku profesional.',
                    '02' => 'Minta contoh konkret dari pengalaman narasumber.',
                    '03' => 'Gali dampak terhadap mutu, keselamatan, dan kepatuhan.',
                ]), $this->practitionerInstruction()),
                $this->page('C. Konten Wajib', $this->open('S03-C', [
                    '01' => 'Menurut Bapak/Ibu, area, alur, dan fasilitas apa yang wajib direpresentasikan dalam PharmVR?', '02' => 'Menurut Bapak/Ibu, tahapan produksi tablet apa yang paling penting dipraktikkan secara berurutan dalam PharmVR?', '03' => 'Hal apa saja terkait higiene, gowning, serta alur personel dan material yang perlu ditampilkan dalam PharmVR?', '04' => 'Hal apa saja terkait line clearance, pencegahan kontaminasi silang, serta status area dan peralatan yang perlu ditampilkan dalam PharmVR?', '05' => 'Menurut Bapak/Ibu, dokumentasi dan keputusan mutu apa yang perlu dimasukkan ke dalam simulasi PharmVR?',
                ], [
                    '02' => 'Bila relevan, gali penimbangan/dispensing, mixing/granulation, drying, compression, coating, dan packaging.',
                    '05' => 'Gali batch record, label/status, deviation, dan QA release bila relevan.',
                ]), $this->practitionerInstruction()),
                $this->page('D. Realisme dan Penyederhanaan', $this->open('S03-D', [
                    '01' => 'Menurut Bapak/Ibu, bagian apa yang boleh disederhanakan dalam PharmVR tanpa menimbulkan miskonsepsi?', '02' => 'Menurut Bapak/Ibu, bagian apa yang tidak boleh disederhanakan karena bersifat kritis terhadap CPOB?', '03' => 'Risiko apa yang perlu diantisipasi agar mahasiswa tidak menganggap simulasi PharmVR identik dengan seluruh praktik di industri?',
                ]), $this->practitionerInstruction()),
                $this->page('E. Aktivitas, Feedback, Asesmen', $this->open('S03-E', [
                    '01' => 'Menurut Bapak/Ibu, kesalahan atau penyimpangan apa yang aman dan bermanfaat untuk dijadikan skenario pembelajaran?', '02' => 'Umpan balik seperti apa yang tepat diberikan ketika mahasiswa melakukan tindakan yang salah?', '03' => 'Kinerja apa yang menurut Bapak/Ibu layak dinilai melalui PharmVR?', '04' => 'Indikator apa yang menunjukkan bahwa mahasiswa telah cukup memahami suatu prosedur untuk melanjutkan ke tahap berikutnya?',
                ], [
                    '03' => 'Gali urutan tindakan, critical step, dokumentasi, dan pengambilan keputusan.',
                ]), $this->practitionerInstruction()),
                $this->page('F. Prioritas dan Kelayakan', [
                    $this->q('S03-F01', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 5 scene prioritas:', $this->scenes(), ['max_selections' => 5]),
                    $this->q('S03-F02', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 5 fitur prioritas:', $this->features(), ['max_selections' => 5]),
                    $this->q('S03-F03', SurveyQuestion::TYPE_LONG_TEXT, 'Dalam kondisi seperti apa VR cocok digunakan untuk mendukung pembelajaran CPOB?'),
                    $this->q('S03-F04', SurveyQuestion::TYPE_LONG_TEXT, 'Dalam kondisi seperti apa VR tidak cocok digunakan atau perlu dilengkapi dengan metode pembelajaran lain?'),
                    $this->q('S03-F05', SurveyQuestion::TYPE_LONG_TEXT, 'Dukungan apa yang diperlukan agar PharmVR tetap relevan dan dapat diterapkan dalam pembelajaran?'),
                    $this->q('S03-F06', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah Bapak/Ibu memiliki rekomendasi lain untuk pengembangan PharmVR?'),
                ], $this->practitionerInstruction()),
            ],
        ];
    }

    private function intro(string $purpose, string $duration, string $contact): array
    {
        return [
            'intro_title' => 'Informasi Penelitian dan Persetujuan',
            'intro_text' => $purpose."\n\nEstimasi waktu: {$duration}.\nKontak peneliti: {$contact}.",
            'estimated_duration' => $duration,
            'privacy_statement' => 'Jawaban dijaga kerahasiaannya dan digunakan hanya untuk tujuan penelitian. Identitas tidak diwajibkan; gunakan kode responden/pseudonim bila linkage diperlukan.',
            'respondent_instruction' => 'Partisipasi bersifat sukarela. Anda dapat berhenti kapan saja tanpa konsekuensi. Pilih jawaban sesuai pengalaman atau pandangan Anda.',
            'consent_text' => 'Saya telah membaca dan memahami informasi penelitian di atas.',
            'require_consent_before_start' => true,
        ];
    }

    private function q(string $key, string $type, string $label, ?array $options = null, ?array $settings = null, bool $required = false): array
    {
        return ['key' => $key, 'type' => $type, 'label' => $label, 'options' => $options, 'settings' => $settings, 'required' => $required || $type === SurveyQuestion::TYPE_CONSENT];
    }

    private function page(string $title, array $questions, ?string $description = null): array
    {
        return ['title' => $title, 'description' => $description, 'questions' => $questions];
    }

    private function scale(array $labels): array
    {
        return ['scale' => array_keys($labels), 'scale_labels' => $labels];
    }

    private function scaled(string $prefix, array $items, array $settings): array
    {
        return collect($items)->map(fn (string $label, string $suffix): array => $this->q($prefix.$suffix, SurveyQuestion::TYPE_LIKERT, $label, null, $settings))->values()->all();
    }

    private function open(string $prefix, array $items, array $probes = []): array
    {
        return collect($items)->map(fn (string $label, string $suffix): array => $this->q(
            $prefix.$suffix,
            SurveyQuestion::TYPE_LONG_TEXT,
            $label,
            null,
            isset($probes[$suffix]) ? ['interviewer_probe' => $probes[$suffix]] : null,
        ))->values()->all();
    }

    private function practitionerInstruction(): string
    {
        return 'Pertanyaan utama dapat diperdalam dengan probe; jawaban tetap dicatat sebagai data wawancara, bukan skor Likert.';
    }

    private function scenes(): array
    {
        return ['Orientasi CPOB dan fasilitas', 'Higiene/gowning/masuk area', 'Alur personel/material', 'Gudang dan dispensing/penimbangan', 'Pencampuran/granulasi/pengeringan', 'Kompresi/coating', 'Pengemasan', 'In-process control/QC', 'Dokumentasi batch/line clearance', 'Penyimpangan/tindakan tepat'];
    }

    private function features(): array
    {
        return ['Tutorial/demonstrasi', 'Mode latihan mandiri', 'Petunjuk kontekstual/scaffolding', 'Umpan balik kesalahan', 'Skenario kesalahan/penyimpangan', 'Checklist', 'Pengulangan', 'Ringkasan progres', 'Narasi/subtitle'];
    }
}
