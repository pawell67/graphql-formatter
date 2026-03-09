<?php

declare(strict_types=1);

namespace GraphQLFormatter\Command;

use GraphQLFormatter\Config\FormatterConfig;
use GraphQLFormatter\Finder\FileFinder;
use GraphQLFormatter\Formatter\GraphQLFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    public function __construct(private readonly FormatterConfig $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('check')->setDescription('Check if .gql/.graphql files are formatted (exits 1 if not)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new FileFinder($this->config->paths);
        $files = $finder->find();
        $formatter = new GraphQLFormatter($this->config);
        $unformatted = [];
        foreach ($files as $file) {
            $original = file_get_contents($file);
            if ($original !== $formatter->format($original)) {
                $unformatted[] = $file;
                $output->writeln("<error>Needs formatting:</error> {$file}");
            }
        }
        $output->writeln('');
        if ($unformatted === []) {
            $output->writeln('<info>All ' . count($files) . ' file(s) are properly formatted.</info>');

            return Command::SUCCESS;
        }
        $output->writeln('<error>' . count($unformatted) . ' of ' . count($files) . ' file(s) need formatting. Run `graphql-formatter fix` to fix them.</error>');

        return Command::FAILURE;
    }
}
