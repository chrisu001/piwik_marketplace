<h2>{'APUA_Advanced_Title'|translate}</h2>
<p>{'APUA_Advanced_Teaser'|translate}</p>

<!--  select release formular -->
<div class='entityContainer'>
	<form id="selectrelease" method="GET" action="index.php">
		<table cellspacing="2">
			<tr>
				<td><label for="release">{'APUA_Release_select_label'|translate}:</label>
				</td>
				<td><select name="release" id="release">
						<option value="all">{'APUA_Release_select_all'|translate}</option>
				        <option value="stable">{'APUA_Release_select_stable'|translate}</option>
						<option value="developer">{'APUA_Release_select_developer'|translate}</option>
						<option value="alpha">{'APUA_Release_select_alpha'|translate}</option>
						<option value="beta">{'APUA_Release_select_beta'|translate}</option>
						<!--  <option value="unittest">{'APUA_Release_select_unittest'|translate}</option> -->
				</select><img id="loaderrelease"
					src="plugins/PluginMarketplace/templates/img/ajax-loader.gif"
					style="display: none" /></td>
					<td><div class="ui-inline-help ui-state-highlight ui-corner-all" style="margin-top:5px"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span><div style="text-align:left">{'APUA_Release_select_tip'|translate}</div></div>
			</tr>

			<tr>
				<td><label for="expert">{'APUA_Expert_choose_label'|translate}:</label>
				</td>
				<td><input id="expert" type="checkbox" value="1"{if $expertmode} checked{/if}/><img id="loaderexpert"
					src="plugins/PluginMarketplace/templates/img/ajax-loader.gif"
					style="display: none" /></td>
			</tr>
		</table>
	</form>
	
	
</div>
<!--  /select release formular -->
{literal}
<script type="text/javascript">

$(function() {
	$('#release').val('{/literal}{$appstoreRelease}{literal}');
	$('#release').change(function(){
         var release = $("#release").val();
          $('#release').hide();
          $('#loaderrelease').show();
          // reloadAppFrame(release); 
          $.ajax({
              url: '?module=PluginMarketplace&action=switchrelease&release=' + release,
              async: true,
              success: function(data) {
            	  $('#pstabs').tabs('load', 1);
            	  reloadAppFrame(release);
            	  $('#loaderrelease').hide();       
            	  $('#release').show();
             	  $('#pstabs').tabs({ selected: 0 }); 
            	   // pm.unbind("intercomm");
                  }
          });
          
      });

    // expert mode
    $('#expert').click(function () {
        var expertmode = $('#expert').is(':checked')?1:0;
        $('#loaderexpert').show();
        $('#expert').hide();
        // reloadAppFrame(release); 
        $.ajax({
            url: '?module=PluginMarketplace&action=switchexpert&expert=' + expertmode,
            async: true,
            success: function(data) {
            	pm.unbind("intercomm");
            	var loc = window.location + '';
            	// reload page on tab 1 (manager)
            	window.location.href =  loc.replace('(#.+))','')  + '#pstabs-2';
            	window.location.replace(window.location.href); 
                window.location.reload();
            }
        });
    });
    
    
});
</script>
{/literal}



