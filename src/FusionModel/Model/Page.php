<?php

namespace FusionModel\Model;

use FusionModel\Type\PostType;

use Fusion\Manager;
use Fusion\Builder;
use Fusion\FieldGroup;
use Fusion\Field\Text;
use Fusion\Field\Tab;
use Fusion\Field\Textarea;

class Page extends PostType {

    public static $post_defaults = [
        'post_title' => 'Page',
        'post_content' => 'Page Details',
        'post_excerpt' => 'Page Details',
        'post_type' => 'page',
        'post_status' => 'publish'
    ];

    public static $postType = 'page';

    public static function builder() {
        // Return the field groups
        return (new Builder())
            ->addFieldGroup((new FieldGroup('page_settings', 'PAGE SETTINGS'))
                ->setPosition('acf_after_title')
                ->addLocation('post_type', static::$postType)
                ->addField(new Tab('meta','META'))
                ->addField((new Text('meta_title', 'Meta Title')))
                ->addField((new Textarea('meta_description', 'Meta Description'))->setWrapper(50))
                ->addField((new Textarea('meta_keywords', 'Meta Keywords'))
                    ->setWrapper(50)
                )
            );
    }

}