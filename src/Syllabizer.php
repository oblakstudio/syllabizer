<?php

declare(strict_types=1);

namespace Oblak;

use Stringable;

/**
 * Splits a Serbian word into syllables (podela reči na slogove).
 *
 * Supports both Serbian Latin and Cyrillic input. In Latin, the digraphs
 * lj / nj / dž (and their case variants) are treated as a single consonant
 * and are never split. Syllabic R (slogotvorno r) is recognised as its own
 * syllable nucleus.
 *
 * Rules follow the pedagogical description at
 * https://srednjeskole.edukacija.rs/srpski-jezik/gramatika/podela-reci-na-slogove
 */
class Syllabizer
{
    /** Vowels — primary syllable carriers. */
    private const VOWELS = [ 'a', 'e', 'i', 'o', 'u', 'а', 'е', 'и', 'о', 'у' ];

    /** Approximants (j l lj r v) — attract the preceding consonant into the onset. */
    private const APPROXIMANTS = [ 'j', 'l', 'lj', 'r', 'v', 'ј', 'л', 'љ', 'р', 'в' ];

    /** Sonants (j l lj m n nj r v) — sonant+sonant splits between them. */
    private const SONANTS = [ 'j', 'l', 'lj', 'm', 'n', 'nj', 'r', 'v', 'ј', 'л', 'љ', 'м', 'н', 'њ', 'р', 'в' ];

    /** Plosives (p b t d k g) — plosive+non-approximant splits between them. */
    private const PLOSIVES = [ 'p', 'b', 't', 'd', 'k', 'g', 'п', 'б', 'т', 'д', 'к', 'г' ];

    /** The R sound, in both scripts — candidate for syllabic R. */
    private const R = [ 'r', 'р' ];

    /**
     * Split a Serbian word into an ordered list of its syllables.
     *
     * Joining the returned syllables reproduces the original input exactly.
     *
     * @param  string|Stringable $word The word to syllabize.
     * @return list<string>
     */
    public function syllabize(string|Stringable $word): array
    {
        $word = (string) $word;

        if ('' === \trim($word)) {
            return [];
        }

        $tokens = $this->findTokens($word);
        $nuclei = $this->findNuclei($tokens);

        // No vowel and no syllabic R — return the chunk unsplit.
        if ([] === $nuclei) {
            return [ $word ];
        }

        return $this->cut($tokens, $nuclei);
    }

    /**
     * Split a Serbian word into syllables and join them with a separator.
     *
     * @param  string|Stringable $word The word to syllabize.
     * @param  string $separator Optional. Separator to join syllables. Default is a hyphen ("-").
     * @return string The syllabized word with syllables joined by the separator.
     *
     * @see syllabize()
     */
    public function tokenize(string|Stringable $word, string $separator = "-"): string
    {
        return \implode($separator, $this->syllabize($word));
    }

    /**
     * Split a word into letter-tokens. Latin digraphs (lj/nj/dž, any case)
     * collapse into one token; every other Unicode grapheme is its own token.
     *
     * @return list<string>
     */
    private function findTokens(string $word): array
    {
        // Match a Latin digraph (case-insensitively) OR any single character.
        // \X matches a full grapheme so combining marks stay with their base letter.
        \preg_match_all('/lj|nj|dž|\X/iu', $word, $matches);

        return $matches[0];
    }

    /**
     * Indices of tokens that act as syllable nuclei: every vowel, plus any R
     * that carries a syllable (no vowel neighbour — i.e. between consonants or
     * word-initial before a consonant).
     *
     * @param  list<string> $tokens
     * @return list<int>
     */
    private function findNuclei(array $tokens): array
    {
        $nuclei = [];

        foreach ($tokens as $i => $token) {
            if ($this->isVowel($token)) {
                $nuclei[] = $i;
                continue;
            }

            if (!$this->isSyllabicR($tokens, $i)) {
                continue;
            }

            $nuclei[] = $i;
        }

        return $nuclei;
    }

    /**
     * An R is syllabic when it has no adjacent vowel: the previous token is a
     * consonant (or absent, at word start) and the next is a consonant (or absent).
     *
     * @param list<string> $tokens
     */
    private function isSyllabicR(array $tokens, int $i): bool
    {
        if (!$this->inSet($tokens[$i], self::R)) {
            return false;
        }

        $prev = $tokens[$i - 1] ?? null;
        $next = $tokens[$i + 1] ?? null;

        $prevBlocks = null !== $prev && $this->isVowel($prev);
        $nextBlocks = null !== $next && $this->isVowel($next);

        return !$prevBlocks && !$nextBlocks;
    }

    /**
     * Build syllable strings: distribute consonant runs around the nuclei and
     * concatenate the original token substrings so output rejoins to the input.
     *
     * @param  list<string> $tokens
     * @param  list<int>    $nuclei
     * @return list<string>
     */
    private function cut(array $tokens, array $nuclei): array
    {
        // Boundary $i means: a cut falls before token index $i.
        // Onset of the first syllable: everything before the first nucleus.
        $boundaries = [];

        for ($n = 0; $n < \count($nuclei) - 1; $n++) {
            $a = $nuclei[$n];
            $b = $nuclei[$n + 1];

            // Consonant cluster strictly between the two nuclei.
            $cluster = \array_slice($tokens, $a + 1, $b - $a - 1);
            $keep = $this->clusterSplit($cluster); // how many consonants stay with A

            $boundaries[] = $a + 1 + $keep;
        }

        // Slice the token list at the computed boundaries and join each chunk.
        $syllables = [];
        $start = 0;

        foreach ($boundaries as $boundary) {
            $syllables[] = \implode('', \array_slice($tokens, $start, $boundary - $start));
            $start = $boundary;
        }

        $syllables[] = \implode('', \array_slice($tokens, $start));

        return $syllables;
    }

    /**
     * Decide how many consonants of a cluster stay with the preceding syllable
     * (the remainder becomes the onset of the following syllable).
     *
     * The cluster is scanned left to right and cut after the first consonant
     * whose pair with its successor forces a boundary:
     *   sonant + sonant            -> split between them (e.g. or-la, tram-vaj)
     *   plosive + non-approximant  -> split between them (e.g. lop-ta, sred-stvo)
     * If no pair forces a boundary the whole cluster opens the next syllable
     * (open syllable, e.g. la-sta, je-dva, sve-tlost). A single consonant
     * therefore always joins the following syllable (V-CV).
     *
     * @param list<string> $cluster
     */
    private function clusterSplit(array $cluster): int
    {
        $len = \count($cluster);

        for ($i = 0; $i < $len - 1; $i++) {
            if ($this->forcesBoundary($cluster[$i], $cluster[$i + 1])) {
                return $i + 1; // keep consonants 0..$i with the preceding syllable
            }
        }

        return 0; // entire cluster becomes the next syllable's onset
    }

    /**
     * Whether a syllable boundary must fall between two adjacent consonants:
     * sonant + sonant, or plosive + non-approximant.
     */
    private function forcesBoundary(string $first, string $second): bool
    {
        if ($this->inSet($first, self::SONANTS) && $this->inSet($second, self::SONANTS)) {
            return true;
        }

        return $this->inSet($first, self::PLOSIVES) && !$this->inSet($second, self::APPROXIMANTS);
    }

    private function isVowel(string $token): bool
    {
        return $this->inSet($token, self::VOWELS);
    }

    /**
     * Case-insensitive membership test against a lowercase set.
     *
     * @param list<string> $set
     */
    private function inSet(string $token, array $set): bool
    {
        return \in_array(\mb_strtolower($token), $set, true);
    }
}
