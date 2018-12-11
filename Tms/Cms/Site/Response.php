<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Site;

/**
 * Site management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Site
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => 'サイト管理', 'id' => 'site', 'class' => 'site']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.exec');

        if (is_null($this->request->param('mode'))
         && !is_null($this->session->param('current_site'))
        ) {
            \P5\Http::redirect($this->app->systemURI().'?mode=cms.entry.response');
        }

        $options = [];
        $sql = 'SELECT id, url, title, description FROM table::site';
        if ($this->userinfo['admin'] !== '1') {
            $where = [];

            $owners = (array) parent::siteOwners($this->db, $this->uid);
            if (count($owners) > 0) {
                $where[] = 'userkey IN ('.implode(',', array_fill(0, count($owners), '?')).')';
                $options = array_merge($options, $owners);
            }

            $filtered = (array)parent::filteredSite($this->db, $this->uid);
            if (count($filtered) > 0) {
                $where[] = 'id IN ('.implode(',', array_fill(0, count($filtered), '?')).')';
                $options = array_merge($options, $filtered);
            }

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
        }
        $sites = $this->db->getAll($sql, $options);
        $this->view->bind('sites', $sites);

        $this->setHtmlId('site-default');
        $this->view->render('cms/site/default.tpl');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.site.'.$check, $this->request->param('id'));

        if (   $this->request->method === 'post'
            && $this->request->param('convert_request_method') !== 'get'
        ) {
            $post = $this->request->POST();
        } elseif (empty($id)) {
            // Default values
            $post = [
                     'defaultpage' => 'index.htm',
                'defaultextension' => '.htm',
                        'styledir' => 'style',
                       'uploaddir' => 'upload',
                         'maskdir' => '0755',
                        'maskfile' => '0644',
                        'maskexec' => '0755',
                     'maxrevision' => 0,
            ];
        } else {
            $post = $this->db->selectSingle('*', 'site', 'WHERE id = ?', [$id]);
        }
        $this->view->bind('post', $post);

        // Site owner candidate
        $this->view->bind('owners', $this->siteOwnerCandidates());

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('subform', $form);

        $this->view->bind('err', $this->app->err);

        $this->app->execPlugin('beforeRendering', __FUNCTION__);

        $this->setHtmlId('site-edit');
        $this->view->render('cms/site/edit.tpl');
    }

    /**
     * Site owner candidate.
     *
     * @return mixed
     */
    private function siteOwnerCandidates()
    {
        $children = $this->childUsers($this->uid, 'id');
        $options = [$this->uid];
        foreach ($children as $unit) {
            $options[] = $unit['id'];
        }
        $sql = 'SELECT id,company,fullname
                  FROM table::user
                 WHERE id IN ('.implode(',', array_fill(0, count($options), '?')).')';

        return $this->db->getAll($sql, $options);
    }
}
