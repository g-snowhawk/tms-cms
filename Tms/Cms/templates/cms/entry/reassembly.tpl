{% extends "master.tpl" %}

{% block head %}
  <link rel="stylesheet" href="style/cms/default.css">
{% endblock %}

{% block main %}
      <div class="wrapper">
        <h1>再構築</h1>
        <p>本処理は、公開されているドキュメント全てを再作成します。<br>
           カテゴリの追加／削除など、サイト全体に影響する変更を行なった場合に実行してください。</p>
        <p>ドキュメント数が多い場合は、時間のかかる可能性があります。<br>
           Webサイトへのアクセスが少ない時間帯に実行することを推奨します。</p>
        <div class="form-footer">
          <input type="submit" name="s1_submit" value="再構築を実行">
          <input type="hidden" name="mode" value="cms.entry.receive:reassembly">
        </div>
      </div>
{% endblock %}
