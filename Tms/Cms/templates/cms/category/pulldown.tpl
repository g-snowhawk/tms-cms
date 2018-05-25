{% macro recursion(i, type, name) %}
  {% import _self as self %}
  {% set category = apps.childCategories(i) %}
  {% for item in category %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li><label><input type="{{ type }}" name="{{ name }}" value="{{ item.id }}"{% if item.id == session.current_category %} checked{% endif %}><span>✓</span><span class="pulldown">{{ item.title }}</span></label>
    {% if item.id is not null %}
      {{ self.recursion(item.id, type, name) }}
    {% endif %}
    </li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %}
{% endmacro %}
