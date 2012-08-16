<?php

namespace Model;

class Fail_Exception extends \Exception {}
class Cancel_Exception extends \Exception {}

class Unit {
	
	const ANSI_RED = "\033[31m";
	const ANSI_GREEN = "\033[32m";
	const ANSI_YELLOW = "\033[33m";

	const ANSI_RESET = "\033[0m";
	const ANSI_HIGHLIGHT = "\033[1m\033[4m";

	var $_fails = array();
	var $_done = array();

	final function test() {

		$this->printf("\033[34m==>\033[0m start test %s\n", self::ANSI_HIGHLIGHT.get_class($this).self::ANSI_RESET);

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

		$this->printf("%3d. setup environment...\n", $step);
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


		$this->printf("%3d. teardown environment...\n", ++$step);
		$this->teardown();

		$this->printf("\n");
	}

	private function _test($name, $step) {

		$test = 'test_'.$name;

		try {
			$this->$test();
			$status = self::ANSI_GREEN . '√' . self::ANSI_RESET;
			$this->_done[$name] = TRUE;
		}
		catch (Fail_Exception $e) {
			$status = self::ANSI_RED . '×'.self::ANSI_RESET.' ('.$e->getMessage().')';
			$this->_done[$name] = FALSE;
		}
		catch (Cancel_Exception $e) {
			$status = self::ANSI_YELLOW . '-' . self::ANSI_RESET.' ('.$e->getMessage().')';
			$this->_fails[] = array(
				'name' => sprintf('test %s is canceled', $name),
			);
			$this->_done[$name] = FALSE;
		}

		$this->printf("%3d. run test %-25s %s\n", $step, self::ANSI_HIGHLIGHT.$name.self::ANSI_RESET, $status);
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
			throw new Fail_Exception($name);
		}
	}

	final function depend() {
		$names = func_get_args();
		foreach($names as $name) {
			if (!isset($this->_done[$name])) {
				$this->_test($name);
			}

			if (!$this->_done[$name]) {
				throw new Cancel_Exception(sprintf('test %s required', self::ANSI_HIGHLIGHT.$name.self::ANSI_RESET));
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
			foreach ($a as $ak => $av) {
				$bv = $b[$ak];
				if (!isset($bv)) return 1;
				if ($this->_diff_array($av, $bv) != 0) {
					return 1;
				}				
			}
		}
		elseif (is_array($b) || $a != $b) {
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