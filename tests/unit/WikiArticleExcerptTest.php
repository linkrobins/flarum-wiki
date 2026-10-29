<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\unit;

use LinkRobins\Wiki\WikiArticle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WikiArticleExcerptTest extends TestCase
{
    private function excerptOf(string $parsed, int $width = 180): string
    {
        $article = new WikiArticle();
        $article->parsed_content = $parsed;

        return $article->excerpt($width);
    }

    #[Test]
    public function excerpt_drops_formatting_from_valid_content(): void
    {
        $this->assertSame(
            'Hello bold world',
            $this->excerptOf('<r><p>Hello <STRONG><s>**</s>bold<e>**</e></STRONG> world</p></r>')
        );
    }

    #[Test]
    public function excerpt_of_empty_content_is_empty(): void
    {
        $this->assertSame('', $this->excerptOf(''));
    }

    #[Test]
    public function excerpt_falls_back_to_plain_text_for_malformed_content(): void
    {
        $this->assertSame('Hello & world', $this->excerptOf('<r><p>Hello &amp; world</p><broken'));
        $this->assertSame('', $this->excerptOf('<r><broken'));
    }

    #[Test]
    public function malformed_content_does_not_leak_libxml_state(): void
    {
        $before = libxml_use_internal_errors();
        $this->excerptOf('<r><broken');

        $this->assertSame($before, libxml_use_internal_errors());
    }
}
