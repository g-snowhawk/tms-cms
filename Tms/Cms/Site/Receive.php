<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Site;

/**
 * Site management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Site Select.
     */
    public function select()
    {
        parent::changeSite();
        \P5\Http::redirect($this->app->systemURI());
    }

    /**
     * Save the data.
     */
    public function save()
    {
        if (parent::save()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_SAVED'));
            \P5\Http::redirect($this->app->systemURI().'?mode=cms.site.response');
        }
        $this->edit();
    }

    /**
     * Remove the data.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_REMOVED'));
        }
        \P5\Http::redirect($this->app->systemURI().'?mode=cms.site.response');
    }
}
