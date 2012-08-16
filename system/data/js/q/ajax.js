(function($){

	var Q = window['Q'];

	var _triggerQueue = {};

	Q['trigger'] = function(opt) {
		//o, event, data, func, url
		opt = opt || {};
		opt.url = opt.url || window.location.href;
		if (opt.global !== false) {
			opt.global = true;
		}

		Q.triggered = opt;

		var e = opt.event;
		
		if (typeof(e)=='string') {
			e = $.Event(e);
		}

		var key = opt.url + ":" + (opt.widget || '*') + ':' +  opt.object + ":" + e.type;

		var req = _triggerQueue[key];
		if (!opt.parallel && req != undefined) {
			req.abort();
		}

		var post={
			_ajax: 1, 
			_object: opt.object,
			_event: e.type
		};

		if (opt.widget) {
			post._widget = opt.widget;
		}

		if (e.pageX) {
			post._mouse=$.toJSON({x:e.pageX, y:e.pageY});	
		}

		if (e.view) {
			post._view = $.toJSON({ 
				left:e.view.pageXOffset, 
				top:e.view.pageYOffset, 
				width:e.view.innerWidth, 
				height:e.view.innerHeight
			});
		}

		data = opt.data || {};
		var form;
		if(data._form){
			form = data._form;
			delete data._form;
		}

		var p = {};
		$.extend(p, Q.globals, data);

		//post._data=$.toJSON(p);
		$.extend(post, p);
		var url = opt.url;

		function onSuccess(data, status){

			setTimeout(function() {
				if (opt.success) {
					switch (typeof opt.success) {
						case 'function':
							opt.success.apply(this, [data, status, url]);
							break;
						case 'string':
							eval(opt.success).apply(this, [data, status, url]);
							break;
					}
				}

				for (var key in data) {
					if (data.hasOwnProperty(key)) {
						if(Q.ajaxProcess[key]) {

							Q.ajaxProcess[key].apply(this, [data[key], status, url]);
						} else {
							//其他
							Q.ajaxProcess.content.apply($(key), [data[key]]);
						}
					}
				}

				if(opt.postAJAX) { opt.postAJAX.apply(this, [data, status, url]); }
			}, 1);
		}

		function onComplete() {
			setTimeout(function() {
				if (opt.complete) {
					switch (typeof opt.complete) {
						case 'function':
							opt.complete.apply(this, [data, status, url]);
							break;
						case 'string':
							eval(opt.complete).apply(this, [data, status, url]);
							break;
					}
				}
			}, 2);

			delete _triggerQueue[key];
		}

		if (form) {
			var $form = $(form);

			$form.attr({
				action: url,
				enctype: 'multipart/form-data',
				method: 'post'
			});

			$form.find('input:not(:file)').addClass('temp_disabled').attr('disabled', 'true');	//temporarily disable them

			_triggerQueue[key] = {
				abort: function() {
					   }
			};

			$form.ajaxSubmit({
				dataType: 'json',
				success: onSuccess,
				data: post,
				global: opt.global,
				complete: function(){
					$form.find('input.temp_disabled').removeAttr('disabled').removeClass('temp_disabled');
					$form.removeAttr('action').removeAttr('enctype').removeAttr('method');
					onComplete.apply(this);
				}
			});

		}
		else {
			// 记录该AJAX请求, 再下次请求同样类型的时候abort
			_triggerQueue[key] = $.ajax({
				global: opt.global,
				url: url,
				data: post,
				type: "POST",
				dataType: "json",
				success: onSuccess,
				complete: onComplete,
				cache: false
			});

		}

	};

	Q['retrigger'] = function(data,remember){
		data = data||{};
		if (Q.triggered) {
			$.extend(Q.triggered.data, data);
			Q.trigger(Q.triggered);
			//移除相关数据
			for (var i in data) {
				if (data.hasOwnProperty(i)) {
					delete Q.triggered.data[i];
				}
			}
		}
	};

	Q['ajaxProcess'] = {
		script: function(data){ eval(data); },
		content: function(data){
			if(typeof(data) == "object"){
				if(Q.ajaxContent[data.mode]){
					Q.ajaxContent[data.mode].apply(this, [data.data]);
				} else {
					this.empty().append(data.data);
				}
			}else{
				this.empty().append(data);
			}
		}
	};

	Q['ajaxContent'] = {
		replace: function(data) { this.replaceWith(data); },
		append: function(data) { this.append(data); },
		prepend: function(data) { this.prepend(data); },
		after: function(data) { this.after(data); },
		before: function(data) { this.before(data); },
		textarea_insert: function(data) {
			this.filter('textarea').each(function(){
				// 将文本插入当前选择
				$(this).focus();
				if(document.selection){
					document.selection.createRange().text= data;
				}else{
					var start = this.selectionStart;
					var end = this.selectionEnd;
					this.value=[this.value.substr(0, start), data, this.value.substr(end)].join('');
					this.selectionStart = this.selectionEnd = start + data.length;
				}
			});
		}
	};

	$('.view, [q-object]').livequery(function(){

		var $el = $(this);

		var object = $el.classAttr('object') || this.id;
		var _events = Q.toQueryParams($el.classAttr('event')) || {};
		var _data = Q.toQueryParams($el.classAttr('static')) || {};
		var dynamics = Q.toQueryParams($el.classAttr('dynamic')) || {};
		var url = $el.classAttr('src') || $el.parents('div[src]:first').attr('src');
		var success_func = $el.classAttr('success') || null;
		var complete_func = $el.classAttr('complete') || null;

		var global = true;
		if ($el.classAttr('global') == false) global = false;

		var widget = $el.classAttr('widget');

		$el.data('_data', _data);

		if ($el.is('form')) {
			// store options in hash
			$(":submit, input:image", $el).bind('click', function() {
				var $submit = $(this);
				$el.data('view_form.submit', $submit.attr('name'));
				if (!$.support.submitBubbles) {
					$el.submit();
					return false;
				}
			});
			_events.submit = _events.submit || 0;
		}
		else {
			$el.set_unselectable();
		}

		$.each(_events, function(event, delay) {
			$el.bind(event, function(e) {
				var data = $el.data('_data') || {};
				$.extend(data, Q.dynamicData(dynamics));

				if ($el.is('form') && e.type=='submit') {
					//check if it's a form containing files				
					var $files = $('input:file', $el);
					var found = false;

					$files.each(function() {
						if (this.value) {
							found=true;
						}
					});

					if (found) {
						$.extend(data, {_form: $el[0]});
					}

					$(':input:not(:submit, :image, :disabled)', $el).each(function(){
						if(this.name) {
							var $this = $(this);
							if($this.is(':radio')) {
								if ($this.is(':checked')) {
									data[this.name]=$this.val();
								}
							}
							else if($this.is(':checkbox')) {
								data[this.name]=$this.is(':checked') ? 'on' : null ;
							}
							else if (!$this.hasClass('hint')) {
								data[this.name]=$this.val();
							}					
						}
					});

					data.submit = $el.data('view_form.submit') || $el.find(':submit:eq(0)').attr('name');

				} 
				else if ($el.is(':input')) {
					if($el.attr('name')) {
						data[$el.attr('name')]=$el.val();
					}
				}

				delay = parseInt(delay, 10); //确保delay是整数

				var opt = {
					object:object, 
					event:e, 
					data:data, 
					success: success_func,
					complete: complete_func,
					url: url,
					global: global
				};

				if (widget) {
					opt.widget = widget;
				}

				window.setTimeout(function(){
					Q.trigger(opt, delay);
				}, delay);

				e.preventDefault();
				return false;
			});
			
		});
		
	});
	
	/**
	 * @brief 对于具备src属性的div节点, 自动加载src指定的内容, 节点如包含noload, 则不进行加载
	 *
	 * @param empty
	 */
	$('div[src]:not(.noload)').livequery(function(){
		var $div = $(this);
		$div.load($div.attr('src'), function() {
			$(window).resize();
		});
	});

	$('div[src] a[href]:not(.view, [q-object], .prevent_default, .group_prevent_default a)').live('click', function(){
		var $div = $(this).parents('div[src]:first');
		if ($div.data('ajaxing')) return false;
		$div.data('ajaxing', true);
		$div.attr('src', this.href);
		$div.load(this.href, function() {
			$div.data('ajaxing', false);
			$(window).resize();
		});
		return false;
	});
	
	$('div[src] form:not(.view, [q-object], .prevent_default)').livequery(function(){
		var $form = $(this);
		var $div = $form.parents('div[src]:first');
		$form.ajaxForm({
			target: $div,
			url: $div.attr('src'),
			beforeSubmit: function() {
				if ($form.data('submitting')) return false;
				$form.data('submitting', true);
				//提交的时候阻止该表单的其他提交
			},
			complete: function() {
				$form.data('submitting', false);
			}
		});
	});
	
})(jQuery);
