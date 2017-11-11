<?php
	
	/*
	 * String
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	function str_truncate($text, $length, $dots = '', $char = ' ') {
		if (!is_int($length) or $length < 0) {
			return $text;
		}
		
		$textLength = mb_strlen($text);
		
		if ($textLength <= $length) {
			return $text;
		}
		
		$length -= mb_strlen($dots);
		
		if ($char === false or $char === '') {
			return mb_substr($text, 0, $length) . $dots;
		}
		
		return mb_substr(
			$text,
			0,
			mb_strrpos(
				$text, $char,
				max(-($textLength - $length), -$textLength)
			)
		) . $dots;
	}
	
	function str_shorten($text, $length, $minimal = 2, $dots = '…', $char = ' ') {
		if (!is_int($length) or empty($char)) {
			return false;
		}
		
		$textLength = $last = mb_strlen($text);
		
		if ($textLength <= $length) {
			return $text;
		}
		
		while ($minimal > 0 and $last < $textLength) {
			$last = mb_strrpos($text, $char, -($textLength - $last) - 1);
			$minimal--;
		}
		
		$last = mb_substr($text, $last + 1);
		
		if ($length - mb_strlen($last) <= 0) {
			return str_truncate($text, $length, $dots, $char);
		}
		
		return str_truncate($text, $length - mb_strlen($last), $dots, $char) . $last;
	}
	
	// TODO: wordwrap?
	function str_wrap($text, $length, $delimiter = '<br />', $range = 6, $additionalDelimiter = null, $break = true) {
		if (!is_int($length)) {
			return false;
		}
		
		$i = $length;
		
		while($i < mb_strlen($text)) {
			$pos = mb_strpos(
				$text,
				' ',
				$i - (($i - round($range / 2) > 0)? round($range / 2) : 0)
			);
			
			if ($pos === false) {
				$pos = mb_strlen($text);
			}
			
			if ($pos - $i > $range and $break) {
			 	$text = mb_substr($text, 0, $i) .
					(($additionalDelimiter !== null and mb_substr($text, $i - 1, 1) !== ' ')?
						$additionalDelimiter : '') .
					$delimiter .
					mb_substr($text, $i);
			} else {
				$text = mb_substr($text, 0, $pos) .
					$delimiter .
					mb_substr($text, $pos);
			}
			
			$i += $length + mb_strlen($delimiter);
		}
		
		return $text;
	}
	
	function str_utf8_ascii_transliterate($string) {
		return transliterator_transliterate(
			'Any-Latin; Latin-ASCII; [\u0100-\u7fff] remove',
			str_replace(
				['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'],
				['ae', 'oe', 'üe', 'AE', 'OE', 'UE'],
				Normalizer::normalize($string, Normalizer::FORM_C)
			)
		);
	}
	
	function str_to_decimal($string) {
		$pos = [
			strrpos($string, '.'),
			strrpos($string, ',')
		];
		
		if (
			$pos[0] !== false and
			$pos[0] >= $pos[1] and
			$pos[0] === strpos($string, '.')
		) {
			$pos = $pos[0];
		} elseif ($pos[1] !== false and $pos[1] === strpos($string, ',')) {
			$pos = $pos[1];
		} else {
			return strtr(
				$string,
				['.' => '', ',' => '']
			) . '.0';
		}
		
		$fractional = substr($string, $pos + 1);
		
		return strtr(
			($pos === 0)? '0' : substr($string, 0, $pos),
			['.' => '', ',' => '']
		) . '.' . (($fractional === false)? '0' : $fractional);
	}
	
	function mb_ucwords($string) {
		return mb_convert_case($string, MB_CASE_TITLE);
	}

	function mb_ucfirst($string) {
		return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
	}
