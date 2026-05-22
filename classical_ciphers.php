<?php
function toUpper(string $s): string {
    return strtoupper($s);
}
function lettersOnly(string $s): string {
    return preg_replace('/[^A-Z]/', '', strtoupper($s));
}
function modInv(int $a, int $m): int {
    $a = (($a % $m) + $m) % $m;
    for ($x = 1; $x < $m; $x++)
        if (($a * $x) % $m === 1) return $x;
    return -1;
}
function prompt(string $msg): string {
    echo $msg;
    return trim(fgets(STDIN));
}
function promptInt(string $msg): int {
    return (int) prompt($msg);
}


function buildPlayfairSquare(string $key): array {
    $k = lettersOnly($key);
    $k = str_replace('J', 'I', $k);
    $sq = [];
    $used = array_fill(0, 26, false);
    $used[ord('J') - ord('A')] = true;
    for ($i = 0; $i < strlen($k); $i++) {
        $idx = ord($k[$i]) - ord('A');
        if (!$used[$idx]) { $used[$idx] = true; $sq[] = $k[$i]; }
    }
    for ($i = 0; $i < 26; $i++)
        if (!$used[$i]) $sq[] = chr(ord('A') + $i);
    return $sq;
}
function pfPos(array $sq, string $c): array {
    if ($c === 'J') $c = 'I';
    for ($i = 0; $i < 25; $i++)
        if ($sq[$i] === $c) return [(int)($i / 5), $i % 5];
    return [-1, -1];
}
function playfairProcess(string $text, string $key, bool $encrypt): string {
    $sq = buildPlayfairSquare($key);
    $t = str_replace('J', 'I', lettersOnly($text));
    $chars = str_split($t);

    // Build digraphs
    $digraphs = [];
    $i = 0;
    while ($i < count($chars)) {
        $a = $chars[$i];
        $b = isset($chars[$i + 1]) ? $chars[$i + 1] : 'X';
        $filler = ($a === 'X') ? 'Q' : 'X';
        if ($a === $b) { $digraphs[] = [$a, $filler]; $i++; }
        else           { $digraphs[] = [$a, $b];   $i += 2; }
    }

    $result = '';
    $shift = $encrypt ? 1 : 4;
    foreach ($digraphs as [$a, $b]) {
        [$ra, $ca] = pfPos($sq, $a);
        [$rb, $cb] = pfPos($sq, $b);
        if ($ra === $rb) {
            $result .= $sq[$ra * 5 + ($ca + $shift) % 5];
            $result .= $sq[$rb * 5 + ($cb + $shift) % 5];
        } elseif ($ca === $cb) {
            $result .= $sq[(($ra + $shift) % 5) * 5 + $ca];
            $result .= $sq[(($rb + $shift) % 5) * 5 + $cb];
        } else {
            $result .= $sq[$ra * 5 + $cb];
            $result .= $sq[$rb * 5 + $ca];
        }
    }
    return $result;
}
function playfairMenu(): void {
    echo "\n--- PLAYFAIR CIPHER ---\n";
    $text   = prompt("Enter text: ");
    $key    = prompt("Enter keyword: ");
    $choice = promptInt("1. Encrypt  2. Decrypt\nChoice: ");
    $enc    = $choice === 1;
    echo ($enc ? "Ciphertext: " : "Plaintext:  ");
    echo playfairProcess($text, $key, $enc) . "\n";
}


