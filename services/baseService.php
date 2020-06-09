<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class BaseService
{
    /**
     * Retrieves a single object from the database
     *
     * @param   query              query to execute
     * @param   parameters         parameters for the query: array("param1"=>valueForParam1, ...)
     * @param   throw404           set to true if an 404 exception should be thrown if the query returns 0 rows.
     * @param   objectClass404     the name of the object's class that has not been retrieved (only for the error message)
     * @param   objectId404        the identifier of the object (only for the error message)
     *
     * @return item returns the item from the database or null if it has not been found
     */
    public static function get($query, $parameters=array(), $throw404=false, $objectClass404="object", $objectId404="Unnamed")
    {
        $tmp = DB::fetch($query, $parameters);
        if (!$tmp && $throw404) {
            throw new RestException(404, "Could not find the $objectClass404 '$objectId404'!");
        }
        return $tmp ? $tmp : null;
    }

    /**
     * Retrieves a list from the database
     *
     * @param   query              query to execute
     * @param   parameters         parameters for the query: array("param1"=>valueForParam1, ...)
     * @param   singleArrayField   if set, the result will be an array of the query's result list (only the $singleArrayField)
     *
     * @return itemList returns a list of items from the database.
     */
    public static function getList($query, $parameters=array(), $singleArrayField=null)
    {
        $tmp = DB::fetch($query, $parameters, true);
        if ($singleArrayField != null) {
            return is_array($tmp) ? array_map(function ($element) use ($singleArrayField) {
                return $element[$singleArrayField];
            }, $tmp) : false;
        }
        return is_array($tmp) ? $tmp : false;
    }

    /**
     * Counts the occurence of items
     *
     * @param query               the count-query to execute
     * @param parameters          parameters for the query: array("param1"=>valueForParam1, ...)
     * @param countField          the field that contains the count
     *
     * @return count returns the number of items according to the query, or 0 if the query failed
     */
    public static function count($query, $parameters=array(), $countField = "COUNT(*)")
    {
        $result = self::get($query, $parameters);
        return !$result || !array_key_exists($countField, $result) ? 0 : intval($result[$countField]);
    }

    /**
     * Executes a query
     *
     * @param   query             query to execute
     * @param   parameters        parameters for the query: array("param1"=>valueForParam1, ...)
     *
     * @return result returns the queries result
     */
    public static function execute($query, $parameters=array())
    {
        return DB::execute($query, $parameters);
    }

    /**
     * Creates a new entry in the database and returns the created ID
     *
     * @param   query              query to execute
     * @param   parameters         parameters for the query: array("param1"=>valueForParam1, ...)
     *
     * @return id returns the new id of the created item
     */
    public static function create($query, $parameters)
    {
        if (!DB::execute($query, $parameters)) {
            return false;
        }
        return DB::lastInsertId();
    }

    /**
     * Updates a single field of an item
     *
     * @param id the id of the item to identify it (must be the table's ID field)
     * @param table the name of the table (must only contain numbers, letters or -_)
     * @param field name of the field (must be a inside $allowedFields)
     * @param value the value to set
     * @param allowedFields an array of field names that are allowed.
     *
     * @return id returns the id of the item that has been changed
     */
    public static function update($id, $table, $field, $value, $allowedFields)
    {
        if (!in_array($field, $allowedFields)) {
            throw new RuntimeException("The field '$field' must not be set externally!");
        }
        if (preg_match("/[^0-9A-Za-z-_]/", $table)) {
            throw new RuntimeException("The tablename '$table' does contain illegal characters. Only numbers, letters and -_ are allowed as table name!");
        }
        $sql = "UPDATE $table SET `$field` = :field WHERE ID = :id;";
        $updateQuery = DB::prepare($sql);
        if (!DB::execute($updateQuery, array("id"=>$id, "field" => $value))) {
            throw new RuntimeException("Could not update field '$field' of item with the id '$id' in table '$table'.");
        }
        return $id;
    }
};
