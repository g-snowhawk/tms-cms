<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Template;

/**
 * Template management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Template
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
            ['title' => 'テンプレート管理', 'id' => 'template', 'class' => 'template']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.template.read');

        $templates = $this->db->select(
            'id ,title, kind, create_date, modify_date',
            'template',
            'WHERE sitekey = ? AND revision = ? ORDER BY kind',
            [$this->siteID, 0]
        );
        $this->view->bind('templates', $templates);
        $this->view->bind('kinds', $this->kind_of_template);

        $this->setHtmlId('template-default');

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->render('cms/template/default.tpl');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.template.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $param_key = 'id';
            $columns = ['id','title','sourcecode','kind','path','create_date','modify_date'];
            if (!empty($this->request->param('cp'))) {
                $param_key = 'cp';
                $columns = ['title','sourcecode','kind'];
            }
            $post = $this->db->get(
                implode(',', $columns),
                'template',
                'id = ? AND sitekey = ?',
                [$this->request->param($param_key), $this->siteID]
            );
            if (!empty($this->request->param('cp'))) {
                $post['title'] .= ' (Copied)';
            }
        }
        $this->view->bind('post', $post);
        $this->view->bind('kinds', $this->kind_of_template);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('template-edit');
        $this->view->render('cms/template/edit.tpl');
    }
}
