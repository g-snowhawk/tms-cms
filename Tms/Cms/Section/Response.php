<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Section;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Section
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
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.entry.'.$check);

        $id = $this->request->param('id');
        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $fetch = $this->db->select(
                'id, title, body, level, author_date, create_date, modify_date',
                'section', 'WHERE id = ?', [$this->request->param('id')]
            );
            if (count((array) $fetch) > 0) {
                $post = $fetch[0];
                if (!empty($post['author_date'])) {
                    $post['author_date'] = date('Y年n月j日 H:i', strtotime($post['author_date']));
                }
            }
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, $id, 'section', 'cst_%']
        );
        foreach ((array) $customs as $unit) {
            $post[$unit['name']] = $unit['data'];
        }

        $post['eid'] = $this->getEntryKey($id);
        $post['prn'] = $this->getParentSection($id);
        $post['publish'] = 'draft';
        $this->view->bind('post', $post);

        // Files
        $custom = [];
        $data = $this->db->select('*', 'custom', 'WHERE kind = ? AND relkey != ? AND relkey = ? AND name LIKE ?', ['section', 0, $id, 'file.%']);
        foreach ($data as $unit) {
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

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $form['enctype'] = 'multipart/form-data';
        $this->view->bind('form', $form);

        $this->app->execPlugin('beforeRendering');

        $this->setHtmlId('section-edit');
        $this->view->render('cms/section/edit.tpl');
    }
}
