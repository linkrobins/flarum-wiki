<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\unit;

use LinkRobins\Wiki\Slug;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure slug rules: normalization and the reserved namespace. Uniqueness
 * (isTaken/forArticle) needs a database and is covered by the integration
 * suite.
 */
class SlugTest extends TestCase
{
    #[Test]
    public function normalize_slugifies_titles(): void
    {
        $this->assertSame('getting-started', Slug::normalize('Getting Started'));
        $this->assertSame('getting-started', Slug::normalize('  getting---started  '));
        $this->assertSame('faq-2-electric-boogaloo', Slug::normalize('FAQ 2: Electric Boogaloo!'));
    }

    #[Test]
    public function normalize_returns_null_when_nothing_survives(): void
    {
        $this->assertNull(Slug::normalize(null));
        $this->assertNull(Slug::normalize(''));
        $this->assertNull(Slug::normalize('   '));
        $this->assertNull(Slug::normalize('???'));
        // CJK doesn't transliterate to ASCII, so nothing survives...
        $this->assertNull(Slug::normalize('目录指南'));
        // ...while Cyrillic does, and keeps its slug.
        $this->assertSame('spravocnik', Slug::normalize('справочник'));
    }

    #[Test]
    public function normalize_caps_length(): void
    {
        $slug = Slug::normalize(str_repeat('very ', 100).'long');

        $this->assertNotNull($slug);
        $this->assertLessThanOrEqual(Slug::MAX_LENGTH, strlen($slug));
    }

    #[Test]
    public function reserved_slugs_are_detected(): void
    {
        // `new` is the compose route; digits would shadow id URLs.
        $this->assertTrue(Slug::isReserved('new'));
        $this->assertTrue(Slug::isReserved('123'));
        $this->assertTrue(Slug::isReserved('0'));

        $this->assertFalse(Slug::isReserved('news'));
        $this->assertFalse(Slug::isReserved('123-guide'));
        $this->assertFalse(Slug::isReserved('getting-started'));
    }
}
