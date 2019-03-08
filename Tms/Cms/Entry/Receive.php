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

use Tms\Cms\Category;
use P5\Environment;
use P5\Http;
use P5\Lang;
use P5\Text;

/**
 * Entry management data receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

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

        $this->postReceived(Lang::translate($message), $status, $response, $options); 
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
        $result = ($type === 'category') ? Category::remove() : parent::remove();

        if (!$result) {
            $message = 'FAILED_REMOVE';
            $status = 1;
        }

        $this->postReceived(Lang::translate($message), $status, $response, $options); 
    }

    public function createRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('relay');
        $result = parent::createRelation($entrykey, $relkey);

        if (strtolower(Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            Http::nocache();
            header('Content-type:text/plain;charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', Lang::translate('SUCCESS_SAVED'));
        }
        Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:edit'
        );
    }

    public function removeRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('removeRelay');
        $result = $this->db->delete('relation', 'entrykey=? AND relkey=?', [$entrykey, $relkey]);

        if (strtolower(Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            Http::nocache();
            header('Content-type:text/plain;charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', Lang::translate('SUCCESS_REMOVED'));
        }
        Http::redirect(
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
        if (!Category::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $callback = [
                [[$this, 'init'], []],
                [[$this, 'defaultView'], []]
            ];
            $args = [];
        }
        $this->postReceived(Lang::translate($message), $status, $callback, $args); 
    }

    /**
     * Reassemble the site.
     */
    public function reassembly()
    {
        $message = 'SUCCESS_REASSEMBLY';
        $status = 0;
        $callback = [[$this, 'redirect'], 'cms.entry.response:reassembly'];
        $args = [];

        if ($this->isAjax) {
            $disable_functions = Text::explode(',', ini_get('disable_functions'));
            if (!in_array('exec', $disable_functions)) {
                $query = ['polling_id' => $this->request->param('polling_id')];
                $command = $this->nohup() . ' ' . $this->phpCLI()
                    . ' -d memory_limit=-1'
                    . ' -d include_path="'.ini_get('include_path') . '"'
                    . ' ' . Environment::server('script_filename')
                    . ' --phpsessid=' . escapeshellarg(session_id())
                    . ' --mode=' . escapeshellarg('cms.entry.receive:ajaxReassembly')
                    . ' --params=' . escapeshellarg(http_build_query($query))
                    . ' > /dev/null &';
                exec($command, $output, $return);
                $status = [
                    'status' => $return,
                ];

                if ($return !== 0) {
                    $message = 'FAILED_REASSEMBLY';
                } else {
                    $status['polling_id'] = $this->request->param('polling_id');
                    $status['polling_address'] = $this->app->systemURI().'?mode=cms.entry.response:pollingReassembly';
                }

            }
        } else {
            if (false === Category::reassembly()) {
                $message = 'FAILED_REASSEMBLY';
                $status = 1;
                $callback = [
                    [[$this, 'reassembly'], []]
                ];
                $args = [];
            } else {
                $this->app->logger->log("Reassembly the site `{$this->siteID}'", 101);
            }
        }

        $this->postReceived(Lang::translate($message), $status, $callback, $args); 
    }

    /**
     * Set current category.
     *
     * return void
     */
    public function setCategory($id = null)
    {
        parent::setCategory($this->request->param('id'));
        Http::redirect(
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

    public function ajaxReassembly()
    {
        $this->startPolling();

        $message = 'SUCCESS_REASSEMBLY';
        if (false === Category::reassembly()) {
            $message = 'FAILED_REASSEMBLY';
        }

        $this->endPolling();
        $this->echoPolling(['message' => Lang::translate($message)]);
    }
}
