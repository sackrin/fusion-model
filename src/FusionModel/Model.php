<?php

namespace FusionModel;

use Fusion\Manager;
use Underscore\Types\Arrays;

abstract class Model {

    /**
     * A simple name for this model
     * @var string
     */
    public static $label = '';

    /**
     * Define the column used for ID
     * Example: Post is ID, term is term_id
     * @var string
     */
    public static $idAttrName = 'ID';

    /**
     * Post Attributes
     * The list of post object attributes
     * @var array
     */
    public $attributes = [];

    /**
     * Define the post defaults
     * Could be used in extensions of this class
     * @var string
     */
    public static $attrDefaults;

    /**
     * ACF Fields List
     * The list of formated list values
     * @var array
     */
    public $fields;

    /**
     * IS GHOST
     * Used to determine if this model is a ghost or actual record
     * @var bool
     */
    public $isGhost = false;

    /**
     * Define the field defaults
     * Could be used in extensions of this class
     * @var string
     */
    public static $fieldDefaults = [];

    /**
     * Returns the expected ID for ACF.
     * Example: Post = $id, user = user_{$id}
     * @return mixed|null
     */
    abstract public function getFieldsID();

    /**
     * LOAD MODEL ATTRIBUTES
     * Directly loads attributes from the DB
     * This avoids triggering inbuilt wordpress hooks
     * @return $this|bool
     */
    abstract public function loadAttributes();

    /**
     * SAVE ATTRIBUTES
     * Persist attributes within the DB
     * If the model does not yet exist this will need to be called first
     * Will create a new record in DB if no ID is provided
     * @return $this
     * @throws \Exception
     */
    abstract public function saveAttributes();

    /**
     * SAVE
     * Saves both attributes and fields within the db
     * Basically a shortcut to saveAttributes and saveFields
     * Have actions to make hookable for listeners
     * @return $this
     */
    abstract public function save();

    /**
     * INIT
     * Sets up required model hooks
     * Should be called in WP init or earlier
     */
    public static function init() {
        // Register field groups
        (static::builder())->register();
    }

    /**
     * LOAD MODEL
     * Loads an existing record and returns a new instance
     * @param $pid
     * @return mixed
     */
    public static function load($pid) {
        // Retrieve the classname
        $classname = get_called_class();
        // Create a new instance and preload
        $instance = (new $classname())
            ->setAttribute(static::$idAttrName, is_object($pid) ? $pid->ID : $pid);
        // Set the ghost flag
        $instance->isGhost = false;
        // Trigger any fusion actions
        do_action('fusion/model/pre_load', $instance);
        // Load attributes and fields
        $instance->loadAttributes()
            ->loadFields();
        // Trigger any fusion action s
        do_action('fusion/model/load', $instance);
        // Return a built instance of the model
        return $instance;
    }

    /**
     * CREATE MODEL
     * Returns a newly created model instance
     * Model will be persisted within the DB upon creation
     * @return mixed
     */
    public static function create($inject=false) {
        // Retrieve the current classname
        $classname = get_called_class();
        // Create a new instance and save
        $instance = (new $classname());
        // Set the ghost flag
        $instance->isGhost = false;
        // If we are injecting values
        $instance->attributes = is_array($inject) ? $inject : [] ;
        // Trigger any fusion actions
        do_action('fusion/model/pre_create', $instance);
        // Save the model instance
        $instance->save();
        // Trigger any fusion actions
        do_action('fusion/model/create', $instance);
        // Return a built instance of the model
        return $instance;
    }

    /**
     * GHOST MODEL
     * Returns a ghosted model instance
     * Ghost means it wasn't actually created or retrieved from the DB
     * We inject the attribute and field values directly without any DB interaction
     * The model will be able to be persisted normally
     * @param $attributes
     * @param $fields
     * @return mixed
     * @throws \Exception
     */
    public static function ghost($attributes, $fields) {
        // Retrieve the current classname
        $classname = get_called_class();
        // Create a new instance and save
        $instance = (new $classname());
        // Set the ghost flag
        $instance->isGhost = true;
        // Trigger any fusion actions
        do_action('fusion/model/pre_ghost', $instance, $attributes, $fields);
        // If we are injecting values
        $instance->attributes = $attributes ;
        // Do a quick check to make sure an ID was passed
        if (!$instance->getID()) { throw new \Exception('No object is loaded'); }
        // Create the field manager and inject the field values
        // Values should be injected in KEY format
        $instance->fields = (new Manager($instance->getFieldsID(), static::builder()))->inject($fields);
        // Trigger any fusion actions
        do_action('fusion/model/ghost', $instance);
        // Return a built instance of the model
        return $instance;
    }

    /**
     * GET ID
     * Retrieves the appropriate object id
     * static::$idAttrName will need to be the name of the attribute which contains the object ID
     * @return mixed|null
     */
    public function getID() {
        // Return the object ID if there is one
        return isset($this->attributes[static::$idAttrName]) ? $this->attributes[static::$idAttrName] : null;
    }

    /**
     * GET ATTRIBUTES
     * Returns all model attributes
     * @return array
     */
    public function getAttributes() {
        // Return the value or return the default
        return apply_filters('fusion/model/attributes_get',$this->attributes, $this);
    }

