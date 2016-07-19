<?php

class Tag extends Orm
{
    /**
     * @var array
     */
    protected $columns = [
        'id' => 'int unsigned',
        'tag' => 'varchar',
        'slug' => 'varchar',
        'category_id' => 'int unsigned',
        'name' => 'varchar',
        'type' => 'varchar',
        'hidden' => 'tinyint',
    ];

    /**
     * @var array
     */
    protected $_belongs_to = [
        'category' => ['model' => 'blog_category', 'foreign_key' => 'category_id'],
    ];

    /**
     * @var array
     */
    protected $_has_many = [
        'entries' => ['model' => 'blog_entry', 'foreign_key' => 'tag_id', 'through' => 'blog_entry_tags'],
    ];

    /**
     * Returns the array of tag types.
     *
     * @static
     *
     * @return array
     */
    public static function get_all_tag_types() {
        return self::$tag_types;
    }

    /**
     * Returns the array of tags that are not displayed on the web front-end.
     * These tags are used for admin tools.
     *
     * @return array
     */
    public static function get_cms_tags() {
        return array_filter(self::$tag_types, function ($type) {
            return !$type['is_displayed_web'];
        });
    }

    /**
     * Strip tag's tag and slug of unwanted characters.
     *
     * @param array $changes
     *    Array of changes to be made.
     */
    public function on_update($changes) {
        $this->tag = ucwords($this->tag);
        $this->slug = Util::tagify($this->tag);
    }

    /**
     * Counts the number of entries associated with this tag.
     *
     * @return int
     *    Number of entries associated with this tag.
     */
    public function get_entry_count() {
        return $this->entries->count_all();
    }

    /**
     * Get tag information in the form of an associative array.
     *
     * @return array
     *    Id, name and slug of this tag.
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->tag,
            'slug' => $this->slug,
        ];

    }
}
