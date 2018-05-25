{% if session.current_site is defined %} 
  <li id="cms-page"><a href="?mode=cms.entry.response">ページ管理</a></li>
  {% if site.type == 'static' %}
    <li id="cms-reassembly"><a href="?mode=cms.entry.response:reassembly">再構築</a></li>
  {% endif %}
  <li id="cms-site"><a href="?mode=cms.filemanager.response">ファイル管理</a></li>
{% endif %}
<li id="cms-site"><a href="?mode=cms.site.response">サイト管理</a></li>
