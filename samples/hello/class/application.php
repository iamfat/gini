<?php

namespace GR\Hello {

	TRY_DECLARE('\Application', __FILE__);
	
	class Application extends \GR\System\Application {

	}

}

namespace {

	if (DECLARED('\Application', __FILE__)) {
		class Application extends \GR\Hello\Application {}
	}

}
