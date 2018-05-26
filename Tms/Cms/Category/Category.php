<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 *
 * @uses Tms\Accessor
 */
class Category extends Template
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

    /**
     * Site Root Category.
     *
     * @var int
     */
    private $site_root;

    /**
     * Current category ID.
     *
     * @var int
     */
    private $categoryID;

    /**
     * Current category properties.
     *
     * @var array
     */
    private $category_data;

    /**
     * entry list offset.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * page separation.
     *
     * @var bool
     */
    private $page_separation = false;

    private $page_suffix_watcher;
    private $page_separation_watcher;

    private $file_name_format;

    /**
     * Pagination object
     *
     * @var \P5\Pagination
     */
    public $pager;

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->site_root = self::rootCategory($this->db);
        self::setCategory($this->session->param('current_category'));
        $this->category_data = $this->db->get('*', 'category', 'id = ?', [$this->categoryID]);
    }

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.category.'.$check);

        $table = 'category';
        $skip = ['id', 'sitekey', 'userkey', 'create_date', 'modify_date', 'lft', 'rgt'];

        $post = $this->request->post();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_description', 'description', 'disallowtags', 2];
        if (empty($post['id'])) {
            $valid[] = ['vl_path', 'path', 'empty'];
        } else {
            $old_path = $this->getCategoryPath($post['id'], 1);
        }

        if (!$this->validate($valid)) {
            return false;
        }

        $this->db->begin();

        $fields = $this->db->getFields($table);
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                if ($field === 'author_date' && !empty($post[$field])) {
                    $save[$field] = date('Y-m-d H:i', \P5\Text::strtotime($post[$field]));
                    continue;
                }
                $save[$field] = (empty($post[$field])) ? null : $post[$field];
            }
        }

        // NULL is not empty to foreign key.
        if (empty($save['template'])) {
            $save['template'] = null;
        }

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $reassembly = false;
        if (empty($post['id'])) {
            $parent_rgt = $this->db->get('rgt', 'category', 'id = ?', [$this->categoryID]);

            $save['lft'] = $parent_rgt;
            $save['rgt'] = $parent_rgt + 1;

            $save['sitekey'] = $this->siteID;
            $save['userkey'] = $this->uid;

            // Inherit template from parent category
            $inherit = $this->db->get('inheritance,template', 'category', 'id = ?', [$this->categoryID]);
            if ($inherit['inheritance'] === '1') {
                $save['template'] = $inherit['template'];
            }

            $raw['create_date'] = 'CURRENT_TIMESTAMP';

            $update_parent = $this->db->prepare(
                $this->db->nsmBeforeInsertChildSQL('category')
            );

            if (   false !== $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => 2])
                && false !== $result = $this->db->insert($table, $save, $raw)
            ) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
            }
        } else {
            if (false !== $result = $this->moveCategory($post['id'], $post['parent'])) {
                $result = $this->db->update($table, $save, "id = ? AND reserved = '0'", [$post['id']], $raw);
            }
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                // If there is a need to do something after saving
                $new_path = $this->getCategoryPath($post['id'], 1);
                if (isset($old_path) && $old_path !== $new_path) {
                    if (file_exists($old_path)) {
                        $result = rename($old_path, $new_path);
                    }
                }
                // ^ write here.
            } else {
                $result = false;
            }
            if ($result !== false) {
                $customs = [];
                foreach ((array)$post as $key => $value) {
                    if (strpos($key, 'cst_') === 0) {
                        $customs[$key] = $value;
                    }
                }
                if (false !== $this->saveCustomField('category', $post['id'], $customs)) {
                    if ($reassembly) {
                        self::reassembly($this->categoryID);
                    }
                    $this->app->logger->log("Save the category `{$post['id']}'", 201);

                    return $this->db->commit();
                }
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    //private function breakPermission($id)
    //{
    //    if (false === $priv = $this->hasPermission('cms.category.inherit', $this->siteID, $this->categoryID)) {
    //        return true;
    //    }

    //    $unit = [
    //        'userkey' => $this->uid,
    //        'filter1' => $this->siteID,
    //        'filter2' => $id,
    //        'application' => 'cms',
    //        'class' => 'category',
    //        'priv' => '0',
    //    ];
    //    foreach (['create', 'update', 'delete'] as $type) {
    //        if ($this->hasPermission("cms.category.$type", $this->siteID, $this->categoryID)) {
    //            $unit['type'] = $type;
    //            if (false === $this->db->insert('permission', $unit, [])) {
    //                return false;
    //            }
    //        }
    //    }

    //    return true;
    //}

    /**
     * Remove category data.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('cms.category.delete');

        list($kind, $id) = explode(':', $this->request->param('delete'));

        $this->db->begin();

        if (false === $this->isEmpty($id)) {
            $this->session->param('messages', \P5\Lang::translate('NOT_EMPTY'));

            return false;
        }

        $sitekey = $this->db->quote($this->siteID);
        $path = $this->getCategoryPath($id, 1);
        $parent = $this->parentCategory($id, 'id, title');

        if (false !== $this->db->delete('category', "id = ? AND sitekey = ? AND reserved = '0'", [$id, $this->siteID])) {
            if (!file_exists($path) || \P5\File::rmdirs($path, true)) {
                if ($this->siteProperty('type') === 'static') {
                    self::reassembly($parent);
                }
                $this->app->logger->log("Remove the category `{$id}'", 201);

                return $this->db->commit();
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * Category is empty or not.
     *
     * @param int $id
     *
     * @return bool
     */
    public function isEmpty($id)
    {
        $record_count = 0;

        $children = '(SELECT * FROM table::category WHERE sitekey = ?)';
        $parent = '(SELECT * FROM table::category WHERE id = ?)';
        if (false === $categories = $this->db->nsmGetCount($parent, $children, [$id, $this->siteID, $this->siteID])) {
            return false;
        }
        if (false === $entries = $this->db->count('entry', 'category = ?', [$id])) {
            return false;
        }

        return ((int) $categories + (int) $entries) === 0;
    }

    /**
     * Categories of Entry.
     *
     * @param int    $category
     * @param string $columns
     *
     * @return mixed
     */
    public function categories($category, $columns = '*')
    {
        $tmp = \P5\Text::explode(',', $columns);
        $cols = [];
        foreach ($tmp as $col) {
            $quot = ($col === '*') ? '' : '`';
            $cols[] = "middle.$quot$col$quot";
        }
        $columns = implode(',', $cols);

        $table = "(SELECT * FROM table::category WHERE sitekey = :site_id AND reserved = '0')";

        $root = self::rootCategory($this->db);

        return $this->db->nsmGetNodePath($columns, $table, null, null, ['site_id' => $this->siteID, $root, $category]);
    }

    /**
     * Public Category Path.
     *
     * @param int $id
     * @param int $type 0: fullpath, 1: dirname, 2: relational
     *
     * @return string
     */
    public function getCategoryPath($id, $type = 0)
    {
        $paths = $this->categories($id, 'path');
        $path = array_column($paths, 'path');

        if ($type === 2) {
            return \P5\File::realpath('/'.implode('/', $path));
        }

        //$site_data = $this->db->select('openpath, defaultpage', 'site', 'WHERE id = ?', [$this->siteID]);
        $file_name = ($type === 1) ? '' : '/'.$this->site_data['defaultpage'];

        return \P5\File::realpath($this->site_data['openpath'].'/'.implode('/', $path).$file_name);
    }

    /**
     * Public Path.
     *
     * @param int $id
     *
     * @return string
     */
    public function getEntryPath($id, $type = 0)
    {
        $sql = file_get_contents('Tms/Cms/Entry/entry_path.sql', FILE_USE_INCLUDE_PATH);
        $unit = $this->db->getAll($sql, [$id], \Tms\Db::GET_RETURN_HASH);

        $category_path = $this->getCategoryPath($unit['category'], 2);

        $static = ($type === 0) ? $this->site_data['openpath'] : '';

        return \P5\File::realpath($static.$category_path.'/'.$unit['filepath']);
    }

    /**
     * Set Current Category.
     *
     * @param int $id
     */
    protected function setCategory($id = null)
    {
        $previous = $this->session->param('current_category');
        if (is_null($id)) {
            $id = $this->site_root;
        }
        if (empty($id)) {
            return;
        }
        $this->checkPermission('cms.category.read', $this->siteID, $id);
        $this->categoryID = $id;
        $this->session->param('current_category', $this->categoryID);
        if ($previous !== $this->categoryID) {
            self::__construct();
        }
    }

    /**
     * Parent of the category
     *
     * @param int    $id
     * @param string $col
     *
     * @return array|false
     */
    public function parentCategory($id, $col = '*')
    {
        $tmp = \P5\Text::explode(',', $col);
        $columns = [];
        foreach ($tmp as $column) {
            $columns[] = 'parent.'.$column;
        }
        $columns = implode(',', $columns);

        return $this->db->nsmGetParent(
            $columns,
            '(SELECT * FROM table::category WHERE sitekey = :site_id)',
            '(SELECT * FROM table::category WHERE id = :category_id)',
            ['site_id' => $this->siteID, 'category_id' => $id]
        );
    }

    /**
     * Children of the category.
     *
     * @param int $id
     *
     * @return array|bool
     */
    public function childCategories($id, $col = '*', $depth = 0)
    {
        $tmp = \P5\Text::explode(',', $col);
        $columns = [];
        foreach ($tmp as $column) {
            $columns[] = 'children.'.$column;
        }
        $columns = implode(',', $columns);
        $columns .= ',(SELECT COUNT(*) FROM table::entry WHERE sitekey = :site_id AND category = children.id AND revision = 0 GROUP BY category) AS cnt';

        if (is_null($id)) {
            return self::rootCategory($this->db, $columns);
        }

        $parent = "(SELECT * FROM table::category WHERE id = :category_id)";
        $midparent = "(SELECT * FROM table::category WHERE sitekey = :site_id)";
        $children = $this->categoryListSQL();

        return $this->db->nsmGetChildren($columns, $parent, $midparent, $children, 'AND children.id IS NOT NULL', ['site_id' => $this->siteID, 'category_id' => $id]);
    }

    /**
     * SQL for list category.
     *
     * @return string
     */
    public function categoryListSQL()
    {
        $sql = "(SELECT * FROM table::category WHERE sitekey = ? AND reserved = '0')";
        $options = [$this->siteID];
        $permission = "SELECT * FROM table::permission WHERE userkey = ? AND application = 'cms' AND class = 'category' AND type = 'read'";
        $sql = "(SELECT c.* FROM $sql c LEFT JOIN ($permission) p ON c.id = p.filter2 WHERE p.priv != '0' OR p.priv IS NULL)";
        $options[] = $this->uid;

        return $this->db->build($sql, $options);
    }

    /**
     * Site Root category.
     *
     * @param \Tms\Db $db
     *
     * @return int
     */
    public static function rootCategory(\Tms\Db $db, $columns = 'children.id')
    {
        $siteID = \P5\Environment::session('current_site');
        if (empty($siteID)) {
            return;
        }
        $table = '(SELECT * FROM table::category WHERE sitekey = :site_id)';
        $root = $db->nsmGetRoot($columns, $table, null, ['site_id' => $siteID]);
        if ($columns === 'children.id') {
            return $root[0]['id'];
        }

        return $root;
    }

    /**
     * Build single archive page.
     *
     * @param int $category_id
     * @param bool $preview
     *
     * @return bool
     */
    public function buildArchive($category_id, $preview = false)
    {
        // If entry exists for default page
        if (method_exists($this, 'build')) {
            $release_path = $this->getCategoryPath($category_id);
            $release_file = basename($release_path);
            $eid = $this->db->get('id', 'entry', 'category = ? AND filepath = ? AND active = 1', [$category_id, $release_file]);
            if (!empty($eid)) {
                $source = $this->build($eid, $preview, true);
                return $source;
            }
        }

        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'archive');

        $category = $this->categoryData($category_id,'id,title,path,filepath,template');
        $this->view->bind('current', $category);

        $file_name = pathinfo($this->site_data['defaultpage'], PATHINFO_FILENAME);
        $file_extension = pathinfo($this->site_data['defaultpage'], PATHINFO_EXTENSION);

        $current_build_category_origin = $this->session->param('current_build_category');
        $this->session->param('current_build_category', $category['id']);

        $apps = new Response($this->app);
        $apps->pager = new \P5\Pagination();
        $suffix_separator = '.';
        $apps->pager->setSuffix($suffix_separator);
        $apps->pager->setLinkFormat(sprintf('%s%s%%s.%s', $file_name, $suffix_separator, $file_extension));

        $page = $this->request->param('current_page');
        if (empty($page)) {
            $page = 1;
        }
        $apps->pager->setCurrentPage($page);

        $this->view->bind('apps', $apps);
        $this->view->bind('build_type', $this->session->param('build_type'));

        $this->bindSiteData("{$category['url']}/");

        $template = $this->db->get(
            'sourcecode,kind,path',
            'template',
            'identifier = ? AND active = ?',
            [$category['template'], 1]
        );

        $html_class = str_replace('_', '-', $template['path']);
        $html_id = $this->pathToID($category['url']);
        if (empty($html_id)) {
            $html_id = $html_class;
        }
        $this->view->bind('html_id', $html_id);

        $sub_class = $this->pathToID($category['path']);
        $html_class .= " $sub_class";
        $this->view->bind('html_class', $html_class);

        $path = $this->templatePath($category['template']);
        $this->view->setPath(dirname($path));
        $source = $this->view->render(basename($path), $preview);

        $this->session->param('current_build_category', $current_build_category_origin);

        $this->session->param('build_type', $build_type);

        $this->page_suffix_watcher = $apps->pager->suffix($page);
        $this->page_separation_watcher = $apps->page_separation;

        return $source;
    }

    /**
     * Build archive pages.
     *
     * @param int $entry_id
     *
     * @return bool
     */
    public function buildArchives($entry_id)
    {
        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'archive');

        $category_id = $this->db->get('category', 'entry', 'id = ?', array($entry_id));
        $release_path = $this->getCategoryPath($category_id);
        $release_file = basename($release_path);
        $release_dir = dirname($release_path);
        $original_file = pathinfo($release_path, PATHINFO_FILENAME);
        $file_extension = pathinfo($release_path, PATHINFO_EXTENSION);

        $categories = $this->categories($category_id, 'id, title, path, filepath, template, archive_format');
        $categories = array_reverse($categories);
        foreach ($categories as $category) {
            $original_release_dir = $release_dir;
            $release_dir = dirname($release_dir);
            $url = $this->getCategoryPath($category['id'], 2);
            $dir = \P5\File::realpath($this->site_data['openpath'].'/'.$url);

            if (!empty($category['archive_format'])) {
                $format = preg_replace('/%[a-z]/i', '*', $category['archive_format']);
                foreach ((array)glob("$dir/$format.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }
            }

            $arr_archives_name = [];
            if (!empty($category['archive_format'])) {
                $fetch = $this->getArchivesLink($category['id']);
                foreach ((array)$fetch as $unit) {
                    $arr_archives_name[] = $unit['format'];
                }
            }
            array_unshift($arr_archives_name, $original_file);

            foreach ($arr_archives_name as $file_name) {

                // if find same name in entries
                if (method_exists($this, 'build')) {
                    $eid = $this->db->get('id', 'entry', 'category = ? AND filepath = ? AND active = 1', [$category['id'], $release_file]);
                    if (!empty($eid)) {
                        if ($eid === $entry_id) {
                            continue;
                        }
                        $source = $this->build($eid, false, true);
                        $path = sprintf('%s/%s.%s', $original_release_dir, $file_name, $file_extension);
                        if (empty($source)) {
                            if (file_exists($path)) {
                                @unlink($path);
                                $this->app->logger->log("Remove archive file `$path'", 201);
                            }
                        } else {
                            file_put_contents($path, $source);
                            $this->app->logger->log("Create archive file `$path'", 201);
                        }
                        continue;
                    }
                }

                if (empty($category['template'])) {
                    continue;
                }

                foreach ((array)glob("$dir/$file_name*.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                if ($file_name !== $original_file) {
                    $this->setArchiveMonthOfTheYear($file_name, $category['archive_format']);
                }

                $page = 1;
                do {
                    $this->request->param('current_page', $page);
                    $source = $this->buildArchive($category['id'], true);
                    $path = sprintf(
                        '%s/%s%s.%s',
                        $dir, $file_name,
                        $this->page_suffix_watcher,
                        $file_extension
                    );

                    try {
                        file_put_contents($path, $source);
                    } catch (\ErrorException $e) {
                        trigger_error($e->getMessage());
                        return false;
                    }
                    ++$page;
                } while ($this->page_separation_watcher);
                $this->request->param('amy', null, true);
            }
        }

        $this->session->param('build_type', $build_type);

        return true;
    }

    /**
     * Recent Entries.
     *
     * @param int   $row
     * @param int   $pagenation
     * @param array $filter_category
     *
     * @return array
     */
    public function recent($row = 0, $pagenation = 0, $filter_category = null)
    {
        $this->page_separation = (int) $pagenation !== 0;

        $date_option = '';
        $date_option .= " AND (release_date <= CURRENT_TIMESTAMP OR release_date IS NULL)";
        $date_option .= " AND (close_date > CURRENT_TIMESTAMP OR close_date IS NULL)";

        $statement = "sitekey = ? AND active = ?$date_option ORDER BY author_date DESC";
        $options = [$this->siteID, 1];
        $total = $this->db->count('entry', $statement, $options);

        if ($this->page_separation) {
            if (is_null($this->pager)) {
                $this->pager = new \P5\Pagination();
            }
            if (!$this->pager->isInited()) {
                $this->pager->init($total, $row);
            }
        }

        if ($this->offset + $row >= $total) {
            $this->page_separation = false;
        }

        $statement = "WHERE $statement";
        if ($row > 0) {
            $offset = ($this->offset > 0) ? $this->offset.',' : '';
            $statement .= " LIMIT $offset$row";
        }
        $this->offset += $row;

        $list = (array)$this->db->select('*', 'entry', $statement, $options);

        foreach ($list as $unit) {
            $unit['url'] = \P5\Http::realuri($this->site_data['path'].$this->getEntryPath($unit['id'], 2));
            $unit['html_id'] = $this->pathToID($unit['url']);
        }
        unset($unit);

        return $list;
    }

    /**
     * list entries
     *
     * @param string $filter
     * @param int    $recursive
     * @param int    $row
     * @param int    $offset
     * @param int    $pagenation
     *
     * @return array
     */
    public function entries($filter = '', $recursive = 0, $row = 0, $offset = 0, $pagenation = 0, $current_page = null, $sort = 'ASC', $chroot = null)
    {
        if (!is_null($current_page)) {
            $this->offset = ($current_page - 1) * $row;
        }

        $statement = 'sitekey = ? AND active = ?';
        $options = [$this->siteID, 1];

        if (empty($chroot)) {
            $chroot = $this->session->param('current_build_category');
        }
        $stat = "WHERE sitekey = ?";
        $opt = [$this->siteID];

        if (!empty($filter)) {
            $range = $this->db->get('lft,rgt', 'category', 'id=?', [$chroot]);
            $stat .= " AND (lft >= ? AND rgt <= ?)";
            $opt[] = $range['lft'];
            $opt[] = $range['rgt'];
            $filters = \P5\Text::explode(',', $filter);
            $placeholder = implode(',', array_fill(0, count($filters), '?'));
            $stat .= " AND (id IN ($placeholder) OR path IN($placeholder) OR title IN($placeholder))";
            $opt = array_merge($opt, $filters, $filters, $filters);
        } else {
            $stat .= " AND id = ?";
            $opt[] = $chroot;
        }

        $ids = $this->db->select('id', 'category', $stat, $opt);
        $categories = array_column((array)$ids, 'id');

        if ((bool)$recursive) {
            $child_categories = [];
            foreach ($categories as $category) {
                $parent = '(SELECT * FROM table::category WHERE id = :category_id)';
                $children = 'table::category';
                if (false !== $children = $this->db->nsmGetDecendants('children.id', $parent, $children, ['category_id' => $category])) {
                    foreach ($children as $unit) {
                        $child_categories[] = $unit['id'];
                    }
                } else {
                    trigger_error($this->db->error());
                }
            }
            $categories = array_merge($categories, $child_categories);
        }

        if (count($categories) === 1) {
            $statement .= ' AND category = ?';
            $options[] = $categories[0];
        } elseif (count($categories) > 1) {
            $categories = array_values(array_unique($categories));
            $statement .= ' AND category IN('.implode(',', array_fill(0, count($categories), '?')).')';
            $options = array_merge($options, $categories);
        }

        if (!empty($this->request->param('aby'))) {
            $year = (int)$this->request->param('aby');
            $statement .= " AND author_date >= '$year-01-01 00:00:00' AND author_date <= '$year-12-31 23:59:59'";
        }

        if (!empty($this->request->param('amy'))) {
            $date = explode('-', $this->request->param('amy'));
            $year = array_shift($date);
            $start_month = '01';
            $end_month = '12';
            $start_day = '01';
            $end_day = '31';
            if (!empty($date)) {
                $start_month = array_shift($date);
                $end_month = $start_month;
                $end_day = date('t', strtotime("$year-$start_month"));
                if (!empty($date)) {
                    $start_day = array_shift($date);
                    $end_day = $start_day;
                }
            }
            $statement .= " AND author_date >= '$year-$start_month-$start_day 00:00:00' AND author_date <= '$year-$end_month-$end_day 23:59:59'";
        }

        // TODO: These statements are toggle by argument
        $statement .= " AND body IS NOT NULL AND body != ''";
        $statement .= " AND (release_date <= CURRENT_TIMESTAMP OR release_date IS NULL)";
        $statement .= " AND (close_date > CURRENT_TIMESTAMP OR close_date IS NULL)";

        $statement .= " ORDER BY author_date $sort";

        $this->page_separation = (int) $pagenation !== 0;
        $total = $this->db->count('entry', $statement, $options);
        if ($this->page_separation) {
            if (is_null($this->pager)) {
                $this->pager = new \P5\Pagination();
            }
            if (!$this->pager->isInited()) {
                $this->pager->init($total, $row);
                if (!is_null($current_page)) {
                    $this->pager->setCurrentPage($current_page);
                }
            } elseif ($this->pager->total() !== $total) {
                $this->pager->reset($total);
            }
        }
        if ($this->offset + $row >= $total) {
            $this->page_separation = false;
        }
        if ($row > 0) {
            $offset = ($this->offset > 0) ? $this->offset.',' : '';
            $statement .= " LIMIT $offset$row";
        }
        $this->offset += $row;

        $list = (array)$this->db->select('*', 'entry', "WHERE $statement", $options);
        foreach ($list as &$unit) {
            $unit['url'] = \P5\Http::realuri($this->site_data['path'].$this->getEntryPath($unit['id'], 2));
            $unit['html_id'] = $this->pathToID($unit['url']);

            // Custom fields
            $customs = $this->db->select(
                'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $unit['identifier'], 'entry']
            );
            foreach ((array)$customs as $custom) {
                $unit[$custom['name']] = $custom['data'];
            }

        }
        unset($unit);

        return (empty($list)) ? null : $list;
    }

    /**
     * Children of the section.
     *
     * @param int   $entrykey
     * @param int   $sectionkey
     * @param array $columns
     *
     * @return array
     */
    public function sections($entrykey, $sectionkey, array $columns = null, $sort = 'ASC')
    {
        $cols = ['children.id AS relkey'];
        if (is_null($columns)) {
            $cols[] = 'children.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "children.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $statement = $this->filterPreview();
        $order = " ORDER BY children.author_date $sort";

        if (is_null($sectionkey)) {
            $table = "(SELECT * FROM table::section WHERE entrykey = :entry_id$statement ORDER BY author_date)";
            $list = (array)$this->db->nsmGetRoot($columns, $table, null, ['entry_id' => $entrykey], $order);
        }
        else {
            $parent = '(SELECT * FROM table::section WHERE id = :section_id)';
            $children = "(SELECT * FROM table::section WHERE entrykey = :entry_id$statement)";
            $list = (array)$this->db->nsmGetChildren($columns, $parent, $children, $children, " AND children.id IS NOT NULL$order", ['entry_id' => $entrykey, 'section_id' => $sectionkey]);
        }

        // custom data
        foreach  ($list as &$data) {
            $customs = (array)$this->db->select(
                'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $data['relkey'], 'section']
            );
            foreach ($customs as $unit) {
                $data[$unit['name']] = $unit['data'];
            }
            unset($data['relkey']);
        }
        unset($data);

        return $list;
    }

    /**
     * Single attachment file.
     *
     * @param int    $entrykey
     * @param string $kind
     * @param string $filter
     *
     * @return mixed
     */
    public function attachment($entrykey, $kind = 'entry', $filter = '')
    {
        $ret = $this->attachments($entrykey, $kind, $filter, 1);
        if (!empty($ret)) {
            return array_shift($ret);
        }
    }

    /**
     * Attachment files.
     *
     * @param int    $entrykey
     * @param string $kind
     * @param string $filter
     * @param int    $limit
     *
     * @return mixed
     */
    public function attachments($entrykey, $kind = 'entry', $filter = '', $limit = null)
    {
        if (!is_null($limit) && preg_match('/^[0-9]+(\s*,\s*[0-9]+)?$/', $limit)) {
            $limit = " LIMIT $limit";
        }

        $statement = 'WHERE sitekey = ? AND kind = ? AND name LIKE ?';
        $options = [$this->siteID, $kind, 'file.%', $entrykey];
        if ($this->session->param('ispreview') === 1) {
            $statement .= ' AND relkey IN(?,?)';
            $options[] = '0';
        } else {
            $statement .= ' AND relkey = ?';
        }

        if ($this->session->param('ispreview') === 1) {
            $statement = "WHERE sitekey = ? AND kind = ? AND relkey IN(?,?) AND name LIKE ?";
            $options = [$this->siteID, $kind, 0, $entrykey, 'file.%'];
        }

        $list = (array)$this->db->select(
            'id, data AS path, mime, alternate, note', 'custom',
            "$statement ORDER BY `sort`$limit", $options
        );

        $upload_dir = \P5\File::realpath('/'.$this->site_data['uploaddir']);
        $dir = $this->site_data['openpath'];
        foreach ($list as &$data) {

            // Thumbnail
            $thumbnails = glob("$dir{$data['path']}*".parent::THUMBNAIL_EXTENSION);
            $path = dirname($data['path']).'/';
            foreach ($thumbnails as $thumbnail) {
                if (!isset($data['thumbnail'])) {
                    $data['thumbnail'] = [];
                }
                $data['thumbnail'][] = $path.basename($thumbnail);
            }

            if ($this->session->param('ispreview') === 1) {
                $basename = basename($data['path']);
                $preview_file = "$upload_dir/preview/$basename";
                if (file_exists("$dir/$preview_file")) {
                    $data['path'] = $preview_file;

                    // Thumbnail
                    $thumbnails = glob("$dir/$preview_file*".parent::THUMBNAIL_EXTENSION);
                    $path = dirname($preview_file);
                    foreach ($thumbnails as $thumbnail) {
                        if (!isset($data['thumbnail'])) {
                            $data['thumbnail'] = [];
                        }
                        $data['thumbnail'][] = $path.basename($thumbnail);
                    }
                }
            }
        }
        unset($data);

        return (empty($list)) ? null : $list;
    }

    /**
     * Children of the section.
     *
     * @param int   $eid
     * @param int   $id
     * @param array $columns
     *
     * @return array
     */
    public function childSections($eid, $id, array $columns = null)
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'children.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "children.`$column`";
            }
        }
        $columns = implode(',', $cols);

        if (is_null($id)) {
            $table = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0 ORDER BY author_date)';

            return $this->db->nsmGetRoot($columns, $table, null, ['entry_id' => $eid]);
        }

        $parent = '(SELECT * FROM table::section WHERE id = :section_id)';
        $children = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0)';

        return $this->db->nsmGetChildren($columns, $parent, $children, $children, ' AND children.id IS NOT NULL ORDER BY children.author_date', ['entry_id' => $eid, 'section_id' => $id]);
    }

    /**
     * List of categories
     *
     * @param string $filter
     * @param string $wildcard
     * @param string $columns
     *
     * @return array
     */
    public function categoriesList($filter = '', $wildcard = '', $columns = 'id')
    {
        $statement = "WHERE sitekey = ?";
        $options = [$this->siteID];

        if (!empty($filter)) {
            $filters = \P5\Text::explode(',', $filter);
            $placeholder = implode(',', array_fill(0, count($filters), '?'));
            $where = "id IN($placeholder) OR path IN($placeholder) OR title IN($placeholder)";
        }

        if (!empty($wildcard)) {
            $filters = [str_replace('*', '%', $wildcard)];
            $where = "path LIKE ? OR filepath LIKE ? OR title LIKE ?";
        }
        $statement = "WHERE sitekey = ? AND ($where)";
        $options = array_merge(array($this->siteID), $filters, $filters, $filters);
        $ids = $this->db->select($columns, 'category', $statement, $options);
        if ($ids === false) {
            trigger_error($this->db->error());
        }

        $list = [];
        foreach ($ids as $i) {
            if ($columns !== 'id') {
                $list[] = $i;
                continue;
            }
            $data = $this->childCategories($i['id']);
            foreach ($data as $n => $unit) {
                if (!empty($unit['template'])) {
                    $unit['url'] = $this->getCategoryPath($unit['id'], 2).'/';
                }
                $data[$n] = $unit;
            }
            $list = array_merge($list, $data);
        }

        return $list;
    }

    /**
     * Detail of the category
     *
     * @param int    $id
     * @param string $columns
     *
     * @return array
     */
    public function categoryData($id, $columns = '*')
    {
        $data = [];
        $fetch = $this->db->select($columns, 'category', 'WHERE id = ?', [$id]);
        if (count((array) $fetch) > 0) {
            $data = $fetch[0];
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$this->siteID, $id, 'category']
        );
        foreach ((array) $customs as $unit) {
            $data[$unit['name']] = $unit['data'];
        }

        $data['url'] = $this->getCategoryPath($data['id'], 2).'/';
        $data['html_id'] = $this->pathToID($data['url']);

        return $data;
    }

    /**
     *  in the category
     *
     * @param int    $id
     * @param string $path
     *
     * @return bool
     */
    public function inCategory($id, ...$patterns)
    {
        $method = 'getCategoryPath';
        $path = call_user_func_array([$this, $method], [$id, 2]);
        foreach ($patterns as $pattern) {
            $regex = str_replace(['\\*','\\?'], ['[^\/]*','.?'], preg_replace('/\\*\\*(\\\\\/)?/', '.+', preg_quote($pattern, '/')));
            if (preg_match("/^$regex$/i", $path)) {
                return true;
            }
        }

        return false;
    }
    public function _inCategory($id, $path, $type = 'category')
    {
        $method = ($type === 'category') ? 'getCategoryPath' : 'getEntryPath';
        $fullpath = call_user_func_array([$this, $method], [$id, 2]);
        if (strpos($path, '/*') !== false) {
            $path = preg_quote(str_replace('/*', '', $path), '/').'(\/[^\/]+)?';

            return preg_match("/^$path$/i", $fullpath);
        }

        return strpos($fullpath, $path) === 0;
    }

    /**
     *  not in the category
     *
     * @param int    $id
     * @param string $path
     *
     * @return bool
     */
    public function notInCategory($id, ...$patterns)
    {
        return !call_user_func_array([$this,'inCategory'], func_get_args());
    }


    /**
     * Save the custom data
     *
     * @param string $kind
     * @param int    $id
     * @param array  $data
     *
     * @return int
     */
    public function saveCustomField($kind, $id, $data)
    {
        $total = 0;
        if (empty($data)) {
            return $total;
        }
        foreach ($data as $key => $value) {
            $unit = [
                'sitekey' => $this->siteID,
                'relkey' => $id,
                'kind' => $kind,
                'name' => $key,
                'mime' => 'text/plain',
                'data' => $value,
            ];
            if (false === $count = $this->db->updateOrInsert('custom', $unit, ['sitekey', 'relkey', 'kind', 'name'])) {
                trigger_error($this->db->error());

                return false;
            }
            $total += $count;
        }

        return $total;
    }

    /**
     * List of bread crumbs
     *
     * @param int $id
     * @param bool $all
     *
     * @return array
     */
    public function breadcrumbs($id, $all = false)
    {
        $build_type = $this->session->param('build_type');

        if ($build_type === 'entry') {
            $entry_path = $this->getEntryPath($id);
            $dir = dirname($entry_path);
            $basename = basename($entry_path);
            $entry = $this->db->get('id,category,filepath AS path,title', 'entry', 'id = ?', [$id]);
            $categorykey = $entry['category'];
        } else {
            $categorykey = $id;
        }
        $categories = $this->categories($categorykey, 'id, path, title, template');
        foreach ($categories as $n => $category) {
            if (empty($category['template'])) {
                $statement = 'sitekey = ? AND category = ? AND filepath LIKE ?'.$this->filterPreview();
                $replaces = [$this->siteID, $category['id'], 'index.htm%'];
                if ((bool)$all === false && !$this->db->exists('entry', $statement, $replaces)) {
                    $categories[$n] = null;
                    continue;
                }
            } else {
                $category['url'] = $this->getCategoryPath($category['id'], 2).'/';
            }
            $categories[$n] = $category;
        }

        if ($build_type === 'entry' && strpos($basename, 'index.htm') === 0) {
            array_pop($categories);
        }

        // Unset site root
        array_shift($categories);
        if ($build_type === 'entry') {
            $categories[] = $entry;
        }

        return array_values(array_filter($categories));
    }

    /**
     * Filter of data for preview
     *
     * @return string
     */
    protected function filterPreview()
    {
        return ((int)$this->session->param('ispreview') === 1) ? ' AND revision = 0' : ' AND active = 1';
    }

    /**
     * Reassemble the category.
     */
    protected function reassembly()
    {
        if ($this->siteProperty('type') !== 'static') {
            return true;
        }

        $all = false;
        try {
            $category_id = func_get_arg(0);
        } catch (\ErrorException $e) {
            $category_id = self::rootCategory($this->db);
            $all = true;
        }

        $range = $this->db->get('lft,rgt', 'category', 'id = ?', [$category_id]);
        $categories = $this->db->select('id,template,path,archive_format', 'category', 'WHERE lft >= ? AND rgt <= ?', [$range['lft'], $range['rgt']]);

        $original_file = pathinfo($this->site_data['defaultpage'], PATHINFO_FILENAME); 
        $file_extension = pathinfo($this->site_data['defaultpage'], PATHINFO_EXTENSION); 

        foreach ($categories as $category) {
            if (empty($category['template'])) {
                continue;
            }

            $url = $this->getCategoryPath($category['id'], 2);
            $dir = \P5\File::realpath($this->site_data['openpath'].'/'.$url);
            $directory_exists = file_exists($dir);

            $arr_archives_name = [];
            if (!empty($category['archive_format'])) {

                $format = preg_replace('/%[a-z]/i', '*', $category['archive_format']);
                foreach ((array)glob("$dir/$format.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                $fetch = $this->getArchivesLink($category['id']);
                foreach ((array)$fetch as $unit) {
                    $arr_archives_name[] = $unit['format'];
                }
            }
            array_unshift($arr_archives_name, $original_file);

            foreach ($arr_archives_name as $file_name) {

                foreach (glob("$dir/$file_name*.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                if ($file_name !== $original_file) {
                    $this->setArchiveMonthOfTheYear($file_name, $category['archive_format']);
                }

                $page = 1;
                do {
                    $this->request->param('current_page', $page);
                    $source = $this->buildArchive($category['id'], true);
                    $path = sprintf(
                        '%s/%s%s.%s',
                        $dir, $file_name,
                        $this->page_suffix_watcher,
                        $file_extension
                    );

                    if (!$directory_exists) {
                        try {
                            mkdir($dir, 0777, true);
                            $directory_exists = true;
                        } catch (\ErrorException $e) {
                            trigger_error($e->getMessage());
                            return false;
                        }
                    }

                    if (false === file_put_contents($path, $source)) {
                        trigger_error("Can't assembled $path");

                        return false;
                    }
                    ++$page;
                } while ($this->page_separation_watcher);
            }
        }

        if (method_exists($this, 'createEntryFile')) {
            $filter = [$this->siteID];
            $placeholder = '';
            if ($all === false) {
                $categories = array_column($categories, 'id');
                $placeholder = ' AND category IN('.implode(',', array_fill(0, count($categories), '?')).')';
                $filter = array_merge($filter, $categories);
            }
            $entries = (array) $this->db->select(
                'id', 'entry',
                "WHERE sitekey = ? AND active = 1$placeholder",
                $filter
            );
            foreach ($entries as $entry) {
                $id = $entry['id'];
                if (false === $this->createEntryFile($id)) {
                    trigger_error('Failure assemble '.$id);
                    continue;
                }
            }
        }

        return true;
    }

    public function archivesByYear($category_id = null)
    {
        $options = [1];
        $filter = (empty($category_id)) ? '' : ' AND category = ?';
        if ($filter !== '') {
            $options[] = $category_id;
        }
        $sql = $this->db->build(
            "SELECT DATE_FORMAT(author_date, '%Y') AS year
               FROM table::entry
              WHERE active = ? $filter
              GROUP BY year
              ORDER BY year DESC",
            $options
        );

        if (false !== $this->db->query($sql)) {
            return $this->db->fetchAll();
        }
    }

    protected function moveCategory($self, $new_parent)
    {
        $old_parent = $this->parentCategory($self, 'id');
        if ($old_parent !== $new_parent && $self !== $new_parent) {
            $lftrgt = $this->db->select('lft,rgt', 'category', 'WHERE id = ?', [$self]);
            $lft = (float)$lftrgt[0]['lft'];
            $rgt = (float)$lftrgt[0]['rgt'];
            $offset = $rgt - $lft + 1;

            $update_parent = $this->db->prepare(
                $this->db->nsmBeforeInsertChildSQL('category')
            );

            $parent_rgt = $this->db->get('rgt', 'category', 'id = ?', [$new_parent]);
            if (false === $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => $offset])) {
                return false;
            }

            $lftrgt = $this->db->select('lft,rgt', 'category', 'WHERE id = ?', [$self]);
            $lft = (float)$lftrgt[0]['lft'];
            $rgt = (float)$lftrgt[0]['rgt'];

            $update_self = $this->db->prepare(
                "UPDATE table::category
                    SET lft = lft + :offset,
                        rgt = rgt + :offset
                  WHERE lft >= :lft AND rgt <= :rgt"
            );
            $offset = $parent_rgt - $lft;
            if (false === $update_self->execute(['offset' => $offset, 'lft' => $lft, 'rgt' => $rgt])) {
                return false;
            }

            if (false === $this->db->nsmCleanup('category')) {
                return false;
            }
        }

        return true;
    }

    protected function bindSiteData($path)
    {
        $site = $this->site_data;
        $path = preg_replace('/^'.preg_quote($site['path'],'/').'/', '', $path);
        $dir = dirname("$path.");
        if ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $dirs = explode('/', $dir);
            $depth = array_fill(0, count($dirs), '..');
            $site['relative'] = implode('/', $depth).'/';
        }
        $this->view->bind('site', $site);
    }

    private function setArchiveMonthOfTheYear($file_name, $archive_format)
    {
        $year = date('Y');
        if (preg_match_all('/%[a-z]/i', $archive_format, $params)) { 
            $pattern = preg_replace('/%[a-z]/i', '(.+)', $archive_format);
            preg_match("/$pattern/", $file_name, $matches);
            foreach ($params[0] as $i => $value) {
                if ($value === '%Y' || $value === '%y') {
                    $year = $matches[$i+1];
                }
                else {
                    $month = $matches[$i+1];
                }
            }
        }
        
        $date_format = 'Y-m';
        if (empty($month)) {
            $date_format = 'Y';
            $month = date('m');
        }
        $this->request->param('amy', date($date_format, strtotime(implode('-', array_filter([$year, $month])))));
    }

    /**
     * Interface for View class
     *
     * @param int $category_id
     *
     * @return array
     */
    public function archivesLink($category_id, $sort = 'ASC')
    {
        $file_extension = pathinfo($this->site_data['defaultpage'], PATHINFO_EXTENSION); 
        $url = $this->getCategoryPath($category_id, 2);
        $fetch = (array)$this->getArchivesLInk($category_id, $sort);
        foreach ($fetch as &$unit) {
            $unit['url'] = "$url/{$unit['format']}.$file_extension"; 
        }
        unset($unit);

        return $fetch;
    }

    private function getArchivesLink($category_id, $sort = 'ASC')
    {
        $archive_format = $this->db->get('archive_format', 'category', 'id = ?', [$category_id]);
        if (empty($archive_format)) {
            return;
        }

        $order = '';
        if (!empty($sort) && in_array(strtoupper($sort), ['ASC','DESC'])) {
            $order = " ORDER BY author_date $sort";
        }

        return $this->db->getAll(
            "SELECT DATE_FORMAT(author_date, ?) AS format, MIN(author_date) AS author_date
               FROM table::entry
              WHERE sitekey = ? AND category = ? AND active = ?
              GROUP BY format",
            [$archive_format, $this->siteID, $category_id, 1]
        );
    }
}
