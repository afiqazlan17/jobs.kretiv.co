<?php

namespace Tests\Unit;

use App\Support\NoteSanitizer;
use PHPUnit\Framework\TestCase;

class NoteSanitizerTest extends TestCase
{
    public function test_it_strips_script_tags(): void
    {
        $clean = NoteSanitizer::clean('<p>Hello</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('Hello', $clean);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $clean = NoteSanitizer::clean('<p onmouseover="alert(1)">Hover me</p>');

        $this->assertStringNotContainsString('onmouseover', $clean);
    }

    public function test_it_strips_javascript_uri_links(): void
    {
        $clean = NoteSanitizer::clean('<a href="javascript:alert(1)">click</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_it_drops_img_tags(): void
    {
        $clean = NoteSanitizer::clean('<p>Note</p><img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_it_keeps_allowed_formatting(): void
    {
        $clean = NoteSanitizer::clean('<p><strong>Bold</strong> and <em>italic</em> and <span style="color: rgb(230, 0, 0);">red</span></p><ul><li>item</li></ul>');

        $this->assertStringContainsString('<strong>Bold</strong>', $clean);
        $this->assertStringContainsString('<em>italic</em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>item</li>', $clean);
    }
}
