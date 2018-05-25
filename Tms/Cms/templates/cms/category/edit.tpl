{% extends "master.tpl" %}

{% block head %}
  <link rel="stylesheet" href="style/cms/default.css">
  <script src="script/custom_selector.js"></script>
{% endblock %}

{% block main %}
  <p id="backlink"><a href="?mode=cms.entry.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>カテゴリー編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">和名</label>
      <input type="text" name="title" id="title" value="{{ post.title }}">
    </div>
    <div class="fieldset">
      <label for="path">英名</label>
      <input type="text" name="path" id="path" value="{{ post.path }}">
    </div>
    <div class="fieldset">
      <label for="tags">キーワード</label>
      <input type="text" name="tags" id="tags" value="{{ post.tags }}">
    </div>
    {% if err.vl_description == 2 %}
      <div class="error">
        <i>HTMLタグを含むことはできません</i>
      </div>
    {% endif %}
    <div class="fieldset">
      <label for="description">要約</label>
      <textarea name="description" id="description">{{ post.description }}</textarea>
    </div>

    <div class="fieldset">
      <label for="parent">親カテゴリ</label>
      <div class="input select-box">
        <div class="select-text"></div>
        <div class="select-menu" id="category-selector">
          {% import "cms/category/pulldown.tpl" as pulldown %}
          {{ pulldown.recursion(null, 'radio', 'parent') }}
        </div>
      </div>
    </div>

    {% if err.vl_template == 1 %}
      <div class="error">
        <i>選択してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_template == 1 %} invalid{% endif %}">
      <label for="template">テンプレート</label>
      <select name="template" id="template">
      {% for unit in templates %}
        {% if loop.first %}
          <option value="">選択してください</option>
        {% endif %}
        <option value="{{ unit.id }}"{% if post.template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% else %}
        <option value="">テンプレートが登録されていません</option>
      {% endfor %}
      </select>
    </div>

    <div class="fieldset">
      <label for="default_template">初期テンプレート</label>
      <select name="default_template" id="default_template">
      {% for unit in default_templates %}
        {% if loop.first %}
          <option value="">選択してください</option>
        {% endif %}
        <option value="{{ unit.id }}"{% if post.default_template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% else %}
        <option value="">テンプレートが登録されていません</option>
      {% endfor %}
      </select>
    </div>

    {% if apps.hasPermission('site.create_priv') %}
      <div class="fieldset">
        <div class="legend">継承</div>
        <div class="input">
          <label><input type="checkbox" name="inheritance" value="1"{% if post.inheritance == 1 %} checked{% endif %}>テンプレートを子カテゴリに継承する</label>
        </div>
      </div>
    {% else %}
      <input type="hidden" name="inheritance" value="{{ post.inheritance }}">
    {% endif %}

    <div class="fieldset">
      <label for="archive_format">年月別アーカイブ</label>
      <input type="text" name="archive_format" id="archive_format" value="{{ post.archive_format }}">
      <span class="unit">{{ site.defaultextension }}</span>
    </div>

    <div class="metadata">
      登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
      更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
      編集日：<input type="datetime" name="author_date" id="author_date" value="{{ post.author_date }}" class="normal">
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="保存">
      <input type="hidden" name="mode" value="cms.category.receive:save">
      <input type="hidden" name="id" value="{{ post.id }}">
    </div>
  </div>
{% endblock %}
