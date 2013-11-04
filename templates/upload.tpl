{assign var=showSitesSelection value=false} {assign
var=showPeriodSelection value=false} {include
file="CoreAdminHome/templates/header.tpl"}

<div style="max-width: 980px;">
	<h2>{'APUA_Upload_Title'|translate}</h2>
	<p>{'APUA_Upload_HelpText'|translate}</p>
	<div class='entityContainer'>
		{if !empty($error)}<div class="ui-inline-help ui-state-highlight ui-corner-all" style="margin-bottom:10px">
							<span class="ui-icon ui-icon-alert"
								style="float: left; margin-right: .3em;"></span>{$error}
						</div>{/if}


		<form action="{$selfaction}upload" method="post" enctype="multipart/form-data">
			<input type="hidden" name="MAX_FILE_SIZE" value="300000" />
			<table cellspacing="2">
			{if empty($error)}
				<tr>
					<td>&nbsp;</td>
					<td><div class="ui-inline-help ui-state-highlight ui-corner-all"
							style="margin-bottom: 5px">
							<span class="ui-icon ui-icon-info"
								style="float: left; margin-right: .3em;"></span>{'APUA_Upload_tip'|translate}
						</div></td>
				</tr>
			{/if}	
			<tr>
					<td><label for="upl">{'APUA_Upload_InputLabel'|translate}:</label>
					</td>
					<td><input id="upl" name="userfile" type="file" /></td>
					</tr>
				<tr>
					<td><input type="submit" value="{'APUA_Btn_SendFile'|translate}" class="submit"/>
					</td>
				</tr>
			</table>

		</form>
	</div>
</div>
<br>
{include file="CoreAdminHome/templates/footer.tpl"}
