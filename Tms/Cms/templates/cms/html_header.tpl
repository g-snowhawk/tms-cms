{% if header.id == 'site-default' %}
  <script src="script/cms/site.js" charset="utf-8"></script>
{% elseif header.id == 'entry-default' %}
  <script src="script/cms/category.js" charset="utf-8"></script>
{% elseif header.id == 'entry-edit' or header.id == 'section-edit' %}
  <script src="script/cms/entry.js" charset="utf-8"></script>
  <script src="script/cms/attachments.js" charset="utf-8"></script>
  <script src="script/uploader.js" charset="utf-8"></script>
  <script src="script/editor.js" charset="utf-8"></script>
  <link rel="stylesheet" href="style/editor.css">
{% endif %}
