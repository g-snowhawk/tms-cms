<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Cms extends User implements PackageInterface
{
    /*
     * Using common accessor methods
     */
    use Accessor;

    /**
     * Application default mode.
     */
    const DEFAULT_MODE = 'cms.site.response';
    const USER_EDIT_EXTENDS = '\\Tms\\Cms\\Category';
    const THUMBNAIL_EXTENSION = '.jpg';

    protected $command_convert = null;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        if (class_exists('Imagick')) {
            $this->command_convert = 'imagick';
        }
        else {
            $convert = $this->app->cnf('external_command:convert');
            if (!empty($convert)) {
                $disable_functions = \P5\Text::explode(',', ini_get('disable_functions'));
                if (!in_array('exec', $disable_functions)) {
                    exec('convert --version', $output, $status);
                    if ($status === 0) {
                        $this->command_convert = $convert;
                    }
                }
            }
        }
    }

    /**
     * Default Mode
     *
     * @final
     * @param Tms\App $app
     *
     * @return string
     */
    final public static function getDefaultMode($app)
    {
        $mode = $app->cnf('application:default_mode');
        return ($mode === 'cms.dynamic') ? $mode : self::DEFAULT_MODE;
    }

    /**
     * This package name
     *
     * @final
     *
     * @return string
     */
    final public static function packageName()
    {
        return strtolower(stripslashes(str_replace(__NAMESPACE__, '', __CLASS__)));
    }

    /**
     * Application name
     *
     * @final
     *
     * @return string
     */
    final public static function applicationName()
    {
        return \P5\Lang::translate('APPLICATION_NAME');
    }

    /**
     * This package version
     *
     * @final
     *
     * @return string
     */
    final public static function version()
    {
        return System::getVersion(__CLASS__);
    }

    /**
     * Unload action
     *
     * Clear session data for package,
     * when unload application
     */
    public static function unload()
    {
        if (isset($_SESSION['current_site'])) {
            unset($_SESSION['current_site']);
        }
        if (isset($_SESSION['current_category'])) {
            unset($_SESSION['current_category']);
        }
    }

    /**
     * Clear application level permissions.
     *
     * @see Tms\User::updatePermission()
     *
     * @param Tms\Db $db
     * @param int    $userkey
     *
     * @return bool
     */
    public static function clearApplicationPermission(Db $db, $userkey)
    {
        $filter1 = [0];
        $filter2 = [0];
        if (isset($_SESSION['current_site'])) {
            $filter1[] = $_SESSION['current_site'];
        }
        if (isset($_SESSION['current_category'])) {
            $filter2[] = $_SESSION['current_category'];
        }
        $statement = 'userkey = ? AND application = ?'
            .' AND filter1 IN ('.implode(',', array_fill(0, count($filter1), '?')).')'
            .' AND filter2 IN ('.implode(',', array_fill(0, count($filter2), '?')).')';
        $options = array_merge([$userkey, self::packageName()], $filter1, $filter2);

        return $db->delete('permission', $statement, $options);
    }

    /**
     * Reference permission.
     *
     * @todo Better handling for inheritance
     *
     * @see Tms\User::hasPermission()
     *
     * @param string $key
     * @param int    $filter1
     * @param int    $filter2
     *
     * @return bool
     */
    public function hasPermission($key, $filter1 = 0, $filter2 = 0)
    {
        if ($key === 'root') {
            return parent::hasPermission($key, $filter1, $filter2);
        }

        // Administrators have full control
        if ($this->isAdmin()) {
            if (   $key === 'cms.site.create'
                && !$this->isRoot()
                && $this->app->cnf('application:cms_site_creator') === 'rootonly'
            ) {
                return false;
            }
            return true;
        }

        $exec = ($key === 'cms.exec') ? 1 : 0;
        $type = preg_match("/^cms\.(category|entry)\..+$/", $key);

        if ($exec !== 1 && empty($filter1)) {
            $filter1 = $this->siteID;
        }
        if (!in_array($key, ['system','cms.site.create','cms.site.remove']) && $this->isOwner($filter1)) {
            return true;
        }

        if ($type === 1 && empty($filter2)) {
            $filter2 = $this->categoryID;
        }

        $permission = parent::hasPermission($key, $filter1, $filter2);
        if (   $type === 1
            && false === $permission
            && false === $this->getPrivilege($key, $filter1, $filter2)
        ) {
            $_filter2 = $filter2;
            do {
                $_filter2 = $this->parentCategory($_filter2, $col = 'id');
                $raw = $this->getPrivilege($key, $filter1, $_filter2);
                if ($raw === '0') {
                    break;
                }
                elseif ($raw === '1') {
                    $permission = true;
                    break;
                }
            } while (!empty($_filter2));
        }

        // Not inheritance of parent permssion
        if ($permission && preg_match("/^cms\.category\.(create|delete)$/", $key)) {
            $parent = $this->parentCategory($filter2, 'id');
            $raw = $this->getPrivilege('cms.category.inherit', $filter1, $parent);
            if ($raw === '1') {
                $permission = false;
            }
        }

        return (bool) $permission;
    }

    /**
     * Release the template.
     *
     * @param array $post
     * @param bool  $copy
     * @param int   $sitekey
     *
     * @return bool
     */
    protected function buildTemplate($post, $copy, $sitekey = null)
    {
        $return_value = true;

        $id = $post['id'];
        $table = 'template';

        $latest_version = $this->db->max('revision', 'template', 'identifier = ?', [$id]);
        $new_version = (int)$latest_version + 1;

        if ($copy || $latest_version === '0') {
            $this->db->update($table, ['active' => '0'], 'identifier = ?', [$id]);

            $fields = $this->db->getFields($table);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                case 'id':
                    $cols[] = 'NULL AS id';
                    break;
                case 'revision':
                    $cols[] = $this->db->quote($new_version).' AS revision';
                    break;
                case 'active':
                    $cols[] = "'1' AS active";
                    break;
                default:
                    $cols[] = $field;
                    break;
                }
            }
            if (false === $this->db->copyRecord($cols, $table, '', 'id = ?', [$id])) {
                return false;
            }

            // Remove older version
            $save_count = $this->site_data['maxrevision'];
            $limit = $new_version - (int)$save_count;
            if (false === $this->db->delete($table, "identifier = ? AND revision > '0' AND revision < ?", [$id, $limit])) {
                trigger_error($this->db->error());

                return false;
            }
        } else {
            $this->db->update($table, ['active' => '1'], 'identifier = ? ORDER BY revision DESC LIMIT 1', [$id]);
        }

        $path = $this->templatePath($id, $sitekey);

        $this->view->clearCache($path);

        $sourcecode = ($post['kind'] === '6')
            ? $this->view->render($post['sourcecode'], true, true)
            : $post['sourcecode'];

        return file_put_contents($path, $sourcecode);
    }

    public function init()
    {
        parent::init();
        $config = $this->view->param('config');
        $config['application'] = ['guest' => 'allow'];
        $this->view->bind('config', $config);
    }

    /**
     * Find current site number from static URL
     *
     * @return int|false
     */
    public function currentSiteFromURI($uri)
    {
        $origin = $uri;
        while ($uri !== '.') {
            if (false !== $id = $this->db->get('id', 'site', 'url = ? OR url = ?', [$uri, "$uri/"])) {
                if ($origin === $uri && !preg_match('/\/$/', $uri)) {
                    \P5\Http::redirect("$uri/");
                }
                break;
            }
            $uri = dirname($uri);
        }

        return $id;
    }

    public static function extendedTemplatePath($uri, Common $app)
    {
        $origin = $uri;

        while ($uri !== '.') {
            $sitekey = $app->db->get('id', 'site', 'url = ? OR url = ?', [$uri, "$uri/"]);
            if (!empty($sitekey)) {
                break;
            }
            $uri = dirname($uri);
        }

        if (empty($sitekey)) {
            return;
        }

        $app->session->param('current_site', $sitekey);

        return $app->app->cnf('global:data_dir')."/mirror/$sitekey/templates";
    }

    public function availableConvert()
    {
        return !empty($this->command_convert);
    }

    protected function pathToID($path)
    {
        return trim(str_replace(['/','.'], ['-','_'], preg_replace('/\.html?$/','',$path)), '-_');
    }
}
