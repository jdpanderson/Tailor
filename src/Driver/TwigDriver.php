<?php

namespace Tailor\Driver;

use PDO;
use PDOException;
use Twig_Environment;
use Tailor\Model\Table;

/**
 * Driver that reads and writes to a JSON file.
 */
class TwigDriver extends BaseDriver
{
    const OPT_TWIG = 'twig';
    const OPT_TEMPLATELIST = 'templatelist';
    const OPT_DESTDIR = 'destdir';

    /**
     * The twig instance which will serve to render templates.
     *
     * @var Twig
     */
    private $twig;


    /**
     * Get an associative array of options supported by the driver.
     *
     * @return string[] An array of driver options, with option as the key pointing to a description in the value.
     */
    public static function getOptions()
    {
        return [
            self::OPT_TWIG => 'The configured Twig_Environment object',
            self::OPT_TEMPLATELIST => 'A list of templates to be rendered',
            self::OPT_DESTDIR => 'The destination directory for rendered templates'
        ];
    }

    /**
     * Create a new Twig Driver
     *
     * @param mixed[] $opts An associative array of driver options.
     */
    public function __construct(array $opts = [])
    {
        parent::__construct();

        if (empty($opts[self::OPT_TWIG]) || empty($opts[self::OPT_TEMPLATELIST])) {
            throw new DriverException("Twig Driver missing required option");
        }

        $this->twig = $opts[self::OPT_TWIG];
        $this->templates = $opts[self::OPT_TEMPLATELIST];
        $this->destdir = empty($opts[self::OPT_DESTDIR]) ? getcwd() : $opts[self::OPT_DESTDIR];
    }

    /**
     * Create or update a table in a database.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @param Table $table The Table object representing what the table should become.
     * @param bool $force If false, the driver should not perform possibly destructive changes.
     * @return bool True if operations were performed successfully.
     */
    public function setTable($database, $schema, Table $table, $force = false)
    {
        $success = true;
        foreach ($this->templates as $dest => $template) {
            $output = $this->twig->render($template, [
                'database' => $database,
                'schema' => $schema,
                'table' => $table
            ]);

            $success = $success && file_put_contents($dest, $output);
        }
        return $success;
    }
}
