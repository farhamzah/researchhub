<?php

namespace Tests\Unit;

use App\Modules\ResearchLinks\Services\ResearchLinkUrlSafetyService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResearchLinkUrlSafetyServiceTest extends TestCase
{
    public function test_http_and_https_urls_are_allowed_and_hosts_can_be_extracted(): void
    {
        $service = new ResearchLinkUrlSafetyService;

        $this->assertSame('https://scholar.google.com/search?q=research', $service->assertSafe('https://scholar.google.com/search?q=research'));
        $this->assertSame('http://example.test/path', $service->assertSafe(' http://example.test/path '));
        $this->assertSame('scholar.google.com', $service->host('https://scholar.google.com/search?q=research'));
    }

    #[DataProvider('dangerousUrls')]
    public function test_dangerous_url_schemes_are_rejected(string $url): void
    {
        $this->expectException(ValidationException::class);

        (new ResearchLinkUrlSafetyService)->assertSafe($url);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dangerousUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdA=='],
            'file' => ['file:///C:/Users/private/report.docx'],
            'ftp' => ['ftp://example.test/file'],
            'mailto' => ['mailto:researcher@example.test'],
            'chrome' => ['chrome://settings'],
            'about' => ['about:blank'],
            'missing host' => ['https:///missing-host'],
        ];
    }
}
