<?php

declare(strict_types=1);

namespace GraphQLFormatter\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PublishConfigCommand extends Command
{
    public function __construct(private readonly ?string $baseDir = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('publish-config')
            ->setDescription('Publish a graphql-formatter.php config file to your project\'s config/ directory')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing config file')
            ->addOption('target-dir', null, InputOption::VALUE_REQUIRED, 'Override the target directory (default: <base>/config)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $targetDir = $input->getOption('target-dir')
            ?? ($this->baseDir ?? getcwd()) . '/config';

        $targetDir = rtrim($targetDir, '/');
        $target = $targetDir . '/graphql-formatter.php';
        $force = (bool) $input->getOption('force');

        if (file_exists($target) && !$force) {
            $io->warning(sprintf('File [%s] already exists. Use --force to overwrite.', $target));

            return Command::FAILURE;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $source = $this->findExampleConfig();
        copy($source, $target);

        $io->info(sprintf('Copying file [%s] to [%s]', basename($source), $target));

        return Command::SUCCESS;
    }

    private function findExampleConfig(): string
    {
        // Works both from source repo and when installed as a dependency
        $candidates = [
            __DIR__ . '/../../graphql-formatter.php.example',
            __DIR__ . '/../../../pawell67/graphql-formatter/graphql-formatter.php.example',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not locate graphql-formatter.php.example');
    }
}
