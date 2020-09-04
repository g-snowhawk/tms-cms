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

use P5\Http;
use P5\Lang;

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
        Http::redirect($this->app->systemURI());
    }

    /**
     * Save the data.
     */
    public function save()
    {
        if (parent::save()) {
            $this->session->param('messages', Lang::translate('SUCCESS_SAVED'));
            Http::redirect($this->app->systemURI().'?mode=cms.site.response');
        }
        $this->edit();
    }

    /**
     * Remove the data.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', Lang::translate('SUCCESS_REMOVED'));
            Http::redirect($this->app->systemURI().'?mode=cms.site.response');
        }
        $this->request->param('convert_request_method', 'get');
        $this->edit();
    }
}
