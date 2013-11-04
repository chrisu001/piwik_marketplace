/**
 * Communication with Pluginstore iframe
 */
var PlugstoreComm = {

		// Layer to be used for the marketplace content
	iframelayer : 'plugstoreframe',
	// selected release to be displayed by marketplace
	selectedRelease: 'all',
	// Appstore UID
	appstoreUID: null, 
	
	
	/**
	 * Inittialize Intercommunication betweeen marketplace frame and piwik
	 * @param string selfurl url to the piwik module
	 * @param string appstoreUID
	 * @param string selected release
	 */
	init: function (selfurl, appstoreUID, selectedRelease ) {
		
		var self = this;
		self.selectedRelease = selectedRelease;
		self.appstoreUID = appstoreUID;
		
		$.pm.bind("intercomm", function(data) {
  		  // $("#debug").append('received' + JSON.stringify(data) + '<br>');
  		  // console.log('parent received', data);
  		
			// Height has changed, update the iframe.
			// ACTION: setHeight
			// adjust the hight of the frame if necessary
  		  if(data.action == 'setHeight') { 
  			  var h = Number( data.height );
  	    	  if ( !isNaN( h ) && h > 500 ) {
  	    	      $('#' + self.iframelayer).height( h );
  	    	      // console.log('setHeight', h);
  	    	  }
  		  }
  		  // ACTION: install
  		  // install a plugin by relocating to the install page
  		  if(data.action == 'install' ){
      		  var url = selfurl + "addprocess&pluginstall%5B%5D=" + data.pluginname;
      		  document.location.href = url;
  		  }

  		  // ACTION: update
  		  // update config , TODO
  		  if(data.action == 'update' ){
      		  var url = selfurl + "update";
      		  // TODO: implement the config update via browser
      		  $.ajax({ url: url, async: true }); 
  		  }

  		  // ACTION: ping
  		  // send a pong
  		  if(data.action == 'ping' ){
  		         pm({
  		    	    target: window.frames[self.iframelayer],
  		      	    url : $('#' + self.iframelayer).attr('src'),
  		            type: 'intercomm',
  		            origin: '*', 
    		        data: {action: "pong", client:  "piwik"}, 
  		          });
  		  }
  		  //ACTTION: pong
  		  // received a pong, do nothing
  		  if(data.action == 'pong' ){
      		  return null;
  		  }
      		  
  		  // RESPONSE: always PONG
  		  return {action : 'pong' , client:"piwik"};
  		});

	},

	/**
	 * reload the appstoreframe with a selected release
	 * @param release
	 */
      reloadAppFrame : function(release) {
    	  if (release != '' ){
    		  this.selectedRelease = release;
    	  }
    	var defaultUrl ='http://plugin.suenkel.org/ajax/plugin?uid=' + this.appstoreUID + '&release=' + this.selectedRelease;
	    $('#' + this.iframelayer ).attr('src', defaultUrl);
    },

};


/**
 * Control the tabs
 */
tabController = {
	
		/**
		 * Init the main jquierui Tab 
		 */
		init: function() {
			$( "#pstabs" ).tabs({
     		   cache:true,
     		   load: function (e, ui) {
     		     $(ui.panel).find(".tab-loading").remove();
     		   },
     		   select: function (e, ui) {
     		     var $panel = $(ui.panel);
     		     if ($panel.is(":empty")) {
     		         $panel.append("<img src='plugins/PluginMarketplace/images/ajax-loader.gif'/><div class='tab-loading'>Loading...</div>");
     		     }
     		    },
      		   beforeActivate: function (e, ui) { // jqueryUI>1.8
       		     var $panel = $(ui.panel);
       		     if ($panel.is(":empty")) {
       		         $panel.append("<img src='plugins/PluginMarketplace/images/img/ajax-loader.gif'/><div class='tab-loading'>Loading...</div>");
       		     }
       		    }
               });
			
			
			var url = document.URL;
	        // grab the value of the hash
	        var hashValue = parseInt(url.substring(url.indexOf('#')).replace('#', ''));
	        // check to make sure it is a number
	        if (hashValue != 0 ) {
	            // set the active tab
	        	preUIVersion() ? $('#pstabs').tabs({ selected: hashValue }) : $('#pstabs').tabs( "option", "active" , hashValue ) ;
	        }   
			
		},
		
		/**
		 * reload the Marketplace-Frame
		 * and swith to thetab
		 * @param release
		 */
		reloadMarketplace : function(release) {
			PlugstoreComm.reloadAppFrame(release);
			
		},
		
};

/**
 * check jquery UI version for tab compatibility
 */
//Return 1 if a > b
//Return -1 if a < b
//Return 0 if a == b
var preUIVersion = function  () {
 var a = $.ui ? $.ui.version || "1.5.2" : '0.0'; 	
 var b = '1.9';
 if (a === b) { return false; }

 var a_components = a.split(".");
 var b_components = b.split(".");

 var len = Math.min(a_components.length, b_components.length);

 // loop while the components are equal
 for (var i = 0; i < len; i++)
 {
     // A bigger than B
     if (parseInt(a_components[i]) > parseInt(b_components[i]))
     {
         return false;
     }

     // B bigger than A
     if (parseInt(a_components[i]) < parseInt(b_components[i]))
     {
         return true;
     }
 }

 // If one's a prefix of the other, the longer one is greater.
 if (a_components.length > b_components.length)
 {
     return false;
 }

 if (a_components.length < b_components.length)
 {
     return true;
 }
 // Otherwise they are the same.
 return false;
};
