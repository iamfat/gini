<?php

namespace Model\Test {
	class Fail_Exception extends \Exception {}
	class Cancel_Exception extends \Exception {}	
}

namespace Model {

	class Test {
		
		const ANSI_RED = "\x1b[31m";
		const ANSI_GREEN = "\x1b[32m";
		const ANSI_YELLOW = "\x1b[33m";

		const ANSI_RESET = "\x1b[0m";
		const ANSI_HIGHLIGHT = "\x1b[1m\x1b[4m";

		var $_fails = array();
		var $_done = array();

		final function run() {

			TRACE_INDENT_BEGIN(5);

			$this->printf("\x1b[34m==>\x1b[0m start test %s\n", self::ANSI_HIGHLIGHT.get_class($this).self::ANSI_RESET);

			if (!is_callable(array($this, 'setup'))) {
				$this->_fails[] = array(
					'name' => 'setup() is not callable',
				);
				$this->printf('setup() is not callable');
				return;
			}

			if (!is_callable(array($this, 'teardown'))) {
				$this->_fails[] = array(
					'name' => 'teardown() is not callable',
				);
				$this->printf('teardown() is not callable');
				return;
			}

			$step = 1;

			$this->printf("%3d. setup environment\n", $step);
			$this->setup();

			$this->_done = array();

			$rc = new \ReflectionClass($this);
			foreach ($rc->getMethods() as $method) {
				if (!$method->isPublic()) continue;

				$test = $method->getName();
				list($flag, $name) = explode('_', $test, 2);
				if ($flag != 'test' || !$name) continue;
				if (isset($this->_done[$name])) continue;

				$this->_test($name, ++$step);
			}


			$this->printf("%3d. teardown environment\n", ++$step);
			$this->teardown();

			$this->printf("\n");

			TRACE_INDENT_END();
		}

		private function _test($name, $step) {

			$test = 'test_'.$name;

			try {
				$this->$test();
				$status = self::ANSI_GREEN . '√' . self::ANSI_RESET . ' done';
				$this->_done[$name] = TRUE;
			}
			catch (Test\Fail_Exception $e) {
				$status = self::ANSI_RED . '×'.self::ANSI_RESET."  \x1b[1;31m".$e->getMessage().self::ANSI_RESET;
				$this->_done[$name] = FALSE;
			}
			catch (Test\Cancel_Exception $e) {
				$status = self::ANSI_YELLOW . '-' . self::ANSI_RESET."  \x1b[1;33m".$e->getMessage().self::ANSI_RESET;
				$this->_fails[] = array(
					'name' => sprintf('test %s is canceled', $name),
				);
				$this->_done[$name] = FALSE;
			}

			$this->printf("%3d. %-50s %s\n", $step, 'run test '.self::ANSI_HIGHLIGHT.$name.self::ANSI_RESET, $status);
		}

		final function printf() {
			$args = func_get_args();
			call_user_func_array('printf', $args);
		}

		final function reset() {
			$this->fails = array();
		}

		final function assert($name, $expr, $desc=NULL) {
			if (!$expr) {
				$this->_fails[] = array(
					'name' => $name,
					'desc' => $desc,
				);
				throw new Test\Fail_Exception($name);
			}
		}

		final function depend() {
			$names = func_get_args();
			foreach($names as $name) {
				if (!isset($this->_done[$name])) {
					$this->_test($name);
				}

				if (!$this->_done[$name]) {
					throw new Test\Cancel_Exception(sprintf('test %s required', self::ANSI_HIGHLIGHT.$name.self::ANSI_RESET));
				}
			}		
		}

		// 获得某个对象的私有成员属性
		final function get_property($object, $name) {
			$ro = new \ReflectionObject($object);
			$rp = $ro->getProperty($name);
			$rp->setAccessible(TRUE);
			return $rp->getValue($object);
		}

		final function invoke($object, $method, $params=NULL) {
			$ro = new \ReflectionObject($object);
			$rm = $ro->getMethod($method);
			$rm->setAccessible(TRUE);
			return $rm->invokeArgs($object, (array) $params);
		}

		final function _diff_array($a, $b) {
			if (is_array($a)) {
				if (!is_array($b)) return 1;
				foreach ($a as $ak => $av) {
					if (!array_key_exists($ak, $b)) return 1;
					$bv = $b[$ak];
					if ($this->_diff_array($av, $bv) != 0) {
						return 1;
					}				
				}
			}
			elseif (is_array($b) || $a !== $b) {
				return 1;
			}

			return 0;
		}

		final function diff_array_deep(array $a, array $b) {
			return array_merge(
					array_udiff_assoc($a, $b, array($this, '_diff_array')),
					array_udiff_assoc($a, $b, array($this, '_diff_array'))
				);

		}

		final function passed() {
			return count($this->_fails) == 0;
		}

	}

}

