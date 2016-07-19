<?php

namespace Greydnls\Workshop;

class Entry extends ORM
{
    /**
     * Constants for statuses.
     */
    const STATUS_ARCHIVED = 'ARCHIVED';
    const STATUS_EDIT = 'EDIT';
    const STATUS_PENDING = 'PENDING';
    const STATUS_PUBLISHED = 'PUBLISHED';
    const STATUS_QUEUE = 'QUEUE';
    const STATUS_REMOVED = 'REMOVED';

    /**
     * Constants for types.
     */
    const TYPE_SCROLLABLE = 'SCROLLABLE';
    const TYPE_SLIDESHOW = 'SLIDESHOW';
    const TYPE_VIDEO = 'VIDEO';

    const DEFAULT_SITE = 'US';

    /**
     * Constant for thumbnail size codes.
     */
    const THUMB_SIZE_CODES = [
        '50x',
        '75x',
        '100x',
        '150x',
        '400x400bm',
        '720x389',
        'x',
    ];

    protected $_has_many = [
        'meta' => ['model' => 'blog_entry_meta', 'foreign_key' => 'entry_id'],
        'assets' => ['model' => 'blog_entry_asset', 'foreign_key' => 'entry_id'],
        'bylines' => ['model' => 'blog_entry_byline', 'foreign_key' => 'entry_id'],
        'tags' => ['model' => 'blog_tag', 'foreign_key' => 'entry_id', 'through' => 'blog_entry_tags'],
        'entry_tags' => ['model' => 'blog_entry_tag', 'foreign_key' => 'entry_id'],
        'entry_categories' => ['model' => 'blog_entry_category', 'foreign_key' => 'entry_id'],
        'categories' => ['model' => 'blog_category', 'foreign_key' => 'entry_id', 'through' => 'blog_entry_categories'],
        'extra_tags' => ['model' => 'blog_entry_tag_extra', 'foreign_key' => 'entry_id'],
        'share_urls' => ['model' => 'blog_entry_share_url', 'foreign_key' => 'entry_id'],
        'newsletters' => ['model' => 'blog_newsletter', 'foreign_key' => 'entry_id', 'through' => 'blog_newsletter_entries'],
        'editors' => ['model' => 'blog_contributor', 'foreign_key' => 'entry_id', 'through' => 'blog_entry_editors'],
        'entry_editors' => ['model' => 'blog_entry_editor', 'foreign_key' => 'entry_id'],
        'campaigns' => ['model' => 'ad_campaign', 'foreign_key' => 'entry_id', 'through' => 'ad_campaign_entries'],
        'series' => ['model' => 'blog_series', 'foreign_key' => 'entry_id', 'through' => 'blog_series_entries'],
        'sections' => ['model' => 'blog_entry_section', 'foreign_key' => 'entry_id'],
    ];

    protected $_belongs_to = [
        'last_modified_user' => ['model' => 'user', 'foreign_key' => 'last_modified_user_id'],
    ];

    /**
     * @var array
     */
    public static $LIVE_STATUSES = [
        self::STATUS_ARCHIVED,
        self::STATUS_PUBLISHED,
    ];

    /**
     * @var array
     */
    public static $STATUSES = [
        self::STATUS_EDIT,
        self::STATUS_PENDING,
        self::STATUS_QUEUE,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
        self::STATUS_REMOVED,
    ];

    /**
     * @var array
     */
    private static $TYPES = [
        self::TYPE_VIDEO,
        self::TYPE_SLIDESHOW,
        self::TYPE_SCROLLABLE,
    ];

    /**
     * @var bool
     */
    private $disable_save = false; // If true, save() has no effect

    /**
     * Overrides Kohana_ORM::__get() call to allow overriding of property value
     * access.
     *
     * @param string $prop
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __get($prop)
    {
        $override_method = sprintf('override_%s', $prop);

        return method_exists($this, $override_method)
            ? $this->$override_method()
            : parent::__get($prop);
    }

    /**
     * Overrides the logic for accessing the 'body' property of this class.
     * If this entry's body is empty, the content is retrieved from the
     * Blog_Entry_Sections table and those section model objects will be used to
     * generate the string representation of the body's content, ie the 'body'
     * property. Otherwise, if the body is populated, that string is returned.
     * All other property values are returned as usual.
     *
     * @throws Kohana_Exception
     *
     * @return string
     */
    protected function override_body()
    {
        $sections = $this->sections->get_body()->find_all();

        return Blog_Entry_Bodyizer::generate($sections, $this->_object['id']);
    }

    /**
     * Returns the value stored for the body column. Accessing the body via the
     * body property ($entry->body) may return sections data in their string
     * representation if the body value is empty.
     *
     * @return string
     */
    public function get_true_body()
    {
        return parent::__get('body');
    }

    /**
     * Determines if the body property of this blog_entry record is empty
     * (empty string "", or null).
     *
     * @return bool
     *              True if the body property's value is an empty string or null.
     */
    public function is_true_body_empty()
    {
        return $this->get_true_body() === '' || $this->get_true_body() === null;
    }

    public function is_published()
    {
        return $this->status == 'PUBLISHED' || $this->status == 'ARCHIVED';
    }

