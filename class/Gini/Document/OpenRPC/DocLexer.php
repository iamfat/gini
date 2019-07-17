<?php

namespace Gini\Document\OpenRPC;

use Doctrine\Common\Lexer\AbstractLexer;

final class DocLexer extends AbstractLexer
{
    const T_NONE                = 1;
    const T_INTEGER             = 2;
    const T_STRING              = 3;
    const T_FLOAT               = 4;
    const T_NEWLINE             = 5;
    const T_SPACE               = 6;

    // All tokens that are also identifiers should be >= 100
    const T_IDENTIFIER          = 100;
    const T_AT                  = 101;
    const T_CLOSE_CURLY_BRACES  = 102;
    const T_CLOSE_PARENTHESIS   = 103;
    const T_COMMA               = 104;
    const T_EQUALS              = 105;
    const T_FALSE               = 106;
    const T_NAMESPACE_SEPARATOR = 107;
    const T_OPEN_CURLY_BRACES   = 108;
    const T_OPEN_PARENTHESIS    = 109;
    const T_TRUE                = 110;
    const T_NULL                = 111;
    const T_COLON               = 112;
    const T_MINUS               = 113;

    /**
     * @var array
     */
    protected $noCase = array(
        '@'  => self::T_AT,
        ','  => self::T_COMMA,
        '('  => self::T_OPEN_PARENTHESIS,
        ')'  => self::T_CLOSE_PARENTHESIS,
        '{'  => self::T_OPEN_CURLY_BRACES,
        '}'  => self::T_CLOSE_CURLY_BRACES,
        '='  => self::T_EQUALS,
        ':'  => self::T_COLON,
        '-'  => self::T_MINUS,
        '\\' => self::T_NAMESPACE_SEPARATOR,
        "\n" => self::T_NEWLINE,
        "\r" => self::T_NEWLINE,
        "\t" => self::T_SPACE,
        ' '  => self::T_SPACE
    );

    /**
     * @var array
     */
    protected $withCase = array(
        'true'  => self::T_TRUE,
        'false' => self::T_FALSE,
        'null'  => self::T_NULL
    );

    /**
     * Whether the next token starts immediately, or if there were
     * non-captured symbols before that
     */
    public function nextTokenIsAdjacent() : bool
    {
        return $this->token === null
            || ($this->lookahead !== null
                && ($this->lookahead['position'] - $this->token['position']) === strlen($this->token['value']));
    }

    /**
     * {@inheritdoc}
     */
    protected function getCatchablePatterns()
    {
        return array(
            '[a-z_\\\][a-z0-9_\:\\\]*[a-z_][a-z0-9_]*',
            '(?:[+-]?[0-9]+(?:[\.][0-9]+)*)(?:[eE][+-]?[0-9]+)?',
            '"(?:""|[^"])*+"',
            '[\x{4E00}-\x{9FFF}]+',
            '\n|\r',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getNonCatchablePatterns()
    {
        return array('\s{2,}', '\*+', '(.)');
    }

    /**
     * {@inheritdoc}
     */
    protected function getModifiers()
    {
        return 'iu';
    }

    /**
     * {@inheritdoc}
     */
    protected function getType(&$value)
    {
        $type = self::T_NONE;

        if ($value[0] === '"') {
            $value = str_replace('""', '"', substr($value, 1, strlen($value) - 2));

            return self::T_STRING;
        }

        if (isset($this->noCase[$value])) {
            return $this->noCase[$value];
        }

        if ($value[0] === '_' || $value[0] === '\\' || ctype_alpha($value[0])) {
            return self::T_IDENTIFIER;
        }

        $lowerValue = strtolower($value);

        if (isset($this->withCase[$lowerValue])) {
            return $this->withCase[$lowerValue];
        }

        // Checking numeric value
        if (is_numeric($value)) {
            return (strpos($value, '.') !== false || stripos($value, 'e') !== false)
                ? self::T_FLOAT : self::T_INTEGER;
        }

        return $type;
    }
}
