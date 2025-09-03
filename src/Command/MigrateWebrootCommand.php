<?php

declare(strict_types=1);

namespace Mautic\Composer\Plugin\MigrateWebroot\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateWebrootCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('mautic:migrate-webroot')
            ->setDescription('Renames docroot/ to public/ and updates composer.json and config/local.php references');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->validatePrerequisites($output);
            $this->renameWebrootDirectory($output);
            $this->patchComposerJsonFile($output);
            $this->patchLocalConfigFile($output);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Migration completed.</info>');
        return Command::SUCCESS;
    }

    private function validatePrerequisites(OutputInterface $output): void
    {
        $errors = [];

        // Folders
        if (!is_dir('docroot')) {
            $errors[] = "Directory docroot/ not found (already migrated?).";
        }
        if (is_dir('public')) {
            $errors[] = "Directory public/ already exists (already migrated?).";
        }

        // composer.json (exists + rw)
        if (!is_file('composer.json')) {
            $errors[] = 'composer.json not found.';
        } else {
            if (!is_readable('composer.json')) {
                $errors[] = 'composer.json is not readable.';
            }
            if (!is_writable('composer.json')) {
                $errors[] = 'composer.json is not writable.';
            }
        }

        // config/local.php (if present, must be rw)
        if (is_file('config/local.php')) {
            if (!is_readable('config/local.php')) {
                $errors[] = 'config/local.php is not readable.';
            }
            if (!is_writable('config/local.php')) {
                $errors[] = 'config/local.php is not writable.';
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException("Prerequisite checks failed:\n- ".implode("\n- ", $errors));
        }

        $output->writeln('<info>Prerequisites OK.</info>');
    }

    private function renameWebrootDirectory(OutputInterface $output): void
    {
        $oldDirectory = 'docroot';
        $newDirectory = 'public';

        if (!is_dir($oldDirectory)) {
            throw new \RuntimeException("Directory {$oldDirectory}/ not found. Already migrated? Exiting.");
        }
        if (is_dir($newDirectory)) {
            throw new \RuntimeException("Directory {$newDirectory}/ already exists. Already migrated? Exiting.");
        }

        if (!@rename($oldDirectory, $newDirectory)) {
            throw new \RuntimeException("Failed to rename {$oldDirectory}/ to {$newDirectory}/.");
        }

        $output->writeln("Renamed <comment>{$oldDirectory}/</comment> → <comment>{$newDirectory}/</comment>");
    }

    private function patchComposerJsonFile(OutputInterface $output): void
    {
        $file = 'composer.json';
        if (!is_file($file)) {
            throw new \RuntimeException('composer.json not found.');
        }

        $backup = $file.'.backup';
        if (!@copy($file, $backup)) {
            throw new \RuntimeException('Failed to create composer.json.backup.');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException('Failed to read composer.json.');
        }

        $patterns = [
            '/\"web-root\"\s*:\s*\"docroot\//',
            '/\"docroot\/app\"\s*:\s*\[/',
            '/\"docroot\/plugins\/\{\$name\}\"\s*:\s*\[/',
            '/\"docroot\/themes\/\{\$name\}\"\s*:\s*\[/',
            '/"MauticPlugin\\\\\\\\":\s*"docroot\/plugins\//',
        ];

        $replacements = [
            '"web-root": "public/',
            '"public/app": [',
            '"public/plugins/{$name}": [',
            '"public/themes/{$name}": [',
            '"MauticPlugin\\\\\\\\": "public/plugins/',
        ];

        $patched = preg_replace($patterns, $replacements, $content);
        if ($patched === null) {
            throw new \RuntimeException('Regex replace failed for composer.json.');
        }

        if (file_put_contents($file, $patched) === false) {
            throw new \RuntimeException('Failed to write updated composer.json.');
        }

        $output->writeln("Patched <comment>composer.json</comment> (backup: <comment>{$backup}</comment>)");
    }

    private function patchLocalConfigFile(OutputInterface $output): void
    {
        $file = 'config/local.php';
        if (!is_file($file)) {
            $output->writeln('Skipped config/local.php - file not found.');
            return;
        }

        $backup = $file.'.backup';
        if (!@copy($file, $backup)) {
            throw new \RuntimeException('Failed to create config/local.php.backup.');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException('Failed to read config/local.php.');
        }

        // Replace ... 'some_key' => '.../docroot/media...' ...  with .../public/media...
        $pattern = "/('([^']+)'\s*=>\s*')(.*?)\/docroot\/media(.*?')/s";
        $replacement = "\\1\\3/public/media\\4";
        $patched = preg_replace($pattern, $replacement, $content);

        if ($patched === null) {
            throw new \RuntimeException('Regex replace failed for config/local.php.');
        }

        if (file_put_contents($file, $patched) === false) {
            throw new \RuntimeException('Failed to write updated config/local.php.');
        }

        $output->writeln("Patched <comment>config/local.php</comment> (backup: <comment>{$backup}</comment>)");
    }
}
