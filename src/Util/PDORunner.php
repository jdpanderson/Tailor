<?php

namespace Tailor\Util;

use \PDO;

/**
 * Simple wrapper to run PDO statements.
 *
 * This class is used to enhance testability by de-coupling some drivers from PDO
 */
class PDORunner
{
    /**
     * The PDO handle to be
     *
     * @var PDO
     */
    private $pdo;

    /**
     * Build a PDORunner
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        if (empty($params)) {
            return $this->pdo->exec($query);
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->rowCount();
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
        if (empty($params)) {
            $stmt = $this->pdo->query($query);
        } else {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
        }

        return $stmt->fetchAll($fetchMode);
    }

    /**
     * Pass through (some of) the PDO::quote method.
     *
     * @param string $string A string to be quoted, according to the rules of PDO::quote.
     * @return string A quoted version of the string parameter, or false on failure.
     */
    public function quote($string)
    {
        return $this->pdo->quote($string);
    }
}
