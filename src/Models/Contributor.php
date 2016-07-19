<?php

class Contributor extends ORM
{
    /**
     * Constants for types.
     */
    const TYPE_FULL_TIME_EDITOR = 'Full-time editor';
    const TYPE_BE_CONTRIBUTOR = 'BE contributor';
    const TYPE_OTHER_EMPLOYEE = 'Other employee';
    const TYPE_CONTRIBUTOR_NETWORK = 'Contributor Network';
    const TYPE_FREELANCE_WRITER = 'Freelance Writer';
    const TYPE_FREELANCE_WRITER_BI = 'Freelance Writer - BI';

    protected $columns = [
        'id' => 'int unsigned',
        'slug' => 'varchar',
        'user_id' => 'int unsigned',
        'display_name' => 'varchar',
        'bio' => 'text',
        'image_id' => 'int unsigned',
        'redirect_url' => 'varchar',
        'position' => 'varchar',
        'start_date' => 'varchar',
        'twitter' => 'varchar',
        'email' => 'varchar',
        'favorite_entry_id' => 'int unsigned',
        'website' => 'varchar',
        'google_profile_url' => 'varchar',
        'instagram' => 'varchar',
        'pinterest' => 'varchar',
        'type' => 'varchar',
        'in_page_bio' => 'text',
        'include_in_page_bio' => 'int',
        'deleted' => 'datetime',
        'collection_id' => 'int',
    ];

    public static $TYPES = [
        self::TYPE_FULL_TIME_EDITOR,
        self::TYPE_BE_CONTRIBUTOR,
        self::TYPE_OTHER_EMPLOYEE,
        self::TYPE_CONTRIBUTOR_NETWORK,
        self::TYPE_FREELANCE_WRITER,
        self::TYPE_FREELANCE_WRITER_BI,
    ];

    protected $_belongs_to = [
        'user' => ['model' => 'user',      'foreign_key' => 'user_id'],
        'image' => ['model' => 'image',     'foreign_key' => 'image_id'],
        'thumbnail_image' => ['model' => 'image',     'foreign_key' => 'thumbnail_image_id'],
        'favorite_entry' => ['model' => 'blog_entry', 'foreign_key' => 'favorite_entry_id'],
    ];

    protected $_has_many = [
        'entries' => ['model' => 'blog_entry', 'foreign_key' => 'contributor_id', 'through' => 'blog_entry_bylines'],
        'series' => ['model' => 'blog_series', 'foreign_key' => 'contributor_id', 'through' => 'blog_series_bylines'],
    ];
    
    /**
     * Returns all the contributor types.
     *
     * @return array
     *   The array of contributor types.
     */
    public static function get_types()
    {
        return self::$TYPES;
    }

    public function url($full = false) {

        $url = sprintf('/author/%s', trim($this->slug));

        if ($full) {
            $url = sprintf('http://www.%s%s', BASE_DOMAIN, $url);
        }

        return $url;
    }

    public function is_published() {

        return is_null($this->deleted->ts);
    }

    /**
     * Counts the total number of entries this contributor has written.
     * Optionally, the status of entries being totalled can be specified.
     * If entry status is not specified, ALL entries, published and unpublished
     * will be counted.
     *
     * @param string $entry_status (default: '')
     *
     * @return int
     *   Total number of entries written.
     */
    public function count_entries_written($entry_status = '') {

        $query = $this->entries;

        if ($entry_status !== ''
            && in_array($entry_status, Entry::$STATUSES)) {
            $query->where('status', '=', $entry_status);
        }

        return $query->count_all();
    }

    /**
     * Get the contributor's
     * It returns null if the author doesn't have an image associated with.
     *
     * @return string|void
     *
     * The image Url
     */
    public function get_image() {

        return $this->image_id !== null ?  $this->image->full_src() : null;
    }

    /**
     * Return all contributors with bio and google_profile_url.
     *
     * @return Database_Result|Contributor[]
     */
    public function findAllWithBioGoogleProfileUrl()
    {
        return ORM::factory('blog_contributor')
            ->where('bio', '!=', 'null')
            ->and_where('google_profile_url', '!=', 'null')
            ->find_all();
    }
}
