{% if session.current_site is defined %} 
  {% if apps.hasPermission('cms.template.read') %}
    <li id="cms-template"><a href="?mode=cms.template.response">テンプレート管理</a><hr></li>
  {% endif %}
{% endif %}
