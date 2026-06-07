<?php

declare(strict_types=1);

namespace Oblak\Tests;

use Oblak\Syllabizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

final class SyllabizerTest extends TestCase
{
    private Syllabizer $syllabizer;

    protected function setUp(): void
    {
        $this->syllabizer = new Syllabizer();
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('latinWords')]
    #[DataProvider('cyrillicWords')]
    #[DataProvider('syllabicR')]
    #[DataProvider('digraphs')]
    public function testSyllabize(string $word, array $expected): void
    {
        $this->assertSame($expected, $this->syllabizer->syllabize($word));

        // Joining the syllables must reproduce the input exactly.
        $this->assertSame($word, implode('', $this->syllabizer->syllabize($word)));
    }

    /**
     * tokenize() joins the syllabize() output with a separator (default "-").
     */
    #[DataProvider('tokenizeWords')]
    public function testTokenizeUsesHyphenByDefault(string $word, string $expected): void
    {
        $this->assertSame($expected, $this->syllabizer->tokenize($word));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenizeWords(): iterable
    {
        yield 'doneti'  => ['doneti', 'do-ne-ti'];
        yield 'jednak'  => ['jednak', 'jed-nak'];
        yield 'pidzama' => ['pidžama', 'pi-dža-ma'];
        yield 'brzo'    => ['brzo', 'br-zo'];
        yield 'сломљен' => ['сломљен', 'слом-љен'];
    }

    public function testTokenizeAcceptsCustomSeparator(): void
    {
        $this->assertSame('do·ne·ti', $this->syllabizer->tokenize('doneti', '·'));
        $this->assertSame('jed/nak', $this->syllabizer->tokenize('jednak', '/'));
        $this->assertSame('doneti', $this->syllabizer->tokenize('doneti', ''));
    }

    public function testTokenizeSingleSyllableHasNoSeparator(): void
    {
        $this->assertSame('prst', $this->syllabizer->tokenize('prst'));
        $this->assertSame('a', $this->syllabizer->tokenize('a'));
    }

    public function testTokenizeEmptyAndWhitespaceReturnEmptyString(): void
    {
        $this->assertSame('', $this->syllabizer->tokenize(''));
        $this->assertSame('', $this->syllabizer->tokenize('   '));
    }

    public function testTokenizeAcceptsStringable(): void
    {
        $word = new class implements Stringable {
            public function __toString(): string
            {
                return 'mama';
            }
        };

        $this->assertSame('ma-ma', $this->syllabizer->tokenize($word));
    }

    /**
     * Latin examples drawn from the spec (open/closed syllables + all four
     * cluster rules).
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function latinWords(): iterable
    {
        // Open syllables (V-CV) and basic words.
        yield 'doneti'  => ['doneti', ['do', 'ne', 'ti']];
        yield 'livada'  => ['livada', ['li', 'va', 'da']];
        yield 'mama'    => ['mama', ['ma', 'ma']];
        yield 'poljana' => ['poljana', ['po', 'lja', 'na']];

        // Rule: sonant + sonant -> split between.
        yield 'orla'      => ['orla', ['or', 'la']];
        yield 'lomljiv'   => ['lomljiv', ['lom', 'ljiv']];
        yield 'slomljen'  => ['slomljen', ['slom', 'ljen']];
        yield 'tramvaj'   => ['tramvaj', ['tram', 'vaj']];
        yield 'polomljen' => ['polomljen', ['po', 'lom', 'ljen']];
        yield 'marljiv'   => ['marljiv', ['mar', 'ljiv']];

        // Rule: plosive + non-approximant -> split between.
        yield 'jednak'  => ['jednak', ['jed', 'nak']];
        yield 'lopta'   => ['lopta', ['lop', 'ta']];
        yield 'leptir'  => ['leptir', ['lep', 'tir']];
        yield 'sredstvo' => ['sredstvo', ['sred', 'stvo']];
        yield 'svetski' => ['svetski', ['svet', 'ski']];

        // Rule: second consonant is an approximant -> onset of next syllable.
        yield 'jedva'    => ['jedva', ['je', 'dva']];
        yield 'vidra'    => ['vidra', ['vi', 'dra']];
        yield 'svetlost' => ['svetlost', ['sve', 'tlost']];
        yield 'topljen'  => ['topljen', ['to', 'pljen']];

        // Rule: fricative/affricate groups -> onset of next syllable.
        yield 'lasta' => ['lasta', ['la', 'sta']];
        yield 'macka' => ['mačka', ['ma', 'čka']];
        yield 'cesce' => ['češće', ['če', 'šće']];
    }

    /**
     * Cyrillic counterparts to confirm script-agnostic behaviour.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function cyrillicWords(): iterable
    {
        yield 'донети'  => ['донети', ['до', 'не', 'ти']];
        yield 'орла'    => ['орла', ['ор', 'ла']];
        yield 'сломљен' => ['сломљен', ['слом', 'љен']];
        yield 'трамвај' => ['трамвај', ['трам', 'вај']];
        yield 'једнак'  => ['једнак', ['јед', 'нак']];
        yield 'лопта'   => ['лопта', ['лоп', 'та']];
        yield 'једва'   => ['једва', ['је', 'два']];
        yield 'ласта'   => ['ласта', ['ла', 'ста']];
        yield 'мачка'   => ['мачка', ['ма', 'чка']];
    }

    /**
     * Syllabic R: nucleus between consonants or word-initial before a consonant.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function syllabicR(): iterable
    {
        // Single-syllable words carried entirely by R.
        yield 'brz'  => ['brz', ['brz']];
        yield 'crn'  => ['crn', ['crn']];
        yield 'prst' => ['prst', ['prst']];

        // R between consonants mid-word.
        yield 'brzo'   => ['brzo', ['br', 'zo']];
        yield 'trka'   => ['trka', ['tr', 'ka']];
        yield 'drvo'   => ['drvo', ['dr', 'vo']];
        yield 'drzati' => ['držati', ['dr', 'ža', 'ti']];
        yield 'srebrn' => ['srebrn', ['sre', 'brn']];

        // Word-initial R before a consonant.
        yield 'rdja'  => ['rđa', ['r', 'đa']];
        yield 'rvac'  => ['rvač', ['r', 'vač']];
        yield 'rzati' => ['rzati', ['r', 'za', 'ti']];

        // Cyrillic syllabic R.
        yield 'брзо' => ['брзо', ['бр', 'зо']];
        yield 'рђа'  => ['рђа', ['р', 'ђа']];
    }

    /**
     * Latin digraphs lj/nj/dž (incl. case variants) are a single, unsplittable
     * consonant.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function digraphs(): iterable
    {
        yield 'volja'   => ['volja', ['vo', 'lja']];
        yield 'stanje'  => ['stanje', ['sta', 'nje']];
        yield 'pidzama' => ['pidžama', ['pi', 'dža', 'ma']];

        // Mixed / leading capitals keep the digraph intact.
        yield 'Njegov' => ['Njegov', ['Nje', 'gov']];
        yield 'Mama'   => ['Mama', ['Ma', 'ma']];
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->syllabizer->syllabize(''));
    }

    public function testWhitespaceOnlyReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->syllabizer->syllabize('   '));
    }

    public function testSingleVowel(): void
    {
        $this->assertSame(['a'], $this->syllabizer->syllabize('a'));
        $this->assertSame(['и'], $this->syllabizer->syllabize('и'));
    }

    public function testWordWithoutAnyNucleusIsReturnedWhole(): void
    {
        // No vowel and no syllabic R: returned as a single chunk.
        $this->assertSame(['bcd'], $this->syllabizer->syllabize('bcd'));
    }

    public function testAcceptsStringable(): void
    {
        $word = new class implements Stringable {
            public function __toString(): string
            {
                return 'mama';
            }
        };

        $this->assertSame(['ma', 'ma'], $this->syllabizer->syllabize($word));
    }
}
