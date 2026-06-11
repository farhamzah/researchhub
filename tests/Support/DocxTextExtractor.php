<?php

namespace Tests\Support;

use RuntimeException;
use ZipArchive;

class DocxTextExtractor
{
    /**
     * @param  array<int, string>  $parts
     */
    public function text(string $content, array $parts = ['word/document.xml']): string
    {
        $xml = $this->xml($content, $parts);
        $text = preg_replace('/<[^>]+>/', ' ', $xml) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @param  array<int, string>  $parts
     */
    public function xml(string $content, array $parts = ['word/document.xml']): string
    {
        $path = tempnam(storage_path('framework/cache'), 'docx-qa-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX QA file.');
        }

        file_put_contents($path, $content);

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            unlink($path);

            throw new RuntimeException('Unable to open generated DOCX for QA.');
        }

        $xml = '';
        foreach ($parts as $part) {
            $partXml = $zip->getFromName($part);
            if ($partXml !== false) {
                $xml .= "\n".$partXml;
            }
        }

        $zip->close();
        unlink($path);

        if ($xml === '') {
            throw new RuntimeException('DOCX QA could not find requested XML parts.');
        }

        return $xml;
    }
}
