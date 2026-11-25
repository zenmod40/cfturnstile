{* Cloudflare Turnstile : en-tête ZM40, onglets Réglages / Formulaires / Journal / Modules ZM40 (un seul formulaire). *}
<div class="zm40-ah">
  <div class="zm40-ah-brand">
    <div>
      <h2>Cloudflare Turnstile</h2>
      <div class="zm40-ah-sub">Vérification anti-robot des formulaires et du back-office &middot; v{$cft.version|escape:'html':'UTF-8'}</div>
    </div>
  </div>
  <span class="zm40-ah-badge">{if $cft.verified}Protection active{else}Protection inactive{/if}</span>
</div>

{include file=$cft.partials|cat:'zm40_update.tpl'}
{if $cft.notice}<div class="alert alert-info">{$cft.notice|escape:'html':'UTF-8'}</div>{/if}
{if $cft.recaptcha}
  <div class="alert alert-warning">Le module Google reCAPTCHA (nocaptcharecaptcha) est encore actif : les visiteurs auraient deux vérifications.
    Une fois la protection Turnstile active et essayée, désinstallez-le (Modules &gt; nocaptcharecaptcha &gt; Désinstaller), ce qui retire aussi ses overrides.</div>
{/if}

<ul class="zm40-tabs" id="zm40-tabs">
  <li data-tab="reglages"><i class="icon icon-key"></i>Réglages</li>
  <li data-tab="formulaires"><i class="icon icon-list"></i>Formulaires</li>
  <li data-tab="journal"><i class="icon icon-time"></i>Journal</li>
  <li data-tab="ecosystem"><i class="icon icon-th-large"></i>Modules ZM40</li>
</ul>

