{assign var=showSitesSelection value=false}
{assign var=showPeriodSelection value=false}
{include file="CoreAdminHome/templates/header.tpl"}
{literal}
<style>
div.updateerror {
	border: 1px solid red;
    margin-left: 28px;
    padding: 3px;
}

table.updateprogress {
	margin-left:100px;
	marign-top: 5px;
}

div#progressupdateinfo {
	border: 1px solid green;
}
div#htmlerror {
	border: 3px solid red;
	padding: 3px;
	display: none;
}
div#ready {
  text-align: right;
  min-width: 800px;
  margin-top: 10px;
 }
</style>
{/literal}
<script
	type="text/javascript"
	src="plugins/PluginMarketplace/templates/js/jquery.progressbar.min.js?cb=44"></script>
<script src="plugins/PluginMarketplace/templates/js/script.js?cb=44"></script>


<div style="max-width: 980px;">
<h2>{'APUA_Install_Title'|translate}</h2>
<div class='entityContainer'>
<div id="progressupdateinfo"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif"></div>
<div id="ready">
<div id="readyloader"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif" width="100"></div>
<div id="readybutton" style="display: none;"><a href='index.php?module=PluginMarketplace&action=index&{$commonquery.query}#pstabs-2' class="submit" style="color:#FFF;text-decoration:none">{'APUA_Btn_Finish'|translate}</a></div>
</div>
</div>
{if 'live' == 'jenkins'}
<!--  Debugging  -->
<div id="debug" style="background-color: #ccc;display:none;">Debuglog<hr>{if !empty($debug)}<pre>{$debug}</pre>{/if}</div>
<a href="#" onclick="$('#debug').show();">show debug</a><br>
<!--  /Debugging  -->
{/if}
<div id="htmlerror" style="display:none">Exception</div>
{literal}
<script type="text/javascript">


  $(document).ready(function() {
	  // $('#debug').hide();
{/literal}
      {if isset($plugstatus)}
	    ProcessDisplay.reset({$plugstatus});
	  {/if}
	  {if $doRun}PollStatus.isRunable = true;{/if}
{literal}      
      PollStatus.run();
  });
</script>
{/literal}
