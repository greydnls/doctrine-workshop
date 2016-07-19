<?php

use Refinery29\Container;
use Refinery29\Core\Entity\HasProductionUrl;
use Refinery29\Event\CanHazEmitter;
use Refinery29\Foundry\ArrayBuilder\BlogCategory as ArrayBuilder;
use Refinery29\Foundry\TemplateArrayBuilder\BlogCategory as TemplateArrayBuilder;
use Refinery29\Localization\LocalizationHelper;
use Refinery29\Logger\CanHazLogger;
use Refinery29\Module\HasModules;
use Refinery29\Route\Routable;
use Refinery29\Search\Searchable;
use Refinery29\Site\Site;
use Refinery29\Site\SiteRepository;
use Refinery29\Worker\WorkerCallable;

class Model_Blog_Category extends Orm
{
    use CanHazEmitter;
    use CanHazLogger;
    use R29_Orm_Cache_Dictionary;
    use Routable;
    use Searchable;
    use WorkerCallable;

    /**
     * Constants for types.
     */
    const TYPE_GLOBAL = 'GLOBAL';
    const TYPE_LOCAL = 'LOCAL';
    const TYPE_POLY = 'POLY';

    protected $columns = [
        'id' => 'int unsigned',
        'site_id' => 'char',
        'name' => 'varchar',
        'slug' => 'varchar',
        'fullpath' => 'varchar',
        'parent_id' => 'int unsigned',
        'is_active' => 'tinyint',
        'display_order' => 'int unsigned',
        'is_root' => 'tinyint',
        'is_dynamic' => 'tinyint',
        'type' => 'char',
        'is_visible' => 'tinyint',
        'admin_is_visible_targeting_tool' => 'tinyint',
        'admin_is_visible_mass_tagging' => 'tinyint',
        'admin_is_visible_entry_admin' => 'tinyint',
        'layout' => 'varchar',
        'module_title' => 'varchar',
        'display_order_mobile' => 'int unsigned',
    ];

    protected $_sorting = ['display_order' => 'ASC', 'id' => 'ASC'];

    protected $_has_many = [
        'children' => ['model' => 'blog_category',  'foreign_key' => 'parent_id'],
        'meta' => ['model' => 'blog_category_meta_value', 'foreign_key' => 'category_id'],
        'tags' => ['model' => 'blog_tag', 'foreign_key' => 'category_id'],
        'entries' => ['model' => 'blog_entry', 'foreign_key' => 'category_id', 'through' => 'blog_entry_categories'],
    ];

    protected $_belongs_to = [
        'parent' => ['model' => 'blog_category', 'foreign_key' => 'parent_id'],
    ];

    public static $TYPES = [
        self::TYPE_GLOBAL,
        self::TYPE_LOCAL,
        self::TYPE_POLY,
    ];

    /**
     * Holds local result of get_metas().
     */
    private $metas;

    /**
     * Returns this category's parent, if it has one.
     * Otherwise, returns empty category.
     *
     * @return Model_Blog_Category
     */
    public function get_parent() {

        if (empty($this->parent_id)) {
            return new self();
        }

        return $this->parent;
    }

    public function is_published() {

        return (bool) $this->is_active && (bool) $this->is_visible;
    }

    public function before_delete() {

        //Cascade!
        foreach (ORM::factory('blog_category_meta_value')->where('category_id', '=', $this->id)->find_all() as $meta) {
            $meta->delete();
        }

        foreach ($this->children->find_all() as $category) {
            $category->delete();
        }

    }

    public function url($full = false, $first_slash = true) {

        $url = $this->fullpath;

        if ($full) {

            $url = 'http://www.' . BASE_DOMAIN . '/' . $url;

        } elseif ($first_slash) {

            $url = '/' . $url;
        }

        return $url;
    }

    public function getProductionUrl()
    {
        return $this->url(true);
    }

    public function set_trending($tags) {

        if (!$this->loaded()) {
            return [];
        }

        $out = [];

        foreach ($tags as $tag) {
            $blog_tag = ORM::factory('blog_tag')->where('slug', '=', $tag)->find();

            if ($blog_tag->loaded()) {
                $out[] = $blog_tag->slug;
            }

        }

        return $this->set_meta('trending', implode(',', $out), false);

    }

    public function get_trending($non_obj = false) {

        if (!$this->loaded() || !$this->get_meta_value('trending')) {
            return;
        }

        $out = [];

        $tags = explode(',', $this->get_meta_value('trending'));

        foreach ($tags as $tag) {
            $blog_tag = ORM::factory('blog_tag')->where('slug', '=', $tag)->find();

            if (!$blog_tag->loaded()) {
                return;
            }

            if ($non_obj) {
                $out[] = $blog_tag->tag;
            } else {
                $out[] = $blog_tag;
            }

        }

        return $out;

    }