    /**
     * GET ATTRIBUTE
     * Retrieves an object's attribute
     * Paths can be nested with dot notation
     * @return array
     */
    public function getAttribute($path, $default=false) {
        // Retrieve the value
        $value = Arrays::get($this->attributes, $path);
        // Return the value or return the default
        return apply_filters('fusion/model/attribute_get', $value ? $value : $default, $this);
    }

    /**
     * SET ATTRIBUTE
     * Updates an object's attribute value
     * Paths can be nested with dot notation
     * @param $path
     * @param $value
     * @return $this
     */
    public function setAttribute($path, $value) {
        // Apply any fusion filters
        $value = apply_filters('fusion/model/attribute_set', $value, $this);
        // Set the attribute list
        $this->attributes = Arrays::set($this->attributes, $path, $value);
        // Return for method chaining
        return $this;
    }

    /**
     * GET FIELD MANAGER
     * Retrieves the field manager for this model
     * @return array
     */
    public function getFieldManager() {
        // Return the field manager object
        return $this->fields;
    }

    /**
     * GET FIELDS BY NAMES
     * Retrieves all of the current field values in fieldName => value format
     * @return mixed
     * @throws \Exception
     */
    public function getFieldNames() {
        // If no post ID then throw an exception
        // We can only load fields for an actual post object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Dump all of the fields in name format
        return apply_filters('fusion/model/dump_names', $this->getFieldManager()->dumpNames(), $this);
    }

    /**
     * GET FIELDS BY KEY
     * Retrieves all of the current field values in fieldKey => value format
     * @return mixed
     * @throws \Exception
     */
    public function getFieldKeys() {
        // If no post ID then throw an exception
        // We can only load fields for an actual post object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Dump all of the fields in key format
        return apply_filters('fusion/model/dump_keys', $this->getFieldManager()->dumpKeys(), $this);
    }

    /**
     * GET FIELD
     * Retrieves a field value within the field manager
     * Paths can be nested with dot notation
     * Example getField('somegroup.somefield')
     * Example getField('somerepeater.0.somefield')
     * @param $path
     * @param bool $default
     * @param bool $formatted
     * @return mixed
     * @throws \Exception
     */
    public function getField($path, $default=false, $formatted=true) {
        // If no post ID then throw an exception
        // We can only load fields for an actual post object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Return the value or return the default
        return apply_filters('fusion/model/get_field', $this->getFieldManager()->getField($path, $default, $formatted), [$path, $default, $formatted], $this);
    }

    /**
     * SET FIELD
     * Updates a field value within the field manager
     * Paths can be nested with dot notation
     * Example setField('somegroup.somefield', 'some_value')
     * Example setField('somerepeater.0.somefield', 'some_value')
     * @param $path
     * @param $value
     * @return $this
     * @throws \Exception
     */
    public function setField($path, $value) {
        // If no post ID then throw an exception
        // We can only load fields for an actual post object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Apply any fusion filters
        //$value = apply_filters('fusion/model/get_field', $value, [$path, $value], $this);
        // Sets the field with the passed value
        $this->getFieldManager()->setField($path, $value);
        // Return for method chaining
        return $this;
    }

    /**
     * REMOVE FIELD
     * Empties a field of it's value
     * An alias to setField(field_name, null)
     * @param $path
     * @return $this
     * @throws \Exception
     */
    public function removeField($path) {
        // If no post ID then throw an exception
        // We can only load fields for an actual object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Set the field value to null
        $this->getFieldManager()->setField($path, null);
        // Return for method chaining
        return $this;
    }

    /**
     * LOAD FIELDS
     * Loads and refreshes the model with fields from the DB
     * @return $this
     * @throws \Exception
     */
    public function loadFields() {
        // If no post ID then throw an exception
        // We can only load fields for an actual object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // Apply any fusion filters
        do_action('fusion/model/pre_load_fields', $this);
        // Create a new fields collection and load from the database
        $this->fields = (new Manager($this->getFieldsID(), static::builder()))->load();
        // Apply any fusion filters
        do_action('fusion/model/load_fields', $this);
        // Return for method chaining
        return $this;
    }

    /**
     * SAVE FIELDS
     * Persists the model's field values within the DB
     * @return $this
     * @throws \Exception
     */
    public function saveFields() {
        // If no post ID then throw an exception
        // We can only load fields for an actual object
        if (!$this->getID()) { throw new \Exception('No object is loaded'); }
        // If no field manager is present
        if (!$this->getFieldManager()) {
            // Create a new fields collection
            $this->fields = (new Manager($this->getFieldsID(), static::builder()));
        }
        // Apply any fusion filters
        do_action('fusion/model/pre_save_fields', $this);
        // Trigger a field save
        $this->getFieldManager()->save();
        // Apply any fusion filters
        do_action('fusion/model/save_fields', $this);
        // Return for method chaining
        return $this;
    }

    /**
     * UUID
     * Utility method to return a UUID
     * @return string
     */
    public static function uuid() {
        // Generate a record UUID
        return strtoupper(sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,
            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        ));
    }

}