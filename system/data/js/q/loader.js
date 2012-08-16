(function($){

	var Q = window['Q'];

	var _loaded_css = {};
	Q['require_css'] = function(css) {
		var i;
		if (typeof(css) !== 'string') {
			//数字
			for (i in css) {
				if (css.hasOwnProperty(i)) {
					Q.require_css(css[i]);
				}
			}
		}
		else {
			//单个文件
			if (_loaded_css[css]) { return true; }
			
			if (document.createStyleSheet) {
				document.createStyleSheet(css);
			}
			else {
				$('<link rel="stylesheet" type="text/css" media="screen" />').attr('href', css).appendTo('head');
			}
			
			_loaded_css[css] = true;
		}
	};

	var _loaded_js = {};
	var _js_ready = {};
	
	Q['require_js'] = function(js, key) {
		var i;
		if (typeof(js) !== 'string') {
			//数字
			for (i in js) {
				if (js.hasOwnProperty(i)) {
					Q.require_js(js[i], i);
				}
			}
		}
		else {
			//单个文件
			if (key === undefined) { key = js; }
			
			if (_loaded_js.hasOwnProperty(key)) { return true; }
			_loaded_js[key] = true;

			$.getScript(js, function(){
				var js_cb = _js_ready[key];
				if (js_cb && js_cb.handlers.length > 0) {
					for (var i in js_cb.handlers) {
						js_cb.handlers[i].callback.call(Q, js_cb.handlers[i].data);
					}
					js_cb.loaded = true;
				}
				_js_ready[key] = _js_ready[key] || {handlers:[], loaded:true};
			});
			
		}
	};
		
	Q['js_ready'] = function (key, data, func) {
		if (arguments.length == 2) {
			func = data;
			data = {};
		}
		
		_js_ready[key] = _js_ready[key] || {handlers:[], loaded:false};
		
		if (_js_ready[key].loaded) {
				func.call(Q, data);
		}
		else {
			_js_ready[key].handlers.push({
				callback: func,
				data: data
			});
		}
		
	};
	
	
	//用于框架下个模块之间的通信
	Q['broadcast'] = function(el, message, params) {
		if (!_broadcast_handlers[message]) return true;
		var handlers = _broadcast_handlers[message] || [];
		for (var i=0; i < handlers.length; i++) {
			handlers[i].apply(el, [message, params]);
		}
	};
	
	//设置消息听众函数
	var _broadcast_handlers = {};
	Q['on_broadcasting'] = function(message, func) {
		_broadcast_handlers[message] = _broadcast_handlers[message] || [];
		_broadcast_handlers[message].push(func);
	};
	
	Q['leave_broadcast'] = function(message, func) {
		var handlers = _broadcast_handlers[message] || [];
		for (var i=0; i < handlers.length; i++) {
			if (handlers[i] === func) {
				handlers.splice(i, 1);
				break;
			}
		}
	};
	
})(jQuery);
