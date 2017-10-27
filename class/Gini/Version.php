<?php

namespace Gini;

// A simplified Semantic Version Implementations

class Version
{
    protected $valid;

    public $fullVersion;
    public $majorVersion;
    public $minorVersion;
    public $patchVersion;
    public $preReleaseVersion;
    public $buildVersion;

    protected static $versionCache = [];

    public function __construct($version)
    {
        if (!isset(self::$versionCache[$version])) {
            $this->valid = preg_match('`^(\d+)(?:\.(\d+|[*x]))?(?:\.(\d+|[*x]))?(?:-([^+]+))?(?:\+(.+))?$`', $version, $parts);
            $this->majorVersion = $parts[1];
            $this->minorVersion = is_numeric($parts[2]) ? $parts[2] : 'x';
            $this->patchVersion = is_numeric($parts[3]) ? $parts[3] : 'x';
            $this->preReleaseVersion = $parts[4];
            $this->buildVersion = $parts[5];
            self::$versionCache[$version] = [
                'valid' => $this->valid,
                'major' => $this->majorVersion,
                'minor' => $this->minorVersion,
                'patch' => $this->patchVersion,
                'pre-release' => $this->preReleaseVersion,
                'build' => $this->buildVersion,
            ];
        } else {
            $c = self::$versionCache[$version];
            $this->valid = $c['valid'];
            $this->majorVersion = $c['major'];
            $this->minorVersion = $c['minor'];
            $this->patchVersion = $c['patch'];
            $this->preReleaseVersion = $c['pre-release'];
            $this->buildVersion = $c['build'];
        }

        $this->_refreshFullVersion();
    }