    /**
     * Returns an array of all entry types that have ever existed (muahahaha).
     *
     * @return array
     *               Array where each element is a string representation of an entry type.
     */
    public function get_types()
    {
        return self::$TYPES;
    }

    public function seo_prefix()
    {
        $datetime = $this->original_published->ts ?: $this->published->ts;
        $prefix = date('/Y/m/', $datetime) . $this->id;

        return $prefix;
    }

    /**
     * @param bool $full
     *
     * @return string
     */
    public function url($full = false)
    {
        $path = '/' . $this->basename;
        if ($this->needs_seo_prefix()) {
            $path = $this->seo_prefix() . $path;
        }

        if ($full) {
            $domain = $this->get_site()->getDomain();

            return "http://${domain}${path}";
        }

        return $path;
    }

    public function getProductionUrl()
    {
        return $this->url(true);
    }

    /**
     * Returns the primary tag, if no primary tag it returns the first tag.
     *
     * @return Model_Blog_Tag
     */
    public function primary_tag()
    {
        $exclude_tag_types = [];

        foreach (Model_Blog_Tag::get_cms_tags() as $type) {
            $exclude_tag_types[] = $type['slug'];
        }

        $exclude_tag_ids = ORM::factory('blog_tag')
            ->where('type', 'IN', $exclude_tag_types)
            ->find_all()
            ->as_array('id');

        $entry_tags = $this->entry_tags;

        if (count($exclude_tag_ids)) {
            $entry_tags->where('tag_id', 'NOT IN', $exclude_tag_ids);
        }

        return $entry_tags->where('is_primary', '=', 1)
            ->find()
            ->tag;
    }

    /**
     * Returns the primary category, if no primary category it returns the first category.
     *
     * @param bool $refresh Whether or not to bust the cache, opts to false
     *
     * @return Model_Blog_Category
     */
    public function primary_category($refresh = false)
    {
        return $this->categories
            ->where('is_active', '=', 1)
            ->order_by('is_primary', 'DESC')
            ->limit(1)
            ->find(null, $refresh);
    }

    /**
     * Get an entry by its basename.
     *
     * @param string $basename
     *                         Entry basename.
     *
     * @return Model_Blog_Entry
     */
    public function by_basename($basename)
    {
        return $this->where('basename', '=', $basename)->find();
    }

    /**
     * Get multiple entries by their basenames.
     *
     * @param array $basenames Basenames delmited by a comma.
     *
     * @return Model_Blog_Entry
     */
    public function by_basenames(array $basenames = [])
    {
        return $this->where('basename', 'IN', $basenames)->find_all();
    }

    /**
     * @param bool $include_link
     * @param bool $solo
     *
     * @return string
     */
    public function get_byline($include_link = true, $solo = false)
    {
        $bylines = $this->bylines->find_all();
        $byline_str = '';

        foreach ($bylines as $i =>  $byline) {

            //if we only want one byline
            if ($solo) {
                $contributor_image_src = ORM::factory('image', $byline->contributor->image_id);
                return '<img src="' . $contributor_image_src->src('40x40') . '">' . $byline_str;
            }

            if ($i == 0 && !strlen($byline->byline)) {
                $byline_str = 'By ';
            }

            //Manual imploding with , and &
            if ($i > 0 && count($bylines) == $i + 1 && !strlen($byline->byline)) {
                $byline_str .= ' & ';
            } elseif ($i > 0) {
                $byline_str .= ', ';
            }

            if (strlen($byline->byline)) {
                $byline_str .= $byline->byline . ' by ';
            }

            if ($include_link) {
                if (strlen($byline->contributor->redirect_url)) {
                    $byline_str .= '<a href="' . html_entity_decode($byline->contributor->redirect_url) . '" rel="nofollow" target="_blank">';
                } else {
                    $byline_str .= '<a href="/author/' . $byline->contributor->slug . '" rel="author">';
                }
            }

            $byline_str .= '<span itemprop="author">' . $byline->contributor->display_name . '</span>';

            if ($include_link) {
                $byline_str .= '</a>';
            }
        }

        return $byline_str;
    }

    /**
     * Returns primary categories from the tree.
     *
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function categories($offset = 0, $limit = null)
    {
        $category = $this->primary_category();

        if (!$category->loaded()) {
            return [];
        }

        $out = array_merge([$category], $category->parents());
        $out = array_reverse($out);

        return array_slice($out, $offset, $limit);

    }

    /**
     * Returns all categories from the tree.
     *
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function all_categories($offset = 0, $limit = null)
    {

        $categories = $this->categories
            ->where('is_active', '=', 1)
            ->find_all();

        $out = [];

        foreach ($categories as $category) {
            if ($category->loaded()) {
                $out = array_merge($out, [$category], $category->parents());
            }
        }

        $out = array_reverse($out);

        return array_slice($out, $offset, $limit);

    }

    public function has_category($slug)
    {
        foreach ($this->all_categories() as $category) {
            if ($category->slug == $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a list of category ids from the entry.
     *
     * @return array
     */
    public function get_cat_ids()
    {
        $categories = $this->categories
            ->where('is_active', '=', 1)
            ->find_all();

        $ids = [];

        foreach ($categories as $category) {
            if ($category->loaded()) {
                $ids[] = $category->id;
            }
        }

        return $ids;
    }