    /**
     * Sets a meta value for $name.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $pass_down
     */
    public function set_meta($name, $value, $pass_down = true)
    {
        //Model_Blog_Category_Meta
        $meta = ORM::factory('blog_category_meta')
            ->where('name', '=', $name)
            ->find();

        if (!$meta->loaded()) {
            //Create it
            $meta->name = $name;
            $meta->save();
        }

        $meta_value = ORM::factory('blog_category_meta_value')
            ->where('meta_id', '=', $meta->id)
            ->where('category_id', '=', $this->id)
            ->find();

        if (!$meta_value->loaded()) {
            $meta_value->meta_id = $meta->id;
            $meta_value->category_id = $this->id;
        }

        $meta_value->value = $value;
        $meta_value->pass_down = $pass_down;

        $meta_value->save();

        $this->refresh_metas_cache();
    }

    /**
     * Returns associative array of this object's
     * meta values in 'name' => 'value' format.
     *
     * @param array
     */
    public function get_metas()
    {
        if (!$this->loaded()) {
            return [];
        }

        if (!is_array($this->metas)) {
            $this->metas = Cache_Remote::instance()->get($this->metas_cache_key());

            if (!is_array($this->metas)) {
                $this->metas = $this->refresh_metas_cache();
            }
        }

        $metas = [];
        foreach ($this->metas as $name => $meta) {
            $metas[$name] = $meta['value'];
        }

        return $metas;
    }

    /**
     * Returns a single value for $name or null.
     *
     * @param string
     *
     * @return mixed
     */
    public function get_meta_value($name)
    {
        return Arr::get($this->get_metas(), $name);
    }

    public function parents($include_self = false, $include_root = false) {

        $out = [];

        if (!$this->loaded()) {
            return $out;
        }

        if ($include_self) {
            $out[] = $this;
        }

        if (!$this->get_parent()->is_root || $include_root) {
            $out = array_merge($out, $this->get_parent()->parents(true, $include_root));
        }

        return $out;
    }

    /**
     * Get root category, the parent to all the baby categories.
     *
     * @todo Cache this call
     */
    public function get_root()
    {
        $root = ORM::factory('blog_category')
            ->where('site_id', '=', $this->getSite()->getId())
            ->where('is_root', '=', true)
            ->where('parent_id', 'IS', null)
            ->find();

        if (!$root->loaded()) {
            throw new Exception('Root category not found');
        }

        return $root;
    }

    /**
     * Get local categories that are type active.
     */
    public function get_local_categories()
    {
        return $this->where('type', '=', 'LOCAL')
            ->where('is_active', '=', 1)
            ->find_all()
            ;
    }

    public function by_fullpath($path, $first_match = true, $only_active = true, $default_edition = null) {

        // for backward compatibility
        if (is_array($path)) {

            $path = implode('/', $path);
        }

        $path = trim($path, '/');

        if (empty($path)) {

            $path = $default_edition->fullpath;
        }

        $query = new Model_Blog_Category();

        if ($first_match) {

            $first_slug = current(explode('/', $path));
            $query->where('fullpath', 'LIKE', $first_slug . '%')->order_by('fullpath');

        } else {

            $query->where('fullpath', '=', $path);
        }

        if ($only_active) {

            $query->where('is_active', '=', 1);
        }

        $category = $query->find();

        if (!$category->loaded()) {

            $category = new Model_Blog_Category();
        }

        return $category;
    }

    public function by_path($path, $first_match = true, $only_active = true, $default_edition = null) {

        if (is_array($path)) {
            $path = trim(trim(implode('/', $path), '/'));
        }

        $category = new Model_Blog_Category();

        if (!empty($path)) {

            $category->where('fullpath', '=', $path);

            if ($only_active) {
                $category->where('is_active', '=', 1);
            }

            $category->order_by('is_active', 'desc');

            $category = $category->find();
        }

        if (!$category->loaded() && !empty($default_edition)) {
            $category = $category->where('fullpath', '=', $default_edition)
                ->find();
        }

        return $category;
    }

    public function count_entries($recursive = false, $is_primary = false, $count = '') {

        $query = ORM::factory('blog_entry_category')->where('category_id', '=', $this->id);
        if ($is_primary) {
            $query = $query->where('is_primary', '=', 1);
        }
        $query = $query->count_all();

        //base cases
        if ($recursive == true && $this->children->count_all() == 0 && $count == '') {
            return $count;
        }
        elseif ($count == 0) {
            $count = $query;
        } else {
            $count = $count + $query;
        }

        if ($recursive == true) {
            foreach ($this->children->find_all() as $child) {
                $count = $child->count_entries($recursive, $is_primary, $count);
            }
        }

        return $count;

    }

