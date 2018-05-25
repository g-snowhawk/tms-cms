{% extends "master.tpl" %}

{% block head %}
  <link rel="stylesheet" href="style/cms/default.css">
  <script src="script/custom_selector.js"></script>
{% endblock %}

{% block main %}
  <p id="backlink"><a href="?mode=cms.entry.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>エントリ編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">タイトル</label>
      <input type="text" name="title" id="title" value="{{ post.title }}">
    </div>
    {% if err.vl_body == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_body == 1 %} invalid{% endif %}">
      <label for="body">本文</label>
      <textarea name="body" id="body">{{ post.body }}</textarea>
    </div>
    <nav class="insert">
      <a href="#body" data-insert="link">リンク挿入</a>
      <a href="#body" data-insert="image">画像挿入</a>
    </nav>

    <div class="fieldset">
      <label for="category">カテゴリ</label>
      <div class="input select-box">
        <div class="select-text"></div>
        <div class="select-menu" id="category-selector">
          {% import "cms/category/pulldown.tpl" as pulldown %}
          {{ pulldown.recursion(null, 'radio', 'category') }}
        </div>
      </div>
    </div>

    <div class="fieldset">
      <label for="tags">キーワード</label>
      <input type="text" name="tags" id="tags" value="{{ post.tags}}">
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
    {% if err.vl_filepath == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_filepath == 1 %} invalid{% endif %}">
      <label for="filepath">ファイル名</label>
      <input type="text" name="filepath" id="filepath" value="{{ post.filepath }}">
    </div>
    {% if err.vl_template == 1 %}
      <div class="error">
        <i>選択してください</i>
      </div>
    {% endif %}
    {% for unit in templates %}
      {% if loop.first %}
        <div class="fieldset{% if err.vl_template == 1 %} invalid{% endif %}">
          <label for="template">テンプレート</label>
          <select name="template" id="template">
            <option value="">選択してください</option>
      {% endif %}
      <option value="{{ unit.id }}"{% if post.template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% if loop.last %}
          </select>
        </div>
      {% endif %}
    {% else %}
      <script>
        alert('テンプレートが登録されていません。\n先にエントリ用のテンプレートを追加してください');
      </script>
    {% endfor %}
    <div class="fieldset">
      <div class="legend">公開オプション</div>
      <fieldset>
        <label><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>保存のみ</label>
        <label><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>公開</label>
        <label><input type="radio" name="publish" value="private"{% if post.publish == 'private' %} checked{% endif %}>非公開</label>
      </fieldset>
    </div>

    {% if site.type == 'dynamic' %}
      <div class="fieldset">
        <label for="release_date">公開期間</label>
        <div class="input">
          <input type="datetime-local" name="release_date" id="release_date" value="{{ post.release_date|date('Y-m-d\\TH:i') }}" placeholder="開始" class="datetime">&nbsp;〜&nbsp;<input type="datetime-local" name="close_date" id="close_date" value="{{ post.close_date|date('Y-m-d\\TH:i') }}" placeholder="終了" class="datetime">
        </div>
      </div>
    {% endif %}

    {% include 'cms/attachments.tpl' %}

    {% if post.id is not empty %}
      <section id="section-list" class="relational-list">
        <h2>セクション</h2>
        <nav>
          {% import "cms/section/tree.tpl" as tree %}
          {{ tree.recursion(post.id, null) }}
        </nav>
        <p class="create function-key"><a href="?mode=cms.section.response:edit&amp;eid={{ post.id }}" class="small-button"><i>+</i>セクション追加</a></p>
      </section>
    {% endif %}
    <div class="metadata">
      登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
      更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
      編集日：<input type="datetime-local" name="author_date" id="author_date" value="{{ post.author_date|date('Y-m-d\\TH:i') }}" class="normal">
    </div>
    <div class="form-footer">
      <input type="submit" name="preview" value="プレビュー">
      <input type="submit" name="s1_submit" value="保存">
      <input type="hidden" name="mode" value="cms.entry.receive:save">
      <input type="hidden" name="id" value="{{ post.id }}">
    </div>
  </div>
{% endblock %}
