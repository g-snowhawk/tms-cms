<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Category;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Category
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
            ['title' => 'カテゴリ管理', 'id' => 'category', 'class' => 'category']
        );
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        if ($this->isAjax) {
            return $this->editSubform();
        }

        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';

        if ($check === 'update') {
            $parent = $this->db->nsmGetParent(
                'id',
                '(SELECT * FROM table::category WHERE sitekey = :site_id)',
                '(SELECT * FROM table::category WHERE id = :category_id)',
                ['site_id' => $this->siteID, 'category_id' => $id]
            );
            if ($this->session->param('current_category') !== $parent) {
                throw new \Tms\PermitException(\P5\Lang::translate('ILLEGAL_OPERATION'));
            }
        }

        $this->checkPermission('cms.category.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $fetch = $this->db->select(
                'id, title, tags, description, path, archive_format, template, default_template, inheritance, author_date, create_date, modify_date',
                'category', 'WHERE id = ?', [$id]
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
            'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$this->siteID, $id, 'category']
        );
        foreach ((array) $customs as $unit) {
            $post[$unit['name']] = $unit['data'];
        }

        $this->view->bind('post', $post);

        //
        $templates = $this->db->select('id,title', 'template', "WHERE sitekey = ? AND kind= ? AND revision = ?", [$this->siteID, 3, 0]);
        $this->view->bind('templates', $templates);

        //
        $templates = $this->db->select('id,title', 'template', "WHERE sitekey = ? AND (kind = ? OR kind = ?) AND revision = ?", [$this->siteID, 2, 0, 0]);
        $this->view->bind('default_templates', $templates);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('category-edit');
        $this->view->render('cms/category/edit.tpl');
    }

    public function editSubform()
    {
        $response = $this->view->render('cms/category/subform.tpl', true);
        if ($this->request->method === 'post') {
            return $response;
        }
        $json = [
            'status' => 200,
            'response' => $response,
        ];
        header('Content-type: text/plain; charset=utf-8');
        echo json_encode($json);
        exit;
    }
}
