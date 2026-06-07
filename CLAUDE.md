# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

`oblak/syllabizer` is a small PHP library that splits a Serbian word into its
syllables (*podela reči na slogove* / syllabification). It supports both Serbian
**Latin and Cyrillic** input and recognises syllabic R (*slogotvorno r*). The whole
library is a single class, `Oblak\Syllabizer`.

## Non-Interactive Shell Commands

File operations like `cp`/`mv`/`rm` may be aliased to interactive (`-i`) mode and will hang waiting for y/n input. Always use non-interactive forms:

```bash
cp -f source dest        # rm -f file        # mv -f source dest
rm -rf directory         # cp -rf source dest
```

Other commands that may prompt: `scp`/`ssh` (`-o BatchMode=yes`), `apt-get` (`-y`), `brew` (`HOMEBREW_NO_AUTO_UPDATE=1`).

## Beads Notes

The beads backend here is **Dolt** (embedded mode, database `slogovnik`), not the JSONL-only mode. The `SessionStart` and `PreCompact` hooks in `.claude/settings.json` auto-run `bd prime`. Additional rules beyond the managed block below:

- Use `bd remember "insight"` for persistent knowledge across sessions; search with `bd memories <keyword>`. Do **NOT** create MEMORY.md files.
- Priority is `0`–`4` / `P0`–`P4` (0 = critical, 2 = medium, 4 = backlog) — not "high"/"medium"/"low".
- Do **NOT** run `bd edit` — it opens `$EDITOR` and blocks the agent. Use `bd update <id> --title/--description/--notes/--design` instead.
- Create the beads issue *before* writing code; mark it in-progress when you start.
- **No git remote is configured yet** — beads data and commits stay local. The `git push` / `bd dolt push` steps in the Session Completion workflow below apply only once a remote exists.

<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd dolt push
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
<!-- END BEADS INTEGRATION -->


## Build & Test

Requires PHP ≥ 8.1 with `ext-mbstring`. Composer + PHPUnit 10.

```bash
composer install                          # install dev deps (PHPUnit + coding standard)
composer test                             # run the full suite (alias for phpunit)
composer lint                             # phpcs (PHPCompatibility + Oblak-Slevomat + PSR12)
composer lint:fix                         # phpcbf — auto-fix coding-standard issues
vendor/bin/phpunit --filter testSyllabize # run a single test method
vendor/bin/phpunit --filter brzo          # run one data-set row by its key
```

Coding standard is configured in `phpcs.xml` and only lints `src/`. Cognitive
complexity is capped at 5 (Slevomat) — keep methods small.

## Architecture Overview

Everything lives in `src/Syllabizer.php` (`Oblak\Syllabizer`). `syllabize(string|Stringable $word): array`
returns an ordered list of syllable strings that re-joins to the exact input. It runs
a four-stage pipeline over the word:

1. **tokenize** — split into letter-tokens, collapsing Latin digraphs `lj`/`nj`/`dž`
   (any case) into a single token via `preg_match_all('/lj|nj|dž|\X/iu', …)`. Cyrillic
   `љ`/`њ`/`џ` are already single characters.
2. **classify** — case-insensitive membership tests against the script-spanning
   constant sets `VOWELS`, `APPROXIMANTS` (j l lj r v), `SONANTS` (+ m n nj), `PLOSIVES`.
3. **find nuclei** — every vowel is a nucleus; an `r`/`р` is a (syllabic-R) nucleus
   when it has no vowel neighbour. Syllable count = nucleus count.
4. **cut** — distribute each inter-nucleus consonant cluster via `clusterSplit()`,
   which scans left to right and cuts after the first pair that forces a boundary
   (sonant+sonant, or plosive+non-approximant); otherwise the whole cluster opens the
   next syllable.

The pedagogical ruleset (with worked examples) is documented in the plan file and the
docblocks; source of truth: https://srednjeskole.edukacija.rs/srpski-jezik/gramatika/podela-reci-na-slogove

## Conventions & Patterns

- **PSR-4**: `Oblak\` → `src/`, `Oblak\Tests\` → `tests/`. `declare(strict_types=1)` in every file.
- **UTF-8 everywhere**: use `mb_*` and `preg_*` with the `/u` flag; never byte-index strings. Both scripts must stay supported — when adding a sound to a constant set, add both the Latin and Cyrillic form.
- **Round-trip invariant**: `implode('', syllabize($w))` must equal `$w`. The test suite asserts this for every case; preserve it.
- **Tests**: PHPUnit attributes (`#[DataProvider]`), not annotations. Spec examples live in data providers keyed by an ASCII slug; add new linguistic cases there.