    protected function satisfiesUnit($versionUnit)
    {
        if (!preg_match('/^\s*(?:(<=|>=|[<>~^])\s*)?(.+)$/', $versionUnit, $parts)) {
            return false;
        }

        $op = $parts[1];
        $version = $parts[2];
        $v = new self($version);
        if (!$v->isValid()) {
            return false;
        }

        if ($op == '~') {
            // ~1.2.3 := >=1.2.3-0 <1.3.0-0
            // ~1.2 := >=1.2.0-0 <1.3.0-0
            // ~1 := >=1.0.0-0 <2.0.0-0

            $minVer = implode('.', [
                $v->majorVersion,
                $v->minorVersion == 'x' ? '0' : $v->minorVersion,
                $v->patchVersion == 'x' ? '0' : $v->patchVersion,
            ]).'-0';

            $maxVer = implode('.', [
                $v->minorVersion == 'x' ? $v->majorVersion + 1 : $v->majorVersion,
                $v->minorVersion == 'x' ? '0' : $v->minorVersion + 1,
                0,
            ]).'-0';

            if ($this->compare($minVer) >= 0 && $this->compare($maxVer) < 0) {
                return true;
            }
        } elseif ($op == '^') {
            // ^1.2.3 := >=1.2.3-0 <2.0.0-0
            // ^1.2 := >=1.2.0-0 <2.0.0-0
            // ^1 := >=1.0.0-0 <2.0.0-0

            $minVer = implode('.', [
                $v->majorVersion,
                $v->minorVersion == 'x' ? '0' : $v->minorVersion,
                $v->patchVersion == 'x' ? '0' : $v->patchVersion,
            ]).'-0';

            if ($v->majorVersion == 0) {
                if ($v->minorVersion == 0) {
                    if ($this->compare("0.0.{$v->patchVersion}") == 0) {
                        return true;
                    }

                    return false;
                } else {
                    $maxVer = implode('.', [
                        0,
                        $v->minorVersion + 1,
                        0,
                    ]).'-0';
                }
            } else {
                $maxVer = implode('.', [
                    $v->majorVersion + 1,
                    0,
                    0,
                ]).'-0';
            }

            if ($this->compare($minVer) >= 0 && $this->compare($maxVer) < 0) {
                return true;
            }
        } elseif ($v->majorVersion == 'x') {
            // e.g. */x => any versions
            return true;
        } elseif ($v->minorVersion == 'x') {
            // e.g. 1.x => 1.0.0-0 AND < 2.0.0-0

            $minVer = implode('.', [
                $v->majorVersion,
                0,
                0,
            ]).'-0';

            $maxVer = implode('.', [
                $v->majorVersion + 1,
                0,
                0,
            ]).'-0';

            if ($this->compare($minVer) >= 0 && $this->compare($maxVer) < 0) {
                return true;
            }
        } elseif ($v->patchVersion == 'x') {
            // e.g. 1.2.x => >= 1.2.0-0 AND < 1.3.0-0

            $minVer = implode('.', [
                $v->majorVersion,
                $v->minorVersion,
                0,
            ]).'-0';

            $maxVer = implode('.', [
                $v->majorVersion,
                $v->minorVersion + 1,
                0,
            ]).'-0';

            if ($this->compare($minVer) >= 0 && $this->compare($maxVer) < 0) {
                return true;
            }
        } else {
            $ret = $this->compare($v);
            if ($op == '') {
                if ($ret == 0) {
                    return true;
                }
            } elseif ($op == '<=') {
                if ($ret <= 0) {
                    return true;
                }
            } elseif ($op == '>=') {
                if ($ret >= 0) {
                    return true;
                }
            } elseif ($op == '>') {
                if ($ret > 0) {
                    return true;
                }
            } elseif ($op == '<') {
                if ($ret < 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function satisfies($versionRange)
    {
        if (!$this->isValid()) {
            return false;
        }

        $versionRange = trim($versionRange);
        if ($versionRange == '' || $versionRange == '*') {
            return true;
        }

        $satisfies = function ($versionRange) {
            if (!preg_match_all('`\s*(\S+)(?:\s+(-)\s|$)?`', $versionRange, $parts, PREG_PATTERN_ORDER)) {
                return false;
            }
            for ($i = 0, $max = count($parts[1]); $i < $max; ++$i) {
                $versionUnit = $parts[1][$i];
                if ($parts[2][$i] == '-') {
                    if (!$this->satisfiesUnit('>='.$versionUnit)) {
                        return false;
                    }
                    $parts[1][$i + 1] = '<='.$parts[1][$i + 1];
                } else {
                    // and op
                    if (!$this->satisfiesUnit($versionUnit)) {
                        return false;
                    }
                }
            }

            return true;
        };

        $ranges = explode('||', $versionRange);
        foreach ($ranges as $range) {
            if ($satisfies(trim($range))) {
                return true;
            }
        }

        return false;
    }

    public function isValid()
    {
        return $this->valid;
    }

    public function compare($v)
    {
        if (!$this->isValid()) {
            return 0;
        }

        if (!$v instanceof self) {
            $v = new self($v);
        }
        if (!$v->isValid()) {
            return 0;
        }

        if ($this->majorVersion > $v->majorVersion) {
            return 1;
        } elseif ($this->majorVersion < $v->majorVersion) {
            return -1;
        }

        if ($this->minorVersion > $v->minorVersion) {
            return 1;
        } elseif ($this->minorVersion < $v->minorVersion) {
            return -1;
        }

        if ($this->patchVersion > $v->patchVersion) {
            return 1;
        } elseif ($this->patchVersion < $v->patchVersion) {
            return -1;
        }

        // 1.0.0-alpha < 1.0.0-alpha.1 < 1.0.0-alpha.beta < 1.0.0-beta < 1.0.0-beta.2 < 1.0.0-beta.11 < 1.0.0-rc.1 < 1.0.0
        if (!is_null($this->preReleaseVersion)) {
            // 1.0.0-xxx < 1.0.0
            if (is_null($v->preReleaseVersion)) {
                return -1;
            }

            $a = explode('.', $this->preReleaseVersion);
            $b = explode('.', $v->preReleaseVersion);
            foreach ($a as $i => $av) {
                if (!isset($b[$i])) {
                    return 1;
                }
                $bv = $b[$i];
                if (is_numeric($av)) {
                    if (!is_numeric($bv)) {
                        return -1;
                    } elseif ($av < $bv) {
                        return -1;
                    } elseif ($av > $bv) {
                        return 1;
                    }
                } elseif (is_numeric($bv)) {
                    return 1;
                } else {
                    // both A-Z
                    $ret = strcasecmp($av, $bv);
                    if ($ret > 0) {
                        return 1;
                    } elseif ($ret < 0) {
                        return -1;
                    }
                }
            }

            if (count($b) > count($a)) {
                return -1;
            }
        } elseif (!is_null($v->preReleaseVersion)) {
            return 1;
        }

        return 0;
    }

    public function bump($part, $num = 1)
    {
        if (!$this->isValid()) {
            return false;
        }
        $part = strtolower($part);
        switch ($part) {
            case 'major':
                $this->majorVersion += $num;
                break;
            case 'minor':
                $this->minorVersion += $num;
                break;
            default:
                $this->patchVersion += $num;
        }

        $this->_refreshFullVersion();
        return true;
    }

    private function _refreshFullVersion() {
        $fullVersion = implode('.', [(int) $this->majorVersion, (int) $this->minorVersion, (int) $this->patchVersion]);
        if ($this->preReleaseVersion) {
            $fullVersion .= '-' . $this->preReleaseVersion;
        }
        if ($this->buildVersion) {
            $fullVersion .= '+' . $this->buildVersion;
        }
        $this->fullVersion = $fullVersion;
    }

    public function __toString()
    {
        return $this->fullVersion;
    }
}

/*

$version = new Version('1.2.3');
if ($version->satisfies('1.x || >=2.5.0 || 5.0.0 - 7.2.3')) {

}

*/
