<?php
// Generator hasel. Dwa sposoby generowania (parametr mode):
//  - random     - losowe znaki: litery, zadana liczba cyfr i znakow specjalnych,
//  - passphrase - fraza z losowych slow z listy resources/passphrase_words.dat,
//  - syllables  - "melodyjne" haslo skladane z sylab z syllables.dat.
// Wszystkie losowania uzywaja random_int() (kryptograficznie bezpieczny generator).

define('GEN_LOWER', 'abcdefghijklmnopqrstuvwxyz');
define('GEN_UPPER', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
define('GEN_DIGITS', '0123456789');
define('GEN_SPECIAL', '!@#$%^&*-_=+?');

function random_char($chars) {
	return $chars[random_int(0, strlen($chars) - 1)];
}

// Tasowanie Fishera-Yatesa z bezpiecznym losowaniem (shuffle() uzywa zwyklego generatora)
function secure_shuffle($items) {
	for ($i = count($items) - 1; $i > 0; $i--) {
		$j = random_int(0, $i);
		$tmp = $items[$i];
		$items[$i] = $items[$j];
		$items[$j] = $tmp;
	}
	return $items;
}

function generate_random_password($length, $digitsCount, $specialCount) {
	// Cyfry i znaki specjalne nie moga przekroczyc dlugosci hasla
	$specialCount = min($specialCount, $length);
	$digitsCount = min($digitsCount, $length - $specialCount);
	$lettersCount = $length - $digitsCount - $specialCount;

	$chars = array();
	for ($i = 0; $i < $lettersCount; $i++) {
		// Co najmniej jedna mala i jedna wielka litera, reszta z obu zbiorow
		if ($i == 0) {
			$chars[] = random_char(GEN_LOWER);
		}
		elseif ($i == 1) {
			$chars[] = random_char(GEN_UPPER);
		}
		else {
			$chars[] = random_char(GEN_LOWER . GEN_UPPER);
		}
	}
	for ($i = 0; $i < $digitsCount; $i++) {
		$chars[] = random_char(GEN_DIGITS);
	}
	for ($i = 0; $i < $specialCount; $i++) {
		$chars[] = random_char(GEN_SPECIAL);
	}

	return implode('', secure_shuffle($chars));
}

function passphrase_words() {
	$words = array();
	foreach (file(__DIR__ . '/resources/passphrase_words.dat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$line = trim($line);
		if ($line !== '') {
			$words[] = $line;
		}
	}
	return $words;
}

function generate_passphrase($wordsCount, $separator, $capitalize, $addNumber) {
	$list = passphrase_words();
	$words = array();
	for ($i = 0; $i < $wordsCount; $i++) {
		$word = $list[random_int(0, count($list) - 1)];
		$words[] = $capitalize ? ucfirst($word) : $word;
	}
	// Cyfra doklejona do losowo wybranego slowa
	if ($addNumber) {
		$words[random_int(0, $wordsCount - 1)] .= random_int(0, 9);
	}
	return implode($separator, $words);
}

// Slowo z calych sylab o dokladnie zadanej liczbie liter, pierwsza litera wielka.
// Sylaby maja 2-4 litery; dobieramy je tak, by nie zostala reszta 1 litery,
// ktorej nie da sie wypelnic cala sylaba (wyjatek: slowo 1-literowe).
function syllable_word($syllables, $letters) {
	if ($letters <= 0) {
		return '';
	}
	if ($letters == 1) {
		$s = $syllables[random_int(0, count($syllables) - 1)];
		return strtoupper($s[0]);
	}

	$word = '';
	$remaining = $letters;
	while ($remaining > 0) {
		$candidates = array();
		foreach ($syllables as $s) {
			$rest = $remaining - strlen($s);
			if ($rest == 0 || $rest >= 2) {
				$candidates[] = $s;
			}
		}
		if (empty($candidates)) {
			// Lista sylab bez pasujacych dlugosci - uzupelniamy fragmentem sylaby
			$s = $syllables[random_int(0, count($syllables) - 1)];
			$candidates[] = substr(str_repeat($s, $remaining), 0, $remaining);
		}
		$s = $candidates[random_int(0, count($candidates) - 1)];
		$word .= $s;
		$remaining -= strlen($s);
	}
	return ucfirst($word);
}

// Haslo z sylab: [slowo] [cyfry] [znaki specjalne] [slowo]. Litery dzielimy po rowno
// miedzy oba slowa; przy nieparzystej liczbie liter dodatkowa trafia losowo na jedna strone.
function generate_syllable_password($length, $digitsCount, $specialCount) {
	$syllables = array();
	foreach (file(__DIR__ . '/syllables.dat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$line = strtolower(trim($line));
		if ($line !== '') {
			$syllables[] = $line;
		}
	}
	$special = '!@#$%&*';

	// Cyfry i znaki specjalne nie moga przekroczyc dlugosci hasla
	$specialCount = min($specialCount, $length);
	$digitsCount = min($digitsCount, $length - $specialCount);
	$letters = $length - $digitsCount - $specialCount;

	$before = intdiv($letters, 2);
	if ($letters % 2 == 1 && random_int(0, 1) == 1) {
		$before++;
	}
	$after = $letters - $before;

	$password = syllable_word($syllables, $before);
	for ($i = 0; $i < $digitsCount; $i++) {
		$password .= random_char(GEN_DIGITS);
	}
	for ($i = 0; $i < $specialCount; $i++) {
		$password .= random_char($special);
	}
	$password .= syllable_word($syllables, $after);

	return $password;
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'random';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if ($mode == 'passphrase') {
	$words = isset($_GET['words']) ? (int)$_GET['words'] : 5;
	$separator = isset($_GET['separator']) ? (string)$_GET['separator'] : '-';
	$capitalize = !empty($_GET['capitalize']);
	$number = !empty($_GET['number']);

	if ($words < 3) $words = 3;
	if ($words > 12) $words = 12;
	if (!in_array($separator, array('-', '.', '_', ' ', ''), true)) $separator = '-';

	echo generate_passphrase($words, $separator, $capitalize, $number);
}
elseif ($mode == 'syllables') {
	$length = isset($_GET['length']) ? (int)$_GET['length'] : 12;
	$digits = isset($_GET['digits']) ? (int)$_GET['digits'] : 1;
	$special = isset($_GET['special']) ? (int)$_GET['special'] : 1;

	if ($length < 4) $length = 4;
	if ($length > 256) $length = 256;
	if ($digits < 0) $digits = 0;
	if ($digits > 256) $digits = 256;
	if ($special < 0) $special = 0;
	if ($special > 256) $special = 256;

	echo generate_syllable_password($length, $digits, $special);
}
else {
	$length = isset($_GET['length']) ? (int)$_GET['length'] : 16;
	$digits = isset($_GET['digits']) ? (int)$_GET['digits'] : 2;
	$special = isset($_GET['special']) ? (int)$_GET['special'] : 2;

	if ($length < 4) $length = 4;
	if ($length > 256) $length = 256;
	if ($digits < 0) $digits = 0;
	if ($special < 0) $special = 0;

	echo generate_random_password($length, $digits, $special);
}
