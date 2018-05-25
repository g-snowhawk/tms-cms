<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Filemanager extends Site
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

    const USER_DIRECTORY_NAME = 'usr';

    /* 
     * Upload root directory
     *
     * @ver string
     */
    protected $upload_root;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->upload_root = \P5\File::realpath(implode(DIRECTORY_SEPARATOR, [$this->site_data['openpath'],$this->site_data['uploaddir'],self::USER_DIRECTORY_NAME]));
        if (!file_exists($this->upload_root)) {
            mkdir($this->upload_root, 0777, true);
        }
    }

    /**
     * Rename the file.
     *
     * @return bool
     */
    protected function rename()
    {
        $source = $this->currentDirectory().'/'.$this->request->param('oldname');
        $dest = $this->currentDirectory().'/'.$this->request->param('newname');

        if (rename($source, $dest)) {
            return basename($dest);
        }

        return false;
    }

    /**
     * Move the file.
     *
     * @return bool
     */
    protected function move()
    {
        $source = $this->currentDirectory().'/'.$this->request->param('source');
        $dest = $this->upload_root.'/'.$this->request->param('dest').'/'.$this->request->param('source');

        return rename($source, $dest);
    }

    /**
     * Save the upload file.
     *
     * @return bool
     */
    protected function remove()
    {
        list($kind, $path) = explode(':', $this->request->param('delete'));
        $path = $this->currentDirectory().'/'.ltrim($path, '/');
        if (!file_exists($path)) {
            return true;
        }
        switch ($kind) {
            case 'file' :
                return unlink($path);
                break;
            case 'folder' :
                return \P5\File::rmdir($path, true);
                break;
        } 
    }

    protected function fileList($directory = null, $parent = null, $filter = null)
    {
        try {
            $parent = trim($parent,'/')."/$directory";
            $cwd = realpath("{$this->upload_root}/$parent");
        } catch (\ErrorException $e) {
            return;
        }

        $files = [];
        $directories = [];
        if (false !== ($dh = opendir($cwd))) {
            while (false !== ($entry = readdir($dh))) {
                $path = "$cwd/$entry";
                if (   $entry === '.'
                    || $entry === '..'
                    || ($filter === 'file' && is_dir($path))
                    || ($filter === 'directory' && is_file($path))
                ) {
                    continue;
                }
                $stat = stat($path);
                $data = [
                    'name' => $entry,
                    'parent' => $parent,
                    'path' => str_replace($this->upload_root.'/', '', $path),
                    'modify_date' => $stat['mtime'],
                    'size' => \P5\File::size((int)$stat['size']),
                    'kind' => (is_dir($path)) ? 'folder' : 'file'
                ];

                if (is_dir($path)) {
                    $directories[] = $data;
                }
                else {
                    $files[] = $data;
                }
            }
        }

        return array_merge($directories, $files);
    }

    protected function setCurrentDirectory($path)
    {
        $this->session->param('current_dir', ltrim($path, '/'));
    }

    protected function currentDirectory($relative = false)
    {
        $root = ($relative === false) ? $this->upload_root.'/' : '';
        return rtrim($root.ltrim($this->session->param('current_dir'),'/'),'/');
    }
}
