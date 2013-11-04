<h2>{'APUA_Upload_Title'|translate}</h2>
<p>{'APUA_Upload_HelpText'|translate}</p>
<div class='entityContainer'>

	<form
		action="{$selfaction}upload"
		method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="300000" />
		<table cellspacing="2">
			<tr>
				<td><label for="upl">{'APUA_Upload_InputLabel'|translate}:</label>
				</td>
				<td><input id="upl" name="userfile" type="file" /></td>
			</tr>
			<tr>
				<td><input type="submit" value="{'APUA_Btn_SendFile'|translate}"
					class="submit" /></td>
			</tr>
		</table>
	</form>
</div>
