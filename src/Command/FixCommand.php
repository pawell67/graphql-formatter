<?php

declare(strict_types=1);

namespace GraphQLFormatter\Command;

use GraphQLFormatter\Config\FormatterConfig;
use GraphQLFormatter\Finder\FileFinder;
use GraphQLFormatter\Formatter\GraphQLFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FixCommand extends Command
{
    public function __construct(private readonly FormatterConfig $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('fix')->setDescription('Format .gql and .graphql files in place');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new FileFinder($this->config->paths);
        $files = $finder->find();
        $formatter = new GraphQLFormatter($this->config);
        $count = 0;
        foreach ($files as $file) {
            $original = file_get_contents($file);
            $formatted = $formatter->format($original);
            if ($original !== $formatted) {
                file_put_contents($file, $formatted);
                $output->writeln("<info>Fixed:</info> {$file}");
                $count++;
            }
        }
        $output->writeln('');
        $output->writeln("<info>Done.</info> {$count} file(s) fixed, " . count($files) . ' file(s) scanned.');

        return Command::SUCCESS;
    }
}
