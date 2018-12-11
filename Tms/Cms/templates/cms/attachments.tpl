{% set available_convert = apps.availableConvert() %}
<section id="file-uploader">
  <h2>添付ファイル</h2>
  {% for unit in custom.file %}
    {% if loop.first %}
      <p class="caution">サムネールを右クリックすると添付ファイルの説明を追加できます。</p>
    {% endif %}
    <div class="file-set">
      <label class="selected">
        <span class="thumbnail">
          <img src="{% if unit.mime == 'application/pdf' %}{{ config.global.assets_path }}style/icon_pdf.svg{% else %}{{ unit.data }}{% endif %}" alt="{{ unit.alternate }}" title="{{ unit.title }}" draggable="false">
          {% if unit.mime == 'application/pdf' %}
            <span class="filename">{{ unit.alternate }}</span>
          {% endif %}
        </span>
        <input type="file" name="file[id_{{ unit.id }}]" accept="image/jpeg,.jpg,image/png,.png,image/gif,.gif,application/pdf,.pdf" class="image-selector">
      </label>
      <a href="#delete" class="mark"></a>
      <div class="popup">
        <b>説明文</b><textarea name="note[id_{{ unit.id }}]">{{ unit.note }}</textarea>
        {% if unit.mime == 'application/pdf' and available_convert == true %}
          <select name="option1[id_{{ unit.id }}]">
            <option value="none"{% if unit.option1 == 'none' %} selected{% endif %}>サムネールなし</option>
            <option value="0"{% if unit.option1 == '0' %} selected{% endif %}>表紙のみ</option>
            <option value="all"{% if unit.option1 == 'all' %} selected{% endif %}>全ページ</option>
          </select>
        {% endif %}
      </div>
    </div>
  {% endfor %}
  <div class="file-set" id="attachment-origin">
    <label>
      <span class="thumbnail"></span>
      <input type="file" name="file[]" accept="image/jpeg,.jpg,image/png,.png,image/gif,.gif,application/pdf,.pdf" class="image-selector">
    </label>
  </div>
  <template id="popup-note">
    <div class="popup">
      <b>説明文</b><textarea name="note[]"></textarea>
      {% if available_convert == true %}
        <select name="option1[]">
          <option value="none">サムネールなし</option>
          <option value="0">表紙のみ</option>
          <option value="all">全ページ</option>
        </select>
      {% endif %}
    </div>
  </template>
</section>
