{% extends "master.tpl" %}

{% block main %}
  <div class="wrapper">
    <h1>登録サイト一覧</h1>
    {% for unit in sites %}
      <section{% if session.current_site == unit.id %} class="current"{% endif %}>
        <h2>{{ unit.title }}</h2>
        <p>{{ unit.url }}
        <p class="controls">
        {% if apps.hasPermission('cms.site.update',unit.id) and unit.update != '0' %} 
          <a href="?mode=cms.site.response:edit&id={{ unit.id }}">編集</a>
        {% endif %}
        <label>選択<input type="radio" name="choice" value="{{ unit.id }}"{% if current_site == unit.id %} checked{% endif %}></label>
    </section>
    {% else %}
      <p>サイトが登録されていません
    {% endfor %}
    {% if apps.hasPermission('cms.site.create') %} 
      <p class="create function-key"><a href="?mode=cms.site.response:edit"><i>+</i>新規サイト</a></p>
    {% endif %}
  </div>
  <input type="hidden" name="mode" value="cms.site.receive:select">
{% endblock %}
