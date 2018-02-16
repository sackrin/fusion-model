<?php

namespace FusionModel\Type;

use Fusion\Manager;
use FusionModel\Model;

abstract class OptionType extends Model {

    /**
     * GET ID
     * Retrieves the appropriate object id
     * static::$idAttrName will need to be the name of the attribute which contains the object ID
     * @return mixed|null
     */
    public function getID() {
        // Return the object ID if there is one
        return 'option';
    }

    /**
     * Returns the expected ID for ACF.
     * Example: Post = $id, user = user_{$id}
     * @return mixed|null
     */
    public function getFieldsID() {
        // Retrieve the ACF fields ID
        return apply_filters('fusion/model/get_fields_id', 'option', $this);
    }

    /**
     * LOAD MODEL ATTRIBUTES
     * Directly loads attributes from the DB
     * This avoids triggering inbuilt wordpress hooks
     * @return $this|bool
     */
    public function loadAttributes() {
        // Return for method chaining
        return $this;
    }

    /**
     * SAVE ATTRIBUTES
     * Persist attributes within the DB
     * If the model does not yet exist this will need to be called first
     * Will create a new record in DB if no ID is provided
     * @return $this
     * @throws \Exception
     */
    public function saveAttributes() {
        // Return for method chaining
        return $this;
    }

    /**
     * LOAD MODEL
     * Loads an existing record and returns a new instance
     * @param $pid
     * @return mixed
     */
    public static function load($pid=false) {
        // Retrieve the classname
        $classname = get_called_class();
        // Create a new instance and preload
        $instance = (new $classname());
        // Set the ghost flag
        $instance->isGhost = false;
        // Trigger any fusion actions
        do_action('fusion/model/pre_load', $instance);
        // Load attributes and fields
        $instance->loadFields();
        // Trigger any fusion actions
        do_action('fusion/model/load', $instance);
        // Return a built instance of the model
        return $instance;
    }

    /**
     * SAVE
     * Saves both attributes and fields within the db
     * Basically a shortcut to saveAttributes and saveFields
     * Have actions to make hookable for listeners
     * @return $this
     */
    public function save() {
        // Trigger the fusion actions
        do_action('fusion/model/pre_save', $this);
        do_action('fusion/model/pre_save_option', $this);
        // Update the object
        $this->saveFields();
        // Update the ghost flag
        $this->isGhost = false;
        // Trigger the fusion actions
        do_action('fusion/model/save', $this);
        do_action('fusion/model/save_option', $this);
        // Return for method chaining
        return $this;
    }

}