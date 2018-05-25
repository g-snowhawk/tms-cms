<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Filemanager;

/**
 * Site management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    public function setDirectory()
    {
        $this->setCurrentDirectory($this->request->param('path'));
        \P5\Http::redirect($this->app->systemURI().'?mode=cms.filemanager.response');
    }

    /**
     * Rename the file|directory.
     */
    public function rename()
    {
        $message = 'SUCCESS_SAVED';
        $status = ['status' => 0];
        $options = [];
        $response = [[$this, 'redirect'], 'cms.filemanager.response'];

        if (false === $result = parent::rename()) {
            $message = 'FAILED_REMOVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
        } else {
            $status[] = $result;
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    /**
     * Move the file|directory.
     */
    public function move()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.filemanager.response'];

        if (parent::move()) {
            $this->setCurrentDirectory($this->request->param('dest'));
        } else {
            $message = 'FAILED_REMOVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    /**
     * Remove the data.
     */
    public function remove()
    {
        $message = 'SUCCESS_REMOVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.filemanager.response'];

        if (!parent::remove()) {
            $message = 'FAILED_REMOVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    public function saveFolder()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.filemanager.response'];

        try {
            $valid = [
                ['vl_path', 'path', 'empty']
            ];
            if (!$this->validate($valid)) {
                throw new \ErrorException('Posted data is invalid');
            }

            $directory = $this->currentDirectory().'/'.$this->request->param('path');
            if (false === mkdir($directory, 0777, true)) {
                throw new \ErrorException("Make directory error");
            }
        } catch (\ErrorException $e) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'addFolder'], null];
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    public function saveFile()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.filemanager.response'];

        try {
            $valid = [
                ['vl_file', 'file', 'upload', 9]
            ];
            if (!$this->validate($valid)) {
                throw new \ErrorException('Upload file is invalid');
            }

            $uploaded_file = $this->request->FILES('file');
            $source = $uploaded_file['tmp_name'];
            $dest = $this->currentDirectory().'/'.$uploaded_file['name'];
            if (false === move_uploaded_file($source, $dest)) {
                throw new \ErrorException("File upload error");
            }
        } catch (\ErrorException $e) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'addFile'], null];
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }
}
