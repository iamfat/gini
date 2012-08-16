window['Q'] = {};

(function($){

	var Q = window['Q'];
	
	Q['globals']={};

	$.fn['set_unselectable'] = function() {
		return $(this).each(function() {
			if (typeof this.onselectstart!="undefined") {
				//IE route 
				this.onselectstart = function(){return false;};
			}
			else if (typeof this.style.MozUserSelect!="undefined") {
				//Firefox route 
				this.style.MozUserSelect = "none";
			}
			else {
				//All other route (ie: Opera) 
				this.onmousedown = function(){return false;}; 
			}
			//$el[0].style.cursor = "default";
		});
	};

	// 获取类属性 get value from class="name:value" by name
	$.fn['classAttr'] = function(name) {
		var $el = $(this);
		var value = $el.attr('q-'+name);
		if (value) return decodeURIComponent(value)||value;
		var cls = $el.attr('class') || ""; //fix for jQuery 1.6
		var parts = cls.match(new RegExp("\\b" + Q.escape(name) + ":(\\S+)"));
		if(parts) {
			return decodeURIComponent(parts[1]) || parts[1];
		}
		return null;
	};
	
	//设置类属性 class="name:value"
	$.fn['setClassAttr'] = function(name, value) {
		var $el = $(this);
		var cls = $el.attr('class');
		cls = cls.replace(new RegExp("\\b" + Q.escape(name) + ":(\\S+)"), '');
		if (value !== undefined && value !== null) {
			cls = [cls, ' ', name, ':', encodeURIComponent(value)].join('');
		}
		$el.attr('class', cls);
	};

	$('form.autosubmit :input.autosubmittable, .autosubmit:input').livequery('change', function(){
		var $submit = $(':submit', this.form);
		if ($submit.length > 0) {
			$submit.click();
		}
		else {
			$(this.form).submit();
		}
	});

	// 页面全局唯一ID
	var _uniqid_count=0;
	
	Q['uniqid'] = function() {
		return 'uniq' + (_uniqid_count++);
	};
	
	Q['toQueryParams'] = function(str, separator) {
		var hash={};
		if (typeof(str) == 'string') $.each(str.split(separator || '&'), function(i, pair_str) {
			if ((pair = pair_str.split('='))[0]) {
				var key = decodeURIComponent(pair.shift());
				var value = pair.length > 1 ? pair.join('=') : pair[0];
				if (value !== undefined) {
					value = decodeURIComponent(value.replace('+', ' '));
				}
				else {
					value = null;
				}
				
				if (key in hash) {
					if (!Object.isArray(hash[key])) {
						hash[key] = [hash[key]];
					}
					hash[key].push(value);
				}
				else { 
					hash[key] = value;
				}
			}
		});

		return hash;
	};
	
	Q['escape'] = function(str) {
		return (str || "").replace(/([!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~])/g, "\\$1");
	};
	
	Q['dynamicData'] = function(dynamics) {
		var data = {};
		for (var k in dynamics) {
			if (dynamics.hasOwnProperty(k)) {
				data[k] = $(dynamics[k]).val();
			}
		}
		return data;
	};
	
	Q['clone'] = function(html, suffix, conversions) {
		var $dummy = $('<div/>');
		$dummy.html(html);
	
		$('[id]', $dummy).each(function() {
			var $el = $(this);
			var currentId = $el.attr('id');
			var newId = currentId + '_' + suffix;
			
			var pattern = new RegExp('(["])(.*?)'+ Q.escape(currentId) + '(.*?)\\1', 'g');
			
			html = html.replace(pattern, ['$1$2', newId, '$3$1'].join(''));
		});
		
		if (conversions && conversions.length > 0) {
			for (var i=0; i<conversions.length; i++) {
				html = html.replace(conversions[i].pattern, conversions[i].value);
			}
		}
		
		return $(html);
	};
	
	Q['refresh'] = function(selector) {
		var $el = selector ? $(selector) : null;
		if ($el && $el.length > 0) {
			$el.load($el.attr('src'));
		}
		else {
			window.location.href = window.location.href;
		}
	};

})(jQuery);


