<?php

/**
 * @package Dbmover
 * @subpackage Constraints
 *
 * Drop and re-add all foreign key constraints in the database.
 */

namespace Dbmover\Constraints;

use Dbmover\Core;
use PDO;

class Plugin extends Core\Plugin
{
    private $foreignKeyConstraints = [];

    public function __invoke(string $sql) : string
    {
        $operations = [];
        $stmt = $this->loader->getPdo()->prepare(
            "SELECT TABLE_NAME tbl, CONSTRAINT_NAME constr, CONSTRAINT_TYPE ctype
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND (CONSTRAINT_CATALOG = ? OR CONSTRAINT_SCHEMA = ?)"
        );
        $stmt->execute([$this->loader->getDatabase(), $this->loader->getDatabase()]);
        while (false !== ($constraint = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (!$this->loader->shouldBeIgnored($constraint['constr'])) {
                $this->loader->addOperation(sprintf(
                    "ALTER TABLE %s DROP %s IF EXISTS %s CASCADE",
                    $constraint['tbl'],
                    $constraint['ctype'],
                    $constraint['constr']
                ));
            }
        }
        if (preg_match_all("@^ALTER TABLE \w+ ADD FOREIGN KEY.*?;@ms", $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->foreignKeyConstraints[] = $match[0];
                $sql = str_replace($match[0], '', $sql);
            }
        }
        return $sql;
    }

    public function __destruct()
    {
        foreach ($this->foreignKeyConstraints as $sql) {
            $this->loader->addOperation($sql);
        }
    }
}

