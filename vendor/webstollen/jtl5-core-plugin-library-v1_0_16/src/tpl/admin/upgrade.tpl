<h1>{__('Upgrade-Assistent')}</h1>

{if isset($upgradeErrors)}

    {if count($upgradeErrors)}
        <div class="alert alert-danger">
            {__('Beim Upgrade tarten ein oder mehrere Fehler auf:')}
            <ul>
                {foreach from=$upgradeErrors item=error}
                    <li>{$error}</li>
                {/foreach}
            </ul>
        </div>
    {else}
        <div class="alert alert-success">
            {__('Upgrade wurde erfolgreich durchgeführt!')}
            <a href="?kPlugin={$oPlugin->getID()}" class="btn btn-success">weiter</a>
        </div>
    {/if}

{else}
    <p>{sprintf(__("Da die JTL-Shop 4 Version dieses Plugins (<code>%s</code>) noch installiert ist, hast du hier die Möglichkeit die Daten zu migrieren."), $oldPlugin->getPluginID())}</p>
    {if $isValid}
        <form method="post" action="?kPlugin={$oPlugin->getID()}">
            <input type="hidden" name="_upgrade" value="1"/>
            <div class="alert alert-success">{__('Migration kann durchgeführt werden.')}</div>
            <ul>
                {foreach from=$upgradeTypes item=type}
                    <li>
                        <label>
                            <input name="types[]" value="{$type}" type="checkbox" checked> {__("Synctype.$type")}
                        </label>
                    </li>
                {/foreach}
            </ul>
            <button type="submit" class="btn btn-success">{__('Upgrade!')}</button>
            <a href="?kPlugin={$oPlugin->getID()}&skipUpgrade=1" class="btn btn-danger">{__('Abbrechen')}</a>
        </form>
    {else}
        <div class="alert alert-danger">{__('Migration kann nicht durchgeführt werden:')}
            <ul>
                {foreach from=$errors item=error}
                    <li>{$error}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

{/if}