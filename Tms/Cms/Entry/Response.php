<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Entry;

use P5\Http;
use P5\Lang;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Entry
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
            ['title' => 'エントリ管理', 'id' => 'entry', 'class' => 'entry']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.entry.read', $this->siteID, $this->site_root);

        // Change category when current category is system reserved
        $reserved = $this->db->get('reserved', 'category', 'id=?', [$this->session->param('current_category')]);
        if ($reserved === '1') {
            if (!is_null($this->session->param('escape_current_category'))) {
                $this->setCategory($this->session->param('escape_current_category'));
                $this->session->clear('escape_current_category');
            }
            else {
                $this->setCategory();
            }
            $this->setCategory($this->session->param('current_category'));
        }

        $sql = file_get_contents(__DIR__ . '/default.sql');

        // Sort order
        $sort_option = '';
        if ($this->session->param('cms_entry_list_order')) {
            $sort_option = ','.$this->session->param('cms_entry_list_order');
        }
        elseif ($this->app->cnf('application:cms_entry_list_order')) {
            $sort_option = ','.$this->app->cnf('application:cms_entry_list_order');
        }
        $sql = str_ireplace('{{ sort_option }}', $sort_option, $sql);

        $entry = $this->db->getAll($sql, ['user_id' => $this->uid, 'site_id' => $this->siteID, 'category_id' => $this->categoryID, 'revision' => 0]);
        $this->view->bind('entries', $entry);

        $form = $this->view->param('form');
        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->bind('err', $this->app->err);

        $this->setHtmlId('entry-default');

        if ($this->isAjax) {
            return $this->view->render('cms/entry/default.tpl', true);
        }
        parent::defaultView('cms-entry-default');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        if (!is_null($this->request->param('rel'))) {
            $eid = $this->request->param('rel');
            $cid = $this->db->get('category', 'entry', 'id = ?', [$eid]);
            $this->setCategory($cid);
            $this->request->param('id', $eid);
            $this->request->param('rel', null, true);
        }

        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';

        if (!is_null($this->request->param('ccp'))) {
            $cid = $this->db->get('id', 'category', 'path=?', [$this->request->param('ccp')]);
            $this->setCategory($cid);
        }

        if ($check === 'update') {
            $parent = $this->db->get('category', 'entry', 'id = ?', [$id]);
            if ($this->session->param('current_category') !== $parent) {
                throw new \Tms\PermitException(Lang::translate('ILLEGAL_OPERATION'));
            }
        }

        $this->checkPermission('cms.entry.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $post = [];
            $fields = array_diff(
                $this->db->getFields('entry'),
                ['sitekey','userkey','category','path','identifier','revision','active','status']
            );
            $fetch = $this->db->select(
                implode(',',$fields),
                'entry', 'WHERE id = ?', [$this->request->param('id')]
            );
            if (count((array)$fetch) > 0) {
                $post = $fetch[0];
                foreach ($this->date_columns as $x_date) {
                    if (!empty($post[$x_date])) {
                        $post[$x_date] = date($this->date_columns_format, strtotime($post[$x_date]));
                    }
                }
            } else {
                foreach ($fields as $key) {
                    if (!is_null($this->request->GET($key))) {
                        if (in_array($key, $this->date_columns)) {
                            $post[$key] = date($this->date_columns_format, strtotime($this->request->GET($key)));
                            continue;
                        }
                        $post[$key] = $this->request->GET($key);
                    }
                }
            }

            if(empty($post['category'])) {
                $post['category'] = $this->db->get('id', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $this->session->param('current_category')]);
            }

            if(empty($post['template'])) {
                $post['template'] = $this->db->get('default_template', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $this->session->param('current_category')]);
            }
        }
        if (empty($post['publish'])) {
            $post['publish'] = 'draft';
        }
        if (empty($post['filepath'])) {
            $post['filepath'] = 'doc'.date('ymdhis').$this->site_data['defaultextension'];
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data, note', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, $id, 'entry', 'cst_%']
        );
        foreach ((array) $customs as $unit) {
            $post[$unit['name']] = $unit['data'];
        }

        // Convert datetime
        foreach (['release_date', 'close_date', 'author_date'] as $key) {
            if (isset($post[$key]) && !empty($post[$key])) {
                try {
                    $date = new \DateTime($post[$key]);
                    $post[$key] = $date->format('Y-m-d\TH:i');
                }
                catch (\Exception $e) {
                    //
                }
            }
        }

        $this->view->bind('post', $post);

        $category_reservation = (empty($post['category']))
            ? null : $this->db->get('reserved', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $post['category']]);
        $this->view->bind('category_reservation', $category_reservation);

        // Files
        $custom = [];
        $data = $this->db->select('*', 'custom', 'WHERE kind = ? AND relkey != ? AND relkey = ? AND name LIKE ? ORDER BY `sort`', ['entry', 0, $id, 'file.%']);
        foreach ((array)$data as $unit) {
            $name = $unit['name'];
            if (strpos($name, 'file.') === 0) {
                $name = 'file';
            }
            unset($unit['name']);
            if (!isset($custom[$name])) {
                $custom[$name] = [];
            }
            if ($name === 'file') {
                $unit['title'] = basename($unit['data']);
            }
            $custom[$name][] = $unit;
        }
        $this->view->bind('custom', $custom);

        //
        $templates = $this->db->select(
            'id,title', 'template',
            'WHERE sitekey = ? AND kind IN(0,2) AND revision = 0',
            [$this->siteID]
        );
        $this->view->bind('templates', $templates);

        // Revision
        $revision = $this->db->get('revision', 'entry', 'identifier = ? AND active = ?', [$id, 1]);
        $this->view->bind('revision', $revision);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = Lang::translate('CONFIRM_SAVE_DATA');
        $form['enctype'] = 'multipart/form-data';
        $this->view->bind('form', $form);

        if (!is_null($this->request->param('eid'))) {
            $this->view->bind('relayentry', $this->request->param('eid'));
        }

        $this->setHtmlClass(['entry', 'entry-edit']);

        parent::defaultView('cms-entry-edit');
    }

    /**
     * Preview Entry.
     */
    public function preview()
    {
        $this->session->param('ispreview', 1);

        // Save temporary image files
        $this->removePreviewImages();
        $this->saveFiles('preview');

        Http::responseHeader('X-Frame-Options', 'SAMEORIGIN');
        Http::responseHeader('X-XSS-Protection', '1');
        Http::responseHeader('X-Content-Type-Options', 'nosniff');
        echo $this->build($this->request->param('id'), true);
        $this->session->clear('ispreview');
        exit;
    }

    /**
     * Reassemble the site.
     */
    public function reassembly()
    {
        // Can uses async
        $enable_async = false;
        $disable_functions = array_map('trim', explode(',', ini_get('disable_functions')));
        if (!in_array('exec', $disable_functions)) {
            $enable_cli = exec(
                $this->nohup() . ' ' . $this->phpCLI() . ' --version',
                $response, $status
            );

            if ($status === 0) {
                $this->view->bind('runAsyncBy', uniqid('pol'));
                $this->view->bind('confirmReassembly', Lang::translate('CONFIRM_REASSEMBLY'));
                $enable_async = true;
            }
        }

        if (false === $enable_async) {
            $form = $this->view->param('form');
            $form['confirm'] = Lang::translate('CONFIRM_REASSEMBLY');
            $this->view->bind('form', $form);
        }

        $this->view->render('cms/entry/reassembly.tpl');
    }

    public function ajaxImageList()
    {
        $list = $this->imageList('0');
        header('Content-type: text/plain; charset=utf-8');
        echo json_encode($list);
        exit;
    }

    public function pollingReassembly()
    {
        $polling_file = $this->echoPolling(['message' => Lang::translate('SUCCESS_REASSEMBLY')]);
    }

    public function trash()
    {
        $sql = file_get_contents(__DIR__ . '/trash.sql');

        $items = $this->db->getAll($sql, ['user_id' => $this->uid, 'site_id' => $this->siteID, 'revision' => 0]);
        if (false === $items) {
            echo $this->db->error();
        }
        $this->view->bind('items', $items);

        $form = $this->view->param('form');
        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        //$this->view->bind('err', $this->app->err);

        $this->setHtmlId('cms-trash');

        parent::defaultView('cms-entry-trash');
    }
}