    /**
     * Gets a list of tag ids from the entry.
     *
     * @return array
     */
    public function get_tag_ids()
    {
        $tags = $this->tags->find_all();

        $ids = [];

        foreach ($tags as $tag) {
            if ($tag->loaded()) {
                $ids[] = $tag->id;
            }
        }

        return $ids;
    }

    /**
     * Returns all tags *except* specified tag(s).
     */
    public function tags_excluding($excluded_tags = false)
    {

        if (!is_array($excluded_tags)) {
            $tags = [$excluded_tags];
        } else {
            $tags = $excluded_tags;
        }

        return $this->tags->where('tag', 'NOT IN', $tags)->find_all();

    }

    /**
     * Returns first tag found, excluding specified tag(s).
     */
    public function tag_excluding($excluded_tags = false)
    {

        if (!is_array($excluded_tags)) {
            $tags = [$excluded_tags];
        } else {
            $tags = $excluded_tags;
        }

        return $this->tags->where('tag', 'NOT IN', $tags)->find();

    }

    /**
     * For use in the Dwoo plugin {ad} which spits out HTML with a tag parameter.
     *
     * @param string $implode
     *                        Optional join character for array of slugs
     *
     * @example
     *    // Getting tags for an article with multiple tags
     *    $entry->ad_tags() // returns "nail-polish.promoted"
     *
     * @return string
     */
    public function ad_tags($implode = '.')
    {

        $tags = [];

        foreach ($this->tags->find_all() as $tag) {
            $tags[] = $tag->slug;
        }

        if ($implode) {
            $tags = implode($implode, $tags);
        }

        return $tags;
    }

    /**
     * All tags for an entry, including category tags (which are the slugs of the categories).
     */
    public function all_tags()
    {
        $out = [];
        $out = array_merge($out, $this->extraTags('CATEGORY'));

        foreach ($this->tags->find_all() as $tag) {
            $out[] = $tag;
        }

        return array_unique($out);

    }

    /**
     * @param string $slug
     *
     * @return Model_Blog_Tag|null
     */
    public function get_tag_by_slug($slug)
    {
        /* @var Model_Blog_Tag $tag */
        $tag = $this->tags->where('slug', '=', $slug)->find();

        if ($tag->loaded()) {
            return $tag;
        }
    }

    public function extraTags($type, $slug = false)
    {

        $out = [];

        foreach ($this->extra_tags->where('type', '=', $type)->order_by('sort_order')->find_all() as $tag) {

            if ($slug) {
                $out[] = Util::tagify($tag->tag);
            } else {
                $out[] = $tag->tag;
            }
        }

        $out = Util::sanitize_array($out);
        $out = Util::strim($out);
        $out = array_unique($out);

        return $out;
    }

    public function setExtraTags($type, $tags)
    {

        $i = 0;

        $tags = Util::sanitize_array($tags);
        $tags = Util::strim($tags);
        $tags = array_unique($tags);

        if (!count($tags)) {
            return;
        }

        $this->getLogger()->debug('Setting extra tags', [
            'type' => $type,
            'entryId' => $this->id,
            'tags' => $tags,
        ]);

        //Delete old ones
        foreach ($this->extra_tags->where('type', '=', $type)->where('tag', 'NOT IN', $tags)->find_all() as $extra_tag) {
            $extra_tag->delete();
        }

        foreach ($tags as $tag) {
            $extra_tag = $this->extra_tags
                ->where('type', '=', $type)
                ->where('tag', '=', $tag)
                ->find();
            $extra_tag->sort_order = $i++;
            $extra_tag->entry_id = $this->id;
            $extra_tag->type = $type;
            $extra_tag->tag = $tag;
            $extra_tag->slug = Util::tagify($tag);
            $extra_tag->save();
        }
    }

    /**
     * Get the URL of the image asset that will represent this entry.
     *
     * Most of the time we use the entry's "Main" image but sometimes we prefer
     * the entry's "Video Poster" image (if it has one). The Video Poster image
     * is a better aspect ratio for muti-item modules, for example.
     *
     * @return string
     */
    public function asset_main_src_x($do_prefer_video_poster = false)
    {

        if ($this->loaded()) {
            if ($this->type === self::TYPE_VIDEO && $do_prefer_video_poster) {
                $image = $this->asset_video_poster(true);
            } else {
                $image = $this->asset_main(false, true);
            }

            if ($image->loaded()) {
                return $image->asset->image->src('x');
            }
        }

        return false;
    }

