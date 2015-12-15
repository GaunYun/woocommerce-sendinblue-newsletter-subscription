<?php
/**
 * Model class <i>SIB_Model_Country</i> represents country code
 * @package SIB_Model
 */

class SIB_Model_Country
{
    /**
     * Tab table name
     */
    const table_name = 'sib_model_country';

    /** Create Table */
    public static function create_table()
    {
        global $wpdb;
        // create list table
        $creation_query =
            'CREATE TABLE IF NOT EXISTS ' . self::table_name . ' (
			`id` int(20) NOT NULL AUTO_INCREMENT,
			`iso_code` varchar(255),
            `call_prefix` int(10),
            PRIMARY KEY (`id`)
			);';
        $result = $wpdb->query( $creation_query );

        return $result;
    }

    /**
     * Remove table
     */
    public static function remove_table()
    {
        global $wpdb;
        $query = 'DROP TABLE IF EXISTS ' . self::table_name . ';';
        $wpdb->query($query);
    }

    /**
     * Get data by id
     * @param $id
     */
    public static function get_prefix($code)
    {
        global $wpdb;
        $query = 'select call_prefix from ' . self::table_name . ' where iso_code="' . $code . '";';
        $results = $wpdb->get_var($query);

        if($results != null)
            return $results;
        else
            return false;
    }

    /** Add record */
    static function add_record($iso_code, $call_prefix)
    {
        global $wpdb;

        $query = 'INSERT INTO ' .  self::table_name  . ' ';
        $query .= '(iso_code,call_prefix) ';
        $query .= "VALUES ('{$iso_code}','{$call_prefix}');";

        $wpdb->query( $query );

        return true;

    }

    public static function Initialize($data){
        foreach($data as $code=>$prefix){
            self::add_record($code, $prefix);
        }
    }

}
