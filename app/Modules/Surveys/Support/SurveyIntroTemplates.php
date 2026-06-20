<?php

namespace App\Modules\Surveys\Support;

class SurveyIntroTemplates
{
    /**
     * @return array<string, mixed>
     */
    public static function studentPharmVr(): array
    {
        return [
            'intro_title' => 'Pengantar Kuesioner Analisis Kebutuhan PharmVR',
            'intro_text' => 'Kuesioner ini bertujuan menggali kebutuhan mahasiswa farmasi terhadap media pembelajaran PharmVR pada tahap analisis kebutuhan. Tidak ada jawaban benar atau salah; jawaban terbaik adalah yang sesuai dengan pengalaman dan kebutuhan belajar Anda.',
            'estimated_duration' => '10-15 menit',
            'privacy_statement' => 'Data yang dikumpulkan bersifat rahasia dan hanya digunakan untuk analisis kebutuhan penelitian PharmVR. Identitas responden tidak ditampilkan pada laporan analisis publik.',
            'respondent_instruction' => 'Bacalah setiap pernyataan dengan cermat, lalu pilih jawaban yang paling sesuai dengan kondisi atau pendapat Anda.',
            'consent_text' => 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.',
            'require_consent_before_start' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function lecturerPharmVr(): array
    {
        return [
            'intro_title' => 'Pengantar Kuesioner Analisis Kebutuhan Dosen PharmVR',
            'intro_text' => 'Kuesioner ini bertujuan untuk menggali pandangan dosen terkait kebutuhan media pembelajaran berbasis Virtual Reality (VR) untuk mendukung pembelajaran CPOB/GMP dan farmasi industri. Dalam penelitian ini, rancangan media pembelajaran VR yang dikembangkan diberi nama PharmVR. PharmVR dirancang untuk mensimulasikan lingkungan industri farmasi, alur produksi, dokumentasi, dan prinsip CPOB/GMP secara visual dan interaktif. Kuesioner ini tidak menilai kemampuan pribadi dosen, tetapi menggali pengalaman mengajar, kesulitan pembelajaran, kesesuaian CPL/CPMK/OBE, kebutuhan fitur pembelajaran, kesiapan implementasi, serta saran pengembangan PharmVR.',
            'estimated_duration' => '10-15 menit',
            'privacy_statement' => 'Data yang dikumpulkan digunakan untuk keperluan penelitian dan pengembangan PharmVR. Identitas responden tidak akan ditampilkan dalam laporan dan hasil penelitian akan disajikan secara agregat atau disamarkan.',
            'respondent_instruction' => 'Bacalah setiap pernyataan dengan saksama. Pilih jawaban yang paling sesuai dengan pengalaman dan pendapat Bapak/Ibu. Untuk pernyataan skala Likert, gunakan skala 1 sampai 5: 1 = Sangat tidak setuju, 2 = Tidak setuju, 3 = Netral, 4 = Setuju, 5 = Sangat setuju. Pertanyaan terbuka dapat diisi sesuai masukan Bapak/Ibu.',
            'consent_text' => 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.',
            'require_consent_before_start' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function practitionerPharmVr(): array
    {
        return [
            'intro_title' => 'Pengantar Wawancara Praktisi/Ahli CPOB PharmVR',
            'intro_text' => 'Pedoman wawancara ini digunakan untuk menggali masukan dari praktisi atau ahli CPOB/GMP terkait pengembangan media pembelajaran Virtual Reality untuk farmasi industri. Dalam penelitian ini, media yang dikembangkan diberi nama PharmVR. Masukan Bapak/Ibu akan digunakan untuk memastikan bahwa scene, alur proses, istilah, risiko miskonsepsi, dan prioritas fitur dalam PharmVR sesuai dengan praktik industri dan prinsip CPOB/GMP.',
            'estimated_duration' => '20-30 menit',
            'privacy_statement' => 'Data wawancara digunakan untuk keperluan penelitian dan pengembangan PharmVR. Identitas narasumber dapat disamarkan dalam laporan sesuai kesepakatan penelitian. Hasil wawancara akan dianalisis secara tematik dan tidak digunakan untuk menilai individu.',
            'respondent_instruction' => 'Pertanyaan berikut bersifat semi-terstruktur. Pewawancara dapat menggali jawaban lebih lanjut sesuai konteks pengalaman narasumber. Jawaban dapat ditulis sebagai ringkasan, kutipan penting, atau catatan tematik.',
            'consent_text' => 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.',
            'require_consent_before_start' => true,
        ];
    }
}
