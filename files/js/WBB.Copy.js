/**
 * Provides extended actions for post clipboard actions.
 */
WBB.Post.CopyClipboard = Class.extend({
    
        _boardID: 0, 
        
        /**
	 * Initializes a new WBB.Post.CopyClipboard object.
	 * 
	 * @param	WBB.Post.Handler	postHandler
	 */
	init: function(boardID) {
                
                this._boardID = boardID; 
                
		// bind listener
		$('.jsClipboardEditor').each($.proxy(function(index, container) {
			var $container = $(container);
			var $types = eval($container.data('types'));
			if (WCF.inArray('com.woltlab.wbb.post', $types)) {
				$container.on('clipboardAction', $.proxy(this._execute, this));
				$container.on('clipboardActionResponse', $.proxy(this._evaluateResponse, this));
				return false;
			}
		}, this));
	},
	
	/**
	 * Handles clipboard actions.
	 * 
	 * @param	object		event
	 * @param	string		type
	 * @param	string		actionName
	 * @param	object		parameters
	 */
	_execute: function(event, type, actionName, parameters) {
		if (type !== 'com.woltlab.wbb.post') {
			return;
		}
		
		switch (actionName) {
			case 'com.woltlab.wbb.post.copyToNewThread':
				WBB.Post.CopyToNewThread.prepare(parameters, this._boardID);
			break;
		}
	},
	
	/**
	 * Evaluates AJAX responses.
	 * 
	 * @param	object		event
	 * @param	object		data
	 * @param	string		type
	 * @param	string		actionName
	 * @param	object		parameters
	 */
	_evaluateResponse: function(event, data, type, actionName, parameters) {
		// ignore unrelated events
		if (type !== 'com.woltlab.wbb.post') {
			return;
		}
		
		if (actionName === 'com.woltlab.wbb.post.copyToExistingThread') {
			var $notification = new WCF.System.Notification();
			$notification.show(function() {
				window.location = data.returnValues.redirectURL;
			});
			
			return;
		}
	}
});

/**
 * Moves selected posts into a new thread.
 */
WBB.Post.CopyToNewThread = {
	/**
	 * dialog overlay
	 * @var	jQuery
	 */
	_dialog: null,
	
	/**
	 * list of affected post ids
	 * @var	array<integer>
	 */
	_objectIDs: [ ],
	
	/**
	 * submit button
	 * @var	jQuery
	 */
	_submitButton: null,
	
	/**
	 * topic input element
	 * @var	jQuery
	 */
	_topic: null,
	
        /**
         * the boardid
         * @type Number
         */
        _boardID: 0,
        
	/**
	 * Prepares moving of posts.
	 * 
	 * @param	object		parameters
	 */
	prepare: function(parameters, boardID) {
                
                this._boardID = boardID; 
            
		if (this._dialog === null) {
			this._dialog = $('<div>' + parameters.template + '</div>').hide().appendTo(document.body);
			this._dialog.wcfDialog({
				title: WCF.Language.get('wbb.post.copyToNewThread')
			});
		}
		else {
			this._dialog.html($.parseHTML(parameters.template)).wcfDialog('open');
		}
		this._objectIDs = parameters.objectIDs;
		this._submitButton = this._dialog.find('.formSubmit > button[data-type=submit]').disable().click($.proxy(this._submit, this));
		this._topic = $('#postCopyNewThreadTopic').keyup($.proxy(this._keyUp, this)).focus();
	},
	
	/**
	 * Handles the 'keyup' event to enable/disable the submit button.
	 */
	_keyUp: function() {
		if ($.trim(this._topic.val()).length > 0) {
			this._submitButton.enable();
		}
		else {
			this._submitButton.disable();
		}
	},
	
	/**
	 * Submits the form input elements.
	 */
	_submit: function() {
		this._submitButton.disable();
		
		new WCF.Action.Proxy({
			autoSend: true,
			data: {
				actionName: 'copyToNewThread',
				className: 'wbb\\data\\post\\PostCopyAction',
				objectIDs: this._objectIDs,
				parameters: {
					subject: $.trim(this._topic.val()), 
                                        boardID: this._boardID
				}
			},
			success: $.proxy(this._success, this)
		});
	},
	
	/**
	 * Handles successful AJAX requests.
	 * 
	 * @param	object		data
	 * @param	string		textStatus
	 * @param	jQuery		jqXHR
	 */
	_success: function(data, textStatus, jqXHR) {
		var $notification = new WCF.System.Notification();
		$notification.show(function() {
			window.location = data.returnValues.redirectURL;
		});
	}
};

/**
 * Provides extended actions for thread clipboard actions.
 */
WBB.Thread.CopyClipboard = Class.extend({
        /**
	 * Initializes a new WBB.Thread.CopyClipboard object.
	 * 
	 * @param	WBB.Thread.Handler	postHandler
	 */
	init: function() { 
		// bind listener
		$('.jsClipboardEditor').each($.proxy(function(index, container) {
			var $container = $(container);
			var $types = eval($container.data('types'));
			if (WCF.inArray('com.woltlab.wbb.thread', $types)) {
				$container.on('clipboardActionResponse', $.proxy(this._evaluateResponse, this));
				return false;
			}
		}, this));
	},
	
	/**
	 * Evaluates AJAX responses.
	 * 
	 * @param	object		event
	 * @param	object		data
	 * @param	string		type
	 * @param	string		actionName
	 * @param	object		parameters
	 */
	_evaluateResponse: function(event, data, type, actionName, parameters) {
		// ignore unrelated events
		if (type !== 'com.woltlab.wbb.thread') {
			return;
		}
		
		if (actionName === 'com.woltlab.wbb.thread.copy') {
			var $notification = new WCF.System.Notification();
			$notification.show(function() {
				window.location = data.returnValues.redirectURL;
			});
			
			return;
		}
	}
});