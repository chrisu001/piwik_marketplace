{assign var=showSitesSelection value=false}
{assign var=showPeriodSelection value=false}
{include file="CoreAdminHome/templates/header.tpl"}
{assign var="enabledebug" value=false}
{if 'live' == 'jenkins'}{assign var="enabledebug" value=true}{/if}
<script
	type="text/javascript"
	src="plugins/PluginMarketplace/templates/js/postmessage.js"></script>


    {literal}
	<script type="text/javascript">
	var selectedrelease = null;
	
    $(function() {
        $( "#pstabs" ).tabs({
        		   cache:true,
        		   load: function (e, ui) {
        		     $(ui.panel).find(".tab-loading").remove();
        		   },
        		   select: function (e, ui) {
        		     var $panel = $(ui.panel);

        		     if ($panel.is(":empty")) {
        		         $panel.append("<img src='plugins/PluginMarketplace/templates/img/ajax-loader.gif'/><div class='tab-loading'>Loading...</div>")
        		     }
        		    }
                  });
        $('#tlnkstore').click(function() {
        	reloadAppFrame(selectedrelease) ;
        });
    });

/*
 * Intercommunication with the Plugstore frame
 */
    $(function() {
    		pm.bind("intercomm", function(data) {
    		  // $("#debug").append('received' + JSON.stringify(data) + '<br>');
    		  // console.log('parent received', data);
    		  // Height has changed, update the iframe.
    		  if(data.action == 'setHeight') { 
    			  var h = Number( data.height );
    	    	  if ( !isNaN( h ) && h > 200 ) {
    	    	      $("#plugstoreframe").height( h );
    	    	      // console.log('setHeight', h);
    	    	      //$("#debug").append('update height to ' + h + 'px<br>');
    	    	  }
    		  }
    		  // install a Plugin
    		  if(data.action == 'install' ){
        		  var url="?module=PluginMarketplace&action=index&{/literal}{$commonquery.query}{literal}&pluginstall%5B%5D=" + data.pluginname;
        		  document.location.href = url;
    		  }

    		  // update config
    		  if(data.action == 'update' ){
        		  var url="?module=PluginMarketplace&action=update";
        		  $.ajax({ url: url,async: true }); 
    		  }
    		  
    		  // ping
    		  if(data.action == 'ping' ){
        		  // send Pong
        		  // console.log('parent sent pong');
    		         pm({
    		    	    target: window.frames['plugstoreframe'],
    		      	    url : $('#plugstoreframe').attr('src'),
    		            type: 'intercomm',
    		            origin: '*', 
      		            data: {action: "pong", client:  "piwik"}, 
    		          });
    		  }
    		  // pong
    		  if(data.action == 'pong' ){
        		  return null;
    		  }
        		  
    		  return {action : 'pong' , client:"piwik"};
    		});
    });
    </script>
{/literal}
<div style="max-width: 980px;">
	<div id="pstabs">
		<ul>
			<li><a id="tlnkstore"    href="#pstabs-1">{'APUA_Index_Tab_Store'|translate}</a></li>
			{if $expertmode }<li><a title="#pstabs-2" id="tlnkinstall" href="?module=PluginMarketplace&action=indexexpert">{'APUA_Index_Tab_Expert'|translate}</a></li>
			{else}			<li><a  title="#pstabs-2" id="tlnkinstall"  href="?module=PluginMarketplace&action=indexlist" id="tabinstallnk">{'APUA_Index_Tab_Install'|translate}</a></li>
			{/if}
			<li><a id="tlnkadvanced" href="#pstabs-3" >{'APUA_Index_Tab_Advanced'|translate}</a></li>
			<li><a id="tlnkfeedback" title="#pstabs-4" href="?module=PluginMarketplace&action=indexfeedback">{'APUA_Index_Tab_Feedback'|translate}</a></li>
			{if $enabledebug }<li><a href="#debug">Debug</a></li>{/if}
		</ul>
		<div id="pstabs-1">{include
			file="PluginMarketplace/templates/index_plugstore.tpl"}</div>
		<div id="pstabs-2"></div>
		<div id="pstabs-3">{include
			file="PluginMarketplace/templates/index_upload.tpl"}
			<div class="clearfix"  style="margin: 30px"></div>
			{include
			file="PluginMarketplace/templates/index_advanced.tpl"}</div>
			<div id="pstabs-4"></div>
		<div id="pstabs-6"></div>
			{if $enabledebug }<!--  Debug --><div id="debug">Debuglog<hr></div><!--  /Debug -->{/if}
	</div>
</div>

{include file="CoreAdminHome/templates/footer.tpl"}


