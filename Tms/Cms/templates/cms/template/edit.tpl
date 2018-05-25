{% extends "master.tpl" %}

{% block head %}
  <link rel="stylesheet" href="style/cms/default.css">
{% endblock %}

{% block main %}
      <p id="backlink"><a href="?mode=cms.template.response">一覧に戻る</a></p>
      <div class="wrapper">
        <h1>テンプレート編集</h1>
{% if err.vl_title == 1 %}
        <div class="error">
          <i>入力してください</i>
        </div>
{% endif %}
        <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
          <label for="title">テンプレート名</label>
          <input type="text" name="title" id="title" value="{{ post.title }}" placeholder="分かりやすい名前をつけてください">
        </div>
{% if err.vl_sourcecode == 1 %}
        <div class="error">
          <i>入力してください</i>
        </div>
{% endif %}
        <div class="fieldset{% if err.vl_sourcecode == 1 %} invalid{% endif %}">
          <label for="sourcecode">テンプレート本体</label>
          <textarea name="sourcecode" id="sourcecode" wrap="off">{{ post.sourcecode }}</textarea>
        </div>
{% if post.kind != 1 %}
{% if err.vl_path == 1 %}
        <div class="error">
          <i>入力してください</i>
        </div>
{% endif %}
        <div class="fieldset{% if err.vl_path == 1 %} invalid{% endif %}">
          <label for="path">ファイル名</label>
          <input type="text" name="path" id="path" value="{{ post.path }}">
        </div>
        <div class="fieldset">
          <label for="kind">タイプ</label>
          <select name="kind" id="kind">
{% for key, value in kinds %}
{% if key != '1' %}
            <option value="{{ key }}"{% if post.kind == key %} selected{% endif %}>{{ value }}</option>
{% endif %}
{% endfor %}
          </select>
        </div>
{% else %}
        <input type="hidden" name="path" value="homepage">
        <input type="hidden" name="kind" value="1">
{% endif %}
        <div class="fieldset">
          <div class="legend">保存オプション</div>
          <div class="input">
            <label><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>保存のみ</label>
            <label><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>このテンプレートを使用する</label>
          </div>
        </div>
        <div class="metadata">
          登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
          更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
        </div>
        <div class="form-footer">
          <input type="submit" name="s1_submit" value="登録">
          <input type="hidden" name="mode" value="cms.template.receive:save">
          <input type="hidden" name="id" value="{{ post.id }}">
        </div>
      </div>
{% endblock %}
