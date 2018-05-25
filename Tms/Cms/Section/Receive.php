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
        if (parent::save()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_SAVED'));
            $class = 'entry';
            $param_key = 'eid';
            if ($this->request->param('level') > 2) {
                $class = 'section';
                $param_key = 'prn';
            }
            $url = $this->app->systemURI()."?mode=cms.$class.response:edit"
                 . '&id='.urlencode($this->request->param($param_key));
            \P5\Http::redirect($url);
        }
        $this->view->bind('err', $this->app->err);
        $this->edit();
    }

    /**
     * Remove data.
     */
    public function remove()
    {
        $eid = $this->getEntryKey($this->request->param('remove'));
        if (parent::remove()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_REMOVED'));
        }
        $url = $this->app->systemURI().'?mode=cms.entry.response:edit'
             .'&id='.urlencode($eid);
        \P5\Http::redirect($url);
    }
}