    public function toArray() {

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url(1),
            'trending_tags' => $this->get_trending(),
            'parent' => $this->parents(),
            'type' => $this->type,
        ];

    }

    public function get_active_children_by_slug()
    {
        return $this->children
            ->where('is_active', '=', 1)
            ->order_by('slug', 'ASC')
            ->find_all();
    }

    /**
     * @param int $page_num
     *    Index page number based off query string.
     * Gets category metadata.
     *
     * @return array
     */
    public function get_metadata($page_num) {
        $path = [];

        foreach ($this->parents(1) as $parent) {
            $path[] = $parent->name;
        }

        $meta = [
            'title' => $this->get_meta_value('meta_title') ?
                $this->get_meta_value('meta_title') : implode(', ', $path),
            'description' => $this->get_meta_value('meta_description') ?
                $this->get_meta_value('meta_description') :
                'Emerging fashion trends covered by experts. Make Refinery29.com your source for current fashion trends',
            'keywords' => $this->get_meta_value('meta_keywords') ?
                $this->get_meta_value('meta_keywords') :
                'current fashion trends, fall fashion trends, spring fashion trends, summer fashion trends, fashion trends and accessories, emerging fashion trends',
            'noindex' => $this->get_meta_value('noindex'),
        ];

        if ($this->slug == 'find' || $this->slug == 'stores' || $page_num > 1) {
            $meta['noindex'] = 1;
        }

        return $meta;
    }

    /**
     * This function gets all categories of a particular type. See the
     * self::$TYPES var for available types.
     *
     * @param $type
     *   A string representing the Model_Blog_Category type. See self::$TYPES
     *   for available types.
     * @param bool $include_tags
     *   This boolean indicates whether to include category tags in the result.
     *
     * @return array
     *   A nested array of arrays, representing the hierarchical structure
     *   of categories of a particular type.
     */
    public function get_all_cats_of_type($type, $include_tags = false) {

        $result = [];

        if (in_array($type, self::$TYPES)) {
            $cats = $this->where('type', '=', $type)->find_all();

            foreach($cats as $cat) {
                $result[] = self::get_cats_tree($cat->id, $include_tags);
            }
        }
        else {
            trigger_error("'{$type}' is not a valid value for the '\$type' arg");
        }

        return $result;
    }

    /**
     * This function gets all the categories in a tree where $category_id is
     * associated with a Model_Blog_Category that represents the root of the tree.
     *
     * @param int $category_id
     *   This is the ID of the root Model_Blog_Category within the tree that will
     *   be returned from this function.
     * @param bool $include_tags
     *   This boolean indicates whether to include category tags in the result.
     *
     * @return array
     *   Returns a tree presentation of categories in which the root is the category
     *   associated with the given $category_id.
     */
    public static function get_cats_tree($category_id = null, $include_tags = false) {

        $this_cat = [
            'category' => null,
            'child_categories' => [],
        ];

        // Get the category at this node.
        $cat = ORM::factory('blog_category', $category_id);
        if (!$cat->loaded()) {
            trigger_error("Blog_Category with ID {$category_id} does not exist.");
        }
        $children = $cat->children
            ->where('is_active', '=', 1)
            ->order_by('type', 'DESC')
            ->order_by('display_order', 'ASC')
            ->find_all();

        $this_cat['category'] = $cat;
        if ($include_tags) {
            $this_cat['category_tags'] = $cat->tags->find_all();
        }

        // Get the child categories of $this_cat if it has any.
        // Otherwise, if this node has 0 children, it is a leaf node. Let's start to
        // bubble back up.
        if (count($children) > 0) {
            foreach ($children as $curr_cat) {
                $this_cat['child_categories'][] = self::get_cats_tree($curr_cat->id, $include_tags);
            }
        }

        return $this_cat;
    }

    /**
     * Returns the global category for this category, if one exists.
     *
     * @return Model_Blog_Category
     */
    public function globalCategory()
    {
        if ($this->type === self::TYPE_GLOBAL && $this->is_active) {
            return $this;
        }

        $parent = $this->parent;

        if (!$parent || !$parent->loaded()) {
            return;
        }

        return $parent->globalCategory();
    }

    /**
     * Returns newest published entry for the category.
     *
     * @return Model_Blog_Category
     */
    public function findNewestEntry()
    {
        return $this
            ->entries
            ->where('status', '=', 'PUBLISHED')
            ->order_by('published', 'DESC')
            ->find();
    }
}
