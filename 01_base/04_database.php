<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
class DB
{
    private static $db = null;
    
    public static function init($pdo)
    {
        self::$db = $pdo;
    }
    
    public static function prepare($query) // Returns a prepared statement based on the query
    {
        return self::$db->prepare($query);
    }

    public static function execute($preparedStatementOrSqlQuery, $param=array()) // Executes a SQL-query or a prepared Statement
    {
        if (is_string($preparedStatementOrSqlQuery)) {
            $preparedStatementOrSqlQuery = self::prepare($preparedStatementOrSqlQuery);
        }

        Log::debug("### DB: Execute Query:");
        Log::debug($preparedStatementOrSqlQuery->queryString);
        Log::debug("### DB: Execute Parameter:");
        Log::debug($param);

        if (!$preparedStatementOrSqlQuery->execute($param)) {
            Log::debug("### DB: Error Info");
            Log::debug($preparedStatementOrSqlQuery->errorInfo());
            Log::debug("### DB:Execution failed.");
            return false;
        }
        return true;
    }

    public static function lastInsertId()
    {
        return self::$db->lastInsertId();
    }

    /**
     * Fetches rows from the database using a query
     *
     * @param preparedStatementOrSqlQuery a prepared statement or query string
     * @param param                       parameters for the query
     * @param fetchAll                    if true, all rows will be fetched, otherwise only one entry will be returned
     *
     * @return result                     if the query execution false, false will be returned. Otherwise:
     *                                    if fetchAll == true, an array (may be empty) will be returned.
     *                                    if fetchAll == false, the row will be returned directly or null if no entry has been found
     */
    public static function fetch($preparedStatementOrSqlQuery, $param=array(), $fetchAll=false) // returns null if the execution failed, or the fetch is null
    {
        if (is_string($preparedStatementOrSqlQuery)) {
            $preparedStatementOrSqlQuery = self::prepare($preparedStatementOrSqlQuery);
        }

        if (!self::execute($preparedStatementOrSqlQuery, $param)) {
            return false;
        }

        Log::debug("### DB: Fetch ".(($fetchAll) ? "ALL" : "ONLY ONE")." RESULT");
        $info = null;
        if ($fetchAll) {
            $info = $preparedStatementOrSqlQuery->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $info = $preparedStatementOrSqlQuery->fetch(PDO::FETCH_ASSOC);
            $info =  ($info) ? $info : null;
        }
        Log::debug($info);
        return $info;
    }

    /**
     * Converts seconds into a SQL date
     *
     * @param seconds the seconds to convert
     *
     * @return result a string in the format Y-m-d H:i:s
     */
    public static function date($seconds)
    {
        return date("Y-m-d H:i:s", $seconds);
    }
}
