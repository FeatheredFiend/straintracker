<?php

namespace App\Command;

use App\Import\ImportException;
use App\Import\StrainImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-strains', description: 'Import strains from an .xlsx or .csv laid out like the original WEED.xlsx')]
class ImportStrainsCommand extends Command
{
    public function __construct(private readonly StrainImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the spreadsheet')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without saving anything')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('file');
        if (!is_file($path)) {
            $io->error(sprintf('No file at "%s".', $path));

            return Command::FAILURE;
        }

        try {
            $report = $this->importer->import($path, basename($path), $input->getOption('dry-run'))->jsonSerialize();
        } catch (ImportException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach ($report['warnings'] as $warning) {
            $io->writeln(sprintf(' <comment>row %d</comment> %s', $warning['row'], $warning['message']));
        }
        $io->success(sprintf(
            '%s%d created, %d updated, %d skipped, %d new brands.',
            $report['dryRun'] ? '[dry run - nothing saved] ' : '',
            $report['created'],
            $report['updated'],
            $report['skipped'],
            \count($report['newBrands']),
        ));

        return Command::SUCCESS;
    }
}
