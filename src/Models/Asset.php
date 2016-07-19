<?php

class Model_Blog_Asset extends Orm
{
    /**
     * Constants for alignments.
     */
    const ALIGNMENT_CENTER = 'center';
    const ALIGNMENT_LEFT = 'left';
    const ALIGNMENT_RIGHT = 'right';

    /**
     * Constants for types.
     */
    const TYPE_IMAGE = 'IMAGE';
    const TYPE_PRODUCT = 'PRODUCT';
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_HYPERTEXT = 'HYPERTEXT';

    protected $columns = [
        'id' => 'int unsigned',
        'type' => 'varchar',
        'title' => 'varchar',
        'description' => 'text',
        'tags' => 'varchar',
        'type_id' => 'int unsigned',
        'clicktracker' => 'text',
        'impressiontracker' => 'text',
        'meta_title' => 'varchar',
        'meta_desc' => 'text',
        'no_pin_it' => 'tinyint',
        'alignment' => 'char',
        'credit' => 'text',
        'is_editable' => 'tinyint',
        'no_facebook' => 'tinyint',
    ];

    protected $_primary_key = 'id';

    protected $_belongs_to = [
        'image' => [
            'model' => 'image',
            'foreign_key' => 'type_id',
        ],
        'video' => [
            'model' => 'video',
            'foreign_key' => 'type_id',
        ],
        'hypertext' => [
            'model' => 'hypertext',
            'foreign_key' => 'type_id',
        ],
    ];

    protected $_has_many = [
        'entries' => [
            'model' => 'blog_entry',
            'through' => 'blog_entry_assets',
            'foreign_key' => 'asset_id',
        ],
        'videos' => [
            'model' => 'video',
            'foreign_key' => 'id',
        ],
    ];

    public static $ASSET_TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_PRODUCT,
        self::TYPE_VIDEO,
        self::TYPE_HYPERTEXT,
    ];

    public $loaded_products = [];

    public static function get_asset_types() {
        return self::$ASSET_TYPES;
    }
}