const PIGPEN = [
    'A' => '[_ ]',  'B' => '[__]', 'C' => '[ _]',
    'D' => '[| ]',  'E' => '[ | ]','F' => '[ |]',
    'G' => '[~ ]',  'H' => '[~~]', 'I' => '[ ~]',
    'J' => '<. >',  'K' => '<..>', 'L' => '< .>',
    'M' => '(. )',  'N' => '(..)','O' => '( .)',
    'P' => '[. ]',  'Q' => '[..]', 'R' => '[ .]',
    'S' => '/\\',   'T' => '\\|/', 'U' => '\\/',
    'V' => '/\\.',  'W' => '\\|/.','X' => '\\/.',
    'Y' => '//',    'Z' => '\\\\'
];
function pigpenEncode(string $text): string {
    $text = strtoupper($text);
    $tokens = [];
    for ($i = 0; $i < strlen($text); $i++) {
        $c = $text[$i];
        if (isset(PIGPEN[$c]))    $tokens[] = PIGPEN[$c];
        elseif ($c === ' ')       $tokens[] = '/';
    }
    return json_encode($tokens);
}
function pigpenDecode(string $encoded): string {
    $reverse = array_flip(PIGPEN);
    $tokens = json_decode($encoded, true);
    if (!is_array($tokens)) {
        // fallback for older pipe-separated format
        $tokens = explode('|', trim($encoded, "|\r\n"));
    }
    $result = '';
    foreach ($tokens as $t) {
        $t = trim((string)$t);
        if ($t === '/')        $result .= ' ';
        elseif (isset($reverse[$t])) $result .= $reverse[$t];
        elseif ($t !== '')     $result .= '?';
    }
    return $result;
}
function pigpenMenu(): void {
    echo "\n--- PIGPEN CIPHER ---\n";
    $choice = promptInt("1. Encode  2. Decode\nChoice: ");
    if ($choice === 1) {
        $text = prompt("Enter text: ");
        echo "Encoded: " . pigpenEncode($text) . "\n";
        echo "(To decode, paste the full JSON output above)\n";
    } else {
        $enc = prompt("Enter encoded tokens (space-separated): ");
        echo "Decoded: " . pigpenDecode($enc) . "\n";
    }
}


