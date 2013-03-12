/**
 * Poll the current status of the installprocess and 
 * hand over the response to the Processdisplay
 */
PollStatus = {

		pollCounter :    180,  // max try to catch thestatus
		timerInterval:   null,
		failCounter:     5,     // max errors allowed    
		failsafeCounter: 3,     // check 3 times the status, before analyse errors
		lockPolling:     false,
		isRunable:       false,

		/**
		 * update the current status
		 */
		update : function () {
			if(this.lockPolling) {
				return;
			}
			
			this.lockPolling = true;
			this.pollCounter--;
			$('#ready').hide();
			var response = MArpc.status();

			if(typeof(response) !== 'undefined' && response == -1) {
				// htmlerror execption();
				this.finish();
				//FIXME: other "function"
			}
			// no valid ajax call, redo
			if(typeof(response) === 'undefined' || response === null || typeof(response.items) === 'undefined'){
				this.failCounter --;
				this.lockPolling = false;
				return;
			}


			var len = 0;
			for (var i in response.items) len++; // erm, length()?....
			if(len === 0) { 
				// console.log('update()','failed',response.items);
				this.lockPolling = false;
				this.failCounter--;
				return;
			}
			ProcessDisplay.applyStatus(response);

			
			// for the first 2 times calling, update the status, before asume the isRunnings status as valid 
			// cause of timing fallbacks
			if(this.failsafeCounter > 0 || response.isRunning) {
				this.failsafeCounter--;
				this.lockPolling = false;
				return;
			}
			// console.log('update()','normal Op');
			this.failsafeCounter = -1 ;
			if(!response.isRunning){
				this.finish();
				return;
			}
			this.lockPolling = false;
		},

		/**
		 * start the remote process
		 */
		run: function () {
			if(this.timerInterval == null) {
				// run the first time
				if(this.isRunable) {
					MArpc.run();
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

		/**
		 * mark the process as finished 
		 */
		finish: function () {
			if(this.timerInterval != null) {
				this.pollCounter = -1 ;
				clearInterval(this.timerInterval);
			}
			// run Postinstall
			$('#ready').show();
			if(this.isRunable) {
				var response = MArpc.postinstall();
				// console.log('postupdate::finish()',response);
				ProcessDisplay.applyStatus(response);
			}
			$('#readyloader').hide();
			$('#readybutton').show();
			// var url=$('#readybutton a').attr('href');
			// setTimeout("location.href = '" + url + "';", 1000 * 10);
		}
};



/**
 * Execute the RPCs to the server
 * and provide their status info 
 */
MArpc = {

	pollInterval : false,
	fallbackLayer : '#htmlerror',

	/**
	 * get Status
	 * @returns
	 */
	status : function() {
		return this.action('status');
	},

	/**
	 * reset the currently running process 
	 * @returns
	 */
	reset : function() {
		return this.action('reset');
	},

	/**
	 * start the processqueue
	 */
	run : function() {
		this.action('run', {}, true);
	},
	
	/**
	 * start the defered steps of the install process 
	 * @returns
	 */
	postinstall : function() {
		return this.action('postinstall');
	},

	/**
	 * execute an action on the server as ajaxrequest
	 * @param action  - action name
	 * @param params  - optional parameters to be submitted
	 * @param async   - as asynchronos call
	 * @returns
	 */
	action : function(action, params, async) {

		if (typeof (params) === 'undefined' || !params) {
			params = {};
		}
		if (typeof (async) === 'undefined' || !async) {
			async = false;
		}

		params['module'] = 'PluginMarketplace';
		params['action'] = action;
		params['token_auth'] = piwik.token_auth;

		// this.backendLog('action send:', params, 'async', async);
		if ($('#debug')) {
			$('#debug').append(
					"<br/><b>Send AjaxAction:" + action + "</b><br/>");
		}
		var csresponse = null;
		$.ajax({
			'url' : 'index.php',
			'async' : async,
			'data' : params,
			'error' : function(data) {
				MArpc.backendLog('ajaxerror:', data);
			},
			'success' : function(data) {
				MArpc.backendLog('ajaxsuccess:', data.success, data);
				if (typeof (data.success) == 'undefined') {
					MArpc.backendLog('fallbackerror:', data);
					$(MArpc.fallbackLayer).html(data);
					$(MArpc.fallbackLayer).show();
					csresponse = -1;
					return;
				}
				if (data.success == true) {
					csresponse = data.payload;
				}
				if ($('#debug')) {
					$('#debug').append(
							'<pre>' + JSON.stringify(data, undefined, 2)
									+ '</pre><br/><hr>');
				}
				// MArpc.backendLog('ajax action received:', csresponse,data);
			}
		});
		// this.backendLog('action received:', csresponse, async);
		return csresponse;
	},

	backendLog : function() {
		// console.log('backend NoDebug!!!', arguments);
	},

};

/**
 * Display the current status of the installprocess
 */
ProcessDisplay = {

	layername : '#progressupdateinfo',
	sequence : -1,

	iconList : {
		'0' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png">', // Step: init
		'1' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png">', // Step: start
		'2' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png">', // Step: transient 
		'3' : '<img src="plugins/PluginMarketplace/templates/img/circle-green-24.png">',// step: finished
		'4' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png">', // step: deffered
		'-1': '<img src="plugins/PluginMarketplace/templates/img/circle-red-24.png">', // step  error
	},
	pgbgList : {
		'0' : 'plugins/PluginMarketplace/templates/img/progressbg_green.gif',
		'1' : 'plugins/PluginMarketplace/templates/img/progressbg_orange.gif',
		'2' : 'plugins/PluginMarketplace/templates/img/progressbg_orange.gif',
		'3' : 'plugins/PluginMarketplace/templates/img/progressbg_green.gif',
		'4' : 'plugins/PluginMarketplace/templates/img/progressbg_green.gif',
		'-1': 'plugins/PluginMarketplace/templates/img/progressbg_red.gif',
		'bg': 'plugins/PluginMarketplace/templates/img/progressbar.gif',
	},
	isRunning : true,

	applyStatus : function(response) {

		this.isRunning = response.isRunning;
		if (this.sequence > response.sequence) {
			return;
		}
		
		if (this.sequence == -1) {
			// run the first time, so generate the HTML-source
			$(this.layername).html(this.toHtml(response.items));
		}
		this.sequence = response.sequence;
		// update progress
		for ( var i in response.items) {
			this.updateHtml(response.items[i]);
		}
	},

	reset : function(response) {
		this.sequence = -1;
		this.applyStatus(response);
		this.sequence ++;
	},

	/**
	 * Generate HTML-source for the progress update - ICON Progrssbar name
	 * current action/status (step)
	 * 
	 * @param item -
	 *            itemstatus of the axajrequest
	 * @returns {String}
	 */
	toHtml : function(items) {
		var html = '<table class="updateprogress">';
		for ( var i in items) {
			var item = items[i];
			var id = item['id'];
			html = html 
			    + '<tr>' 
			    + '<td><span id="sticon_'   + id + '">' + this.iconList[-1] + '</span></td>'
				+ '<td><span id="stname_'  + id	+ '" style="margin-left:10px;font-weight:bold;">' + item['name'] + '</span></td>' 
				+ '<td><span id="stbar_'   + id + '" style="margin-left:10px;">-</span></td>'
				+ '<td><span id="ststep_'  + id + '" style="margin-left:10px;">' + item['step'] + '</span></td>'
				+ '<td><span id="sterror_' + id + '"></span></td>'
				+ '</tr>';
			
		}
		return html + '</table>';
	},

	/**
	 * update the progress
	 * 
	 * @param item
	 *            itemstatus of the axajrequest
	 */
	updateHtml : function(item) {
		var displayStatus = item['statuscode'];
		var id = item['id'];
		if (!this.isRunning && displayStatus != 3 && displayStatus != 4) {
			displayStatus = -1;
		}

		$('#sticon_' + id).html(this.iconList[displayStatus]);
		$('#ststep_' + id).html(item['step']);
		$('#stbar_' + id).progressBar(item['progress'], {
			boxImage : this.pgbgList['bg'],
			barImage : this.pgbgList[displayStatus]
		});

		if (item['statuscode'] < 0 && item['reason'] == '') {
			item['reason'] = 'general error occured, please try again later';
		}

		if (item['reason'] != '' && item['statuscode'] < 0) {
			$('#sterror_' + id)
					.html(
							'<div class="ui-inline-help ui-state-highlight ui-corner-all" style="margin-top:5px"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'
									+ item['reason'] + '</div>');
		}
	}
};

/**
 * Communication with Pluginstore iframe
 */
PlugstoreComm = {

	iframelayer : '#plugstoreframe'
};
