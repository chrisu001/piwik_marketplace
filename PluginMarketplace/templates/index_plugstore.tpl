<h2>{'APUA_Plugstore_Title'|translate}</h2>
<p style="padding-bottom: 0px">{'APUA_Plugstore_Teaser'|translate}</p>

<iframe
	name="plugstoreframe" id="plugstoreframe" src="" width="970"
	height="900" scrolling="no" frameborder="0"></iframe>
{literal}
<script type="text/javascript">
   var reloadAppFrame = function(release){
    	var defaultUrl ='http://plugin.suenkel.org/ajax/plugin?uid={/literal}{$appstoreUID}{literal}&release=' + release;
	    $('#plugstoreframe').attr('src', defaultUrl);
    	selectedrelease = release;
    };
    $(function() {
       reloadAppFrame('{/literal}{$appstoreRelease}{literal}');
    });
</script>
{/literal}
