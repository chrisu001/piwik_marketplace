/**
 * Poll the current status of the installprocess and hand over the response to
 * the Processdisplay
 */
PollStatus = {
	/**
	 * start the remote process
	 */
	run : function() {
		var self = this;
		$('#ready').hide();
		MArpc.run(function() {
			self.success();
		}, function() {
			self.error();
		});
	},
	success : function() {
		$('#ready').show();
	},
	error : function() {
		$('#ready').show();
	},
};

/**
 * Execute the RPCs to the server and provide their status info
 */
MArpc = {
		
	fallbackLayer : '#htmlerror',
	lastResponse : null,
	successCallback : null,
	errorCallback : null,
	hasError : false,
	countdown: 50, // max 50 Steps

	run : function(successCb, errorCb) {
		this.successCallback = successCb;
		this.errorCallback = errorCb;
		this.step();
	},

	
	/**
	 * do one step of the TaskSet and apply the current status to the ProcessDisplay 
	 */
	step : function() {
		var self = this;
		
		this.countdown--;
		
		if (this.hasError) {
			this.errorCallback();
			return;
		}

		if (this.lastResponse) {
			ProcessDisplay.applyStatus(this.lastResponse);
			if(this.lastResponse.finished == true) {
				this.successCallback();
				return;
			}
		}

		if(this.countdown < 0 ){
			this.displayError('maximum ajaxcalls reached');
			return;
		}
		// run next step
		this.action('step', {}, true, function() {
			self.step();
		});
	},

	displayError : function(htmltxt) {
		this.backendLog('ajax failed: received:', htmltxt);
		$(this.fallbackLayer).html(htmltxt);
		$(this.fallbackLayer).show();
		this.lastResponse = false;
		this.hasError = true;
		this.errorCallback();
	},

	/**
	 * execute an action on the server as ajaxrequest
	 * 
	 * @param action -
	 *            action name
	 * @param params -
	 *            optional parameters to be submitted
	 * @param async -
	 *            as asynchronos call
	 * @returns
	 */
	action : function(action, params, async, cb) {

		var self = this;
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
		$.ajax({
			'url' : 'index.php',
			'async' : async,
			'data' : params,
			'error' : function(data) {
				self.displayError('an unknonw error occured, pleas try again later');
			},
			'success' : function(data) {
				self.backendLog('ajax received:', data);
				if (typeof (data.success) != 'undefined' && data.success == true) {
					self.lastResponse = data.payload;
					cb();
					return;
				}
				self.displayError(data);
			},
		});
	},

	backendLog : function() {
		console.log('backend', arguments);
	},

};

/**
 * Display the current status of the installprocess
 */
ProcessDisplay = {

	layername : '#progressupdateinfo',
	firstrun : true,
	iconList : {
		'0'  : '<img src="plugins/PluginMarketplace/images/circle-blue-24.png">', // setup
		'1'  : '<img src="plugins/PluginMarketplace/images/circle-green-24.png">', // done:
		'-1' : '<img src="plugins/PluginMarketplace/images/circle-red-24.png">', // step
	},
	pgbgList : {
		'0'  : 'plugins/PluginMarketplace/images/progressbg_orange.gif', // setup
		'1'  : 'plugins/PluginMarketplace/images/progressbg_green.gif', // finished
		'-1' : 'plugins/PluginMarketplace/images/progressbg_red.gif', // error
		'bg' : 'plugins/PluginMarketplace/images/progressbar.gif',
	},

	applyStatus : function(response) {

		if (!response) {
			return;
		}
		if (this.firstrun ||  response.refresh == true) {
			$(this.layername).html(this.toHtml(response.items));
			this.firstrun = false;
		}
		// update progress
		for ( var i in response.items) {
			this.updateHtml(response.items[i]);
		}
	},

	/**
	 * Generate HTML-source for the progress update - ICON Progrssbar name
	 * current action/status (step)
	 * 
	 * @param item -
	 *            itemstatus of a TaskSets (ajaxresponse)
	 * @returns {String}
	 */
	toHtml : function(items) {
		var html = '<table class="updateprogress">';
		for ( var i in items) {
			var item = items[i];
			var id = item['id'];
			html = html 
			+ '<tr>' 
			        + '<td><span id="sticon_'  + id + '">' + this.iconList[-1] + '</span></td>'
					+ '<td><span id="stname_'  + id + '" style="margin-left:10px;font-weight:bold;">' + item['name'] + '</span></td>' 
					+ '<td><span id="stbar_'   + id + '" style="margin-left:10px;">-</span></td>'
					+ '<td><span id="ststep_'  + id + '" style="margin-left:10px;">' + item['task'] + '</span></td>'
					+ '<td><span id="sterror_' + id	+ '"></span></td>' 
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
		var id = item['id'];
		var progress = item['step'] * 100 / item['steps'];

		$('#sticon_' + id).html(this.iconList[item['status']]);
		$('#ststep_' + id).html(item['task']);
		$('#stname_' + id).html(item['name']);
		$('#stbar_' + id).progressBar(progress, {
			boxImage : this.pgbgList['bg'],
			barImage : this.pgbgList[item['status']]
		});

		if (item['status'] >= 0 ) {
			return;
		}
		var errortext = (item['error'] == '') ? 'general error occured, please try again later' : item['error']; 
		$('#sterror_' + id).html(
		  '<div class="ui-inline-help ui-state-highlight ui-corner-all" style="margin-top:5px"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'
		  + errortext + '</div>');
	}
};
