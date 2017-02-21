<?php

namespace avadim\hjson;

class Hjson
{
    protected $sJson;
    protected $bAssoc = false;
    protected $aTokens;
    protected $iTokenNum = 0;
    protected $iTokenLine = 0;

    public function __construct()
    {
    }

    /**
     * @param string $sString
     * @param bool   $bAssoc
     *
     * @return array
     */
    static public function decode($sString, $bAssoc = false)
    {
        $oHJson = new static();
        return $oHJson->parseString($sString, $bAssoc);
    }

    /**
     * @param $sText
     * @param null $iLine
     *
     * @throws \RuntimeException
     */
    protected function error($sText, $iLine = null)
    {
        if (null !== $iLine) {
            $sText .= ' (at line ' . $iLine . ')';
        }
        throw new HjsonException($sText);
    }

    /**
     * @return int
     */
    protected function currentLine()
    {
        $iNum = $this->iTokenNum;
        while (isset($this->aTokens[$iNum]) && !is_array($this->aTokens[$iNum])) {
            $iNum -= 1;
        }
        return isset($this->aTokens[$iNum][2]) ? ($this->aTokens[$iNum][2] - 1) : 1;
    }

    /**
     * @return string|null
     */
    protected function currentTokenValue()
    {
        return $this->getTokenValue(0);
    }

    /**
     * @param int $iOffset
     *
     * @return string|null
     */
    protected function getTokenValue($iOffset = 0)
    {
        if (isset($this->aTokens[$this->iTokenNum + $iOffset])) {
            if (is_array($this->aTokens[$this->iTokenNum + $iOffset])) {
                return $this->aTokens[$this->iTokenNum + $iOffset][1];
            } else {
                return $this->aTokens[$this->iTokenNum + $iOffset];
            }
        }
        return null;
    }

    /**
     * @param null $xValue
     *
     * @return bool
     */
    protected function isToken($xValue = null)
    {
        if (isset($this->aTokens[$this->iTokenNum]) && is_array($this->aTokens[$this->iTokenNum])) {
            if (null === $xValue) {
                // check the existence of a token only
                return true;
            }
            if (is_array($xValue)) {
                return in_array($this->aTokens[$this->iTokenNum][0], $xValue, true);
            } else {
                return $this->aTokens[$this->iTokenNum][0] === $xValue;
            }
        }
        return false;
    }

    /**
     * @param int $iSkipTokens
     *
     * @return bool|null|string
     */
    protected function nextToken($iSkipTokens = 1)
    {
        $this->iTokenNum += $iSkipTokens;
        if (isset($this->aTokens[$this->iTokenNum])) {
            return $this->currentTokenValue();
        }
        return false;
    }

    /**
     * @return array
     */
    protected function readToken()
    {
        $aToken = false;
        if (isset($this->aTokens[$this->iTokenNum])) {
            $aToken = $this->aTokens[$this->iTokenNum];
        }
        $this->iTokenNum += 1;

        return $aToken;
    }

    /**
     * Is new line
     *
     * @return bool
     */
    protected function isNL()
    {
        if ($this->isToken(T_WHITESPACE) && preg_match("/\r\n|\r|\n/", $this->aTokens[$this->iTokenNum][1])) {
            return true;
        }
        return false;
    }

    /**
     * @param string $sJson
     * @param bool   $bAssoc
     *
     * @return array
     */
    public function parseString($sJson, $bAssoc = false)
    {
        $this->sJson = $sJson;
        $this->bAssoc = $bAssoc;
        $this->aTokens = token_get_all("<?php\n" . $this->sJson);
        unset($this->aTokens[0]);
        $this->iTokenNum = 1;

        return $this->readObject();
    }

    /**
     * @return array
     */
    protected function readMember()
    {
        $sKey = $this->readKey();
        if (empty($sKey)) {
            $this->error('Invalid key "' . $this->currentTokenValue() . '"', $this->currentLine());
        }
        $sColon = $this->readColon();
        if ($sColon === false) {
            $this->error('Missing colon', $this->currentLine());
        }
        $xValue = $this->readValue();
//        if ($xValue === false) {
//            $this->error('Missing value', $this->currentLine());
//        }
        return [$sKey, $xValue];
    }

    /**
     * @return string
     */
    protected function skipNoise()
    {
        while ($this->isToken([T_WHITESPACE, T_COMMENT])) {
            $this->iTokenNum += 1;
        }
        return $this->currentTokenValue();
    }

    /**
     * @return bool|string
     */
    protected function readKey()
    {
        $this->skipNoise();
        $sKey = '';
        while (!$this->isToken(T_WHITESPACE)
            && ($sTokenValue = $this->currentTokenValue())
            && !in_array($sTokenValue, [',', ':', '[', ']', '{', '}'], true)) {
            if (!$sKey && $this->isToken(T_CONSTANT_ENCAPSED_STRING)) {
                $this->nextToken();
                return substr($sTokenValue, 1, -1);
            } else {
                $sKey .= $sTokenValue;
                $this->nextToken();
            }
        }
        return $sKey;
    }