<form method="post" action="{$cft.action|escape:'html':'UTF-8'}" class="form-horizontal">
  <div class="zm40-tab-content" data-content="reglages">
    <div class="panel">
      <h3><i class="icon-key"></i> Clés Cloudflare
        <span class="cft-status {if $cft.verified}cft-on{else}cft-off{/if}">{if $cft.verified}vérifiées{else}non vérifiées{/if}</span></h3>
      <p>Dans le tableau de bord Cloudflare : <b>Turnstile &gt; Add widget</b>, avec le domaine de la boutique, en mode « Managed ».
        Copiez ici la clé de site et la clé secrète.</p>
      <div class="form-group">
        <label class="control-label col-lg-3">Clé de site</label>
        <div class="col-lg-6"><input type="text" name="sitekey" value="{$cft.sitekey|escape:'html':'UTF-8'}" autocomplete="off"></div>
      </div>
      <div class="form-group">
        <label class="control-label col-lg-3">Clé secrète</label>
        <div class="col-lg-6"><input type="password" name="secret" value="" autocomplete="new-password" placeholder="{if $cft.hasSecret}enregistrée : laisser vide pour la garder{/if}"></div>
      </div>
      {if $cft.sitekey}
        <div class="form-group">
          <label class="control-label col-lg-3">Essai</label>
          <div class="col-lg-6">
            <div class="cf-turnstile cft-test" data-sitekey="{$cft.sitekey|escape:'html':'UTF-8'}"></div>
            <script src="{$cft.api|escape:'html':'UTF-8'}" async defer></script>
            <p class="help-block">Attendez que la case soit validée, puis enregistrez : Cloudflare confirme alors les deux clés et la protection s'active.
              Tant que ce n'est pas fait, rien n'est contrôlé, et une clé fausse ne peut pas bloquer la connexion au back-office.</p>
          </div>
        </div>
      {/if}
    </div>
    <div class="panel">
      <h3><i class="icon-cogs"></i> Affichage</h3>
      <div class="form-group">
        <label class="control-label col-lg-3">Thème</label>
        <div class="col-lg-3"><select name="theme">
          {foreach $cft.themes as $v => $l}<option value="{$v|escape:'html':'UTF-8'}"{if $cft.theme == $v} selected{/if}>{$l|escape:'html':'UTF-8'}</option>{/foreach}
        </select></div>
      </div>
      <div class="form-group">
        <label class="control-label col-lg-3">Taille</label>
        <div class="col-lg-3"><select name="size">
          {foreach $cft.sizes as $v => $l}<option value="{$v|escape:'html':'UTF-8'}"{if $cft.size == $v} selected{/if}>{$l|escape:'html':'UTF-8'}</option>{/foreach}
        </select></div>
      </div>
      <div class="form-group">
        <label class="control-label col-lg-3">Clients connectés</label>
        <div class="col-lg-9"><label class="checkbox"><input type="checkbox" name="skip_logged" value="1"{if $cft.skipLogged} checked{/if}>
          Pas de vérification pour un client déjà connecté (formulaire de contact)</label></div>
      </div>
    </div>
  </div>

  <div class="zm40-tab-content" data-content="formulaires">
    <div class="panel">
      <h3><i class="icon-list"></i> Formulaires protégés</h3>
      <p>Le widget se place devant le bouton d'envoi trouvé par le sélecteur CSS, ou dans l'emplacement prévu par le thème quand il en a un. Laissez le sélecteur vide pour le réglage par défaut ; renseignez-le si votre thème n'affiche pas le widget.</p>
      <table class="table">
        <thead><tr><th>Protégé</th><th>Formulaire</th><th>Bouton (sélecteur CSS)</th></tr></thead>
        <tbody>
        {foreach $cft.labels as $form => $label}
          <tr>
            <td><input type="checkbox" name="forms[]" value="{$form|escape:'html':'UTF-8'}"{if in_array($form, $cft.enabled)} checked{/if}></td>
            <td>{$label|escape:'html':'UTF-8'}</td>
            <td>{if $form == 'admin'}<span class="cft-selector">{$cft.defaults[$form]|escape:'html':'UTF-8'}</span>
              {else}<input type="text" class="cft-selector" name="selector_{$form|escape:'html':'UTF-8'}" value="{if isset($cft.selectors[$form])}{$cft.selectors[$form]|escape:'html':'UTF-8'}{/if}" placeholder="{if $cft.defaults[$form]}{$cft.defaults[$form]|escape:'html':'UTF-8'}{else}emplacement prévu par le thème (hook){/if}">{/if}</td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  </div>

  <div class="zm40-tab-content" data-content="journal">
    <div class="panel">
      <h3><i class="icon-bar-chart"></i> 30 derniers jours</h3>
      {if $cft.stats}
        <table class="table"><thead><tr><th>Formulaire</th><th>Refusés</th><th>Laissés passer sans réponse fiable de Cloudflare</th></tr></thead><tbody>
          {foreach $cft.stats as $s}<tr><td>{if isset($cft.labels[$s.form])}{$cft.labels[$s.form]|escape:'html':'UTF-8'}{else}{$s.form|escape:'html':'UTF-8'}{/if}</td><td>{$s.blocked|intval}</td><td>{$s.open|intval}</td></tr>{/foreach}
        </tbody></table>
      {else}<p>Rien de refusé.</p>{/if}
      <h3><i class="icon-time"></i> 50 derniers événements</h3>
      {if $cft.log}
        <table class="table"><thead><tr><th>Date</th><th>Formulaire</th><th>Résultat</th><th>IP</th><th>Codes Cloudflare</th></tr></thead><tbody>
          {foreach $cft.log as $l}<tr><td>{$l.date_add|escape:'html':'UTF-8'}</td><td>{if isset($cft.labels[$l.form])}{$cft.labels[$l.form]|escape:'html':'UTF-8'}{/if}</td>
            <td>{if $l.blocked}refusé{else}laissé passer{/if}</td><td>{$l.ip|escape:'html':'UTF-8'}</td><td>{$l.codes|escape:'html':'UTF-8'}</td></tr>{/foreach}
        </tbody></table>
      {else}<p>Aucun événement.</p>{/if}
    </div>
  </div>

  <div class="zm40-tab-content" data-content="ecosystem">
    {include file=$cft.partials|cat:'zm40_modules.tpl'}
    {if !isset($zm40_modules) || !$zm40_modules|@count}<div class="panel"><p style="margin:0">Aucun module à afficher pour le moment.</p></div>{/if}
    <div class="panel">
      <div class="form-group">
        <label class="control-label col-lg-3">Mises à jour</label>
        <div class="col-lg-9"><label class="checkbox"><input type="checkbox" name="zm40_net" value="1"{if $zm40_net_enabled} checked{/if}>
          Vérifier les mises à jour et actualiser la liste des modules ZM40</label>
          <p class="help-block">Une fois par jour au plus, une requête anonyme vers zm40.com met la liste à jour et GitHub donne la dernière version. Désactivé, la liste reste affichée telle quelle. Aucune donnée de la boutique n'est transmise.</p></div>
      </div>
    </div>
  </div>

  <div class="panel-footer clearfix" id="cft-save">
    <button type="submit" name="submitCfTurnstile" value="1" class="btn btn-default pull-right"><i class="process-icon-save"></i> Enregistrer</button>
  </div>
</form>

{include file=$cft.partials|cat:'zm40_panel.tpl'}
{include file=$cft.partials|cat:'zm40_footer.tpl'}

<script>
(function () {
  var tabs = document.querySelectorAll('#zm40-tabs li');
  var contents = document.querySelectorAll('.zm40-tab-content');
  var save = document.getElementById('cft-save');
  function ouvrir(cible) {
    var li = document.querySelector('#zm40-tabs li[data-tab="' + cible + '"]');
    var pane = document.querySelector('.zm40-tab-content[data-content="' + cible + '"]');
    if (!li || !pane) { return false; }
    tabs.forEach(function (x) { x.classList.remove('is-active'); });
    contents.forEach(function (x) { x.classList.remove('is-active'); });
    li.classList.add('is-active');
    pane.classList.add('is-active');
    save.style.display = cible === 'journal' ? 'none' : '';
    try { localStorage.setItem('zm40_cfturnstile_tab', cible); } catch (e) {}
    return true;
  }
  tabs.forEach(function (li) { li.addEventListener('click', function () { ouvrir(li.getAttribute('data-tab')); }); });
  var saved = '';
  try { saved = localStorage.getItem('zm40_cfturnstile_tab') || ''; } catch (e) {}
  ouvrir(saved) || ouvrir('reglages');
})();
</script>
