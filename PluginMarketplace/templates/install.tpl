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

div.updateprogress {
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
<script src="plugins/PluginMarketplace/templates/js/script.js"></script>

<div style="max-width: 980px;">
<h2>{'APUA_Install_Title'|translate}</h2>
<div class='entityContainer'>
<div id="progressupdateinfo"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif"></div>
<div id="ready">
<div id="readyloader" style="display: none;"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif"></div>
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
  
 PollStatus = {

		  pollCounter : 180,
		  timerInterval: null,
		  failCounter: 5,
		  failsafeCounter: 3,
		  isPolling: false,
		  isRunable: false,


		  update : function () {
			    if(this.isPolling) {
			    	return;
			    }
			    this.isPolling = true;
			    this.pollCounter--;
			    $('#ready').hide();
			    var response = UpdateManager.status();

			    if(typeof(response) !== 'undefined' && response == -1) {
				    // htmlerror execption();
				    this.finish();
				    //FIXME: other "function"
			    }
			    // no valid ajax call, redo
			    if(typeof(response) === 'undefined' || response === null || typeof(response.items) === 'undefined'){
				      this.failCounter --;
				      this.isPolling = false;
			    	  return;
			      }

			      
			        var len = 0;
				    for (var i in response.items) len++;
				    if(len === 0) { 
			    	  console.log('update()','failed',response.items);
		    	      this.isPolling = false;
					  this.failCounter--;
					  return;
				    }
			    // securely 2 times update status, before acapting the isRunnings status as valid 
			    // cause of timing fallbacks 
			    if(this.failsafeCounter > 0 || response.isRunning) {
			    	this.failsafeCounter--;
			    	ProcessDisplay.applyStatus(response);
		    	    this.isPolling = false;
		    	    return;
			    }
			    console.log('update()','normal Op');
			    this.failsafeCounter = -1 ;
			    if(!response.isRunning){
			    	  ProcessDisplay.applyStatus(response);
				      this.finish();
				      return;
			    }
		    	ProcessDisplay.applyStatus(response);
	    	    this.isPolling = false;
		  },

		  run: function () {
			  if(this.timerInterval == null) {
				  if(this.isRunable) {
					  UpdateManager.run();
				  }
              	  $('#ready').hide();
				  this.timerInterval=setInterval(function() { PollStatus.run(); } , 1000 * 1);
				  return; 
			  }

			  if(this.failCounter < 0 || this.pollCounter < 0) {
				  this.finish();
			  }
			  this.update();
		  },

		  finish: function () {
			  if(this.timerInterval != null) {
				  this.pollCounter = -1 ;
				  clearInterval(this.timerInterval);
			  }
			  // run Postinstall
			  // TODO: show balken
			  $('#ready').show();
			  // $('#readyloader').show();
			  if(this.isRunable) {
			    var response = UpdateManager.postinstall();
			    // console.log('postupdate::finish()',response);
			    ProcessDisplay.reset(response);
			  }
			  $('#readyloader').hide();
			  $('#readybutton').show();
          	  var url=$('#readybutton a').attr('href');
          	  // setTimeout("location.href = '" + url + "';", 1000 * 10);
		  }
  };


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
