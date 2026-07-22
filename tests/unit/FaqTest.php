<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\unit;

use LinkRobins\Wiki\Faq;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    #[Test]
    public function normalize_keeps_valid_pairs_and_drops_the_rest(): void
    {
        $out = Faq::normalize([
            ['question' => '  How do I install?  ', 'answer' => '  Run composer.  '],
            ['question' => '', 'answer' => 'No question.'],
            ['question' => 'No answer?', 'answer' => '   '],
            'not-an-array',
            42,
            ['question' => ['nested'], 'answer' => 'Bad question type.'],
            ['question' => 'Second valid', 'answer' => 'Yes.'],
        ]);

        $this->assertSame([
            ['question' => 'How do I install?', 'answer' => 'Run composer.'],
            ['question' => 'Second valid', 'answer' => 'Yes.'],
        ], $out);
    }

    #[Test]
    public function normalize_caps_question_length_but_not_entry_count(): void
    {
        $long = str_repeat('q', Faq::MAX_QUESTION_LENGTH + 100);
        $out = Faq::normalize([['question' => $long, 'answer' => 'A.']]);

        $this->assertSame(Faq::MAX_QUESTION_LENGTH, mb_strlen($out[0]['question']));

        // Uncapped on purpose: writers reject oversized lists loudly rather
        // than silently truncating what the editor submitted.
        $many = array_fill(0, Faq::MAX_ENTRIES + 5, ['question' => 'Q', 'answer' => 'A']);
        $this->assertCount(Faq::MAX_ENTRIES + 5, Faq::normalize($many));
    }

    #[Test]
    public function from_stored_tolerates_garbage(): void
    {
        $this->assertSame([], Faq::fromStored(null));
        $this->assertSame([], Faq::fromStored(''));
        $this->assertSame([], Faq::fromStored('not json'));
        $this->assertSame([], Faq::fromStored('"a string"'));
        $this->assertSame([], Faq::fromStored('{"question":"top-level object"}'));
    }

    #[Test]
    public function from_stored_caps_entry_count(): void
    {
        $many = json_encode(array_fill(0, Faq::MAX_ENTRIES + 5, ['question' => 'Q', 'answer' => 'A']));

        $this->assertCount(Faq::MAX_ENTRIES, Faq::fromStored($many));
    }
}
