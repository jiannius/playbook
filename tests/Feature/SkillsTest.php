<?php

declare(strict_types=1);

use Laravel\Boost\Install\Agents\ClaudeCode;
use Laravel\Boost\Install\Skill;
use Laravel\Boost\Install\SkillComposer;
use Laravel\Boost\Install\SkillWriter;
use Symfony\Component\Yaml\Yaml;

/** The skills this package ships, keyed by the directory they live in. */
function shippedSkillDirs(): array
{
    $root = dirname(__DIR__, 2).'/resources/boost/skills';

    return collect(glob($root.'/*', GLOB_ONLYDIR))
        ->mapWithKeys(fn (string $dir): array => [basename($dir) => $dir])
        ->all();
}

it('ships the two repo-scoped skills', function () {
    expect(array_keys(shippedSkillDirs()))
        ->toEqualCanonicalizing(['jiannius-dev', 'jiannius-qa-tester']);
});

it('gives every skill the SKILL.md filename Boost looks for', function () {
    foreach (shippedSkillDirs() as $name => $dir) {
        expect($dir.'/SKILL.md')->toBeFile("{$name} has no SKILL.md");
    }
});

it('gives every skill frontmatter Boost can parse', function () {
    foreach (shippedSkillDirs() as $name => $dir) {
        $content = (string) file_get_contents($dir.'/SKILL.md');

        expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $content, $matches))
            ->toBe(1, "{$name} has no frontmatter block");

        $frontmatter = Yaml::parse($matches[1]);

        // Boost drops a skill outright when either key is missing — parseSkill() returns null.
        expect($frontmatter['name'] ?? null)
            ->toBe($name, "{$name} frontmatter name must match its directory");
        expect($frontmatter['description'] ?? null)
            ->toBeString()->not->toBe('');

        // The org Skills ZIP upload caps descriptions at 200 characters. Staying under it
        // keeps a skill portable to that channel without an edit.
        expect(strlen($frontmatter['description']))->toBeLessThanOrEqual(200);
    }
});

it('is discovered by Boost when the package is installed', function () {
    $this->fakeProject([
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['jiannius/playbook'],
        'mcp' => false,
        'skills' => [],
    ]);

    $discovered = $this->app->make(SkillComposer::class)->skills();

    foreach (array_keys(shippedSkillDirs()) as $name) {
        expect($discovered->has($name))->toBeTrue("Boost did not discover {$name}");

        /** @var Skill $skill */
        $skill = $discovered->get($name);

        expect($skill->package)->toBe('jiannius/playbook');
    }
});

/**
 * The load-bearing claim of shipping skills through composer: they do not stay in vendor/.
 * Boost copies each one into .claude/skills/, which is a committed path — so a teammate who
 * only ever runs `git clone`, never `composer install`, still gets the skill. If this breaks,
 * the QA tester silently loses their skill and nothing reports an error.
 *
 * The byte-for-byte assertion is deliberate, and it doubles as a guard against a real Boost
 * bug. SkillWriter::copyFile() runs every .md through MarkdownFormatter::format(), whose
 * heading rule is `/(#{1,4} .+)\n(?!\n)/m` — unanchored and not fence-aware. A shell comment
 * inside a fenced code block ("composer install  # if PHP deps changed") therefore reads as a
 * heading, and the installed copy gains a blank line the source never had. Verified against
 * laravel/boost 2.7.0.
 *
 * So: no `#` comments inside fenced code blocks in a shipped SKILL.md. Put the explanation in
 * a table beside the block instead. If someone reintroduces one, this test fails rather than
 * the drift reaching teammates' .claude/skills/ unnoticed.
 */
it('is copied out of vendor into the committed .claude/skills path', function () {
    $dir = $this->fakeProject([
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['jiannius/playbook'],
        'mcp' => false,
        'skills' => [],
    ]);

    $skills = $this->app->make(SkillComposer::class)->skills();
    $writer = new SkillWriter($this->app->make(ClaudeCode::class));

    foreach (shippedSkillDirs() as $name => $sourceDir) {
        expect($writer->write($skills->get($name)))->not->toBe(SkillWriter::FAILED);

        $written = $dir.'/.claude/skills/'.$name.'/SKILL.md';

        expect($written)->toBeFile("{$name} was not written to .claude/skills");
        expect(file_get_contents($written))->toBe(file_get_contents($sourceDir.'/SKILL.md'));
    }
});
