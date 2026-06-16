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
            'intro_text' => 'Kuesioner ini bertujuan menggali kebutuhan dosen terkait pembelajaran CPOB/GMP berbasis PharmVR, termasuk kesesuaian dengan CPL, CPMK, OBE, assessment, monitoring, dan rencana implementasi pembelajaran.',
            'estimated_duration' => '10-15 menit',
            'privacy_statement' => 'Data digunakan hanya untuk analisis kebutuhan penelitian PharmVR dan dilaporkan secara agregat tanpa menampilkan identitas personal.',
            'respondent_instruction' => 'Mohon berikan penilaian dan masukan berdasarkan pengalaman mengajar, kebutuhan kurikulum, serta kelayakan implementasi di program studi farmasi.',
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
            'intro_text' => 'Form ini digunakan sebagai catatan wawancara terstruktur dengan praktisi atau ahli CPOB/GMP untuk menggali akurasi konten, prioritas scene, risiko miskonsepsi, dan kebutuhan industri bagi pengembangan PharmVR.',
            'estimated_duration' => '15-25 menit',
            'privacy_statement' => 'Identitas dapat ditulis menggunakan inisial. Nama perusahaan atau institusi boleh dikosongkan jika bersifat rahasia. Data digunakan hanya untuk analisis penelitian PharmVR.',
            'respondent_instruction' => 'Jawablah setiap pertanyaan secara ringkas dan substantif sesuai pengalaman praktik industri atau keahlian CPOB/GMP.',
            'consent_text' => 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.',
            'require_consent_before_start' => true,
        ];
    }
}
