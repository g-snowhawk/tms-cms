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
class Section extends Entry
{
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

        $table = 'section';
        $skip = [
            'id', 'sitekey', 'entrykey',
            'level', 'identifire', 'revision', 'active',
            'create_date', 'modify_date', 'lft', 'rgt',
        ];

        $eid = $this->request->param('eid');
        $parent_id = $this->request->param('prn');

        $post = $this->request->POST();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        //$valid[] = ['vl_body', 'body', 'empty'];

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
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

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $result = 0;
        if (empty($post['id'])) {
            $raw['create_date'] = 'CURRENT_TIMESTAMP';
            $save['sitekey'] = $this->siteID;
            $save['entrykey'] = $eid;

            if (empty($parent_id)) {
                $statement = 'entrykey = ? AND revision = ?';
                $options = [$eid, 0];
                $parent_rgt = (int) $this->db->max('rgt', 'section', $statement, $options) + 1;
                $save['lft'] = $parent_rgt;
                $save['rgt'] = $parent_rgt + 1;
            } else {
                $child = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0)';
                $parent = '(SELECT * FROM table::section WHERE id = ?)';
                if (false === $unit = $this->db->nsmGetPosition($parent, $child, ['entry_id' => $eid, $parent_id])) {
                    return false;
                }
                $parent_lft = (float) $unit['lft'];
                $parent_rgt = (float) $unit['rgt'];

                $save['lft'] = ($parent_lft * 2 + $parent_rgt) / 3;
                $save['rgt'] = ($parent_lft + $parent_rgt * 2) / 3;
            }

            if (!empty($parent_id)) {
                $level = $this->db->get('level', 'section', 'id = ?', [$parent_id]);
                $save['level'] = $level + 1;
                if ($save['level'] > 6) {
                    return false;
                }
                $this->request->param('level', $save['level']);
            }

            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
                $this->db->update(
                    $table, ['identifier' => $post['id']],
                    'id = ?',
                    [$post['id']]
                );
            }
        } else {
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            $file_count = $this->saveFiles($eid, $post['id']);

            $customs = [];
            foreach ($post as $key => $value) {
                if (strpos($key, 'cst_') === 0) {
                    $customs[$key] = $value;
                }
            }
            $custom_count = $this->saveCustomField('section', $post['id'], $customs);

            if ($modified !== false && $file_count !== false && $custom_count !== false) {
                $result += $file_count + $custom_count;
                if ($this->request->param('publish') === 'release') {
                    $status = $this->db->get('status', 'section', 'id = ?', [$post['id']]);
                    $copy = ($result > 0 || $status !== 'release');
                    if (false === $this->releaseSection($post, $copy)) {
                        $result = false;
                    }

                    if ($this->siteProperty('type') === 'static') {
                        // Rebuild entry file
                        $entrykey = $this->db->get('id', 'entry', 'identifier = ? AND active = 1', [$eid]);
                        if (!empty($entrykey)) {
                            $this->createEntryFile($entrykey);
                            $this->buildArchives($entrykey);
                        }
                    }
                }
                elseif ($this->request->param('publish') === 'private') {
                    if (false === $this->to_private($post)) {
                        $result = false;
                    }
                }
                else {
                    if ($result === 0) {
                        $this->app->err['vl_nochange'] = 1;
                        $result = false;
                    }
                    else {
                        $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$post['id']], $raw);
                    }
                }
            }
            else {
                $result = false;
            }

            $this->app->execPlugin('saved');

            if ($result !== false) {
                return $this->db->commit();
            }
        }
        else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * a Public entry to Private.
     *
     * @param array $post
     *
     * @return bool
     */
    protected function to_private($post)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;
        $eid = $post['id'];
        if (false === $ret = $this->db->update('section', ['active' => '0'], 'identifier = ?', [$eid])) {
            return false;
        }
        if ($ret > 0) {
            $this->db->update('section', ['status' => $this->request->param('publish')], 'id = ?', [$id], $raw);
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

        $id = $this->request->param('remove');
        $eid = $this->getEntryKey($id);

        $this->db->begin();
        $table = $this->db->TABLE('section');

        $unit = $this->db->get('lft, rgt', 'section', 'id = ?', [$id]);

        if (false !== $this->db->delete('section', 'entrykey = ? AND lft BETWEEN ? AND ?', [$eid, $unit['lft'], $unit['rgt']])) {
            return $this->db->commit();
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Parent section.
     *
     * @param int $id
     *
     * @return int
     */
    public function getParentSection($id)
    {
        if (empty($id)) {
            return $this->request->param('prn');
        }
        $eid = $this->getEntryKey($id);

        $parent = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0)';
        $children = '(SELECT * FROM table::section WHERE id = :section_id)';

        return $this->db->nsmGetParent('parent.id', $parent, $children, ['entry_id' => $eid, 'section_id' => $id]);
    }

    /**
     * Entry of the section.
     *
     * @param int $id
     *
     * @return mixed
     */
    public function getEntryKey($id)
    {
        if (empty($id)) {
            return $this->request->param('eid');
        }

        return $this->db->get('entrykey', 'section', 'id = ?', [$id]);
    }
}
