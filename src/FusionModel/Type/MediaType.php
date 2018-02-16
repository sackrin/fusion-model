<?php

namespace FusionModel\Type;

use Fusion\Manager;
use FusionModel\Model;

abstract class MediaType extends Model {

    /**
     * Define the column used for ID
     * Example: Post is ID, term is term_id
     * @var string
     */
    public static $idAttrName = 'ID';

    /**
     * Define the WP post type
     * Example: page, post, example
     * @var string
     */
    public static $postType = 'attachment';

    /**
     * Define the attribute defaults
     * Could be used in extensions of this class
     * @var string
     */
    public static $attrDefaults = [
        'post_title' => 'Attachment',
        'post_content' => '',
        'post_excerpt' => '',
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'guid' => '',
        'post_mime_type' => '',
        'post_parent' => 0
    ];

    /**
     * Define the field defaults
     * Could be used in extensions of this class
     * @var string
     */
    public static $fieldDefaults = [];

    /**
     * FIELD BUILDER
     * Returns the model's ACF Fusion field builder instance
     * @return mixed
     */
    abstract public static function builder();

    /**
     * Returns the expected ID for ACF.
     * Example: Post = $id, user = user_{$id}
     * @return mixed|null
     */
    public function getFieldsID() {
        // Retrieve the ACF fields ID
        return apply_filters('fusion/model/get_fields_id', $this->getID(), $this);
    }

    /**
     * LOAD MODEL ATTRIBUTES
     * Directly loads attributes from the DB
     * This avoids triggering inbuilt wordpress hooks
     * @return $this|bool
     */
    public function loadAttributes() {
        // Retrieve the wordpress db object
        global $wpdb;
        // If there is no post ID then return false
        if (!$this->getID()) { return false; }
        // Query the record within the target data table
        // This is the easiest way of retrieving an object's attributes without triggering actions
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ".static::$idAttrName." = %d", [$this->getID()]), ARRAY_A);
        // Apply any listening filters
        do_action('fusion/model/load_attributes', $this, $record);
        // Set the attributes with the output
        $this->attributes = $record;
        // Trigger the fusion actions
        do_action('fusion/model/load_attributes', $this);
        do_action('fusion/model/load_attributes_'.static::$postType, $this);
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
        // Retrieve the wordpress db object
        global $wpdb;
        // Trigger the fusion actions
        do_action('fusion/model/pre_save_attributes', $this);
        do_action('fusion/model/pre_save_attributes_'.static::$postType, $this);
        // If there is no post ID then create a new post
        if (!$this->getID()) {
            // Populate the post type
            $this->attributes = array_merge((array)static::$attrDefaults, $this->attributes);
            // Update post fields
            $pid = wp_insert_post($this->attributes, true);
            // If the result was a WordPress error object
            if (is_object($pid)) {
                // Throw an exception reporting the error
                throw new \Exception('There was an error inserting this post');
            }
            // Update the post ID
            $this->setAttribute(static::$idAttrName, $pid);
            // Load any attributes
            $this->loadAttributes();
        } // Otherwise if this is an existing object
        else {
            // We do this directly as to not trigger a save_post action
            $wpdb->update($wpdb->posts,[
                'post_content' => $this->attributes['post_content'],
                'post_title' => $this->attributes['post_title'],
                'post_name' => $this->attributes['post_name'],
                'post_excerpt' => $this->attributes['post_excerpt'],
                'post_status' => $this->attributes['post_status'],
                'post_type' => $this->attributes['post_type'],
                'guid' => $this->attributes['guid'],
                'post_mime_type' => $this->attributes['post_mime_type'],
                'post_parent' => $this->attributes['post_parent'],
            ],[static::$idAttrName => $this->getID()],['%s','%s','%s','%s','%s','%s','%s','%s','%s'], ['%d']);
        }
        // Trigger the fusion actions
        do_action('fusion/model/save_attributes', $this);
        do_action('fusion/model/save_attributes_'.static::$postType, $this);
        // Return for method chaining
        return $this;
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
        do_action('fusion/model/pre_save_'.static::$postType, $this);
        // Update the object
        $this->saveAttributes()
            ->saveFields();
        // Update the ghost flag
        $this->isGhost = false;
        // Trigger the save post action
        do_action('save_post', $this->getID(), get_post($this->getID()), false);
        // Trigger the fusion actions
        do_action('fusion/model/save', $this);
        do_action('fusion/model/save_'.static::$postType, $this);
        // Return for method chaining
        return $this;
    }

}