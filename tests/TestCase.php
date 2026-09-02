<?php

declare(strict_types=1);

namespace Jiannius\Playbook\Tests;

use Jiannius\Playbook\PlaybookServiceProvider;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\GuidelineConfig;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?string $fakeProject = null;

    protected function getPackageProviders($app): array
    {
        return [
            BoostServiceProvider::class,
            PlaybookServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        if ($this->fakeProject !== null && is_dir($this->fakeProject)) {
            exec('rm -rf '.escapeshellarg($this->fakeProject));
        }

        parent::tearDown();
    }

    /**
     * Build a throwaway project directory and point the app at it, so Boost's
     * base_path()-driven discovery reads our fixture instead of the workbench.
     *
     * @param  array<string, mixed>|null  $boostJson  null writes no boost.json at all
     */
    protected function fakeProject(?array $boostJson, ?string $claudeMd = null, bool $installSkills = true): string
    {
        $dir = sys_get_temp_dir().'/playbook-test-'.bin2hex(random_bytes(6));
        mkdir($dir.'/vendor/jiannius', 0755, true);

        file_put_contents(
            $dir.'/composer.json',
            json_encode(['require-dev' => ['jiannius/playbook' => '*']], JSON_PRETTY_PRINT)
        );

        // Point at the real package so Boost discovers the real guidelines.
        symlink(dirname(__DIR__), $dir.'/vendor/jiannius/playbook');

        if ($boostJson !== null) {
            file_put_contents($dir.'/boost.json', json_encode($boostJson, JSON_PRETTY_PRINT));
        }

        if ($claudeMd !== null) {
            file_put_contents($dir.'/CLAUDE.md', $claudeMd);
        }

        if ($installSkills) {
            $this->installSkills($dir);
        }

        $this->app->setBasePath($dir);
        $this->fakeProject = $dir;

        return $dir;
    }

    /**
     * Copy the packaged skills into .claude/skills/ the way boost:update would, so a
     * fixture testing the guidelines half is not also failing the skills half.
     *
     * @return array<string, string> skill name => the installed SKILL.md path
     */
    protected function installSkills(string $dir, ?string $contents = null): array
    {
        $installed = [];

        foreach (glob(dirname(__DIR__).'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $source) {
            $name = basename($source);
            $target = $dir.'/.claude/skills/'.$name;

            is_dir($target) || mkdir($target, 0755, true);
            file_put_contents($target.'/SKILL.md', $contents ?? file_get_contents($source.'/SKILL.md'));

            $installed[$name] = $target.'/SKILL.md';
        }

        return $installed;
    }

    /**
     * The guideline text as Boost renders it — not the raw blade file, which differs.
     * Call after fakeProject(), since composition reads base_path().
     */
    protected function renderedGuidelines(): string
    {
        return $this->app->make(GuidelineComposer::class)
            ->config(new GuidelineConfig)
            ->guidelines()
            ->filter(fn (mixed $g, string $key): bool => str_starts_with($key, 'jiannius/playbook'))
            ->map(fn (mixed $g): string => trim((string) ($g['content'] ?? '')))
            ->implode("\n\n");
    }
}
