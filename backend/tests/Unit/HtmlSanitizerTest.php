<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    public function test_it_strips_script_tags(): void
    {
        $dirty = '<p>Safe paragraph</p><script>alert("XSS")</script><p>Another safe</p>';
        $clean = $this->sanitizer->clean($dirty);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>Safe paragraph</p>', $clean);
    }

    public function test_it_strips_inline_event_handlers(): void
    {
        $dirty = '<img src="https://example.com/photo.jpg" onload="alert(1)" onerror="alert(2)" alt="Photo">';
        $clean = $this->sanitizer->clean($dirty);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $clean);
    }

    public function test_it_strips_javascript_uris(): void
    {
        $dirty = '<a href="javascript:alert(1)">Click me</a>';
        $clean = $this->sanitizer->clean($dirty);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('Click me', $clean);
    }

    public function test_it_adds_noopener_to_external_links(): void
    {
        $dirty = '<a href="https://nytimes.com">Times</a>';
        $clean = $this->sanitizer->clean($dirty);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_it_preserves_valid_editorial_markup(): void
    {
        $editorial = '<h2>Title</h2><p>Paragraph with <strong>strong</strong> and <em>emphasis</em>.</p><blockquote>Quote</blockquote>';
        $clean = $this->sanitizer->clean($editorial);
        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<strong>strong</strong>', $clean);
        $this->assertStringContainsString('<blockquote>Quote</blockquote>', $clean);
    }
}
