<?php
	
	/*
	 * Inflector
	 *
	 * Grammatically transform words
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Inflector {
		
		/**
		 * Array with exceptions from normal behaviou rof singular to plural transformations
		 * 
		 * @var array
		 **/
		private static $_singularExceptions = array('matrix' => 'matrizes', 'vertex' => 'vertices', 'index' => 'indices', 'mouse' => 'mice', 'louse' => 'lice', 'search' => 'searches', 'switch' => 'switches', 'fix' => 'fixes', 'box' => 'boxes', 'process' => 'processes', 'adress' => 'adresses', 'half' => 'halves', 'wife' => 'wives', 'basis' => 'bases', 'diagnosis' => 'diagnoses', 'medium' => 'media', 'date' => 'date', 'news' => 'news', 'information' => 'information', 'virus' => 'virus', 'person' => 'people', 'man' => 'men', 'woman' => 'women', 'child' => 'children', 'buffalo' => 'buffaloes', 'tomato' => 'tomatoes', 'bus' => 'buses', 'alias' => 'aliases', 'axis' => 'axes', 'crisis' => 'crises');
		
		/**
		 * Array with exceptions from normal behaviour of plural to singular transformations
		 * 
		 * @var array
		 **/						
		private static $_pluralExceptions = array('matrizes' => 'matrix', 'vertices' => 'vertex', 'indices' => 'index', 'mice' => 'mouse', 'lice' => 'louse', 'searches' => 'search', 'switches' => 'switch', 'fixes' => 'fix', 'boxes' => 'box', 'processes' => 'process', 'adresses' => 'adress', 'halves' => 'half', 'wives' => 'wife', 'bases' => 'basis', 'diagnoses' => 'diagnosis', 'media' => 'medium', 'date' => 'date', 'news' => 'news', 'information' => 'information', 'virus' => 'virus', 'people' => 'person', 'men' => 'man', 'women' => 'woman', 'children' => 'child', 'buffaloes' => 'buffalo', 'tomatoes' => 'tomato', 'buses' => 'bus', 'aliases' => 'alias', 'axes' => 'axis', 'crises' => 'crisis');
		
		private static $_pluraliaTantum = array('Alimente' => true, 'Allüren' => true, 'Eltern' => true, 'Blattern' => true, 'Effekten' => true, 'Eingeweide' => true, 'Einkünfte' => true, 'Faxen' => true, 'Ferien' => true, 'Finanzen' => true, 'Fisimatenten' => true, 'Flitterwochen' => true, 'Gebrüder' => true, 'Honneurs' => true, 'Iden' => true, 'Imponderabilien' => true, 'Kalenden' => true, 'Kinkerlitzchen' => true, 'Knickerbocker' => true, 'Kosten' => true, 'Leggings' => true, 'Leggins' => true, 'Leute' => true, 'Masern' => true, 'Memoiren' => true, 'Molesten' => true, 'Moneten' => true, 'Musikalien' => true, 'Nonen' => true, 'Pocken' => true, 'Ränke' => true, 'Ringelröteln' => true, 'Röteln' => true, 'Shorts' => true, 'Sperenzchen' => true, 'Sperenzien' => true, 'Spesen' => true, 'Terminalien' => true, 'Umschweife' => true, 'Unbilden' => true, 'Unkosten' => true, 'Windpocken' => true, 'Wirren' => true, 'Aleuten' => true, 'Alpen' => true, 'Anden' => true, 'Appalachen' => true, 'Ardennen' => true, 'Azoren' => true, 'Bahamas' => true, 'Balearen' => true, 'Dardanellen' => true, 'Dolomiten' => true, 'Kanaren' => true, 'Karawanken' => true, 'Karpaten' => true, 'Komoren' => true, 'Kordilleren' => true, 'Kurilen' => true, 'Lofoten' => true, 'Malediven' => true, 'Malwinen' => true, 'Marianen' => true, 'Molukken' => true, 'Niederlande' => true, 'Philippinen' => true, 'Pyrenäen' => true, 'Rocky Mountains' => true, 'Salomonen' => true, 'Seychellen' => true, 'Tropen' => true, 'Vogesen');
		
		/**
		 * Transform a word from plural to singular
		 * 
		 * @param string $string word 
		 * @return string
		 **/
		public static function singular($string, $language = 'en_GB') {
			
			$string = trim($string);
			
			if ($language == 'de_DE') {
				if (!isset(self::$_pluraliaTantum[$string])) {
					if (mb_substr($string, -1) == 'e') {
						if (mb_substr($string, -3, 2) == 'ss') {
							$string = mb_substr($string, 0, -2);
						} elseif (mb_substr($string, -3, 2) == 'en' or mb_substr($string, -3, 2) == 'st') {
							$string = mb_substr($string, 0, -1);
						}
					} elseif (mb_substr($string, -2) == 'en') {
						if (mb_substr($string, -4, 2) == 'ss' or mb_substr($string, -4, 2) == 'nn') {
							$string = mb_substr($string, 0, -3);
						} elseif (mb_substr($string, -3, 1) == 's') {
							$string = mb_substr($string, 0, -1);
						} else {
							$string = mb_substr($string, 0, -2);
						}
					} elseif ((mb_substr($string, -1) == 's' and mb_substr($string, -2, 1) != 'r' and mb_substr($string, -2, 1) != 'i') or mb_substr($string, -2) == 'rn' or mb_substr($string, -2) == 'te') {
						$string = mb_substr($string, 0, -1);
					}
				}
			} else {
				if (!empty(self::$_pluralExceptions[$string])) {
					return self::$_pluralExceptions[$string];
				} elseif (mb_strtolower(mb_substr($string, -3)) == 'ies') {
					return mb_substr($string, 0, -3) . 'y';
				} elseif (mb_strtolower(mb_substr($string, -1)) == 's') {
					return mb_substr($string, 0, -1);
				}
			}
			
			return $string;
			
		}
		
		/**
		 * Transform a word from singular to plural
		 * 
		 * @param string $string word 
		 * @return string
		 **/
		public static function plural($string) {
			
			$string = trim($string);
			$end = mb_strtolower(mb_substr($string, -1));
			
			if (!empty(self::$_singularExceptions[$string])) {
				return self::$_singularExceptions[$string];
			} elseif ($end == 'y') {
				return mb_substr($string, 0, -1) . 'ies';
			} elseif ($end != 's') {
				return $string . 's';
			} else {
				return $string;
			}
			
		}
		
		/**
		 * Camelize a string
		 * 
		 * @param string $string string to camelize 
		 * @return string
		 **/
		public static function camelize($string) {
			
			return mb_substr(str_replace(' ', '', ucwords(str_replace('_', ' ', '#' . mb_strtolower(trim($string))))), 1);
			
		}
		
		/**
		 * Reverse camelizing a string
		 * 
		 * @todo find a way to handle strings like getRPCCall and getRPC (don't touch RPC and find the last captical letter)
		 * 
		 * @param string $string string to uncamlize
		 * @param boolean $underscore replace spaces with _
		 * @return string
		 **/
		public static function uncamelize($string, $underscore = false) {
			
			$string = trim($string);
			// TODO: move table to static var
			$string = strtr(mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1), array('A' => ' a', 'B' => ' b', 'C' => ' c', 'D' => ' d', 'E' => ' e', 'F' => ' f', 'G' => ' g', 'H' => ' h', 'I' => ' i', 'J' => ' l', 'K' => ' k', 'L' => ' l', 'M' => ' m', 'N' => ' n', 'O' => ' o', 'P' => ' p', 'Q' => ' q', 'R' => ' r', 'S' => ' s', 'T' => ' t', 'U' => ' u', 'V' => ' v', 'W' => ' w', 'X' => ' x', 'Y' => ' y', 'Z' => ' z'));
			
			if ($underscore) {
				$string = self::underscore($string);
			}
			
			return $string;
			
		}
		
		/**
		 * Replace spaces with _
		 * 
		 * @param string $string string to underscoe 
		 * @return string
		 **/
		public static function underscore($string) {
			
			return str_replace(' ', '_', mb_strtolower(trim($string)));
			
		}
		
		/**
		 * Humanize a string, replace _ with spaces
		 * 
		 * @param string $string string to humanize 
		 * @return string
		 **/
		public static function humanize($string) {
			
			return str_replace('_', ' ', mb_strtolower(trim($string)));
			
		}
		
	}
