<?php

namespace Tailor\Console;

use Tailor\Driver\Driver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TranslateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('translate')
            ->setDescription('Translate from one schema format/provider to another')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'The source of schema information'
            )
            ->addArgument(
               'destination',
               InputArgument::REQUIRED,
               'The the destination for the schema'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($src = $this->getDriver($input->getArgument('source')))) {
            $output->writeln("<error>No known driver for source {$input->getArgument('source')}</error>");
        }
        if (!($dst = $this->getDriver($input->getArgument('destination')))) {
            $output->writeln("<error>No known driver for destination {$input->getArgument('destination')}</error>");
        }

        if (!$dst || !$src) {
            return 1; /* Error, in console terms. */
        }

        $this->copyAll($src, $dst);
    }

    protected function getDriver($source)
    {
        if (substr($source, -5) === '.json') {
            return new JSONDriver($source);
        } elseif (substr($source, 0, 6) === 'mysql:') {
            $pdo = new PDO($source);
            return new MySQLDriver($pdo);
        }

        return null;
    }

    protected function copyAll(Driver $src, Driver $dst)
    {
        foreach ($src->getDatabaseNames() as $db) {
            $dst->createDatabase($db);
            foreach ($src->getSchemaNames($db) as $schema) {
                $dst->createSchema($db, $schema);
                foreach ($src->getTableNames($db, $schema) as $table) {
                    $dst->setTable($db, $schema, $src->getTable($db, $schema));
                }
            }
        }
    }
}
