{assign var=showSitesSelection value=false}
{assign var=showPeriodSelection value=false}
{include file="CoreAdminHome/templates/header.tpl"}
{assign var="enabledebug" value=false}
{if 'live' == 'jenkins'}{assign var="enabledebug" value=true}{/if}
<script
	type="text/javascript"
	src="plugins/PluginMarketplace/javascripts/postmessage.js?cb=56"></script>
<script src="plugins/PluginMarketplace/javascripts/script.js?cb=56"></script>

    {literal}
	<script type="text/javascript">
	
	
    $(function() {
        tabController.init();
        PlugstoreComm.init('{/literal}{$selfaction}', '{$appstoreUID}', '{$appstoreRelease}{literal}');
                
        /* $('#tlnkstore').click(function() {
        	PlugstoreComm.reloadAppFrame('') ;
        });*/
    });

/*
 * Intercommunication with the Plugstore frame
 */
    $(function() {
    		   });
    </script>
{/literal}
<div style="max-width: 980px;">
	<div id="pstabs">
		<ul>
			<li><a id="tlnkstore"    href="#pstabs-1" aria-controls="pstabs-1">{'APUA_Index_Tab_Store'|translate}</a></li>
			{if $expertmode }<li><a title="#pstabs-2" aria-controls="pstabs-2" id="tlnkinstall" href="{$selfaction}tabexpert">{'APUA_Index_Tab_Expert'|translate}</a></li>
			{else}			 <li><a title="#pstabs-2" aria-controls="pstabs-2" id="tlnkinstall" href="{$selfaction}tablist" id="tabinstallnk">{'APUA_Index_Tab_Install'|translate}</a></li>
			{/if}
			<li><a id="tlnkadvanced" href="#pstabs-3" >{'APUA_Index_Tab_Advanced'|translate}</a></li>
			<li><a id="tlnkfeedback" aria-controls="pstabs-4"  title="#pstabs-4" href="{$selfaction}tabfeedback">{'APUA_Index_Tab_Feedback'|translate}</a></li>
			{if $enabledebug }<li><a href="#debug">Debug</a></li>{/if}
		</ul>
		<div id="pstabs-1">{include
			file="PluginMarketplace/templates/tabplugstore.tpl"}</div>
		<div id="pstabs-2"></div>
		<div id="pstabs-3">{include
			file="PluginMarketplace/templates/tabupload.tpl"}
			<div class="clearfix"  style="margin: 30px"></div>
			{include
			file="PluginMarketplace/templates/tabadvanced.tpl"}</div>
			<div id="pstabs-4"></div>
		<div id="pstabs-6"></div>
			{if $enabledebug }<!--  Debug --><div id="debug">Debuglog<hr></div><!--  /Debug -->{/if}
	</div>
</div>

{include file="CoreAdminHome/templates/footer.tpl"}


