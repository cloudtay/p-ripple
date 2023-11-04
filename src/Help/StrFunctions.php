<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Help;

/**
 *
 */
trait StrFunctions
{
    /**
     * @param string $str
     * @return int
     */
    public function strToBytes(string $str): int
    {
        $last = strtolower($str[strlen($str) - 1]);
        $num = (int)substr($str, 0, -1);
        if ($last === 'g') {
            $num *= 1024;
        }
        if ($last === 'm') {
            $num *= 1024;
        }
        if ($last === 'k') {
            $num *= 1024;
        }
        return $num;
    }
}
