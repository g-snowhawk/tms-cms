<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms\Lang;

/**
 * Japanese Languages for Tms.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ja extends \P5\Lang
{
    const APP_NAME = 'CMS';

    protected $APPLICATION_NAME = self::APP_NAME;
    protected $APPLICATION_LABEL = self::APP_NAME;
    protected $APP_DETAIL    = self::APP_NAME.'機能を提供します。';
    protected $SUCCESS_SETUP = self::APP_NAME.'機能の追加に成功しました。';
    protected $FAILED_SETUP  = self::APP_NAME.'機能の追加に失敗しました。';

    protected $CONFIRM_REASSEMBLY = 'サイトを再構築します。この処理は時間のかかることがあります';
    protected $SUCCESS_REASSEMBLY = '再構築を完了しました';
    protected $FAILED_REASSEMBLY = '再構築に失敗しました';
}
