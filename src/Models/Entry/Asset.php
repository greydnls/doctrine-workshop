<?php

class Model_Blog_Entry_Asset extends Orm
{
    /**
     * Constants for types.
     */
    const TYPE_MAIN = 'MAIN';
    const TYPE_SLIDESHOW = 'SLIDESHOW';
    const TYPE_EMBEDDED = 'EMBEDDED';
    const TYPE_MAIN_TOPIC_BIG = 'MAIN_TOPIC_BIG';
    const TYPE_VIDEO_POSTER = 'VIDEO_POSTER';
    const TYPE_OPENER_ARTICLE_FULL = 'OPENER_ARTICLE_FULL'; // for scrollables
    const TYPE_OPENER_SINGLE_HERO = 'OPENER_SINGLE_HERO';
    const TYPE_OPENER_SINGLE_HERO_PT = 'OPENER_SINGLE_HERO_PT';
    const TYPE_OPENER_CIRCLE_STORY = 'OPENER_CIRCLE_STORY';
    const TYPE_OPENER_SMALL_STORY = 'OPENER_SMALL_STORY';
    const TYPE_OPENER_FULL_INTRO = 'OPENER_FULL_INTRO';

    /**
     * Constants for deprecated types.
     */
    const TYPE_MAIN_ALT = 'MAIN_ALT'; // as of 10/06/2014
    const TYPE_MAIN_HOTSPOT = 'MAIN_HOTSPOT'; // as of 10/06/2014

    protected $_belongs_to = [
        'asset' => ['model' => 'blog_asset', 'foreign_key' => 'asset_id'],
        'entry' => ['model' => 'blog_entry', 'foreign_key' => 'entry_id'],
    ];

    protected $_has_many = [
        'assets' => [
            'model' => 'blog_asset',
            'foreign_key' => 'type_id',
        ],
    ];

    protected $_sorting = ['display_order' => 'ASC', 'id' => 'ASC'];

    private static $ENTRY_ASSET_TYPES = [
        self::TYPE_MAIN,
        self::TYPE_SLIDESHOW,
        self::TYPE_EMBEDDED,
        self::TYPE_MAIN_TOPIC_BIG,
        self::TYPE_VIDEO_POSTER,
        self::TYPE_OPENER_ARTICLE_FULL,
        self::TYPE_OPENER_SINGLE_HERO,
        self::TYPE_OPENER_CIRCLE_STORY,
        self::TYPE_OPENER_SMALL_STORY,
        self::TYPE_OPENER_FULL_INTRO,

        // Deprecated types
        self::TYPE_MAIN_ALT,
        self::TYPE_MAIN_HOTSPOT,
    ];

    /**
     * Returns the entry assets type.
     *
     * @param string $type
     *   By default, if $type is not provided, this function returns all entry
     *   asset types. Otherwise, if $type is 'openers' only the opener types
     *   are returned.
     *
     * @return array
     */
    public static function get_entry_asset_types($type = null) {
        $result = self::$ENTRY_ASSET_TYPES;

        if ($type === 'openers') {
            $result = [
                self::TYPE_MAIN,
                self::TYPE_MAIN_TOPIC_BIG,
                self::TYPE_VIDEO_POSTER,
                self::TYPE_OPENER_ARTICLE_FULL,
                self::TYPE_OPENER_SINGLE_HERO,
                self::TYPE_OPENER_CIRCLE_STORY,
                self::TYPE_OPENER_SMALL_STORY,
                self::TYPE_OPENER_FULL_INTRO,

                // Deprecated types
                self::TYPE_MAIN_ALT,
                self::TYPE_MAIN_HOTSPOT,
            ];
        }

        return $result;
    }

    public function getCropCode()
    {
        return implode(',', [
            $this->crop_x,
            $this->crop_y,
            $this->crop_w,
            $this->crop_h,
        ]);
    }

    public function aspect_ratio() {

        if ($this->crop_w && $this->crop_h) {
            return $this->crop_w / $this->crop_h;
        }

        $ratio = 0;
        if ($this->asset->image->height > 0) {
            $ratio = $this->asset->image->width / $this->asset->image->height;
        }

        return $ratio;
    }
}
