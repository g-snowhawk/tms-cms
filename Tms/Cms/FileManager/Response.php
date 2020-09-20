<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2018 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\FileManager;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Cms\FileManager
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

        $this->view->bind('cwd', explode('/', $cwd));

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
        if (   $this->request->method === 'post'
            && $this->request->post('request_type') !== 'response-subform'
        ) {
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
        if (   $this->request->method === 'post'
            && $this->request->post('request_type') !== 'response-subform'
        ) {
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

    public function childFiles($directory, $parent)
    {
        return parent::fileList($directory, $parent, 'file');
    }

    public function download($path)
    {
        $file = $this->upload_root . '/' . urldecode($path);
        if (!file_exists($file)) {
            return false;
        }
        $file_name = basename($file);
        $urlencoded = rawurlencode($file_name);
        $content_length = filesize($file);
        $mime = 'application/octet-stream';
        \P5\Http::nocache();
        \P5\Http::responseHeader('Content-Disposition',"attachment; filename=\"$file_name\"; filename*=UTF-8''$urlencoded");
        \P5\Http::responseHeader('Content-length',"$content_length");
        \P5\Http::responseHeader('Content-type',"$mime");
        readfile($file);
        exit;
    }
}
