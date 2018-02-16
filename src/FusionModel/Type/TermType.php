<?php

namespace FusionModel\Type;

use Fusion\Manager;
use FusionModel\Model;

abstract class TermType extends Model {

    /**
     * Define the column used for ID
     * Example: Post is ID, term is term_id
     * @var string
     */
    public static $idAttrName = 'term_id';

    /**
     * Define the taxonomy term slug
     * Example: page, post, example
     * @var string
     */
    public static $taxonomySlug = 'category';

    /**
     * Define the attribute defaults
     * Could be used in extensions of this class
     * @var string
     */
    public static $attrDefaults = [
        'name' => 'Default Term',
        'slug' => 'default-term',
        'description' => 'Default Term Description'
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
        return apply_filters('fusion/model/get_fields_id', static::$taxonomySlug.'_'.$this->getID(), $this);
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
        $term = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->terms WHERE ".static::$idAttrName." = %d", [$this->getID()]), ARRAY_A);
        // Retrieve the relationship
        $termTaxonomy = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->term_taxonomy WHERE ".static::$idAttrName." = %d AND taxonomy = %s", [$this->getID(), static::$taxonomySlug]), ARRAY_A);
        // Prime the attributes
        $record = [
            'term_id' => $term['term_id'],
            'name' => $term['name'],
            'slug' => $term['slug'],
            'term_group' => $term['term_group'],
            'description' => $termTaxonomy['description'],
            'term_taxonomy_id' => $termTaxonomy['term_taxonomy_id'],
            'parent' => $termTaxonomy['parent'],
            'count' => $termTaxonomy['count'],
        ];
        // Apply any listening filters
        do_action('fusion/model/load_attributes', $this, $record);
        // Set the attributes with the output
        $this->attributes = $record;
        // Trigger the fusion actions
        do_action('fusion/model/load_attributes', $this);
        do_action('fusion/model/load_attributes_'.static::$taxonomySlug, $this);
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
        do_action('fusion/model/pre_save_attributes_'.static::$taxonomySlug, $this);
        // If there is no post ID then create a new post
        if (!$this->getID()) {
            // Populate the post type
            $this->attributes = array_merge((array)static::$attrDefaults, $this->attributes);
            // Insert the term
            $result = wp_insert_term($this->attributes['name'], static::$taxonomySlug, $this->attributes);
            // If the result was a WordPress error object
            if (!is_array($result) || !$result['term_id']) {
                // Throw an exception reporting the error
                throw new \Exception('There was an error inserting this post');
            }
            // Update the post ID
            $this->setAttribute(static::$idAttrName, $result['term_id']);
            // Load any attributes
            $this->loadAttributes();
        } // Otherwise if this is an existing object
        else {
            // Update the term table
            $wpdb->update($wpdb->terms,[
                'name' => $this->attributes['name'],
                'slug' => $this->attributes['slug']
            ],[static::$idAttrName => $this->getID()],['%s','%s'], ['%d']);
            // Update the term taxonomy relationship table
            $wpdb->update($wpdb->term_taxonomy,[
                'description' => $this->attributes['description']
            ],['taxonomy' => static::$taxonomySlug, static::$idAttrName => $this->getID()],['%s'], ['%s','%d']);
        }
        // Trigger the fusion actions
        do_action('fusion/model/save_attributes', $this);
        do_action('fusion/model/save_attributes_'.static::$taxonomySlug, $this);
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
        do_action('fusion/model/pre_save_'.static::$taxonomySlug, $this);
        // Update the object
        $this->saveAttributes()
            ->saveFields();
        // Update the ghost flag
        $this->isGhost = false;
        // Trigger the save post action
        do_action('edited_term', $this->getID(), $this->getAttribute('term_taxonomy_id'), $this->getAttributes());
        do_action('edited_'.static::$taxonomySlug, $this->getID(), $this->getAttribute('term_taxonomy_id'));
        // Trigger the fusion actions
        do_action('fusion/model/save', $this);
        do_action('fusion/model/save_'.static::$taxonomySlug, $this);
        // Return for method chaining
        return $this;
    }

}