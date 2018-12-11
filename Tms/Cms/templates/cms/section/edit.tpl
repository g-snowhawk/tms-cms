{% extends "master.tpl" %}

{% block main %}
  <p id="backlink">
    <a href="?mode=cms.entry.response:edit&amp;id={{ post.eid }}">エントリに戻る</a>
    {% if post.prn is not empty %}
      /&nbsp;<a href="?mode=cms.section.response:edit&amp;id={{ post.prn }}">ひとつ上に戻る</a>
    {% endif %}
  </p>
  <div class="wrapper">
    <h1>セクション編集</h1>
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
    <div class="fieldset">
      <div class="legend">公開オプション</div>
      <div class="input">
        <label><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>保存のみ</label>
        <label><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>公開</label>
        <label><input type="radio" name="publish" value="private"{% if post.publish == 'private' %} checked{% endif %}>非公開</label>
      </div>
    </div>

    {% include 'cms/attachments.tpl' %}

    {% if post.id is not empty and post.level < 6 %}
      <section id="section-list" class="relational-list">
        <h2>子セクション</h2>
        <nav>
          {% import "cms/section/tree.tpl" as tree %}
          {{ tree.recursion(post.eid, post.id) }}
        </nav>
        <p class="create function-key"><a href="?mode=cms.section.response:edit&amp;eid={{ post.eid }}&amp;prn={{ post.id }}"><i>+</i>子セクション追加</a></p>
      </section>
    {% endif %}

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <input type="submit" name="s1_submit" value="保存">
      <input type="hidden" name="mode" value="cms.section.receive:save">
      <input type="hidden" name="eid" value="{{ post.eid }}">
      <input type="hidden" name="prn" value="{{ post.prn }}">
      <input type="hidden" name="id" value="{{ post.id }}">
      <input type="hidden" name="level" value="{{ post.level }}">
    </div>
  </div>
{% endblock %}
