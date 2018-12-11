<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Cms;

/**
 * Entry management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Entry extends Category
{
    /**
     * Date format columns
     *
     * @var array
     */
    protected $date_columns = ['release_date', 'close_date', 'author_date'];
    protected $date_columns_format = 'Y/m/d H:i';

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);
    }

    /**
     * Save the data.
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.entry.'.$check);

        $this->app->execPlugin('beforeSave');

        $table = 'entry';
        $skip = [
            'id', 'sitekey', 'userkey',
            'path', 'identifier', 'revision',
            'status',
            'create_date', 'modify_date',
        ];

        $post = $this->request->POST();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_body', 'body', 'empty'];
        $valid[] = ['vl_category', 'category', 'empty'];
        $valid[] = ['vl_filepath', 'filepath', 'empty'];
        $valid[] = ['vl_template', 'template', 'empty'];
        $valid[] = ['vl_description', 'description', 'disallowtags', 2];
        $valid[] = ['vl_release_period', 'release_date', 'datetime_format', 1, 'allowempty'];
        $valid[] = ['vl_release_period', 'close_date', 'datetime_format', 2, 'allowempty'];
        $valid[] = ['vl_release_period', 'close_date', 'gt_datetime', 3, $post['release_date'], 'allowempty'];
        $valid[] = ['vl_author_date', 'author_date', 'datetime_format', 1, 'allowempty'];

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($table);
        $save = ['revision' => '0'];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                if (!empty($post[$field] && in_array($field, $this->date_columns))) {
                    $save[$field] = date('Y-m-d H:i', \P5\Text::strtotime($post[$field]));
                    continue;
                }

                $save[$field] = (empty($post[$field])) ? null : $post[$field];
            }
        }

        // NULL is not empty to foreign key.
        if (empty($save['template'])) {
            $save['template'] = null;
        }

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $result = 0;
        if (empty($post['id'])) {
            $raw['create_date'] = 'CURRENT_TIMESTAMP';
            $save['sitekey'] = $this->siteID;
            $save['userkey'] = $this->uid;
            $save['category'] = $this->categoryID;
            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
                $this->db->update(
                    $table, ['identifier' => $post['id']],
                    'id = ? AND sitekey = ?',
                    [$post['id'], $this->siteID]
                );
            }
        } else {
            $result = $this->db->update($table, $save, 'id = ? AND sitekey = ?', [$post['id'], $this->siteID], $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            $file_count = $this->saveFiles($post['id']);

            $customs = [];
            foreach ($post as $key => $value) {
                if (strpos($key, 'cst_') === 0) {
                    $customs[$key] = $value;
                }
            }
            $custom_count = $this->saveCustomField('entry', $post['id'], $customs);

            $relation = $this->createRelation($this->request->param('eid'), $post['id']);

            if ($modified !== false && $file_count !== false && $custom_count !== false) {
                $result += $file_count + $custom_count;

                $plugin_result = $this->app->execPlugin('afterSave', $post);
                foreach($plugin_result as $plugin_count) {
                    if (false === $plugin_count) {
                        $result = false;
                        break;
                    }
                    $result += (int)$plugin_count;
                }

                if ($this->request->param('publish') === 'release') {
                    $status = $this->db->get('status', 'entry', 'id = ?', [$post['id']]);
                    $copy = ($result > 0 || $status !== 'release');
                    if (false === $this->release($post, $copy)) {
                        $result = false;
                    }
                }
                elseif ($this->request->param('publish') === 'private') {
                    if (false === $this->toPrivate($post)) {
                        $result = false;
                    }
                }
                else {
                    if ($result === 0) {
                        $this->app->err['vl_nochange'] = 1;
                        $result = false;
                    }
                    else {
                        $result = $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$id], $raw);
                    }
                }
            } else {
                $result = false;
            }
            if ($result !== false) {
                $this->app->logger->log("Save the entry `{$id}'", 101);

                $commit = $this->db->commit();

                $plugin_result = $this->app->execPlugin('completeSave', $post);
                foreach($plugin_result as $result) {
                    if (false === $result) {
                        return false;
                    }
                }

                return $commit;
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * Save upload files.
     *
     * @param int $entrykey
     * @param int $sectionkey
     *
     * @return mixed
     */
    protected function saveFiles($entrykey, $sectionkey = null)
    {
        $kind = (empty($sectionkey)) ? 'entry' : 'section';
        $count = 0;

        $upload_dir = $this->fileUploadDir($entrykey, $sectionkey);

        $delete = $this->request->param('delete');
        $note = $this->request->param('note');
        $option1 = $this->request->param('option1');

        $sort = 0;
        if (isset($_FILES['file'])) {
            foreach ($_FILES['file']['name'] as $key => $name) {
                $id = str_replace('id_', '', $key);
                $save_data = ['sort' => $sort];

                ++$sort;

                if (isset($note[$key])) {
                    $save_data['note'] = (empty($note[$key])) ? null : $note[$key];
                }

                if (isset($option1[$key])) {
                    $save_data['option1'] = (empty($option1[$key]) && $option1[$key] !== '0') ? null : $option1[$key];
                }

                $old = $this->db->get('data,mime,option1', 'custom', 'sitekey = ? AND id = ?', [$this->siteID, $id]);
                $old_path = $upload_dir.'/'.basename($old['data']);

                if (empty($name)) {
                    if (isset($delete[$key])) {
                        if (file_exists($old_path) && is_file($old_path)) {
                            unlink($old_path);
                        }

                        self::clearPDFThumbnail($old_path.parent::THUMBNAIL_EXTENSION);

                        $directory = dirname($old_path);
                        if (count(glob("$directory/*")) === 0) {
                            rmdir($directory);
                        }

                        if (false === $ret = $this->db->delete('custom', 'sitekey = ? AND id = ?', [$this->siteID, $delete[$key]])) {
                            trigger_error($this->db->error());

                            return false;
                        }
                        $count += $ret;
                        continue;
                    }
                    if (false === $ret = $this->db->update('custom', $save_data, 'sitekey = ? AND id = ?', [$this->siteID, $id], [])) {
                        trigger_error($this->db->error());

                        return false;
                    }

                    if (!isset($save_data['option1'])) {
                        $save_data['option1'] = null;
                    }
                    if (!isset($old['option1'])) {
                        $old['option1'] = null;
                    }
                    if ($save_data['option1'] !== $old['option1'] && $old['mime'] === 'application/pdf') {
                        $upload_path = $upload_dir.'/'.basename($old['data']);
                        self::createPDFThumbnail($this->command_convert, $upload_path, $save_data['option1']);
                    }

                    $count += $ret;
                    continue;
                }

                // Convert encoding multibyte characters
                if (mb_strlen($name) !== mb_strwidth($name)) {
                    $name = \P5\Text::convert($name);
                }

                $file_name = urldecode(pathinfo(basename(urlencode($name)), PATHINFO_FILENAME));
                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $alternate = $file_name;

                if ($file_name !== urlencode($file_name)) {
                    $file_name = md5($file_name);
                }

                $upload_path = \P5\File::realpath("$upload_dir/$file_name.$file_extension");

                $save_data['sitekey'] = $this->siteID;
                $save_data['relkey'] = (empty($sectionkey)) ? $entrykey : $sectionkey;
                $save_data['kind'] = $kind;
                $save_data['name'] = 'file.'.$alternate;
                $save_data['mime'] = $_FILES['file']['type'][$key];
                $save_data['alternate'] = $alternate;
                $save_data['data'] = str_replace($this->env->server('DOCUMENT_ROOT'), '', $upload_path);

                $ret = 0;
                if (strpos($key, 'id_') === 0) {
                    if (false === $ret = $this->db->update('custom', $save_data, 'sitekey = ? AND id = ?', [$this->siteID, $id], [])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                    $save_data['id'] = $id;
                }
                else {
                    if (false === $ret = $this->db->insert('custom', $save_data, [])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                }
                $count += $ret;

                $diff = 1;
                if (file_exists($upload_path) && (binary)file_get_contents($upload_path) === (binary) file_get_contents($_FILES['file']['tmp_name'][$key])) {
                    $diff = 0;
                }
                elseif ($old_path !== $upload_path) {
                    if (file_exists($old_path) && is_file($old_path)) {
                        self::clearPDFThumbnail($old_path);
                        unlink($old_path);
                    }
                }

                $directory = dirname($upload_path);
                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }
                if (move_uploaded_file($_FILES['file']['tmp_name'][$key], $upload_path)) {

                    // Create PDF thumbnail
                    if (strtolower($save_data['mime']) === 'application/pdf' && (!empty($save_data['option1']) || $save_data['option1'] === '0')) {
                        self::createPDFThumbnail($this->command_convert, $upload_path, $save_data['option1']);
                    }

                    $count += $diff;
                }
                else {
                    trigger_error("File upload Failure `$upload_path'");

                    return false;
                }
            }
        }

        return $count;
    }

    /**
     * Remove upload files.
     *
     * @param int $entrykey
     * @param int $sectionkey
     *
     * @return bool
     */
    protected function removeFiles($entrykey, $sectionkey = null)
    {
        $upload_dir = $this->fileUploadDir($entrykey, $sectionkey);
        return \P5\File::rmdir($upload_dir, true);
    }

    /**
     * Release the entry.
     *
     * @param array $post
     * @param bool  $copy
     *
     * @return bool
     */
    protected function release($post, $copy)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $sid = $this->siteID;
        $entrykey = $post['id'];
        $table = 'entry';

        $latest_version = $this->db->max('revision', $table, 'sitekey = ? AND identifier = ?', [$sid, $entrykey]);
        $new_version = (int) $latest_version + 1;

        $upload_dir = $this->fileUploadDir();

        if ($copy || $latest_version === '0') {
            $this->db->update($table, ['active' => '0'], 'identifier = ?', [$entrykey]);

            $fields = $this->db->getFields($table);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'active':
                        $cols[] = "'1' AS active";
                        break;
                    case 'id':
                    case 'status':
                        $cols[] = "NULL AS `$field`";
                        break;
                    case 'revision':
                        $cols[] = $this->db->quote($new_version).' AS revision';
                        break;
                    default:
                        $cols[] = $field;
                        break;
                }
            }
            if (false === $this->db->copyRecord($cols, $table, '', 'id = ?', [$entrykey])) {
                return false;
            }
            $new_entrykey = $this->db->lastInsertId(null, 'id');
            $raw = null;
            $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$entrykey], $raw);

            // Remove older version
            $save_count = $this->db->get('maxrevision', 'site', 'id = ?', [$this->siteID]);
            $limit = $new_version - (int) $save_count;

            if (false !== $deletes = $this->db->select('id', $table, "WHERE sitekey = ? AND identifier = ? AND revision > '0' AND revision < ?", [$sid, $entrykey, $limit])) {

                $plugin_result = $this->app->execPlugin('beforeRemoveOldEntries', $deletes);
                foreach($plugin_result as $plugin_count) {
                    if (false === $plugin_count) {
                        return false;
                    }
                }

                foreach ($deletes as $delete) {
                    \P5\File::rmdir("$upload_dir/{$delete['id']}", true);
                    // Custom fields
                    if (false === $this->db->delete('custom', 'sitekey = ? AND kind = ? AND relkey = ?', [$sid, 'entry', $delete['id']])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                }
            }

            if (false === $this->db->delete($table, "sitekey = ? AND identifier = ? AND revision > '0' AND revision < ?", [$sid, $entrykey, $limit])) {
                trigger_error($this->db->error());

                return false;
            }
        } else {
            $save = ['active' => '1'];
            foreach ($this->date_columns as $x_date) {
                if (isset($post[$x_date])) {
                    // TODO: which use empty or is_null
                    $save[$x_date] = (empty($post[$x_date]))
                        ? NULL 
                        : date('Y-m-d H:i', \P5\Text::strtotime($post[$x_date]));
                }
            }
            $this->db->update($table, $save, 'identifier = ? ORDER BY revision DESC LIMIT 1', [$entrykey]);
            $new_entrykey = $this->db->get('id', $table, 'identifier = ? AND active = 1', [$entrykey]);
        }

        // Release sections
        if (false !== $sections = $this->db->select('id, entrykey AS eid', 'section', 'WHERE entrykey = ? AND revision = ? AND status = ?', [$entrykey, '0', 'draft'])) {
            foreach ($sections as $section) {
                if (false === $this->releaseSection($section, true)) {
                    return false;
                }
            }
        }

        $release_path = $this->getEntryPath($entrykey);
        $release_dir = dirname($release_path);

        // Remove older files
        if ($files = $this->db->select('filepath', $table, 'WHERE sitekey = ? AND identifier = ?', [$sid, $entrykey])) {
            foreach ((array) $files as $file) {
                $remove_file = $release_dir.'/'.$file['filepath'];
                if (file_exists($remove_file) && is_file($remove_file)) {
                    @unlink($remove_file);
                }
            }
        }

        $this->copyAttachments($new_entrykey, 'entry', $upload_dir);
        $this->copyCustomFields($new_entrykey);

        $plugin_result = $this->app->execPlugin('afterReleaseEntry', $new_entrykey);
        foreach($plugin_result as $plugin_count) {
            if (false === $plugin_count) {
                return false;
            }
        }

        if ($this->siteProperty('type') === 'static') {
            try {
                $source = $this->build($new_entrykey);
                if (!empty($source)) {
                    // Check release path
                    if (!file_exists($release_dir) && !\P5\File::mkdir($release_dir)) {
                        return false;
                    }
                    file_put_contents($release_path, $source);
                    $this->app->logger->log("Create entry file `$release_path'", 101);
                } elseif (file_exists($release_path)) {
                    @unlink($release_path);
                    $this->app->logger->log("Remove entry file `$release_path'", 101);
                }
                $this->buildArchives($new_entrykey);
            } catch (\Exception $e) {
                trigger_error($e->getMessage());

                return false;
            }
        }

        return $return_value;
    }

    /**
     * Release the Section.
     *
     * @param array $post
     * @param bool  $copy
     *
     * @return bool
     */
    protected function releaseSection($post, $copy)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $sid = $this->siteID;
        $entrykey = $post['eid'];
        $id = $post['id'];
        $table = 'section';

        $latest_version = $this->db->max('revision', $table, 'sitekey = ? AND identifier = ?', [$sid, $id]);
        $new_version = (int) $latest_version + 1;

        $upload_dir = $this->fileUploadDir();

        if ($copy || $latest_version === '0') {

            $this->db->update($table, ['active' => '0'], 'identifier = ?', [$id]);

            $fields = $this->db->getFields($table);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'active':
                        $cols[] = "'1' AS active";
                        break;
                    case 'id':
                    case 'status':
                        $cols[] = "NULL AS `$field`";
                        break;
                    case 'revision':
                        $cols[] = $this->db->quote($new_version).' AS revision';
                        break;
                    default:
                        $cols[] = $field;
                        break;
                }
            }
            if (false === $this->db->copyRecord($cols, $table, '', 'id = ?', [$id])) {
                return false;
            }
            $sectionkey = $this->db->lastInsertId(null, 'id');
            $raw = null;
            $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$id], $raw);

            // Remove older version
            $save_count = $this->db->get('maxrevision', 'site', 'id = ?', [$this->siteID]);
            $limit = $new_version - (int) $save_count;

            if (false !== $deletes = $this->db->select('id, entrykey', $table, "WHERE sitekey = ? AND identifier = ? AND revision > '0' AND revision < ?", [$sid, $id, $limit])) {
                foreach ($deletes as $delete) {
                    \P5\File::rmdir("$upload_dir/{$delete['entrykey']}/{$delete['id']}", true);
                    // Custom fields
                    if (false === $this->db->delete('custom', 'sitekey = ? AND kind = ? AND relkey = ?', [$sid, 'section', $delete['id']])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                }
            }

            if (false === $this->db->delete($table, "sitekey = ? AND identifier = ? AND revision > '0' AND revision < ?", [$sid, $id, $limit])) {
                trigger_error($this->db->error());

                return false;
            }
        } else {
            $save = ['active' => '1'];
            foreach ($this->date_columns as $x_date) {
                if (isset($post[$x_date])) {
                    $save[$x_date] = date('Y-m-d H:i', \P5\Text::strtotime($post[$x_date]));
                }
            }
            $this->db->update($table, $save, 'identifier = ? ORDER BY revision DESC LIMIT 1', [$id]);
            $sectionkey = $this->db->get('id', $table, 'identifier = ? AND active = 1', [$id]);
        }

        $this->copyAttachments($sectionkey, 'section', rtrim($upload_dir, '/') . "/$entrykey");
        $this->copyCustomFields($entrykey, $sectionkey);

        return $return_value;
    }

    private function copyCustomFields($entrykey, $sectionkey = null)
    {
        $kind = (empty($sectionkey)) ? 'entry' : 'section';
        $relkey = ($kind === 'section') ? $sectionkey : $entrykey;
        $this->db->delete('custom', 'relkey = ? AND kind = ?', [$relkey, $kind]);
        $identifier = $this->db->get('identifier', $kind, 'id = ?', [$relkey]);
        $fields = $this->db->select('*', 'custom', 'WHERE relkey = ? AND kind = ?', [$identifier, $kind]);
        $dest = $this->site_data['path'].$this->site_data['uploaddir']."/$entrykey/$sectionkey";
        foreach ((array) $fields as $field) {
            unset($field['id']);
            if (strpos($field['name'], 'file.') === 0) {
                $field['data'] = \P5\File::realpath("$dest/".basename($field['data']));
            }
            $field['relkey'] = $relkey;
            if (false === $this->db->insert('custom', $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * a Public entry to Private.
     *
     * @param array $post
     *
     * @return bool
     */
    protected function toPrivate($post)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $table = 'entry';
        $entrykey = $post['id'];
        if (false === $ret = $this->db->update($table, ['active' => '0'], 'identifier = ?', [$entrykey])) {
            return false;
        }
        if ($ret > 0) {
            $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$entrykey]);
        }

        $this->removeFiles($entrykey);

        if ($this->siteProperty('type') === 'static') {
            // Rebuild Archives
            if (false === $this->buildArchives($entrykey)) {
                return false;
            }

            $release_path = $this->getEntryPath($entrykey);
            if (file_exists($release_path)) {
                if (false === @unlink($release_path)) {
                    return false;
                }
            }

            $directory = dirname($release_path);
            if (count(glob("$directory/*")) === 0) {
                rmdir($directory);
            }
        }

        return $return_value;
    }

    /**
     * Remove data.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('cms.entry.delete');

        list($kind, $id) = explode(':', $this->request->param('delete'));

        // Remove Public files
        $this->toPrivate(['id' => $id]);

        // Clean up attachment files
        $directories = $this->db->select('id', 'entry', 'WHERE identifier = ?', [$id]);
        foreach ($directories as $directory) {
            $this->removeFiles($directory['id']);
        }

        if ($kind === 'category') {
            return parent::remove();
        }

        $this->db->begin();

        $result = true;
        $plugin_result = $this->app->execPlugin('beforeRemove', $id);
        foreach($plugin_result as $plugin_count) {
            if (false === $plugin_count) {
                $result = false;
                break;
            }
        }

        if ($result !== false && false !== $this->db->delete('section', 'entrykey = ?', [$id])) {
            if (false !== $this->db->delete('entry', 'identifier = ?', [$id])) {
                $this->app->logger->log("Remove the entry `{$id}'", 101);

                return $this->db->commit();
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Build entry source.
     *
     * @param int  $id
     * @param bool $preview
     *
     * @return mixed
     */
    protected function build($id, $preview = false, $force_db = false)
    {
        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'entry');

        $entry = [];
        if ($preview === true) {
            $entry = $this->request->param();
            if (!isset($entry['identifier'])) {
                $entry['identifier'] = (isset($entry['id'])) ? $entry['id'] : $id;
            }
        }
        if ((bool) $force_db === false && isset($entry['template'])) {
            $entry['category'] = $this->categoryID;
            $tid = $entry['template'];
        } else {
            if (!empty($id)) {
                $statement = 'WHERE id = ?';
                $data = $this->db->select('*', 'entry', $statement, [$id]);
                if (false === $data) {
                    return false;
                }
                if (count($data) > 0) {
                    if ((bool) $force_db) {
                        $entry = [];
                    }
                    $saved = array_shift($data);
                    $tid = $saved['template'];
                    foreach ($saved as $key => $value) {
                        if (!isset($entry[$key])) {
                            $entry[$key] = $saved[$key];
                        }
                    }
                }
            }
        }

        if (property_exists($this, 'advanced_template') && !empty($this->advanced_template)) {
            $tid = $this->advanced_template;
        }

        if (empty($tid)) {
            return '';
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data', 'custom', 'WHERE sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, $entry['id'], 'entry', 'cst_%']
        );
        foreach ((array) $customs as $unit) {
            if (!isset($entry[$unit['name']])) {
                $entry[$unit['name']] = $unit['data'];
            }
        }

        // Category data
        $category = $this->categoryData($entry['category']);
        $this->view->bind('category', $category);
        $sub_class = trim(str_replace(['/','.'], ['-','_'], preg_replace('/\.html?$/','',$category['path'])), '-_');

        $current_build_category_origin = $this->session->param('current_build_category');
        $this->session->param('current_build_category', $entry['category']);

        $apps = new Response($this->app);
        $this->view->bind('apps', $apps);

        $statement = ($preview) ? ' AND revision = 0' : ' AND active = 1';
        $template = $this->db->get(
            'sourcecode,kind,path', 'template',
            'identifier = ?'.$statement,
            [$tid]
        );

        // The entry use dummy template
        if ($preview === true && $template['kind'] === '0') {
            return $this->buildArchive($entry['category'], $preview);
        }

        $entry['url'] = $this->getEntryPath($id, 1);
        $this->view->bind('current', $entry);

        $this->bindSiteData($entry['url']);

        if (empty($template['sourcecode']) || ($template['kind'] === '0' && $preview === false)) {
            return '';
        }

        $pagenumber = $this->request->param('current_page');
        if (empty($pagenumber)) {
            $pagenumber = 1;
        }
        $this->view->bind('page_number', $pagenumber);

        $html_class = str_replace('_', '-', $template['path']);
        $html_id = $this->pathToID($entry['url']);
        if (empty($html_id)) {
            $html_id = $html_class;
        }
        $this->view->bind('html_id', $html_id);

        if (!empty($sub_class)) {
            $html_class .= " $sub_class";
        }
        $this->view->bind('html_class', $html_class);

        $this->view->bind('get', $this->request->get());

        $this->view->bind('build_type', $this->session->param('build_type'));

        $params = $this->siteProperty('type') !== 'dynamic';
        $path = $this->templatePath($tid);
        $this->view->setPath(dirname($path));

        $source = $this->view->render(basename($path), $params);

        $this->session->param('current_build_category', $current_build_category_origin);
        $this->session->param('build_type', $build_type);

        if ($preview) {
            $js = PHP_EOL.'<script src="script/cms/preview.js"></script>';
            if (preg_match("/<\/(body|html)>/i", $source, $match)) {
                $source = preg_replace('/'.preg_quote($match[0], '/').'/', $js.$match[0], $source);
            } else {
                $source .= $js;
            }
        }

        return $source;
    }

    /**
     * Create HTML file.
     *
     * @param int $entrykey
     *
     * @return bool
     */
    protected function createEntryFile($entrykey)
    {
        $release_path = $this->getEntryPath($entrykey);
        if (empty($release_path)) {
            return false;
        }

        // Check release path
        $dir = dirname($release_path);
        if (!file_exists($dir) && !\P5\File::mkdir($dir)) {
            return false;
        }

        // Remove older files
        if ($files = $this->db->select('filepath', 'entry', 'WHERE identifier = ?', [$entrykey])) {
            foreach ((array) $files as $file) {
                $remove_file = $dir.'/'.$file['filepath'];
                if (file_exists($remove_file)) {
                    @unlink($remove_file);
                }
            }
        }

        $source = $this->build($entrykey);
        if (empty($source)) {
            if (file_exists($release_path)) {
                @unlink($release_path);
            }

            return true;
        }

        return file_put_contents($release_path, $source);
    }

    /*
     * Image List
     *
     * @param int $entrykey
     * @return mixed
     */
    protected function imageList($entrykey)
    {
        $list = $this->db->select(
            'id, data, mime',
            'custom',
            'WHERE sitekey = ? AND relkey = ?',
            [$this->siteID, $entrykey]
        );

        return $list;
    }

    /**
     * Remove image files for preview.
     */
    public function removePreviewImages()
    {
        $upload_dir = $this->fileUploadDir('preview');
        if (file_exists($upload_dir)) {
            $this->db->delete('custom', 'sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?', [$this->siteID, 0, 'entry', 'file.%']);
            \P5\File::rmdir($upload_dir, true);
        }
    }

    public function createRelation($entrykey, $relkey)
    {
        if (   is_null($entrykey)
            || $this->db->exists('relation', 'entrykey = ? AND relkey = ?', [$entrykey, $relkey])
        ) {
            return true;
        }
        return $this->db->insert('relation', ['entrykey' => $entrykey, 'relkey' => $relkey]);
    }

    /**
     * Relational entries
     *
     * @param int   $entrykey
     * @param array $columns
     *
     * @return array
     */
    public function relations($entrykey, array $columns = null)
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'rel.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "rel.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $sql = "SELECT $columns
                  FROM table::entry rel
                  JOIN (SELECT r.relkey
                          FROM table::relation r
                          JOIN (SELECT id FROM table::entry WHERE id = ?) e
                            ON r.entrykey = e.id) rev
                    ON rel.id = rev.relkey";
        
        return $this->db->getAll($sql, [$entrykey]);
    }

    /**
     * Recommend entries
     *
     * @param int   $entrykey
     * @param array $columns
     *
     * @return array
     */
    public function recommends($entrykey, array $columns = null)
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'rel.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "rel.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $sql = "SELECT $columns
                  FROM (SELECT * FROM table::entry WHERE active = 1) rel
                  JOIN (SELECT r.relkey
                          FROM table::relation r
                          JOIN (SELECT identifier FROM table::entry WHERE identifier = ? AND active = 1) e
                            ON r.entrykey = e.identifier) rev
                    ON rel.identifier = rev.relkey";
        
        return $this->db->getAll($sql, [$entrykey]);
    }

    public static function clearPDFThumbnail($pdf)
    {
        $result = true;
        $thumbnails = glob("$pdf*".parent::THUMBNAIL_EXTENSION);
        foreach ($thumbnails as $thumbnail) {
            try {
                unlink($thumbnail);
            } catch (\ErrorException $e) {
                $result = false;
            }
        }

        return $result;
    }

    public function createPDFThumbnail($command, $pdf, $pages = null, $density = 144, $quarity = 90)
    {
        $result = true;
        $start = 0;
        $end = 0;

        // cleanup old files
        self::clearPDFThumbnail($pdf);
        if (strtolower($pages) === 'none') {
            return $result;
        }

        if ($command === 'imagick') {
            $convert = new \Imagick();
            $convert->setResolution($density,$density);
            $convert->readImage($pdf);
            $convert->setCompressionQuality($quarity);
            if ($pages === strtolower('all')) {
                $end = $convert->getImageScene();
            }
            elseif (preg_match('/^(\d+)-(\d+)?$/', $pages, $range)) {
                $start = (int)$range[1];
                $end = (isset($range[2]) && !empty($range[2])) ? (int)$range[2] : $convert->getImageScene();
            }
            elseif (preg_match('/^(\d+)$/', $pages, $range)) {
                $start = $end = (int)$pages;
            }
            for ($i = $start; $i <= $end; $i++) {
                $convert->setIteratorIndex($i);
                $result = $convert->writeImage("$pdf-$i".parent::THUMBNAIL_EXTENSION);
            }
            $convert->destroy();
        }
        elseif (!empty($command)) {
            $page = '[0]';
            if ($pages === strtolower('all')) {
                $page = '';
            }
            elseif (preg_match('/^(\d+)-(\d+)?$/', $pages, $range)) {
                $page = $pages;
            }
            elseif (preg_match('/^(\d+)$/', $pages, $range)) {
                $page = "[$pages]";
            }
            exec("$command -density $density -quality $quarity $pdf$page $pdf".parent::THUMBNAIL_EXTENSION, $output, $status);
            $result = ($status === 0);
        }

        return $result;
    }

    protected function entryData($id, $column='*', $raw = false)
    {
        $statement = "sitekey = ?";
        $options = [$this->siteID];

        $statement .= ($raw !== false) ? " AND identifier = id AND identifier = ?" : " AND id = ?";
        $options[] = $id;

        $fetch = $this->db->select($column, 'entry', "WHERE $statement", $options);
        if (!empty($fetch)) {
            $data = array_shift($fetch);
            $data['url'] = $this->getEntryPath($id, 1);
            $data['html_id'] = $this->pathToID($data['url']);
            return $data;
        }
    }

    /**
     * Copy attachment files
     *
     * @param int $somekey
     * @param string $table
     * @param string $upload_dir
     *
     * @return bool
     */
    private function copyAttachments($somekey, $table, $upload_dir)
    {
        $identifier = $this->db->get('identifier', $table, 'id = ?', [$somekey]);
        $src = rtrim($upload_dir, '/') . "/$identifier";
        if (!is_dir($src)) {
            return false;
        }
        $dest = "$upload_dir/$somekey";
        if (is_dir($dest)) {
            \P5\File::rmdir($dest, true);
        }

        if ($table === 'section') {
            return \P5\File::copy($src, $dest, true, 'hard');
        }

        mkdir($dest, 0777, true);
        $files = scandir($src);
        foreach ($files as $file) {
            $path = "$src/$file";
            if (is_file($path)) {
                if (false === \P5\File::copy($path, "$dest/$file", false, 'hard')) {
                    return false;
                }
            }
        }
    }
}
