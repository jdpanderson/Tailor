<?php

namespace Tailor\Console;

use \PDO;
use Tailor\Driver\Driver;
use Tailor\Driver\MySQLDriver;
use Tailor\Driver\JSONDriver;
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
            )->addOption(
                'src-opt',
                's',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Pass an option to the source driver'
            )->addOption(
                'dst-opt',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Pass an option to the destination driver'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $srcOpts = [];
        foreach ($input->getOption('src-opt') as $opt) {
            if (!$this->parseOption($srcOpts, $opt)) {
                $output->writeln("<warning>Source option does not appear to be in valid format. Option dropped.</warning>");
            }
        }

        if (!($src = $this->getDriver($input->getArgument('source'), $srcOpts))) {
            $output->writeln("<error>No known driver for source {$input->getArgument('source')}</error>");
        }

        $dstOpts = [];
        foreach ($input->getOption('dst-opt') as $opt) {
            if (!$this->parseOption($dstOpts, $opt)) {
                $output->writeln("<warning>Destination option does not appear to be in valid format. Option dropped.</warning>");
            }
        }

        if (!($dst = $this->getDriver($input->getArgument('destination'), $dstOpts))) {
            $output->writeln("<error>No known driver for destination {$input->getArgument('destination')}</error>");
        }

        if (!$dst || !$src) {
            return 1; /* Error, in console terms. */
        }

        $this->copyAll($src, $dst);
    }

    protected function getDriver($source, $opts)
    {
        if (substr($source, -5) === '.json') {
            return new JSONDriver($source);
        } elseif ($source === 'mysql') {
            return new MySQLDriver($opts);
        } elseif (substr($source, 0, 6) === 'mysql:') {
            $opts['dsn'] = $source;
            return new MySQLDriver($opts);
        }

        return null;
    }

    protected function copyAll(Driver $src, Driver $dst)
    {
        foreach ($src->getDatabaseNames() as $db) {
            if ($db != 'test') {
                continue;
            }
            $dst->createDatabase($db);
            foreach ($src->getSchemaNames($db) as $schema) {
                $dst->createSchema($db, $schema);
                foreach ($src->getTableNames($db, $schema) as $table) {
                    $dst->setTable($db, $schema, $src->getTable($db, $schema, $table));
                }
            }
        }
    }

    protected function parseOption(&$opts, $val)
    {
        if (!is_string($val) || strpos($val, "=") === false) {
            return false;
        }

        list($option, $val) = explode("=", $val, 2);

        $opts[$option] = $val;

        return true;
    }
}
