<?php

namespace FusionModel;

class Query {

    public $conditions = [];

    public $joins = [];

    public $sql = [];

    public $results = [];

    public $orderbys = [];

    public $selects = [];

    public $limits = [0,15];

    public $prepares = [];

    public $having = [];

    public $count = [];

    public function __construct($offset=0,$length=15) {
        $this->selects[] = 'p.*';
        $this->conditions[] = "p.post_status = 'publish'";
    }

    protected function wpdb() {
        global $wpdb;
        return $wpdb;
    }

    protected function randStr($len=3) {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randstring = '';
        for ($i = 0; $i < $len; $i++) {
            $randstring .= $characters[rand(0, strlen($characters)-1)];
        }
        return $randstring;
    }

    public function addTypeFilter($filters, $operator='=') {
        // If a collection of values was passed
        if (is_array($filters)) {
            // Collection of values
            $collection = [];
            // Loop through each of the meta values
            foreach ($filters as $k => $value) {
                // Add to the collection
                $collection[] = "p.post_type {$operator} %s";
                // Add the meta values to the prepare list
                $this->prepares['conditions'][] = (string)$value;
            }
            // Add the select entry
            $this->conditions[] = "(".implode(' OR ', $collection).")";
        } else {
            // Add the select entry
            $this->conditions[] = "p.post_type {$operator} %s";
            // Add the meta values to the prepare list
            $this->prepares['conditions'][] = $filters;
        }
        // Return for chaining
        return $this;
    }

    public function addAttributeFilter($attributeName, $attributeValues, $operator='=') {
        // If a collection of values was passed
        if (is_array($attributeValues)) {
            // Collection of values
            $collection = [];
            // Loop through each of the meta values
            foreach ($attributeValues as $k => $value) {
                // Add to the collection
                $collection[] = "p.{$attributeName} {$operator} %s";
                // Add the meta values to the prepare list
                $this->prepares['conditions'][] = (string)$value;
            }
            // Add the select entry
            $this->conditions[] = "(".implode(' OR ', $collection).")";
        } else {
            // Add the select entry
            $this->conditions[] = "p.{$attributeName} {$operator} %s";
            // Add the meta values to the prepare list
            $this->prepares['conditions'][] = $attributeValues;
        }
        // Return for chaining
        return $this;
    }

    public function addFieldFilter($metaName, $metaValues, $operator='=') {
        // Create the meta alias
        $metaAlias = 'acf_'.$this->randStr();
        // Determine the correct meta operator to be used
        $metaOperator = stripos($metaName, '%') === false ? '=' : 'LIKE';
        // Add this field to the join
        $this->joins[] = "
		LEFT JOIN ".$this->wpdb()->postmeta." $metaAlias
          ON ($metaAlias.post_id = p.ID
            AND $metaAlias.meta_key $metaOperator '$metaName')";
        // If a collection of values was passed
        if (is_array($metaValues)) {
            // Collection of values
            $collection = [];
            // Loop through each of the meta values
            foreach ($metaValues as $k => $value) {
                // Add to the collection
                $collection[] = "{$metaAlias}.meta_value {$operator} %s";
                // Add the meta values to the prepare list
                $this->prepares['conditions'][] = (string)$value;
            }
            // Add the select entry
            $this->conditions[] = "(".implode(' OR ', $collection).")";
        } else {
            // Add the select entry
            $this->conditions[] = "{$metaAlias}.meta_value {$operator} %s";
            // Add the meta values to the prepare list
            $this->prepares['conditions'][] = $metaValues;
        }
        // Return for chaining
        return $this;
    }

    protected function getSqlJoins() {
        return implode(' ', $this->joins);
    }

    protected function getSqlSelects() {
        if (!is_array($this->selects) || count($this->selects) < 1) { return ''; }
        return implode(', ', $this->selects);
    }

    protected function getSqlConditions() {
        if (!is_array($this->conditions) || count($this->conditions) < 1) { return ''; }
        return implode(' AND ', $this->conditions);
    }

    protected function getSqlOrderBys() {
        if (!is_array($this->orderbys) || count($this->orderbys) < 1) { return ''; }
        return ' ORDER BY '.implode(', ', $this->orderbys);
    }

    protected function getSqlLimits() {
        if (!is_array($this->limits) || count($this->limits) < 1) { return ''; }
        return " LIMIT ".$this->limits[0].",".$this->limits[1]."";
    }

    protected function getSqlHaving() {
        if (!is_array($this->having) || count($this->having) < 1) { return ''; }
        return ' HAVING '.implode(' AND ', $this->having);
    }

    public function getSQLPrepares() {
        $prepares = [];
        foreach ($this->prepares as $k => $category) {
            foreach ($category as $_k => $var) {
                $prepares[] = $var;
            }
        }
        return $prepares;
    }

    public function search() {
        // Create the search SQL
        $sql = "SELECT 
		" . $this->getSqlSelects() . "
        FROM {$this->wpdb()->posts} AS p
        " . $this->getSqlJoins() . "
        WHERE " . $this->getSqlConditions() . "
        GROUP BY p.ID
        " . $this->getSqlHaving() . "
        " . $this->getSqlOrderBys() . "
        " . $this->getSqlLimits();
        // Retrieve the prepare variables
        $vars = $this->getSQLPrepares();
        // Pass the SQL through the SQL prepare method
        $this->sql['search'] = count($vars) > 0 ? $this->wpdb()->prepare($sql, $vars) : $sql;
        // Store the results
        $this->results = $this->wpdb()->get_results($this->sql['search']);
        // Return for chaining
        return $this;
    }

    public function count() {
        // Create the count SQL
        $sql = "SELECT COUNT(*) FROM (SELECT 
        " . $this->getSqlSelects() . "
        FROM {$this->wpdb()->posts} AS p
        " . $this->getSqlJoins() . "
        WHERE " . $this->getSqlConditions() . "
        GROUP BY p.ID
        " . $this->getSqlHaving() . "
          ) the_query";
        // Retrieve the prepare variables
        $vars = $this->getSQLPrepares();
        // Create the count SQL
        $this->sql['count'] = count($vars) > 0 ? $this->wpdb()->prepare($sql, $vars) : $sql;
        // Retrieve the count numbe
        $this->count = $this->wpdb()->get_var($this->sql['count']);
        // Return for chaining
        return $this;
    }

    public function execute() {
        $this->count();
        $this->search();
        return $this;
    }

    public function getResults() {
        return $this->results;
    }

    public function getFirst() {
        return is_array($this->results) && count($this->results) > 0 ? $this->results[0] : false ;
    }

    public function getLast() {
        return is_array($this->results) && count($this->results) > 0 ? $this->results[count($this->results)-1] : false ;
    }

}