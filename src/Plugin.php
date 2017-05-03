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

abstract class Plugin extends Core\Plugin
{
    public $description = 'Dropping existing constraints...';

    public function __invoke(string $sql) : string
    {
        $stmt = $this->loader->getPdo()->prepare(
            "SELECT TABLE_NAME tbl, CONSTRAINT_NAME constr, CONSTRAINT_TYPE ctype
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE (CONSTRAINT_CATALOG = ? AND CONSTRAINT_SCHEMA = 'public') OR CONSTRAINT_SCHEMA = ?"
        );
        $stmt->execute([$this->loader->getDatabase(), $this->loader->getDatabase()]);
        while (false !== ($constraint = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (!$this->loader->shouldBeIgnored($constraint['constr'])) {
                $this->dropConstraint($constraint['tbl'], $constraint['constr'], $constraint['ctype']);
            }
        }
        if (preg_match_all("@^ALTER TABLE \S+ ADD FOREIGN KEY.*?;@ms", $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->defer($match[0]);
                $sql = str_replace($match[0], '', $sql);
            }
        }
        return $sql;
    }

    public function __destruct()
    {
        $this->description = 'Recreating constraints...';
        parent::__destruct();
    }

    protected abstract function dropConstraint(string $table, string $constraint, string $type);
}

