{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/fix_thead_vertical_scroll.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.entry.receive:remove">
  <div class="explorer">
    <div class="explorer-sidebar resizable" data-minwidth="120">
      <div class="tree">
        <h1 class="headline">カテゴリ一覧</h1>
        <nav>
          {% import "cms/category/tree.tpl" as tree %}
          {{ tree.recursion(null) }}
        </nav>
      </div>
    </div>
    <div class="explorer-mainframe">
      <div class="explorer-list">
        <h2 class="headline">ページ一覧</h2>
        <table class="ftv-table">
          <thead>
            <tr>
              <td>タイトル</td>
              {% if site.type == 'dynamic' %}
                <td>公開期間</td>
              {% endif %}
              <td>作成日</td>
              <td>更新日</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
          </thead>
          <tbody>
          {% for unit in entries %}
          <tr class="{{ unit.kind == 'category' ? 'folder' : 'file' }}{% if unit.status is not empty %} {{ unit.status }}{% endif %}">
              {% if unit.kind == 'category' %}
                <td class="link with-icon spacer"><a href="?mode=cms.entry.receive:setCategory&amp;id={{ unit.id|url_encode }}">{{ unit.title }}</a></td>
                {% if site.type == 'dynamic' %}
                  <td class="date">---</td>
                {% endif %}
              {% else %}
                <td class="link with-icon spacer"><a href="?mode=cms.{{ unit.kind }}.response:edit&amp;id={{ unit.id|url_encode }}"{% if unit.status == 'draft' %} data-tips="下書き有り"{% endif %}>{{ unit.title }}</a></td>
                {% if site.type == 'dynamic' %}
                  <td class="date">
                    {% if unit.release_date is not empty %}{{ unit.release_date|date('Y年n月j日 H:i') }}{% endif %}
                    {% if unit.close_date is not empty %}{{ unit.close_date|date('〜 Y年n月j日 H:i') }}{% endif %}
                  </td>
                {% endif %}
              {% endif %}
              <td class="date">{{ unit.create_date|date('Y年n月j日 H:i') }}</td>
              <td class="date">{{ unit.modify_date|date('Y年n月j日 H:i') }}</td>
              {% if unit.kind == 'category' and apps.hasPermission('cms.category.update', null, session.current_category) %}
                <td class="button"><a href="?mode=cms.{{ unit.kind }}.response:edit&amp;id={{ unit.id|url_encode }}">編集</a></td>
              {% elseif unit.kind == 'entry' and apps.hasPermission('cms.entry.update', null, unit.parent) %}
                <td class="button"><a href="?mode=cms.{{ unit.kind }}.response:edit&amp;id={{ unit.id|url_encode }}">編集</a></td>
              {% else %}
                <td class="button">&nbsp;</td>
              {% endif %}
              {% if unit.kind == 'category' and apps.hasPermission('cms.category.delete', null, session.current_category) %}
                <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.kind }}:{{ unit.id }}">削除</label></td>
              {% elseif unit.kind == 'entry' and apps.hasPermission('cms.entry.delete', null, unit.parent) %}
                <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.kind }}:{{ unit.id }}">削除</label></td>
              {% else %}
                <td class="button reddy">&nbsp;</td>
              {% endif %}
            </tr>
          {% else %}
            <tr>
              <td class="nowrap empty" colspan="{{ site.type == 'dynamic' ? 4 : 3 }}">ページの登録がありません</td>
              <td></td>
              <td></td>
            </tr>
          {% endfor %}
          </tbody>
        </table>
        <div class="footer-controls">
          <nav class="links">
            {% if apps.hasPermission('cms.category.create') %}
            <a href="?mode=cms.category.response:edit" class="subform-opener"><i class="mark">+</i>新規カテゴリ</a>
            {% endif %}
            {% if apps.hasPermission('cms.entry.create') %}
              <a href="?mode=cms.entry.response:edit"><i class="mark">+</i>新規ページ</a>
            {% else %}
              <span>&nbsp;</span>
            {% endif %}
          </nav>
          <nav class="pagination">
            {% include 'pagination.tpl' %}
          </nav>
        </div>
      </div>
    </div>
  </div>
{% endblock %}
