{% macro recursion(eid, i) %}
  {% import _self as self %}
  {% set sections = apps.childSections(eid,i,['id','title']) %}
  {% for item in sections %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li>
      <div class="line">
        <a href="?mode=cms.section.response:edit&amp;id={{ item.id }}"{% if item.id == session.current_category %} class="current"{% endif %}>{{ item.title }}</a>
        <label><input type="radio" name="remove" value="{{ item.id }}">削除</label>
      </div>
    {% if item.id is not null %}
      {{ self.recursion(eid,item.id) }}
    {% endif %}
    </li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %}
{% endmacro %}
