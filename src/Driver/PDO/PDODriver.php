<?php

namespace Tailor\Driver\PDO;

use PDO;
use PDOException;
use Tailor\Driver\DriverException;
use Tailor\Driver\BaseDriver;
use Tailor\Model\Table;
use Tailor\Util\PDORunner;

/**
 * A base set of functionality common to PDO-based drivers.
 */
class PDODriver extends BaseDriver
{
    /**
     * Constants for options accepted by this driver.
     */
    const OPT_PDO = 'pdo';
    const OPT_DSN = 'dsn';
    const OPT_USERNAME = 'username';
    const OPT_PASSWORD = 'password';
    const OPT_OPTIONS = 'options';

    /**
     * Get an associative array of options supported by the driver.
     *
     * @return string[] An array of driver options, with option as the key pointing to a description in the value.
     */
    public static function getOptions()
    {
        return [
            self::OPT_PDO => 'A PDO object to be passed through to the driver',
            self::OPT_DSN => 'A Data Source Name (DSN)',
            self::OPT_USERNAME => 'A username, if required by the driver',
            self::OPT_PASSWORD => 'A password, if required by the driver',
            self::OPT_OPTIONS => 'An associative array of PDO Driver options (attributes)',
        ];
    }

    /**
     * The PDORunner to be used for executing queries.
     *
     * @var PDORunner
     */
    protected $pdoRunner;

    /**
     * Get the PDO object associated with this driver
     *
     * @return PDO
     */
    public function getPDO()
    {
        return $this->pdoRunner->getPDO();
    }

    /**
     * Execute an insert/update/delete statement.
     *
     * @param string $query The query to execute, typically an INSERT/UPDATE/DELETE statement.
     * @param mixed[] $params An array of parameters to be bound to the placeholders in the statement.
     * @return int The number of affected rows, or false on error.
     */
    public function exec($query, array $params = [])
    {
        try {
            return $this->pdoRunner->exec($query, $params);
        } catch (PDOException $e) {
            // TODO: PSR logging here.
            //error_log("Exception caught executing query: {$e->getMessage()} for query {$query}");
            return false;
        }
    }

    /**
     * Execute a query, and get the associated data.
     *
     * @param string $query The query statement, typically a SELECT statement.
     * @param mixed[] $params An array of parameters to be bound to the placeholders in the query.
     * @param int $fetchMode The PDO fetch mode to use in returning results.
     * @return mixed The result of the query, as formatted by PDO according to the specified fetch mode.
     */
    public function query($query, array $params = [], $fetchMode = PDO::FETCH_ASSOC)
    {
        try {
            return $this->pdoRunner->query($query, $params, $fetchMode);
        } catch (PDOException $e) {
            // TODO: Logging here.
            //error_log("Exception caught executing query: {$e->getMessage()} for query {$query}");
            return false;
        }
    }

    /**
     * Pass through (some of) the PDO::quote method.
     *
     * @param string $string A string to be quoted, according to the rules of PDO::quote.
     * @return string A quoted version of the string parameter, or false on failure.
     */
    public function quote($string)
    {
        return $this->pdoRunner->quote($string);
    }

    /**
     * The standard constructor for all drivers.
     *
     * @param mixed[] $opts An associative array of driver options.
     */
    public function __construct(array $opts = [])
    {
        if (isset($opts[self::OPT_PDO]) && $opts[self::OPT_PDO] instanceof PDO) {
            $pdo = $opts[self::OPT_PDO];
            if (!empty($opts[self::OPT_OPTIONS])) {
                foreach ($opts[self::OPT_OPTIONS] as $attr => $value) {
                    $pdo->setAttribute($attr, $value);
                }
            }
        } elseif (!empty($opts[self::OPT_DSN])) {
            $user = empty($opts[self::OPT_USERNAME]) ? null : $opts[self::OPT_USERNAME];
            $pass = empty($opts[self::OPT_PASSWORD]) ? null : $opts[self::OPT_PASSWORD];
            $options = empty($opts[self::OPT_OPTIONS]) ? [] : $opts[self::OPT_OPTIONS];
            $pdo = new PDO($opts[self::OPT_DSN], $user, $pass, $options);
        } else {
            throw new DriverException("Unable to connect to database: No DSN available.");
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdoRunner = new PDORunner($pdo);
    }
}
