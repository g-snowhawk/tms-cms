{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/fix_thead_vertical_scroll.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.template.receive:remove">
  <div class="explorer-list">
    <h1 class="headline">テンプレート一覧</h1>
      <table class="ftv-table">
        <thead>
          <tr>
            <td>タイプ</td>
            <td>テンプレート名</td>
            <td>作成日</td>
            <td>更新日</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </thead>
        <tbody>
          {% for unit in templates %}
            <tr>
              <td>{{ kinds[unit.kind] }}</td>
              <td class="spacer link"><a href="?mode=cms.template.response:edit&id={{ unit.id|url_encode }}">{{ unit.title }}</a></td>
              <td class="date">{{ unit.create_date|date('Y年n月j日 H:i') }}</td>
              <td class="date">{{ unit.modify_date|date('Y年n月j日 H:i') }}</td>
              {% if apps.hasPermission('cms.template.update') %}
                <td class="button"><a href="?mode=cms.template.response:edit&id={{ unit.id|url_encode }}">編集</a></td>
              {% else %}
                <td class="button">&nbsp;</td>
              {% endif %}
              {% if apps.hasPermission('cms.template.create') %}
                <td class="button"><a href="?mode=cms.template.response:edit&cp={{ unit.id|url_encode }}">複製</a></td>
              {% else %}
                <td class="button">&nbsp;</td>
              {% endif %}
              {% if apps.hasPermission('cms.template.delete') %}
                <td class="button reddy">{% if unit.kind != 1 %}<label><input type="radio" name="delete" value="{{ unit.id }}">削除</label>{% else %}&nbsp;{% endif %}</td>
              {% else %}
                <td class="button reddy">&nbsp;</td>
              {% endif %}
            </tr>
          {% else %}
            <tr>
              <td class="nowrap empty" colspan="4">テンプレートの登録がありません</td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
      <div class="footer-controls">
        <nav class="links">
          {% if apps.hasPermission('cms.template.create') %}
            <a href="?mode=cms.template.response:edit"><mark>+</mark>新規テンプレート</a>
          {% endif %}
          {% if apps.hasPermission('root') %}
            <a href="?mode=cms.template.receive:export" class="post-request">エクスポート</a>
          {% else %}
            <span>&nbsp;</span>
          {% endif %}
        </nav>
        <nav class="pagination">
          {% include 'pagination.tpl' %}
        </nav>
      </div>
  </div>
{% endblock %}