    public function asset_main($fallback = false, $alt = false)
    {

        if (!$this->loaded()) {
            return new Asset();
        }

        if (!$this->_asset_main) {
            if ($fallback && $alt) {
                // sorting like this will give types not in this list (new ones) a sort
                // value of 0, which will sort them to the top. not what we want instead
                // list them in reverse order and sort descending so new ones go to the end
                $this->_asset_main = $this->assets
                    ->where('is_active', '=', true)
                    ->order_by(DB::expr('FIELD(type, "SLIDESHOW","EMBEDDED","MAIN","MAIN_ALT")'), 'DESC')
                    ->order_by('display_order')
                    ->find();
            } elseif ($fallback) {
                $this->_asset_main = $this->assets
                    ->where('is_active', '=', true)
                    ->order_by(DB::expr('FIELD(type, "SLIDESHOW","EMBEDDED","MAIN_ALT","MAIN")'), 'DESC')
                    ->order_by('display_order')
                    ->find();
            } elseif ($alt) {
                $this->_asset_main = $this->assets
                    ->where('is_active', '=', true)
                    ->where('type', '=', 'MAIN_ALT')
                    ->order_by('display_order')
                    ->find();
            }
        }

        // Default to MAIN entry asset type
        if (empty($this->_asset_main) || !$this->_asset_main->loaded()) {
            $this->_asset_main = $this->assets
                ->where('is_active', '=', true)
                ->where('type', '=', 'MAIN')
                ->order_by('display_order')
                ->find();
        }

        return $this->_asset_main;
    }

    /**
     * @return Asset
     */
    public function getMainAsset()
    {
        /* @var Asset $assetRepository */
        $assetRepository = ORM::factory('blog_entry_asset');

        return $assetRepository
            ->where('entry_id', '=', $this->id)
            ->where('is_active', '=', true)
            ->where('type', '=', 'MAIN')
            ->order_by('display_order')
            ->find();
    }

    public function asset_slide($slide_number = 0)
    {
        if ($this->loaded() && $this->type == self::TYPE_SLIDESHOW) {
            $assets = $this->assets
                ->where('is_active', '=', true)
                ->where('type', '=', Asset::TYPE_SLIDESHOW)
                ->order_by('display_order')
                ->find_all();

            foreach ($assets as $idx => $asset) {
                if (($idx + 1) == $slide_number) {
                    return $asset;
                }
            }
        }

        return new Asset();
    }

    public function get_slideshow_assets()
    {
        return $this->get_assets('SLIDESHOW');
    }

    public function get_assets($type = null, $limit = null, $offset = 0)
    {
        $assets = $this->assets->where('is_active', '=', 1)->order_by('display_order', 'ASC');

        if ($type) {
            $assets->where('type', '=', $type);
        }

        if ($limit > 0) {
            $assets->limit($limit)->offset($offset);
        }

        return $assets->find_all();
    }

    /**
     * @param string $openerType
     * @param string $size
     * @param null   $scheme
     * @param int    $quality
     *
     * @return string
     */
    public function getOpenerCroppedImageUrl($openerType, $size = 'x', $scheme = null, $quality = 80)
    {
        $entryAsset = $this->getOpenerCrop($openerType);

        $cropCode = implode(',', [
            $entryAsset->crop_x,
            $entryAsset->crop_y,
            $entryAsset->crop_w,
            $entryAsset->crop_h,
        ]);

        return $entryAsset
            ->asset
            ->image
            ->src($size, '', '', $cropCode, $scheme, $quality);
    }

    public function categoryTags()
    {

        $tags = [];

        $categories = $this->categories
            ->where('is_active', '=', 1)
            ->find_all();

        foreach ($categories as $category) {
            foreach ($category->parents(1) as $parent) {
                $tags[] = $parent->name;
            }
        }

        foreach ($this->tags->find_all() as $tag) {
            $tags[] = $tag->tag;
        }

        if ($this->series->loaded()) {
            $tags[] = $this->series->name;
        }

        return array_unique($tags);
    }

    /**
     * Get social share permanent links for entry.
     *
     * @param Model_Blog_Entry_Share_Url_Type $type The type of share
     * @param $share_url
     * @param string $campaign
     * @param bool   $force_write          Whether to over-write old record
     * @param bool   $return_null_on_error
     *                                     true: If Bit.ly service broken, return unll
     *                                     false: return long url if bit.ly service broken
     *
     * @return string|null
     */
    public function get_share_url($type, $share_url, $campaign = '', $force_write = false, $return_null_on_error = true)
    {
        $url = $this->share_urls
            ->where('type_id', '=', $type->id)
            ->where('campaign', '=', $campaign)
            ->find();

        if ($url->loaded() && !$force_write) {
            return $url->url;
        }

        $parameter = $type->parameters;
        $parameter = preg_replace('/^&/', '?', $parameter); // if first character is &, replace it with ?

        $parameter = empty($campaign) ? $parameter : $parameter . '&utm_campaign=' . $campaign;

        //  We need to include entry id so that bit.ly will
        //  regenerate a new url when we reuse a basename.
        $parameter = $parameter . '&unique_id=entry_' . $this->id;

        $shorten_url = $this->getUrlShortener()->shorten($share_url . $parameter);

        if (stripos($shorten_url, $this->url(true)) !== false) {
            if ($return_null_on_error) {
                return;
            } else {
                return $this->url(true) . $parameter;
            }
        }

        //  Re-check it for the case where editor multi-click in quick
        //  succession the generate button of the share url tool
        $url = $this->share_urls
            ->where('type_id', '=', $type->id)
            ->where('campaign', '=', $campaign)
            ->find();

        if (!$url->loaded()) {
            $url->entry_id = $this->id;
            $url->type_id = $type->id;
            $url->campaign = $campaign;
            $url->url = $shorten_url;
            $url->save();
            $this->save(); // save the entry so that the modified date is updated - this is necessary so that the entry will appear in the changes endpoint
        }

        return $url->url;

    }

