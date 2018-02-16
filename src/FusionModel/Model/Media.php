<?php

namespace FusionModel\Model;

use FusionModel\Type\MediaType;

use Fusion\Manager;
use Fusion\Builder;
use Fusion\FieldGroup;
use Fusion\Field\Text;
use Fusion\Field\Tab;
use Fusion\Field\Textarea;

class Media extends MediaType {

    public static $post_defaults = [
        'post_title' => 'Attachment',
        'post_content' => '',
        'post_excerpt' => '',
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'guid' => '',
        'post_mime_type' => '',
        'post_parent' => 0
    ];

    public static $postType = 'post';

    public static function builder() {
        // Return the field groups
        return (new Builder());
    }

}