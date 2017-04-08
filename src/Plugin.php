<?php

/**
 * @package Dbmover
 * @subpackage Pgsql
 * @subpackage Procedures
 */

namespace Dbmover\Pgsql\Procedures;

use Dbmover\Procedures;
use PDO;

class Plugin extends Procedures\Plugin
{
    const REGEX = "@^CREATE (FUNCTION|PROCEDURE).*?AS.*?LANGUAGE '.*?';$@ms";

    protected function dropExistingProcedures()
    {
        $stmt = $this->loader->getPdo()->prepare("SELECT usesysid FROM pg_user WHERE usename = ?");
        $stmt->execute([$this->loader->getUser()]);
        $uid = $stmt->fetchColumn();
        // Source: http://stackoverflow.com/questions/7622908/drop-function-without-knowing-the-number-type-of-parameters
        $format = $this->loader->getPdo()->prepare(
            "SELECT format('DROP FUNCTION %s(%s) CASCADE;',
                oid::regproc,
                pg_get_function_identity_arguments(oid)) the_query
            FROM pg_proc
                WHERE proname = ?
                AND pg_function_is_visible(oid)
                AND proowner = ?");
        $existing = $this->loader->getPdo()->prepare("SELECT
                ROUTINE_TYPE routinetype,
                ROUTINE_NAME routinename
            FROM INFORMATION_SCHEMA.ROUTINES WHERE
                (ROUTINE_CATALOG = ? OR ROUTINE_SCHEMA = ?)");
        $existing->execute([$this->loader->getDatabase(), $this->loader->getDatabase()]);
        while (false !== ($routine = $existing->fetch(PDO::FETCH_ASSOC))) {
            if (!$this->loader->shouldBeIgnored($routine['routinename'])) {
                $format->execute([$routine['routinename'], $uid]);
                while ($query = $format->fetchColumn()) {
                    $this->loader->addOperation($query);
                }
            }
        }
    }
}