    /**
     * Returns an array of all the share URLs that have been recorded in the DB for this entry.
     * It does NOT cause them to be generated if they're not in the DB.  If there are no
     * share URLs in the DB for this entry, an empty array is returned.
     *
     * @return array An array with the share URLs as Strings.  If there are no
     *               share URLs in the DB for this entry, an empty array is returned.
     */
    public function get_share_urls()
    {
        $s_urls = [];

        $urls = $this->share_urls->find_all();

        if ($urls) {
            foreach ($urls as $shr) {
                $s_urls[strtolower($shr->url_type->type)] = $shr->url;
            }
        }

        return $s_urls;
    }

    public function needs_seo_prefix()
    {
        // don't change urls before this date
        $categories_migration = strtotime('2012/06/09 13:00:00');

        if ($this->is_news() && $this->published->ts > $categories_migration) {

            return true;
        }

        return false;
    }

    public function is_news()
    {

        return $this->breaking_news > 0;
    }

    public function get_breadcrumbs($include_links = true, $entry_meta_title = true)
    {

        $out = '';

        foreach (array_reverse($this->primary_category()->parents(true, false)) as $parent) {
            // Don't link to categories that are merely for pre-launch categorization
            if ($parent->is_active && $include_links) {
                $out .= '<a href="' . $parent->url() . '">' . $parent->name . '</a> &raquo; ';
            } else {
                $out .= '<strong>' . $parent->name . '</strong> &raquo; ';
            }
        }

        if ($this->get_meta('meta_title') != '' && $entry_meta_title) {
            $out .= '<h1 class="sub">' . $this->get_meta('meta_title') . '</h1>';
        } else {
            $out .= $this->title;
        }

        return $out;

    }

    /**
     * @return string
     */
    public function getPublishedDateForArchivePage()
    {
        return $this->published->format('Y/m/d');
    }

