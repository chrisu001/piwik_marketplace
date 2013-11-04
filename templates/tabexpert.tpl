<h2>{'APUA_Expert_Title'|translate}</h2>
<p>{'APUA_Expert_Teaser'|translate}</p>

<!--  Appstore formular -->
<div class='entityContainer'>
<form method="GET" action="index.php">
<input type="hidden" name="module" value="PluginMarketplace"/>
<input type="hidden" name="action" value="addprocess"/>

<table class="dataTable entityTable">
	<thead>
	<tr>
		<th>{'APUA_Index_th_Plugin'|translate}</th>
		<th>{'General_Description'|translate}</th>
        <th class="num">{'APUA_Index_th_InstalledVersion'|translate}</th>
		<th class="num">{'APUA_Index_th_AvailableVersion'|translate}</th>
		<th class="action-links">{'APUA_Index_th_Action'|translate}</th>
		<th class="action-links">&nbsp;</th>
	</tr>
	</thead>
	<tbody id="plugins">
	{* Show plugins if: @TODO explain displayprocess
	
	 *}
	{foreach from=$pluginsName key=name item=plugin}
	{* calculate the possible actions and the labels *}
	{assign var="action" value=""}{assign var="actionlabel" value=""}
	{if !$plugin.isActivated}{assign var="actionlabel" value="APUA_Index_Activate"}{assign var="action" value="activate"}{/if}
	{if $plugin.isAppstoreAvail && ($plugin.info.version != $plugin.appstoreinfo.version)}{assign var="actionlabel" value="APUA_Index_Upgrade"}{assign var="action" value="install"}{/if}
	{if $plugin.isAppstoreAvail && !$plugin.isInstalled}{assign var="actionlabel" value="APUA_Index_Install"}{assign var="action" value="install"}{/if}
	{if $plugin.skipinstall}{assign var="actionlabel" value=""}{assign var="action" value="download"}{/if}
	
	
	
		<tr{if $plugin.isAppstoreAvail && (!$plugin.isInstalled || $plugin.info.version != $plugin.appstoreinfo.version) } class="highlighted"{/if}>
			<td class="name">
				{if isset($plugin.appstoreinfo.appstore_url)}<a target="__blank" title="{'PluginMarketplace_PluginHomepage'|translate}" href="?module=Proxy&action=redirect&url={$plugin.appstoreinfo.appstore_url|urlencode}" target="_blank">{/if}
				{$name}
				{if isset($plugin.appstoreinfo.appstore_url)}</a>{/if}
			</td>
			<td class="desc">
				{$plugin.info.description|nl2br}
				{if !empty($plugin.info.license)}&nbsp;({$plugin.info.license}){/if}
				{if !empty($plugin.info.author)}&nbsp;<cite>By {$plugin.info.author}.</cite>{/if}
			</td>
			<td class="vers">{if $plugin.isInstalled}{if $plugin.isActivated}<b>{/if}{if $plugin.info.version}{$plugin.info.version}{/if}{if $plugin.isActivated}</b>{/if}{/if}</td>
			<td class="vers">{if !empty($plugin.appstoreinfo)}{$plugin.appstoreinfo.version}{/if}</td>
			<td class="togl action-links" style="text-align: right">{if $actionlabel != ''} {$actionlabel|translate}{/if}</td>
			<td class="togl action-links">
			 {if $action == 'install'}<input type="checkbox" pname="{$name}" name="pluginstall[]" value="{$plugin.appstoreinfo.webid}"/>
			 {elseif $action == 'activate'}<input type="checkbox" name="plugactivate[]" value="{$name}"/>
			 {elseif $action == 'download'}<a target="__blank" href="{$plugin.appstoreinfo.download_url}" style="color:#FFF;text-decoration:none:font-size:80%" class="submit">{'APUA_Index_BTN_Download'|translate}</a>
    	     {/if}
			</td>
			 <td class="togl action-links">
			 {if  $name != 'PluginMarketplace' 
                   && $name != 'Actions'
                   && !empty($plugin.isInstalled) 
                   && empty($plugin.isAlwaysActivated) } 
			     <a title="{$name}" href="{$selfaction}addprocess&plugremove[]={$name}" style="color:#FFF;text-decoration:none:font-size:80%" class="submit removeplug">{'APUA_Index_BTN_Remove'|translate}</a>
			 {/if}
			 </td>
		</tr>
	
{/foreach}
</tbody>
</table>
<div style="text-align: right; min-width:800px; margin-top:10px;"><input type="submit" class="submit" value="Install"></div>
</form>
</div>
<!--  /Appstore formular -->
{if 'live' == 'jenkins'}
<!--  Debugging  -->
<div id="debug" style="background-color: #ccc;display:none;">Debug<hr>{if !empty($debug)}<pre>{$debug}</pre>{/if}</div>
<a href="#" onclick="$('#debug').show();">show debug</a><br>
<!--  /Debugging  -->
{/if}
<div class="ui-confirm" id="confirmRemovePlugin">
    <h2>{'APUA_Delete_Confirm_Title'|translate} <span id="pluginNameToConfirm"></span></h2>
    {'APUA_Delete_Confirm_Text'|translate}
    <input id="confirmRemovePluginYes" role="yes" type="button" value="{'General_Yes'|translate}" />
    <input id="confirmRemovePluginNo"  role="no" type="button" value="{'General_No'|translate}" />
</div>

{literal}
<script type="text/javascript">

$(function() {

	// remove plugin confirmation and reloacte
	 $('.submit.removeplug').click(function() {
		var url = $(this).attr('href');
		$('#pluginNameToConfirm').html($(this).attr('title'));
		piwikHelper.modalConfirm('#confirmRemovePlugin', {
			yes: function() {
				var loc = window.location.pathname + url; 
				// window.location.href =  loc;
            	window.location.replace(loc);
			 }       
		});
		return false;
	});
});

</script>

{/literal}

