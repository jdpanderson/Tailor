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
    /**
     * The twig instance which will serve to render templates.
     *
     * @var Twig
     */
    private $twig;

    /**
     * @param Twig $twig The twig instance for rendering templates.
     * @param mixed $options An array of options controlling template rendering.
     */
    public function __construct(Twig_Environment $twig, $templates = [], $destdir = '.')
    {
        $this->twig = $twig;
        $this->templates = $templates;
        $this->destdir = $destdir;
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
