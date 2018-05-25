<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2018 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Filemanager;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\Filemanager
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
            ['title' => 'ファイル管理', 'id' => 'filemanager', 'class' => 'filemanager']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $cwd = $this->currentDirectory(true);
        $files = $this->fileList(basename($cwd), dirname($cwd));
        $this->view->bind('files', $files);

        $form = $this->view->param('form');
        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->bind('err', $this->app->err);

        $this->setHtmlId('filemanager-default');
        $this->view->render('cms/filemanager/default.tpl');
    }

    public function addFolder()
    {
        $response = $this->view->render('cms/filemanager/addfolder.tpl', true);
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

    public function addFile()
    {
        $response = $this->view->render('cms/filemanager/addfile.tpl', true);
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

    public function childDirectories($directory, $parent)
    {
        return parent::fileList($directory, $parent, 'directory');
    }
}