    /**
     * @return bool
     */
    protected function readColon()
    {
        $sValue = $this->skipNoise();
        if ($sValue === ':') {
            $this->nextToken();
            return ':';
        }
        return false;
    }

    /**
     * @return string
     */
    protected function readString()
    {
        $sString = '';
        while(isset($this->aTokens[$this->iTokenNum]) && !$this->isNL()) {
            $sTokenValue = $this->currentTokenValue();
            if (!$this->isToken(T_COMMENT)) {
                $sString .= $sTokenValue;
            } elseif (substr($sTokenValue, 0, 2) === '//') {
                break;
            }
            $this->nextToken();
        }
        return $sString;
    }

    /**
     * Convert unicode sequences into symbols
     *
     * @param string $sString
     *
     * @return string
     */
    protected function convertUnicode($sString)
    {
        //return $sString;
        return preg_replace_callback(
            '/^\\\\[u][\da-f]{4}|[^\\\\](\\\\[u][\da-f]{4})/m',
            function ($aM) {
                if (isset($aM[1])) {
                    return $aM[0][0] . mb_convert_encoding('&#x' . substr($aM[0], 3) . ';', 'UTF-8', 'HTML-ENTITIES');
                } else {
                    return mb_convert_encoding('&#x' . substr($aM[0], 2) . ';', 'UTF-8', 'HTML-ENTITIES');
                }
            },
            $sString);
    }

    /**
     * @return mixed
     */
    protected function readValue()
    {
        $this->skipNoise();
        if (isset($this->aTokens[$this->iTokenNum])) {
            if (is_array($this->aTokens[$this->iTokenNum])) {
                $aToken = $this->aTokens[$this->iTokenNum];
                switch ($aToken[0]) {
                    case T_CONSTANT_ENCAPSED_STRING:
                        // check multiline string
                        if ($this->currentTokenValue() === "''") {
                            $sTokenValue1 = $this->getTokenValue(1);
                            $sTokenValue2 = $this->getTokenValue(2);
                            if ($sTokenValue1 && $sTokenValue2 === "''" && preg_match("/^'(\r\n|\r|\n)(.*)(\r?\n|\r)\s*'/msU", $sTokenValue1, $aM)) {
                                $this->nextToken(3);
                                return $this->convertUnicode($aM[2]);
                            }
                        }
                        $this->nextToken();
                        return $this->convertUnicode(substr($aToken[1], 1, -1));
                    case T_LNUMBER:
                        $this->nextToken();
                        return (int)$aToken[1];
                    case T_DNUMBER:
                        $this->nextToken();
                        return (float)$aToken[1];
                    case T_STRING:
                        switch (strtolower($aToken[1])) {
                            case 'true':
                                $this->nextToken();
                                return true;
                            case 'false':
                                $this->nextToken();
                                return false;
                            case 'null':
                                $this->nextToken();
                                return null;
                            default:
                                return $this->convertUnicode($this->readString());
                        }
                }

            } elseif ($this->aTokens[$this->iTokenNum] === '[') {
                return $this->readArray();
            } elseif ($this->aTokens[$this->iTokenNum] === '{') {
                return $this->readObject();
            }
            return $this->convertUnicode($this->readString());
        }
        return null;
    }

    /**
     * @param string $sBeginChar
     * @param string $sEndChar
     * @param bool   $bValueOnly
     *
     * @return array
     */
    protected function readList($sBeginChar, $sEndChar, $bValueOnly)
    {
        $aItems = [];
        $sTokenValue = $this->currentTokenValue();
        if ($sTokenValue !== $sBeginChar) {
            $this->error('Missing ' . $sBeginChar, $this->currentLine());
        }
        $this->nextToken();
        $sTokenValue = $this->skipNoise();
        while (null !== $sTokenValue && $sTokenValue !== $sEndChar) {
            if ($bValueOnly) {
                $xValue = $this->readValue();
                $aItems[] = $xValue;
            } else {
                list($sName, $xValue) = $this->readMember();
                $aItems[$sName] = $xValue;
            }
            $sTokenValue = $this->skipNoise();
            if ($sTokenValue === ',') {
                $this->nextToken();
                $sTokenValue = $this->skipNoise();
            }
        }
        if ($this->currentTokenValue() !== $sEndChar) {
            $this->error('Missing ' . $sEndChar, $this->currentLine());
        }
        $this->nextToken();

        return $aItems;
    }

    /**
     * @return array
     */
    protected function readArray()
    {
        return $this->readList('[', ']', true);
    }

    /**
     * @return array|object
     */
    protected function readObject()
    {
        if ($this->bAssoc) {
            return $this->readList('{', '}', false);
        } else {
            return (object)$this->readList('{', '}', false);
        }
    }

}

// EOF