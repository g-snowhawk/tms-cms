{% extends "master.tpl" %}

{% block head %}
  <script src="script/fix_thead_vertical_scroll.js"></script>
  <script src="script/subform.js"></script>
  <script src="script/cms/explorer.js"></script>
  <link rel="stylesheet" href="style/cms/default.css">
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.filemanager.receive:remove">
  <input type="hidden" name="ondrop_mode" value="cms.filemanager.receive:move">
  <input type="hidden" name="rename_mode" value="cms.filemanager.receive:rename">
  <div class="explorer">
    <div class="explorer-sidebar resizable" data-minwidth="120">
      <div class="tree">
        <h1 class="headline">フォルダ一覧</h1>
        <nav>
          <ul>
            <li><a href="?mode=cms.filemanager.receive:setDirectory&amp;path=" class="drop-target{% if session.current_dir is empty %} current{% endif %}" data-drop-path="">・Site Root</a>
              {% import "cms/filemanager/tree.tpl" as tree %}
              {{ tree.recursion(null, null) }}
            </li>
          </ul>
        </nav>
      </div>
    </div>
    <div class="explorer-mainframe">
      <div class="explorer-list">
        <h2 class="headline">ファイル一覧</h2>
        <table class="ftv-table">
          <thead>
            <tr>
              <td>ファイル名</td>
              <td>サイズ</td>
              <td>更新日</td>
              <td>&nbsp;</td>
            </tr>
          </thead>
          <tbody>
          {% for unit in files %}
            <tr class="{{ unit.kind }}">
              {% if unit.kind == 'folder' %}
                <td class="link spacer with-icon"><a href="?mode=cms.filemanager.receive:setDirectory&amp;path={{ unit.path|url_encode }}" class="renamable">{{ unit.name }}</a></td>
              {% else %}
                <td class="link spacer with-icon"><span class="renamable">{{ unit.name }}</span></td>
              {% endif %}
              <td class="date">{{ unit.size }}</td>
              <td class="date">{{ unit.modify_date|date('Y年n月j日 H:i') }}</td>
              <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.kind }}:{{ unit.name }}">削除</label></td>
            </tr>
          {% else %}
            <tr>
              <td class="nowrap empty" colspan="3">ファイルがありません</td>
              <td></td>
            </tr>
          {% endfor %}
          </tbody>
        </table>
        <div class="footer-controls">
          <nav class="links">
            <a href="?mode=cms.filemanager.response:addfolder" class="subform-opener"><i class="mark">+</i>新規フォルダ</a>
            <a href="?mode=cms.filemanager.response:addfile" class="subform-opener"><i class="mark">+</i>新規ファイル</a>
          </nav>
          <nav class="pagination">
            {% include 'pagination.tpl' %}
          </nav>
        </div>
      </div>
    </div>
  </div>
{% endblock %}
