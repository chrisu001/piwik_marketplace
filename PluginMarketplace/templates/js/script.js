/**
 * Class to handle Ajaxrequests
 */

UpdateManager = {

	isPolling : false,
	pollInterval : false,
	fallbackLayer : '#htmlerror',

	status : function() {

		if (this.isPolling) {
			return;
		}
		this.isPolling = true;
		response = this.action('status');
		this.isPolling = false;
		return response;
	},

	reset : function() {
		return this.action('reset');
	},

	run : function() {
		this.action('run', {}, true);
	},
	postinstall : function() {
		return this.action('postinstall');
	},

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
				UpdateManager.backendLog('ajaxerror:', data);
			},
			'success' : function(data) {
				UpdateManager.backendLog('ajaxsuccess:', data.success, data);
				if (typeof (data.success) == 'undefined') {
					UpdateManager.backendLog('fallbackerror:', data);
					$(UpdateManager.fallbackLayer).html(data);
					$(UpdateManager.fallbackLayer).show();
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
				// UpdateManager.backendLog('ajax action received:',
				// csresponse,data);
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
 * Display the actual status of the installprocess
 * 
 */
ProcessDisplay = {

	layername : '#progressupdateinfo',
	sequence : -1,

	iconList : {
		'0' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif">',
		'1' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif">',
		'2' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif">',
		'3' : '<img src="plugins/PluginMarketplace/templates/img/circle-green-24.png">',
		'4' : '<img src="plugins/PluginMarketplace/templates/img/circle-blue-24.png"><img src="plugins/PluginMarketplace/templates/img/ajax-loader.gif">',
		'-1' : '<img src="plugins/PluginMarketplace/templates/img/circle-red-24.png">',
	},
	isRunning : true,

	applyStatus : function(response) {

		this.isRunning = response.isRunning;
		if (this.sequence > response.sequence) {
			return;
		}

		this.sequence = response.sequence;
		var htmlsrc = this.toHtml(response);

		$(this.layername).html(''
		// + 'Sequence: ' + this.sequence +'<br/>'
		+ htmlsrc
		// + '<hr><pre>' + JSON.stringify(response, undefined, 2))+ '</pre>'
		+ '');
	},

	reset : function(response) {
		this.sequence = -1;
		this.applyStatus(response);
	},

	toHtml : function(response) {
		var html = ''; // 'Status: ' + response.statuscode + "<br/>" + 'is
		// Running: ' + response.isRunning + "<br/> ";
		for ( var i in response.items) {
			html = html + this.itemtohtml(response.items[i]);
		}
		return html;
	},

	itemtohtml : function(item) {
		var displayStatus = item['statuscode'];
		if (!this.isRunning && displayStatus != 3 && displayStatus != 4) {
			displayStatus = -1;
		}
		var html = '<div class="updateprogress">'
				+ this.iconList[displayStatus] + ' ' + item['name'] + ': '
				+ item['step']
				// + '<br/><pre>' + JSON.stringify(item) + '</pre><br/>'
				+ '';
		
		if(item['statuscode'] < 0 && item['reason'] == '' ){
			item['reason'] = 'general error occured, please try again later';
		}
		
		if (item['reason'] != '' && item['statuscode'] < 0) {
			html += '<div class="ui-inline-help ui-state-highlight ui-corner-all" style="margin-top:5px"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'
					+ item['reason'] + '</div>';
		}
		html += '</div>';
		return html;
	}
};

/**
 * Communication with Pluginstore iframe
 */
PlugstoreComm = {
	
		iframelayer : '#plugstoreframe'
};

