<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Site extends \Tms\Cms
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

    const DEFAULT_UPLOAD_DIR = 'upload';

    /**
     * Current site ID.
     *
     * @var int
     */
    private $siteID;

    /**
     * Current site data.
     *
     * @var array
     */
    private $site_data;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->siteID = $this->session->param('current_site');
        if (!empty($this->siteID)) {
            $this->site_data = $this->loadSiteData($this->siteID);
            $this->view->bind('site', $this->site_data);
        }
    }

    protected function loadSiteData($id)
    {
        $site_data = $this->db->get('*', 'site', 'id = ?', [$id]);

        $url = parse_url($site_data['url']);
        if (empty($url['path'])) {
            $url['path'] = '/';
        }
        $site_data['path'] = $url['path'];

        if (empty($site_data['uploaddir'])) {
            $site_data['uploaddir'] = self::DEFAULT_UPLOAD_DIR;
        }

        if (empty($site_data['openpath'])) {
            $site_data['openpath'] = $this->app->cnf('global:docroot') . $site_data['path'];
        }

        $site_data['uploaddir'] = trim($site_data['uploaddir'],'/');
        $site_data['styledir'] = trim($site_data['styledir'],'/');

        $site_data['owner'] = $this->ownerInfo($id);

        return $site_data;
    }

    /**
     * Inserted or Updated the site data.
     *
     * Direct call of this function is prohibited
     *
     * @return bool
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('site.'.$check, $id);

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_url', 'url', 'empty'];
        if (false === $this->validate($valid)) {
            return false;
        }

        $table = 'site';
        $post = $this->request->post();

        // Check writable
        if (empty($post['openpath'])) {
            $url = parse_url($post['url']);
            if (empty($url['path'])) {
                $url['path'] = '/';
            }
            $post['openpath'] = $this->app->cnf('global:docroot').'/'.ltrim($url['path'],'/');
            $this->request->param('openpath', $post['openpath']);
        }
        if (false === \P5\File::mkdir($post['openpath'])) {
            $this->app->err['vl_openpath'] = 2;
            return false;
        }

        $skip = ['id', 'userkey', 'create_date', 'modify_date'];
        if (!$this->hasPermission('cms.site.create')) {
            $skip = array_merge($skip, ['openpath', 'maskdir', 'maskfile', 'maskexec']);
        }

        $save = $this->createSaveData($table, $post, $skip, 'implode');
        if (!empty($post['userkey']) && $this->isAdmin()) {
            $save['userkey'] = $post['userkey'];
        }
        $raw = null;

        $this->db->begin();

        if (empty($post['id'])) {
            if (!isset($save['userkey'])) {
                $save['userkey'] = $this->uid;
            }
            $raw = ['create_date' => 'CURRENT_TIMESTAMP'];
            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
                $escape_data = $this->site_data;
                $this->site_data = $this->loadSiteData($post['id']);
                if (false === $this->createRootCategory($post['id'])) {
                    $result = false;
                }
                $this->site_data = $escape_data;
            }
        } else {
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                // If there is a need to do something after saving
                // write here.
            } else {
                $result = false;
            }
            if ($result === 0) {
                $this->app->err['vl_nochange'] = 1;
            } elseif ($result !== false) {
                return $this->db->commit();
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * Change current site.
     */
    protected function changeSite()
    {
        $id = $this->request->post('choice');
        $this->checkPermission('cms.site.read', $id);
        if ($this->siteID === $id) {
            return;
        }

        $this->session->param('current_site', $id);
        $this->session->clear('current_category');
        $this->app->logger->log("Selected site `{$id}'");
        $this->siteID = $id;
        $this->site_data = $this->loadSiteData($id);
    }

    /**
     * Reference Templates Directory.
     *
     * @param int $sitekey
     * @param int $kind
     *
     * @return string
     */
    public function templateDir($sitekey = null, $kind = null)
    {
        $sitekey = (empty($sitekey)) ? $this->siteID : $sitekey;
        if (empty($sitekey)) {
            return;
        }

        if ($kind === 6) {
            $path = implode(
                '/',
                array_filter([
                    rtrim($this->site_data['openpath'],'/'),
                    trim($this->site_data['styledir'],'/')
                ])
            );
        }
        else {
            $path = $this->app->cnf('global:data_dir')."/mirror/$sitekey/templates";
        }

        if (!is_dir($path)) {
            \P5\File::mkdir($path);
        }

        return $path;
    }

    /**
     * Reference Templates Path.
     *
     * @param int $id
     * @param int $sitekey
     *
     * @return string
     */
    public function templatePath($id, $sitekey = null)
    {
        $sitekey = (empty($sitekey)) ? $this->siteID : $sitekey;
        if (empty($sitekey)) {
            return;
        }
        $unit = $this->db->select('path,kind', 'template', 'WHERE identifier=? AND active=?', [$id, 1]);
        if (isset($unit[0])) {
            $dir = $this->templateDir($sitekey, (int)$unit[0]['kind']);
            $extension = ($unit[0]['kind'] === '6') ? 'css' : 'tpl';
            return "$dir/{$unit[0]['path']}.$extension";
        }
    }

    /**
     * Create Root Category.
     *
     * @param int $sitekey
     *
     * @return bool
     */
    private function createRootCategory($sitekey)
    {
        if (false === $template = $this->createDefaultTemplate($sitekey)) {
            return false;
        }

        $previous_rgt = (int)$this->db->max('rgt', 'category');
        $save = [
            'sitekey' => $sitekey,
            'userkey' => \Tms\User::getUserID($this->db),
            'template' => $template,
            'path' => '/',
            'title' => 'Site Root',
            'lft' => $previous_rgt + 1,
            'rgt' => $previous_rgt + 2,
        ];
        $raw = [
            'create_date' => 'CURRENT_TIMESTAMP',
            'modify_date' => 'CURRENT_TIMESTAMP',
        ];

        return $this->db->insert('category', $save, $raw);
    }

    /**
     * Create Index Template.
     *
     * @param int $sitekey
     *
     * @return mixed
     */
    protected function createDefaultTemplate($sitekey)
    {
        $escape_include_path = ini_get('include_path');
        $templates_dir = \Tms\View::TEMPLATE_DIR_NAME;
        $my_include_path = [
            $this->app->cnf('global:data_dir')."/$templates_dir",
            realpath(__DIR__."/../$templates_dir")
        ];
        ini_set('include_path', implode(PATH_SEPARATOR, $my_include_path));

        try {
            $xml_source = file_get_contents('cms/default_templates.xml', FILE_USE_INCLUDE_PATH);
            if (false === $xml = simplexml_load_string($xml_source)) {
                throw new \ErrorException('Failed to parse XML');
            }
        } catch (\ErrorException $e) {
            ini_set('include_path', $escape_include_path);
            $message = $e->getMessage();
            if (stripos($message, 'No such file or directory') !== false) {
                return true;
            }
            echo $message;
            return false;
        }
        ini_set('include_path', $escape_include_path);

        $root_template = null;
        foreach ($xml->template as $unit) {
            $save = [
                'sitekey' => $sitekey,
                'title' => $unit->title,
                'sourcecode' => $unit->sourcecode,
                'revision' => 0,
                'active' => $unit->active,
                'kind' => $unit->kind,
                'path' => $unit->path,
            ];
            $raw = [
                'create_date' => 'CURRENT_TIMESTAMP',
                'modify_date' => 'CURRENT_TIMESTAMP',
            ];
            if (false === $this->db->insert('template', $save, $raw)) {
                return false;
            }
            $id = $this->db->lastInsertId(null, 'id');
            if (false === $this->db->update('template', ['identifier' => $id], 'id = ?', [$id])) {
                return false;
            }

            // build template file
            if ((string)$unit->active === '1') {
                $save['id'] = $id;
                if (false === $this->buildTemplate($save, true, $sitekey)) {
                    return false;
                }
            }

            if ((string)$unit->attributes()->root === '1') {
                $root_template = $id;
            }
        }

        return $root_template;
    }

    /**
     * pickup site owners.
     *
     * @param \Tms\Db $db
     * @param int     $uid
     *
     * @return mixed
     */
    public static function siteOwners(\Tms\Db $db, $uid)
    {
        $root = $db->nsmGetRoot('children.lft', $db->TABLE('user'));
        $parent_lft = $root[0]['lft'];
        $owners = [$uid];
        if (false !== $ret = $db->nsmGetParents($uid, 'parent.id', $db->TABLE('user'), null, $parent_lft)) {
            foreach ((array) $ret as $unit) {
                $owners[] = $unit['id'];
            }
        }

        return $owners;
    }

    /**
     * Site owner.
     *
     * @param int $sitekey
     *
     * @return bool
     */
    public function isOwner($sitekey)
    {
        $owner = $this->siteProperty('userkey');
        if (empty($owner)) {
            $owner = $this->db->get('userkey', 'site', 'id=?', [$sitekey]);
        }
        return $owner === $this->uid;
    }

    /**
     * Site owner detail
     *
     * @param int sitekey
     *
     * @return mixed
     */
    public function ownerInfo($sitekey)
    {
        $owner = $this->db->get('userkey', 'site', 'id = ?', [$sitekey]);
        $owner_info = $this->db->get(
            'id,email,company,division,fullname,fullname_rubi,url,zip,state,city,town,address1,address2,tel,fax',
            'user', 'id = ?', [$owner]
        );

        // Aliases

        return $owner_info;
    }

    /**
     * filtered by permission.
     *
     * @param \Tms\Db $db
     * @param int     $uid
     *
     * @return mixed
     */
    public static function filteredSite(\Tms\Db $db, $userkey)
    {
        $filtered = [];
        $ret = $db->select(
            'filter1', 'permission',
            'WHERE userkey = ? AND application = ? AND class = ? AND type = ? AND priv = ?',
            [$userkey, 'cms', 'site', 'read', 1]
        );
        if ($ret) {
            foreach ((array) $ret as $unit) {
                $filtered[] = $unit['filter1'];
            }
        }

        return $filtered;
    }

    /**
     * Remove the data.
     */
    protected function remove()
    {
        $this->db->begin();
        $sitekey = $this->request->param('id');
        $this->checkPermission('cms.site.delete', $sitekey);

        $site_data = $this->loadSiteData($sitekey);

        if (false === $this->db->delete('section', 'sitekey = ?', [$sitekey])) {
            return false;
        }
        if (false === $this->db->delete('entry', 'sitekey = ?', [$sitekey])) {
            return false;
        }
        if (false === $this->db->delete('category', 'sitekey = ?', [$sitekey])) {
            return false;
        }
        if (false === $this->db->delete('template', 'sitekey = ?', [$sitekey])) {
            return false;
        }
        if (false === $this->db->delete('site', 'id = ?', [$sitekey])) {
            return false;
        }

        $remove_dirs = [];
        if ($site_data['path'] === '/') {
            $remove_dirs[] = implode(
                DIRECTORY_SEPARATOR,
                array_filter([
                    $site_data['openpath'],
                    $site_data['uploaddir']
                ])
            );
            $remove_dirs[] = $this->templateDir($sitekey, 6);
        }
        else {
            $remove_dirs[] = $site_data['openpath'];
        }
        foreach ($remove_dirs as $remove_dir) {
            try {
                \P5\File::rmdirs($remove_dir, true);
            } catch (\ErrorException $e) {
                if (count(glob("$remove_dir/*")) > 0) {
                    return false;
                }
            }
        }

        try {
            \P5\File::rmdirs($this->app->cnf('global:data_dir').'/mirror/'.$sitekey, true);
            $results = $this->app->execPlugin('afterRemoveCmsSite', $sitekey);
            foreach ((array)$results as $result) {
                if ($result === false) {
                    throw new \ErrorException('Some error in exec plugins');
                }
            }
        } catch (\ErrorException $e) {
            return false;
        }

        if ($this->session->param('current_site') === $sitekey) {
            $this->session->clear('current_site');
        }

        return $this->db->commit();
    }

    public function commonTemplate($title)
    {
        $statement = 'sitekey = ? AND kind = ? AND title = ?';
        $statement .= ($this->session->param('ispreview') === 1) ? ' AND revision = 0' : ' AND active = 1';
        $source = $this->db->get('sourcecode', 'template', $statement, [$this->siteID, 5, $title]);
        if (empty($source)) {
            $source = '<!-- '.htmlspecialchars($title).' is not found -->';
        }

        return $source;
    }

    public function siteProperty($key = null)
    {
        if (is_null($key)) {
            return $this->site_data;
        }

        return (isset($this->site_data[$key])) ? $this->site_data[$key] : null;
    }

    /**
     * Checking permission.
     *
     * @see Tms\User::checkPermission()
     *
     * @param string $type
     * @param int    $filter1
     * @param int    $filter2
     */
    protected function checkPermission($type, $filter1 = null, $filter2 = null)
    {
        $options = array_values(parent::parsePermissionKey($type));
        if (   $this->session->param('uname') === 'guest'
            && in_array(array_pop($options), array('read','exec'))
        ) {
            return true;
        }

        if (   $type === 'create'
            && !$this->isRoot()
            && $this->app->cnf('application:cms_site_creator') === 'rootonly'
        ) {
            return false;
        }

        parent::checkPermission($type, $filter1, $filter2);
    }

    protected function fileUploadDir($entrykey = null, $sectionkey = null)
    {
        $path = implode(
            DIRECTORY_SEPARATOR,
            array_filter([
                rtrim($this->site_data['openpath'], '/'),
                rtrim($this->site_data['uploaddir'], '/'),
                $entrykey,
                $sectionkey
            ])
        );

        return $path;
    }
}
