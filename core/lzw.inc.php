<?php

function lzw_decompress(string $binary): string {
    $dictionary_count = 256;
    $bits = 8;
    $codes = [];
    $rest = 0;
    $rest_length = 0;
    for ($i = 0; $i < strlen($binary); $i++) {
        $rest = ($rest << 8) + ord($binary[$i]);
        $rest_length += 8;
        if ($rest_length >= $bits) {
            $rest_length -= $bits;
            $codes[] = $rest >> $rest_length;
            $rest &= (1 << $rest_length) - 1;
            $dictionary_count++;
            if ($dictionary_count >> $bits) {
                $bits++;
            }
        }
    }
    $dictionary = range("\0", "\xFF");
    $return = '';
    $word = '';
    foreach ($codes as $i => $code) {
        $element = $dictionary[$code] ?? null;
        if ($element === null) {
            $element = $word . $word[0];
        }
        $return .= $element;
        if ($i) {
            $dictionary[] = $word . $element[0];
        }
        $word = $element;
    }
    return $return;
}
