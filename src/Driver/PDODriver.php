<?php

namespace Tailor\Driver;

use Tailor\Model\Table;

/**
 * A base set of functionality common to PDO-based drivers.
 */
abstract class PDODriver extends BaseDriver
{    /**
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
            self::OPT_OPTIONS => 'An associative array of PDO Driver options',
        ];
    }

	/**
     * The standard constructor for all drivers.
     *
     * @param mixed[] $opts An associative array of driver options.
     */
    public function __construct(array $opts)
    {
         if (isset($opts[self::OPT_PDO]) && $opts[self::OPT_PDO] instanceof PDO) {
            $pdo = $opts[self::OPT_PDO];
        } elseif (!empty($opts[self::OPT_DSN])) {
            $user = empty($opts[self::OPT_USERNAME]) ? null : $opts[self::OPT_USERNAME];
            $pass = empty($opts[self::OPT_PASSWORD]) ? null : $opts[self::OPT_PASSWORD];
            $pdo = new PDO($opts[self::OPT_DSN], $user, $pass);
        } else {
            throw new DriverException("Unable to connect to database: No DSN available.");
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->queryRunner = new PDORunner($pdo);
    }
}