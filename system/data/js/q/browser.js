(function($){

	var Q = window['Q'];

	//browser兼容的东西
	var userAgent = navigator.userAgent.toLowerCase();
	var version = (userAgent.match( /.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/ ) || [0,'0'])[1];
	var msie = /msie/.test( userAgent ) 
			&& !/opera/.test( userAgent )
			&& !/chromeframe/.test( userAgent );
	var chrome = /chrome/.test( userAgent ) || (/chromeframe/.test( userAgent ));
	var safari = !chrome && (/safari/.test( userAgent ) || (/opera/.test( userAgent )));

	Q['browser'] = {
		safari: safari,
		msie: msie,
		chrome: chrome,
		version: version
	};

	var supportTouch = null;
		
	Q['supportTouch'] = function() {
		var body = document.body || document.documentElement;
		if (null === supportTouch) {
			supportTouch = body.ontouchstart !== undefined && userAgent.match(/ipad|iphone|android/) !== null;
		}
		return supportTouch;
	};

	Q['event'] = function(e) {
		
		if (e.originalEvent.touches && e.originalEvent.touches.length) {
			e.pageX = e.originalEvent.touches[0].pageX;
			e.pageY = e.originalEvent.touches[0].pageY;
			e.touches = e.originalEvent.touches;
		}
		else if (e.originalEvent.changedTouches && e.originalEvent.changedTouches.length) {
			e.pageX = e.originalEvent.changedTouches[0].pageX;
			e.pageY = e.originalEvent.changedTouches[0].pageY;
			e.touches = e.originalEvent.changedTouches; 
		}
		else {
			e.touches = [];
		}
		
		e.isTouch = Q.supportTouch();

		/*
		if (e.originalEvent.targetTouches && e.originalEvent.targetTouches.length) {
			e.pageX = e.originalEvent.targetTouches[0].pageX;
			e.pageY = e.originalEvent.targetTouches[0].pageY;
		}
		*/
		return e;
	};
	
})(jQuery);
