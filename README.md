<div align="center">

<h1 align="center" style="border-bottom: none; margin-bottom: 0px">Syllabizer</h1>
<h3 align="center" style="margin-top: 0px">Split serbian words into syllables (podela reči na slogove)</h3>


[![Packagist Version](https://img.shields.io/packagist/v/oblak/syllabizer?label=Release&style=flat-square)](https://packagist.org/packages/oblak/syllabizer)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/oblak/syllabizer/php?label=PHP&logo=php&logoColor=white&logoSize=auto&style=flat-square)

</div>


## Installation

You can install the package via composer:

```bash
$ composer require oblak/syllabizer
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Oblak\Syllabizer;

$syllabizer = new Syllabizer();

$syllabizer->syllabize('jednak');   // ['jed', 'nak']
$syllabizer->syllabize('tramvaj');  // ['tram', 'vaj']
$syllabizer->syllabize('pidžama');  // ['pi', 'dža', 'ma']
$syllabizer->syllabize('mačka');    // ['ma', 'čka']

// Cyrillic works just as well
$syllabizer->syllabize('сломљен');  // ['слом', 'љен']

// Syllabic R (slogotvorno r) is a nucleus of its own
$syllabizer->syllabize('brzo');     // ['br', 'zo']
$syllabizer->syllabize('rđa');      // ['r', 'đa']

// Count the syllables
count($syllabizer->syllabize('slogovnik')); // 3
```

`syllabize()` accepts a `string` or any `Stringable`, and returns an ordered `array`
of syllables. Joining the result reproduces the original word exactly:

```php
$word = 'doneti';

implode('', $syllabizer->syllabize($word)) === $word; // true
```

### Joining syllables

`tokenize()` is a convenience wrapper that returns the syllables as a single string,
joined by a separator (a hyphen by default):

```php
$syllabizer->tokenize('doneti');        // 'do-ne-ti'
$syllabizer->tokenize('сломљен');       // 'слом-љен'

// Pass any separator you like
$syllabizer->tokenize('doneti', '·');   // 'do·ne·ti'
```

## How it works

The library follows the standard pedagogical rules for Serbian syllabification:

- **Both scripts** — Latin and Cyrillic input are supported. The Latin digraphs
  `lj`, `nj` and `dž` (in any case) count as a single consonant and are never split,
  just like their Cyrillic counterparts `љ`, `њ`, `џ`.
- **Vowels carry syllables** — the number of syllables equals the number of vowels
  (`a e i o u`), plus any syllabic R.
- **Syllabic R** — an `r` with no neighbouring vowel (between consonants, or
  word‑initial before a consonant) becomes a syllable nucleus: `pr‑st`, `tr‑ka`,
  `r‑vač`.
- **Consonant clusters** — a single consonant opens the following syllable
  (`li‑va‑da`); within a cluster the boundary falls between two sonants
  (`or‑la`, `tram‑vaj`) or between a plosive and a following non‑approximant
  (`lop‑ta`, `sred‑stvo`); otherwise the whole cluster opens the next syllable
  (`la‑sta`, `je‑dva`, `sve‑tlost`).

## Testing

```bash
$ composer test
```

## Coding standards

```bash
$ composer lint      # check
$ composer lint:fix  # auto-fix
```

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