function hillDet(array $m): int {
    return $m[0][0] * $m[1][1] - $m[0][1] * $m[1][0];
}
function hillInverse(array $m): array {
    $det    = ((hillDet($m) % 26) + 26) % 26;
    $detInv = modInv($det, 26);
    if ($detInv === -1) throw new Exception("Key matrix not invertible mod 26");
    return [
        [( $m[1][1] * $detInv % 26 + 26) % 26, (-$m[0][1] * $detInv % 26 + 26) % 26],
        [(-$m[1][0] * $detInv % 26 + 26) % 26, ( $m[0][0] * $detInv % 26 + 26) % 26],
    ];
}
function keyToMatrix(string $key): array {
    $k = lettersOnly($key);
    while (strlen($k) < 4) $k .= 'A';
    return [
        [ord($k[0]) - 65, ord($k[1]) - 65],
        [ord($k[2]) - 65, ord($k[3]) - 65],
    ];
}
function hillProcess(string $text, string $key, bool $encrypt): string {
    $mat = keyToMatrix($key);
    $det = ((hillDet($mat) % 26) + 26) % 26;
    if (modInv($det, 26) === -1)
        throw new Exception("Key matrix det has no inverse mod 26");
    $useMat = $encrypt ? $mat : hillInverse($mat);
    $t = lettersOnly($text);
    if (strlen($t) % 2 !== 0) $t .= 'X';
    $result = '';
    for ($i = 0; $i < strlen($t); $i += 2) {
        $v0 = ord($t[$i])     - 65;
        $v1 = ord($t[$i + 1]) - 65;
        $r0 = ($useMat[0][0] * $v0 + $useMat[0][1] * $v1) % 26;
        $r1 = ($useMat[1][0] * $v0 + $useMat[1][1] * $v1) % 26;
        $result .= chr(65 + $r0) . chr(65 + $r1);
    }
    return $result;
}
function hillMenu(): void {
    echo "\n--- HILL CIPHER (2x2) ---\n";
    $text   = prompt("Enter text: ");
    $key    = prompt("Enter 4-letter key (forms 2x2 matrix): ");
    $choice = promptInt("1. Encrypt  2. Decrypt\nChoice: ");
    try {
        $enc = $choice === 1;
        echo ($enc ? "Ciphertext: " : "Plaintext:  ");
        echo hillProcess($text, $key, $enc) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}


function isCoprime26(int $a): bool {
    $valid = [1,3,5,7,9,11,15,17,19,21,23,25];
    return in_array((($a % 26) + 26) % 26, $valid);
}
function affineProcess(string $text, int $a, int $b, bool $encrypt): string {
    if (!isCoprime26($a))
        throw new Exception("'a' must be coprime with 26 (valid: 1,3,5,7,9,11,15,17,19,21,23,25)");
    $aInv   = modInv((($a % 26) + 26) % 26, 26);
    $result = '';
    for ($i = 0; $i < strlen($text); $i++) {
        $c = strtoupper($text[$i]);
        if ($c >= 'A' && $c <= 'Z') {
            $x = ord($c) - 65;
            $y = $encrypt ? ($a * $x + $b) % 26
                          : ($aInv * (($x - $b + 260) % 26)) % 26;
            $result .= chr(65 + (($y % 26 + 26) % 26));
        } else {
            $result .= $text[$i];
        }
    }
    return $result;
}
function affineMenu(): void {
    echo "\n--- AFFINE CIPHER ---\n";
    $text   = prompt("Enter text: ");
    $a      = promptInt("Enter key a (must be coprime with 26): ");
    $b      = promptInt("Enter key b (0-25): ");
    $choice = promptInt("1. Encrypt  2. Decrypt\nChoice: ");
    try {
        $enc = $choice === 1;
        echo ($enc ? "Ciphertext: " : "Plaintext:  ");
        echo affineProcess($text, $a, $b, $enc) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}


function vigenereProcess(string $text, string $key, bool $encrypt): string {
    $k = preg_replace('/[^A-Z0-9]/', '', strtoupper($key));
    if (!$k) throw new Exception("Key cannot be empty");
    $k = preg_replace_callback('/[1-9]/', function($m){ return chr(64 + (int)$m[0]); }, $k);    
    $result = '';
    $ki = 0;
    for ($i = 0; $i < strlen($text); $i++) {
        $c = strtoupper($text[$i]);
        if ($c >= 'A' && $c <= 'Z') {
            $x     = ord($c) - 65;
            $shift = ord($k[$ki % strlen($k)]) - 65;
            $y     = $encrypt ? ($x + $shift) % 26
                              : (($x - $shift + 26) % 26);
            $result .= chr(65 + $y);
            $ki++;
        } else {
            $result .= $text[$i];
        }
    }
    return $result;
}
function vigenereMenu(): void {
    echo "\n--- VIGENERE CIPHER ---\n";
    $text   = prompt("Enter text: ");
    $key    = prompt("Enter keyword: ");
    $choice = promptInt("1. Encrypt  2. Decrypt\nChoice: ");
    $enc    = $choice === 1;
    echo ($enc ? "Ciphertext: " : "Plaintext:  ");
    echo vigenereProcess($text, $key, $enc) . "\n";
}

if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    do {
        echo "\n============================\n";
        echo "  CLASSICAL CIPHERS — Group 3\n";
        echo "============================\n";
        echo "1. Playfair Cipher\n";
        echo "2. Pigpen Cipher\n";
        echo "3. Hill Cipher (2x2)\n";
        echo "4. Affine Cipher\n";
        echo "5. Vigenere Cipher\n";
        echo "0. Exit\n";
        $choice = promptInt("Choice: ");

        switch ($choice) {
            case 1: playfairMenu();  break;
            case 2: pigpenMenu();    break;
            case 3: hillMenu();      break;
            case 4: affineMenu();    break;
            case 5: vigenereMenu();  break;
            case 0: echo "Goodbye!\n"; break;
            default: echo "Invalid choice.\n";
        }
    } while ($choice !== 0);
}
