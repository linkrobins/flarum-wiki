<?php

namespace LinkRobins\Wiki;

/**
 * Per-article FAQ entries, stored as a JSON list of {question, answer} pairs
 * on the article row, where `answer` holds the formatter's parsed
 * representation (same pipeline as the article body, so Markdown, mentions
 * and emoji work and nothing raw ever reaches the client). Normalized on
 * every read: a hand-edited row can't inject shapes the frontend doesn't
 * expect.
 */
final class Faq
{
    public const MAX_ENTRIES = 50;

    public const MAX_QUESTION_LENGTH = 500;

    /**
     * Decode the stored JSON column, defensively capped.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function fromStored(?string $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return array_slice(self::normalize(is_array($decoded) ? $decoded : []), 0, self::MAX_ENTRIES);
    }

    /**
     * Reduce a decoded list to trimmed {question, answer} pairs, dropping
     * anything malformed or empty. Deliberately uncapped: writers check the
     * count and reject oversized input loudly instead of truncating it.
     *
     * @param  array<mixed>  $raw
     * @return list<array{question: string, answer: string}>
     */
    public static function normalize(array $raw): array
    {
        $out = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = isset($entry['question']) && is_string($entry['question']) ? trim($entry['question']) : '';
            $answer = isset($entry['answer']) && is_string($entry['answer']) ? trim($entry['answer']) : '';

            if ($question === '' || $answer === '') {
                continue;
            }

            $out[] = [
                'question' => mb_substr($question, 0, self::MAX_QUESTION_LENGTH),
                'answer' => $answer,
            ];
        }

        return $out;
    }
}