    public function get_tags()
    {
        $result = [];

        foreach ($this->tags->where('type', 'IN', ['editorial', 'collections', 'reporting'])->find_all() as $tag) {
            $result[$tag->type][] = $tag;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getCollectionsList()
    {
        if (!count($this->collections_list)) {
            $key = $this->collection_cache_key();

            $collectionIds = (array) Cache_Remote::instance()->get($key);

            // empty array doesn't play well with ->where id IN
            if (empty($collectionIds)) {
                return [];
            }

            $collections = ORM::factory('blog_collection')
                ->where('id', 'IN', $collectionIds)
                ->where('type', '!=', Model_Blog_Collection::TYPE_CONTRIBUTOR)
                ->find_all()
            ;

            /** @var Model_Blog_Collection $collection */
            foreach ($collections as $collection) {
                $this->collections_list[] = [
                    'name' => $collection->name,
                    'url' => $collection->url(true),
                    'slug' => trim($collection->slug),
                ];
            }
        }

        return $this->collections_list;
    }

    /**
     * Returns an array of alphabetically sorted collection slugs.
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function getCollectionSlugs()
    {
        $slugs = array_map(function (array $collection) {
            Assertion::keyExists($collection, 'slug');

            return strtolower($collection['slug']);
        }, $this->getCollectionsList());

        // filter empty slugs
        $slugs = array_filter($slugs);

        sort($slugs);

        return $slugs;
    }

    /**
     * @return string
     */
    public function get_author_names()
    {
        $authors = $this->getAuthorDisplayNames();

        return implode(',', $authors);
    }

    /**
     * @return string[]
     */
    public function getAuthorDisplayNames()
    {
        $authors = [];

        $byLines = $this->bylines->find_all();

        foreach ($byLines as $byLine) {
            $authors[] = $byLine->contributor->display_name;
        }

        return $authors;
    }

    /**
     * Gets the topic big image.
     *
     * @return Asset
     */
    public function asset_topic_big()
    {
        $entry_asset = $this->get_opener_by_type(
            Asset::TYPE_MAIN_TOPIC_BIG
        );

        // If there is no alternate opener use new opener tool
        if (!$entry_asset->loaded()) {
            $entry_asset = $this->asset_opener(Asset::TYPE_MAIN);
        }

        return $entry_asset;
    }

    /**
     * Gets the video poster asset.
     *
     * @param bool $allow_fallback - optional -  if true (the default), returns the main asset if there is no video poster.
     *
     * @return Asset
     */
    public function asset_video_poster($allow_fallback = true)
    {
        $asset_topic_big = $this->get_opener_by_type(
            Asset::TYPE_VIDEO_POSTER
        );
        if ($asset_topic_big->loaded()) {
            return $asset_topic_big;
        } elseif ($allow_fallback) {
            return $this->asset_main(false, true);
        }
    }

    /**
     * Gets an 'opener' entry asset belonging to this entry.
     *
     * @param $type
     *   The entry asset type, particularly an opener.
     *
     * @return Asset
     */
    public function get_opener_by_type($type)
    {
        $opener_types = Asset::get_entry_asset_types('openers');
        if (!in_array($type, $opener_types)) {
            trigger_error("'$type' is not a valid Asset opener type");
        }

        $entry_asset_model = $this->assets
            ->where('is_active', '=', true)
            ->where('type', '=', $type)
            ->order_by('display_order')
            ->find();

        return $entry_asset_model;
    }

    /**
     * Returns a url for use with comments and social sharing based on entry's social_url.
     *
     * @param string $utm_source
     *                           String to use for utm_source parameter.
     * @param bool   $add_params
     *                           Control whether to output extra parameters on social url.
     *
     * @return string
     **/
    public function social_url($utm_source = null, $add_params = true)
    {

        $url = 'http://www.' . BASE_DOMAIN . $this->social_url;

        if ($add_params) {

            $url .= '?';

            if (!empty($utm_source)) {
                $url .= 'utm_source=' . $utm_source . '&';
            }

            $url .= 'unique_id=' . $this->unique_id();
        }

        return $url;
    }

    /**
     * @param bool $encode
     *
     * @return string
     */
    public function canonical($encode = false)
    {
        if ($encode) {
            return urlencode($this->url(true));
        }

        return $this->url(true);
    }

    /**
     * Disqus-specific wrapper for social_url().
     *
     * @param bool $for_js
     *                     Controls whether to split the string for usage in js vars.
     *
     * @return string
     */
    public function disqus_url($for_js = false)
    {

        $params = ($this->comments_version > 1);
        $url = $this->social_url('disqus', $params);

        // this is sort of ridiculous, but it's so googlebot doesn't scrape the url out of js
        if ($for_js) {
            $url = str_replace('?', "' + '?' + '", $url);
        }

        return $url;
    }

    /**
     * Gets page type. Used in ad tags.
     *
     * @return string
     */
    public function get_page_type()
    {
        switch ($this->type) {
            case self::TYPE_SLIDESHOW:
                return 'slideshow';
                break;
            default:
                return 'entry';
        }
    }

    /**
     * @param int $page_num
     *                      Article page number based off query string.
     *                      Gets entry metadata.
     *
     * @return array
     */
    public function get_metadata($page_num)
    {

        $metadata = [
            'title' => trim(plaintext($this->get_meta('meta_title'))) ?
                trim(plaintext($this->get_meta('meta_title'))) :
                plaintext($this->title),
            'description' => trim($this->get_meta('meta_description')) ?
                trim($this->get_meta('meta_description')) :
                plaintext($this->get_excerpt()),
            'keywords' => $this->get_meta('meta_keywords'),
            'noindex' => $this->get_meta('noindex'),
            'social_title' => trim(plaintext($this->get_meta('social_title'))) ?
                trim(plaintext($this->get_meta('social_title'))) :
                plaintext($this->title),
            'facebook_path' => $this->get_facebook_image(),
        ];

        if ($this->get_page_type() == 'slideshow') {

            $entry_assets = $this->get_slideshow_assets();
            $i = 1;
            foreach ($entry_assets as $entry_asset) {

                if ($i == $page_num) {
                    // Title
                    if ($entry_asset->asset->meta_title) {
                        $metadata['title'] = $entry_asset->asset->meta_title;
                    }

                    // Description
                    $metadata['description'] = $entry_asset->asset->meta_desc ?
                        $entry_asset->asset->meta_desc : $this->title;

                    break;
                }
                $i += 1;
            }
        }

        return $metadata;
    }

    public function exclude_from_trending_id()
    {
        static $tag_id;

        if (null === $tag_id) {
            $exclude_trending = ORM::factory('blog_tag')
                ->where('slug', '=', 'exclude-trending')
                ->find();
            $tag_id = 0;
            if ($exclude_trending->loaded()) {
                $tag_id = (int) $exclude_trending->id;
            }
        }

        return $tag_id;
    }

    public function exclude_from_trending()
    {

        $tag_id = $this->exclude_from_trending_id();

        if (!$tag_id) {
            return false;
        }

        if (!$this->loaded()) {
            return true;
        }

        return (bool) $this->entry_tags->where('tag_id', '=', $tag_id)
            ->find()->loaded();
    }

    /**
     * Like most_popular_social but excludes entries tagged exclude-trending
     * and has smart fall back and front loads image at least.
     */
    public function social_trending($limit = 5, $sources = null, Model_Blog_Category $category = null)
    {

        // entries found
        $entries = [];

        // values added
        $values = [];

        $categories = null;
        if (null !== $category && $category->loaded()) {
            $categories = [$category->id];
        }

        if (null !== $categories) {
            $query[] = ['category_ids', 'IN', $categories];
        }

        $query[] = [
            'tag_ids',
            'NOT IN',
            [
                $this->exclude_from_trending_id(),
            ],
        ];

        $results = $this->search($query, 0, $limit * 4, ['sort' => 'published desc']);

        $defaults = [];
        $scores = [];
        foreach ($results as $entry) {
            $entry_id = (int) $entry->id;
            $entries[$entry_id] = $entry;
            $tmp = $entry->social_score_stats($sources);
            $values[$entry_id] = isset($tmp->value) ? $tmp->value : 0;
        }

        $min_img_height = 452;
        $min_img_width = 377;

        // find a leader image
        $query = [
            ['id', 'IN', array_keys($entries)],
            ['asset_main_width', '>', $min_img_width],
            ['asset_main_height', '>', $min_img_height],
        ];

        $tmp = $this->search($query, 0, count($entries));
        $large_img_entries = [];
        if ($tmp && $tmp->num_found > 0) {
            foreach ($tmp->docs as $entry) {
                $large_img_entries[(int) $entry->id] = $entry;
            }
        }

        $entry_ids = array_keys($values);
        $values = array_values($values);

        array_multisort($values, SORT_NUMERIC, SORT_DESC, $entry_ids);

        $leader = false;
        $records = [];
        foreach ($entry_ids as $idx => $entry_id) {

            $record = [
                'id' => $entry_id,
                'blog_entry' => $entries[$entry_id],
                'value' => $values[$idx],
                'nice' => sprintf('%s %s',
                    number_abbr($values[$idx]),
                    Social_Score::map_source_noun($sources)
                ),
            ];

            if (!$leader && isset($large_img_entries[$entry_id])) {
                $leader = true;
                array_unshift($records, $record);
            } else {
                array_push($records, $record);
            }
        }

        return array_slice($records, 0, $limit, true);
    }

    /**
     * Get social score statistics for the current entry.
     *
     * @param Model_Social_Score_Source|string|int|null $source Source (social_score_sources)
     *                                                          model, id, label or null. If null, return all social scores
     *
     * @return array Collection of social scores for current entry
     */
    public function social_score_stats($source = null)
    {
        if (!$this->loaded()) {
            return;
        }

        return Social_Score::social_score_stats($this, $source);
    }

    /**
     * Get summary of all social score information.
     *
     * @return array Collection of social scores for current entry
     */
    public function social_score_summary($source = null)
    {
        if (!$this->loaded()) {
            return;
        }
        if (null === $this->social_scores) {
            $this->social_scores = Social_Score::social_score_summary($this);
        }
        if (null === $source) {
            return $this->social_scores;
        }
        if (!isset($this->social_scores[$source])) {
            return;
        }

        return $this->social_scores[$source];
    }

    /**
     * Get all contributor information.
     *
     * @return array of contributors for current entry
     */
    public function get_contributors()
    {
        if (!$this->loaded()) {
            return;
        }

        $bylines = [];
        $contributors = $this->bylines->where('byline', 'IS', null)->order_by('display_order')->limit(2)->find_all();
        if (count($contributors) == 0) {
            $contributors = [$this->bylines->order_by('id')->find()];
        }

        foreach ($contributors as $byline) {

            $bylines[] = [
                'id' => $byline->id,
                'display_order' => $byline->display_order,
                'contributor_id' => $byline->contributor->id,
                'slug' => $byline->contributor->slug,
                'name' => $byline->contributor->display_name,
                'img' => $byline->contributor->image->toArray(),
                'position' => $byline->contributor->position,
                'include_in_page_bio' => $byline->contributor->include_in_page_bio,
                'in_page_bio' => $byline->contributor->in_page_bio,
            ];
        }

        return $bylines;
    }

    /**
     * Get other contributor information.
     *
     * @return array of contributors with bylines like photo/video by...
     */
    public function get_other_contributors()
    {
        if (!$this->loaded()) {
            return;
        }

        $bylines = [];

        foreach ($this->bylines->where('byline', 'IS NOT', null)->find_all() as $byline) {
            $bylines[] = [
                'display_order' => $byline->display_order,
                'contributor_id' => $byline->contributor->id,
                'slug' => $byline->contributor->slug,
                'name' => $byline->contributor->display_name,
                'byline' => $byline->byline,
            ];
        }

        return $bylines;
    }

    /**
     * Just get the social score value for a given source.
     *
     * @param string $source social_score_sources.label
     * @param bool   $nice   True for abbreviated numbers (2.5K instead of 2500)
     *
     * @return string Social score value for give source
     */
    public function social_score_value($source, $nice = false)
    {
        $value = $this->social_score_summary($source);

        if (null === $value) {
            $value = 0;
        }

        if ($nice) {
            return number_abbr($value);
        }

        return $value;
    }

    /**
     * Return true if entry is breaking news and if it has non-empty news_keywords meta tag.
     *
     * @return bool
     */
    public function has_news_keywords()
    {
        if ($this->is_news()) {

            $news_keywords_meta = $this->get_meta('news_keywords');

            if (is_null($news_keywords_meta) || empty($news_keywords_meta) ) {
                return false;
            } else {
                return true;
            }

        }

        return false;
    }

    /**
     * Returns editoring contributors for entry.
     *
     * @param string $type Type to filter by.
     *
     * @return Model_Blog_Contributor
     */
    public function get_editor($type = null)
    {
        $entry_editor = $this->entry_editors;

        if ($type) {
            $entry_editor->where('type', '=', $type);
        }

        return $entry_editor->find()->editor;
    }

    /**
     * Update the last_modified_user and modified date for this entry.
     *
     * @param mixed $user
     * @param bool  $autosave
     *                        Whether to automatically save the modified time and user
     */
    public function update_last_modified($user = null, $autosave = true)
    {
        if ($user !== null) {
            //  Update the time stamp
            $this->last_updated_timestamp = new Date('now');

            $this->modified = $this->last_updated_timestamp;
            $this->last_modified_user_id = $user->id;

            if ($autosave) {
                $this->save();
            }

            //  Also set the user that updated this information
            $this->employee_name = $user->first_name . ' ' . $user->last_name;
            $this->employee_email = $user->email;
        }
    }

    /**
     *  get_last_modified_data().
     *
     * @desc  Get the last modified data
     */
    public function get_last_modified_data()
    {
        return [
            'last_updated_timestamp' => date('M j, Y g:i A T', $this->last_updated_timestamp->ts),
            'employee_name' => $this->employee_name,
            'employee_email' => $this->employee_email,
        ];
    }


    /**
     * Determines whether this entry is live to the public.
     *
     * @return bool
     *              Live if true (ie. the entry has a status of PUBLISHED or ARCHIVED), false
     *              otherwise.
     */
    public function is_live()
    {
        return in_array($this->status, self::$LIVE_STATUSES);
    }

    public static function get_live_statuses()
    {
        return [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];
    }

    /**
     * This function gets all the embedded internal video assets.
     *
     * @return Model_Blog_Asset[]
     */
    public function get_video_assets()
    {
        $blogAssets = [];

        $blogEntryAssetVideos = $this->get_video_entry_assets();

        foreach ($blogEntryAssetVideos as $blogEntryAssetVideo) {
            $blogAssets[] = $blogEntryAssetVideo->asset;
        }

        return $blogAssets;
    }

    /**
     * Returns all embedded internal video assets.
     *
     * @return Asset[]
     */
    public function get_video_entry_assets()
    {
        $videoAssets = [];

        $entryAssets = $this->get_assets(Asset::TYPE_EMBEDDED);

        foreach ($entryAssets as $entryAsset) {
            // Asset type must be 'VIDEO'
            if ($entryAsset->asset->type === Model_Blog_Asset::TYPE_VIDEO) {
                /* @var Model_Video $video */
                $video = $entryAsset->asset->video;

                if ($video->loaded() && $video->type === Model_Video::INTERNAL) {
                    $videoAssets[] = $entryAsset;
                }
            }
        }

        return $videoAssets;
    }


    /**
     * Retrieves the category/subcategory if the current entry.
     *
     * @param bool $subcategory Indicator whether we want a parent category or a subcategory
     *
     * @return Model_Blog_Category Entry category
     */
    public function get_module_category($subcategory = false)
    {
        $categories = $this->primary_category(true)->parents(true);

        if (count($categories)) {
            if ($subcategory) {
                return $categories[0];
            }

            return isset($categories[1]) ? $categories[1] : $categories[0];
        }
    }


    /**
     * Used to initialize Common_Template_Array().
     *
     * @return Model_Blog_Category
     */
    public function get_category()
    {
        return $this->primary_category();
    }

    /**
     * Returns a shorter title.
     *
     * @param int    $max_title_length
     * @param string $ender
     *
     * @return string
     */
    public function get_short_entry_title()
    {
        if (strlen($this->hero_shorter_title) > 0) {
            return $this->hero_shorter_title;
        }

        return $this->title;
    }

    /**
     * This function checks if entry has feature video.
     * Entry has feature video only in the case if the video is first section and if there is only one
     * internal video in the whole entry.
     *
     * todo: pass in sections so we don't skip calling `get_sections`, remove true body check
     *
     * @return bool
     */
    public function hasFeaturedVideo()
    {
        $sections = $this->get_sections();
        $entry_body = $this->get_true_body();

        $is_new_article = (is_null($entry_body) || $entry_body === '');

        if ($is_new_article && !empty($sections['body'])) {
            if ($sections['body'][0]['type'] !== 'video:internal') {
                return false;
            }

            $number_of_internal_videos = 0;

            foreach ($sections['body'] as $section) {
                if ($section['type'] === 'video:internal') {
                    ++$number_of_internal_videos;
                }
            }

            return $number_of_internal_videos === 1;
        } elseif (!is_null($sections['featured_video']) && $sections['featured_video']['type'] === 'video:internal') {
            return true;
        }

        return false;
    }

    /**
     * Returns the date and time when this entry was last published.
     *
     * @return DateTimeImmutable
     */
    public function published()
    {
        if ($this->published === null) {
            return;
        }

        if ($this->published->ts === null) {
            return;
        }

        $date = new DateTimeImmutable();

        return $date->setTimestamp($this->published->ts);
    }

    /**
     * Returns the date and time when this entry was last updated.
     *
     * @return DateTimeImmutable
     */
    public function updated()
    {
        if ($this->modified === null) {
            return;
        }

        if ($this->modified->ts === null) {
            return;
        }

        $date = new DateTimeImmutable();

        return $date->setTimestamp($this->modified->ts);
    }
}