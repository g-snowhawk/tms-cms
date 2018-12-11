<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Entry;

/**
 * Entry management data receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Save the data.
     */
    public function save()
    {
        $redirect_type = 'redirect';
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : 'cms.entry.response';

        if ($referer = $this->request->param('script_referer')) {
            $redirect_mode = $referer;
            $redirect_type = 'referer';
        }

        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if (!parent::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'edit'], null];
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    /**
     * Remove data.
     */
    public function remove()
    {
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : 'cms.entry.response';

        $message = 'SUCCESS_REMOVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], $redirect_mode];

        list($type, $id) = explode(':', $this->request->post('delete'));
        $result = ($type === 'category') ? \Tms\Cms\Category::remove() : parent::remove();

        if (!$result) {
            $message = 'FAILED_REMOVE';
            $status = 1;
        }

        $this->postReceived(\P5\Lang::translate($message), $status, $response, $options); 
    }

    public function createRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('relay');
        $result = parent::createRelation($entrykey, $relkey);

        if (strtolower(\P5\Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            \P5\Http::nocache();
            header('Content-type:text/plain;charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_SAVED'));
        }
        \P5\Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:edit'
        );
    }

    public function removeRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('removeRelay');
        $result = $this->db->delete('relation', 'entrykey=? AND relkey=?', [$entrykey, $relkey]);

        if (strtolower(\P5\Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            \P5\Http::nocache();
            header('Content-type:text/plain;charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_REMOVED'));
        }
        \P5\Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:edit'
        );
    }

    /**
     * Save the category data.
     */
    public function saveCategory()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $callback = '\P5\Http::redirect';
        $args = [$this->app->systemURI().'?mode=cms.entry.response'];
        if (!\Tms\Cms\Category::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $callback = [
                [[$this, 'init'], []],
                [[$this, 'defaultView'], []]
            ];
            $args = [];
        }
        $this->postReceived(\P5\Lang::translate($message), $status, $callback, $args); 
    }

    /**
     * Reassemble the site.
     */
    public function reassembly()
    {
        // Clear template cache
        if (false === $this->view->clearAllCaches()) {
            return false;
        }

        if ($this->siteProperty('type') !== 'dynamic') {
            if (false === \Tms\Cms\Category::reassembly()) {
                return false;
            }
        }

        $this->session->param('messages', \P5\Lang::translate('SUCCESS_REASSEMBLY'));
        $this->app->logger->log("Reassembly the site `{$this->siteID}'", 101);
        \P5\Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:reassembly'
        );
    }

    /**
     * Set current category.
     *
     * return void
     */
    public function setCategory($id = null)
    {
        parent::setCategory($this->request->param('id'));
        \P5\Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response'
        );
        //$this->init();
        //$this->defaultView();
    }

    public function ajaxUploadImage()
    {
        $response = '';
        if (false !== $this->saveFiles('0')) {
            $list = $this->imageList('0');
            $response = json_encode($list);
        } else {
            $response = 'Upload Failure';
        }
        header('Content-type: text/plain; charset=utf-8');
        echo $response;
        exit;
    }

    public function ajaxDeleteImage()
    {
        $id = $this->request->param('id');

        $upload_dir = $this->fileUploadDir();
        $file = $this->db->get('data', 'custom', 'id = ?', [$id]);
        $path = $upload_dir.'/'.basename($file);
        if (file_exists($path)) {
            unlink($path);
        }

        $response = 'Delete Failure';
        if ($this->db->delete('custom', 'id = ?', [$id])) {
            $response = json_encode(['id' => $id, 'status' => 'success']);
        }
        header('Content-type: text/plain; charset=utf-8');
        echo $response;
        exit;
    }
}
