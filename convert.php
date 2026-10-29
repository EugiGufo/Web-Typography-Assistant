<?php
/*
 * Web Typography Assistant
 * Copyright (C) 2026 Evgueni Sytchev
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/*
Warning!
This file will only work correctly if it remains in UTF-8 encoding without a BOM.
Please check your editor’s settings before making any changes.
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $text = $_POST["text"] ?? ""; // textarea
    $htmlFormat = $_POST["htmlFormat"] ?? "mnemonic"; // hidden radio button
    $quotesType = $_POST["quotesType"] ?? "double"; // hidden radio button
    $subQuotesType = $_POST["subQuotesType"] ?? "single"; // hidden radio button
    $dashType = $_POST["dashType"] ?? "ndash"; // hidden radio button - everything that follows consists of checkboxes
    $addBr = $_POST["addBr"] ?? "no";
    $addP = $_POST["addP"] ?? "no";
    $convertLinks = $_POST["convertLinks"] ?? "no";
	$convertBareDomains = $_POST["convertBareDomains"] ?? "no";
    $convertEmails = $_POST["convertEmails"] ?? "no";
    $convertPhones = $_POST["convertPhones"] ?? "no";
    $convertLists = $_POST["convertLists"] ?? "no";
	// specify how line breaks are handled
	$lineBreaks = "none"; // default value
	if (isset($_POST["addBr"]) && $_POST["addBr"] === "yes") {
	    $lineBreaks = "br";
	} elseif (isset($_POST["addP"]) && $_POST["addP"] === "yes") {
	    $lineBreaks = "p";
	}
$text = str_replace(["’", "`", "´"], "'", $text);

function convertDashesAndRules($text) {
    // a line consisting solely of at least 3 hyphens
    $text = preg_replace('/^\s*-{3,}\s*$/m', '<hr>', $text);
    // exactly 2 consecutive hyphens
    $text = preg_replace('/(?<!-)\-\-(?!-)/', ' - ', $text);
    return $text;
}

$text = convertDashesAndRules($text);

function normalizeMSWText($text) {
    $replacements = [
        // hyphens
        '–' => '-', '—' => '-', '-' => '-', '-' => '-',
        // quotation marks
        '“' => '"', '”' => '"', '„' => '"', '«' => '"', '»' => '"',
        '‘' => "'", '’' => "'", '‚' => "'",
        // special characters
        ' ' => ' ', ' ' => ' ', ' ' => ' ', ' ' => ' ',
        // various symbols
        '…' => '...', '©' => '(c)', '®' => '(r)', '™' => '(tm)',
        // invisible characters
		'\xE2\x80\x8B' => '',   // zero-width space (reallycopied)
		'\xEF\xBB\xBF' => '',   // BOM (really copied)
        // other common symbols
        '′' => "'", '″' => '"', '※' => '*', '⁄' => '/', '∕' => '/',
        '←' => '<-', '→' => '->', '↔' => '<->', '±' => '+/-',
        '×' => 'x', '÷' => '/', '§' => '§', '°' => '°', '‰' => '‰',
        '‽' => '!?', '﹟' => '#', '﹠' => '&', '﹡' => '*', '﹢' => '+', '﹣' => '-',
        // typographic fractions
        '⅛' => '1/8', '¼' => '1/4', '⅜' => '3/8', '½' => '1/2', '⅝' => '5/8', '¾' => '3/4', '⅞' => '7/8',
    ];

    return strtr($text, $replacements);
}
$text = normalizeMSWText($text);

function restoreElisions($text, $placeholders) {
    return strtr($text, $placeholders);
}

    // **1. Text enclosed between ‘<’ and ‘>’ (with no spaces either side)**
    $placeholders = [];
    $text = preg_replace_callback('/<(?!\s)([^>]+)(?<!\s)>/', function ($matches) use (&$placeholders) {
        $placeholder = "%%TAG" . count($placeholders) . "%%";
        $placeholders[$placeholder] = $matches[0];
        return $placeholder;
    }, $text);

    // **Mapping of quotation marks**
    $quotesMapMnemonic = [
        'double' => ['&ldquo;', '&rdquo;'], // English double
        'french' => ['&laquo;', '&raquo;'], // French
        'german' => ['&bdquo;', '&ldquo;'], // German
        'single' => ['&lsquo;', '&rsquo;'], // English single
        'code' => ['"', '"']                // coding style
    ];

    $quotesMapNumeric = [
        'double' => ['&#8220;', '&#8221;'], // English double
        'french' => ['&#171;', '&#187;'],   // French
        'german' => ['&#8222;', '&#8220;'], // German
        'single' => ['&#8216;', '&#8217;'], // English single
        'code' => ['"', '"']
    ];

    $subQuotesMapMnemonic = [
        'double' => ['&ldquo;', '&rdquo;'],
        'french' => ['&laquo;', '&raquo;'],
        'german' => ['&bdquo;', '&ldquo;'],
        'single' => ['&lsquo;', '&rsquo;']
    ];

    $subQuotesMapNumeric = [
        'double' => ['&#8222;', '&#8220;'],
        'french' => ['&#8249;', '&#8250;'],
        'german' => ['&#8218;', '&#8222;'],
        'single' => ['&#8216;', '&#8217;']
    ];

    $dashMapMnemonic = ['ndash' => '&ndash;', 'mdash' => '&mdash;'];
    $dashMapNumeric = ['ndash' => '&#8211;', 'mdash' => '&#8212;'];

    // If the user selects the numerical format, use the numerical tables
    if ($htmlFormat === "numeric") {
        $quotesMap = $quotesMapNumeric;
        $subQuotesMap = $subQuotesMapNumeric;
        $dashMap = $dashMapNumeric;
    } else {
        $quotesMap = $quotesMapMnemonic;
        $subQuotesMap = $subQuotesMapMnemonic;
        $dashMap = $dashMapMnemonic;
    }

    $openQuote = $quotesMap[$quotesType][0];
    $closeQuote = $quotesMap[$quotesType][1];
    $subOpenQuote = $subQuotesMap[$subQuotesType][0];
    $subCloseQuote = $subQuotesMap[$subQuotesType][1];
    $dash = $dashMap[$dashType];

    // **Protection of existing HTML code**
    $htmlEntitiesPattern = '/&[a-zA-Z0-9#]+;/';
    $htmlEntities = [];
    $text = preg_replace_callback($htmlEntitiesPattern, function ($matches) use (&$htmlEntities) {
        $placeholder = "%%ENTITY" . count($htmlEntities) . "%%";
        $htmlEntities[$placeholder] = $matches[0];
        return $placeholder;
    }, $text);

$quoteStack = [];
$convertedText = '';

$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
$len = count($chars);

for ($i = 0; $i < $len; $i++) {
    $char = $chars[$i];

    if ($char === '"') {
		$prevChar = $i > 0 ? $chars[$i - 1] : '';

        if ($i === 0 || preg_match('/[\s\(\[\{]/u', $prevChar)) {
            // opening
            if (empty($quoteStack)) {
                $convertedText .= $openQuote;
                $quoteStack[] = 'primary';
            } else {
                $convertedText .= $subOpenQuote;
                $quoteStack[] = 'secondary';
            }
        } else {
            // closing
            if (!empty($quoteStack) && end($quoteStack) === 'secondary') {
                $convertedText .= $subCloseQuote;
            } else {
                $convertedText .= $closeQuote;
            }

            if (!empty($quoteStack)) {
                array_pop($quoteStack);
            }
        }
    } else {
        // retains all other characters
        $convertedText .= $char;
    }
}
$text = $convertedText;

function normalizePSPPSExpressions($text) {
    // list of expressions to be normalised
    $expressions = [
        'P. P. S.' => 'P.P.S.', 'p. p. s.' => 'P.P.S.', 'P. S.' => 'P.S.', 'p. s.' => 'P.S.', 'p.s.' => 'P.S.', 'P.p.S.' => 'P.P.S.', 'P. P.S.' => 'P.P.S.',
        'З. З. Ы.' => 'З.З.Ы.', 'З. Ы.' => 'З.Ы.', 'з. ы.' => 'З.Ы.', 'з.ы.' => 'З.Ы.',
    ];

    // replacement in the text
    return str_replace(array_keys($expressions), array_values($expressions), $text);
}
//    $text = normalizePSPPSExpressions($text);

function fixITDITP($text) {
    // list of expressions to be corrected, along with their patterns and replacements
    $patternsitd = [
        '/\bи др\.\b/u'   => 'и&nbsp;др.',
        '/\bи т\.д\.\b/u' => 'и&nbsp;т.д.',
        '/\bи т\.п\.\b/u' => 'и&nbsp;т.п.',
        '/\bв т\.ч\.\b/u' => 'в&nbsp;т.ч.',
    ];

    // remove any spaces after the first full stop in each expression
    $text = preg_replace([
        '/и&nbsp;т\. д\./u',
        '/и&nbsp;т\. п\./u',
        '/в&nbsp;т\. ч\./u',
    ], [
        'и&nbsp;т.д.',
        'и&nbsp;т.п.',
        'в&nbsp;т.ч.',
    ], $text);

    // first substitution: a normal space becomes a non-separable space (&nbsp;)
    $text = preg_replace(array_keys($patternsitd), array_values($patternsitd), $text);

    return $text;
}
$text = fixITDITP($text);

function formatEraExpressions($text) {
    // expressions to be processed
    $expressions = [
        'н.э.' => 'н.э.',         // it must remain unchanged, but with no space after the first full stop
        'до н.э.' => 'до&nbsp;н.э.' // it must be linked with &nbsp;
    ];

    // regex to find the expressions and correct them
    $pattern = '/(?<!\S)(до\s)?н\.\sэ\.(?!\S)/u';

    // replace with a callback to handle both cases
    $text = preg_replace_callback($pattern, function ($matches) use ($expressions) {
        if (!empty($matches[1])) { // if there is "до "
            return $expressions['до н.э.'];
        } else {
            return $expressions['н.э.'];
        }
    }, $text);

    // let’s add &nbsp; between the expressions and the preceding word/numbers
    $text = preg_replace('/(\S)\s(н\.э\.|доnbsp;н\.э\.)/u', '$1&nbsp;$2', $text);

    return $text;
}
$text = formatEraExpressions($text);

function DashIntervalNumbers($text, $preference = 'mnemonic') {
    // symbols to use
    $replacement = ($preference === 'mnemonic') ? '&ndash;' : '&#8211;';
    $minus = ($preference === 'mnemonic') ? '&minus;' : '&#8722;';
    // definition of number types
    $decimal = '[0-9]+';
    $fraction = '[½⅓¼¾⅛⅜⅝⅞]';
    $roman = '\b[IVXLCDM]+\b|\b[ivxlcdm]+\b';

    $number = "(?:$decimal|$fraction|$roman)";

    // =========================================================
    // 1. MATHEMATICAL FORMULAS
    // =========================================================
    $mathPattern = '/\b(' . $number . ')-(' . $number . ')(?=\s*[=+\-*\/<>])/u';
    $text = preg_replace($mathPattern, '$1' . $minus . '$2', $text);

    // =========================================================
    // 2. NUMERICAL INTERVALS
    // =========================================================
    $intervalPattern = '/\b(' . $number . ')-(' . $number . ')\b/u';
    $text = preg_replace($intervalPattern, '$1' . $replacement . '$2', $text);

    return $text;
}

$text = DashIntervalNumbers($text);

    // **Handling hyphens with non-breaking spaces**
    $text = preg_replace([
        '/^- /m',         // a hyphen at the start of a line followed by a space
        '/(\s)-(\s|$)/m'  // a hyphen with a space before and after
    ], [
        $dash . '&nbsp;', // converts the hyphen and replaces the space with &nbsp;
        '$1&nbsp;' . $dash . '$2' // the same applies to hyphens within a sentence
    ], $text);

	// **Ellipsis protection**
	$elisionPlaceholders = [];
	
function addNBSPForYearsAndCenturies($text) {
    // list of expressions to be covered
    $yearTerms = ['г.', 'гг.', 'год', 'годы', 'года', 'году', 'годах', 'anno', 'anni'];
    $centuryTerms = ['в.', 'вв.', 'век', 'веке', 'веках', 'secolo', 'secoli'];
    // number patterns
    $integerPattern = '\d+';
    // roman numerals WITHOUT \b
    $romanPattern = '[IVXLCDM]+|[ivxlcdm]+';
    // dynamic regexes
    $yearTermsPattern = implode('|', array_map('preg_quote', $yearTerms));
    $centuryTermsPattern = implode('|', array_map('preg_quote', $centuryTerms));
    // number or range of numbers
$yearNumberPattern = '\d+(?:&ndash;\d+)?';

// roman numeral
$romanNumberPattern = '[IVXLCDM]+|[ivxlcdm]+';

// pattern by year
$yearPattern =
    '/(?<!\S)(' . $yearTermsPattern . ')\s+(' . $yearNumberPattern . ')(?=[\s.,;:!?]|$)'
    . '|(?<!\S)(' . $yearNumberPattern . ')\s+(' . $yearTermsPattern . ')(?=[\s.,;:!?]|$)/u';

// patterns through the centuries
$centuryPattern =
    '/(?<!\S)(' . $centuryTermsPattern . ')\s+(' . $romanNumberPattern . ')(?=[\s.,;:!?]|$)'
    . '|(?<!\S)(' . $romanNumberPattern . ')\s+(' . $centuryTermsPattern . ')(?=[\s.,;:!?]|$)/u';
    // substitutions
    $text = preg_replace($yearPattern, '$1$3&nbsp;$2$4', $text);
    $text = preg_replace($centuryPattern, '$1$3&nbsp;$2$4', $text);
    return $text;
}
$text = addNBSPForYearsAndCenturies($text);

function addNBSPForDates($text) {
    // months in various languages
    $months = [
        // Italian
        'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
        'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
        // English
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
        // Russian
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
        // French
        'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        // Spanish
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        // Portuguese
        'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        // German
        'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
    ];
    $monthsPattern = implode('|', array_map('preg_quote', $months));
    // =====================================================
    // 1. Day + month
    // =====================================================
    $text = preg_replace('/(\d{1,2}\.?)\s+(' . $monthsPattern . ')(?=[\s.,;:!?]|$)/ui', '$1&nbsp;$2', $text);
    // =====================================================
    // 2. Month + day
    // =====================================================
    $text = preg_replace('/(' . $monthsPattern . ')\s+(\d{1,2})(?=[\s.,;:!?]|$)/ui', '$1&nbsp;$2', $text);
    return $text;
}

$text = addNBSPForDates($text);

function addNBSPAfterArticles($text) {
    // list of articles without duplicates (in lower case)
    $articles = [
        // definite articles
        'il', 'lo', 'la', 'i', 'gli', 'le', 'l\'', 'l’', // Italian
        'the', // English
        'le', 'la', 'les', 'l\'', 'l’', // French
        'el', 'la', 'los', 'las', 'l\'', 'l’', // Spanish

        // indefinite articles
        'un', 'uno', 'una', // Italian
        'a', 'an', // English
        'un', 'une', 'des', // French
        'un', 'una', 'unos', 'unas' // Spanish
    ];
    // let's create a single dynamic regex
    $articlesPattern = implode('|', array_map('preg_quote', $articles));
    // regex: finds a word (case-insensitive) followed by a space and a letter or number
    $pattern = '/\b(' . $articlesPattern . ')\b\s(?=[a-zA-ZÀ-ÿ0-9])/ui';
    // replacement: space becomes &nbsp;
    $replacement = '$1&nbsp;';

    return preg_replace($pattern, $replacement, $text);
}
$text = addNBSPAfterArticles($text);

function correggiTakilibonibud($text) {
    // replaces the space before the target words with a hyphen
    $text = preg_replace('/(?<![\s[:punct:]])\s+(ка|де|кась|таки|либо|нибудь)\b/u', '-$1', $text);
    return $text;
}
$text = correggiTakilibonibud($text);

function addNBSPAfterConjunctions($text) {
    // conjunctions in the four languages
    $conjunctions = [
        // Italian
        'e', 'ed', 'ma', 'o', 'od', 'se', 'perché', 'che', 'ne', 'né', 'oppure', 'affinché', 'nonostante', 'dunque', 'quindi', 'poiché', 'benché',
        // English
        'and', 'or', 'but', 'so', 'yet', 'for', 'nor', 'if', 'because', 'although', 'since', 'unless', 'whereas',
        // French
        'et', 'ou', 'mais', 'donc', 'or', 'ni', 'car', 'si', 'parce que', 'que', 'puisque', 'bien que', 'néanmoins',
        // Russian
        'и', 'а', 'но', 'или', 'либо', 'что', 'потому что', 'если', 'хотя', 'также', 'поскольку', 'ведь', 'однако'
    ];

    // let's create a single dynamic regex
    $conjunctionsPattern = implode('|', array_map('preg_quote', $conjunctions));

    // regex: finds a conjunction followed by a space and a letter or digit
    $pattern = '/\b(' . $conjunctionsPattern . ')\b\s(?=[a-zA-ZА-Яа-яЁё0-9])/ui';

    // replacement: space becomes &nbsp;
    $replacement = '$1&nbsp;';

    return preg_replace($pattern, $replacement, $text);
    }
$text = addNBSPAfterConjunctions($text);

function addNBSPAfterPrepositions($text) {
    // short prepositions in the 4 languages
    $prepositions = [
        // Italian
        'a', 'ad', 'al', 'da', 'di', 'e', 'ed', 'in', 'o', 'od', 'u', 'su', 'con', 'per', 'tra', 'fra', 'non',
        // English
        'a', 'an', 'in', 'on', 'to', 'of', 'by', 'at', 'as', 'up', 'for', 'not',
        // French
        'à', 'de', 'du', 'en', 'au', 'et', 'ou', 'sur', 'par', 'pour', 'chez',
        // Russian
        'без', 'в', 'во', 'для', 'до', 'к', 'ко', 'с', 'со', 'у', 'о', 'об', 'от', 'из', 'за', 'на', 'над', 'по', 'при', 'под', 'не', 'ни'
    ];

    // let's create a dynamic regex
    $prepositionsPattern = implode('|', array_map('preg_quote', $prepositions));
    // regex: a preposition followed by a space and then a letter or number
	$pattern = '/\b(' . $prepositionsPattern . ')\b\s(?!(за|под)\b)(?=[a-zA-ZА-Яа-яЁё0-9])/ui';
    // replacement: space becomes &nbsp;
    $replacement = '$1&nbsp;';

    return preg_replace($pattern, $replacement, $text);
}
$text = addNBSPAfterPrepositions($text);

function addNBSPAfterAbbreviations($text) {
    $abbreviazioni_con_numero = [
        'pag.', 'pp.', 'p.',        // Italian
        'стр.', 'д.', 'корп.', 'кв.' // Russian
    ];
    $abbreviazioni_generiche = [
        'cap.', 'art.',             // Italian
        'см.', 'гл.', 'рис.', 'илл.', 'г.', 'ул.', 'пер.' // Russian
    ];
    // 1. Adds &nbsp; only if followed by a number
    $pattern1 = '/\b(' . implode('|', array_map('preg_quote', $abbreviazioni_con_numero)) . ')(\s?)(?=[0-9])/ui';
    $text = preg_replace_callback($pattern1, function ($matches) {
        return $matches[1] . '&nbsp;';
    }, $text);
    // 2. Adds &nbsp; if followed by any alphanumeric character
    $pattern2 = '/\b(' . implode('|', array_map('preg_quote', $abbreviazioni_generiche)) . ')(\s?)(?=[\p{L}\p{N}])/ui';
    $text = preg_replace_callback($pattern2, function ($matches) {
        return $matches[1] . '&nbsp;';
    }, $text);
    return $text;
}
$text = addNBSPAfterAbbreviations($text);

// normalises all types of apostrophes to the typographic form
function normalizeApostrophes($text) {
    return preg_replace("/['`´]/u", "’", $text);
}
$text = normalizeApostrophes($text);

// typical case scenarios: l ' estate → l’estate
function fixApostrophatedWords($text) {
    $allApostrophes = "[\'’´`]";
    return preg_replace('/\b(\p{L}+)\s*' . $allApostrophes . '\s*/u', '$1’', $text);
}
$text = fixApostrophatedWords($text);

// it handles ONLY genuine elisions (it joins words together)
function fixElisions($text) {
    $elisionWords = [
        // Italiano
        'l', 'un', 'quell', 'quest', 'dell', 'all', 'nell', 'sull',
        // Francese
        'l', 'd', 'c', 'j', 'm', 'n', 's', 't',
        'qu', 'jusqu', 'lorsqu', 'puisqu', 'quoiqu',
        // English (only in poetic or rare contexts)
        'o' // es: o’clock
    ];

    $pattern = '/\b(' . implode('|', $elisionWords) . ')’\s+(\p{L})/iu';

    return preg_replace($pattern, '$1’$2', $text);
}
$text = fixElisions($text);

// Handles word breaks (ensures there is a space after the apostrophe)
function fixTroncamenti($text) {
    return preg_replace('/\b(po|mo|to|so|fa)’(?=\p{L})/iu', '$1’ ', $text);
}
$text = fixTroncamenti($text);

$elisionPlaceholders = [];

// Converts apostrophes between letters into HTML entities
function convertApostrophesInWords($text, $htmlFormat = "numeric") {
    if ($htmlFormat === "numeric") {
        $apostrophe = '&#8217;';
    } else {
        $apostrophe = '&rsquo;';
    }

    return preg_replace_callback(
        '/(?<=\p{L})’(?=\p{L})/u',
        fn() => $apostrophe,
        $text
    );
}
	$text = convertApostrophesInWords($text, $htmlFormat);
	
	function addNBSPAfterSymbols($text, $htmlFormat = 'mnemonic') {
    // Symbols to manage
    $symbols = ['§', '№'];

    // Let's create a single dynamic regex
    $symbolsPattern = implode('|', array_map('preg_quote', $symbols));

    // Find the symbols followed by a number
    $pattern = '/(' . $symbolsPattern . ')\s*(?=\d)/u';

    // Inserts &nbsp; after the symbol
    $text = preg_replace($pattern, '$1&nbsp;', $text);

    // Converts the § symbol into an HTML entity
    if ($htmlFormat === 'numeric') {
        $text = str_replace('§', '&#167;', $text);
    } else {
        $text = str_replace('§', '&sect;', $text);
    }

    return $text;
}
	
$text = addNBSPAfterSymbols($text, $htmlFormat);

function convertFractions($text, $useNamedEntities = true) {
    $fractions = [
        '1/4' => ['named' => '&frac14;', 'numeric' => '&#188;'],
        '1/2' => ['named' => '&frac12;', 'numeric' => '&#189;'],
        '3/4' => ['named' => '&frac34;', 'numeric' => '&#190;'],
        '1/3' => ['named' => '&frac13;', 'numeric' => '&#8531;'],
        '2/3' => ['named' => '&frac23;', 'numeric' => '&#8532;'],
        '1/5' => ['named' => '&frac15;', 'numeric' => '&#8533;'],
        '2/5' => ['named' => '&frac25;', 'numeric' => '&#8534;'],
        '3/5' => ['named' => '&frac35;', 'numeric' => '&#8535;'],
        '4/5' => ['named' => '&frac45;', 'numeric' => '&#8536;'],
        '1/6' => ['named' => '&frac16;', 'numeric' => '&#8537;'],
        '5/6' => ['named' => '&frac56;', 'numeric' => '&#8538;'],
        '1/8' => ['named' => '&frac18;', 'numeric' => '&#8539;'],
        '3/8' => ['named' => '&frac38;', 'numeric' => '&#8540;'],
        '5/8' => ['named' => '&frac58;', 'numeric' => '&#8541;'],
        '7/8' => ['named' => '&frac78;', 'numeric' => '&#8542;'],
    ];

    foreach ($fractions as $fraction => $html) {
        $replacement = $useNamedEntities ? $html['named'] : $html['numeric'];
        $text = preg_replace('/\b' . preg_quote($fraction, '/') . '\b/', $replacement, $text);
    }

    return $text;
}

    $useNamedEntities = ($htmlFormat === "mnemonic");
    $text = convertFractions($text, $useNamedEntities);

function convertAuthorsprop($text, $preference = 'mnemonic') {
    $replacements = [
        '(TM)' => ['mnemonic' => '&trade;', 'numeric' => '&#8482;'],
        '(tm)' => ['mnemonic' => '&trade;', 'numeric' => '&#8482;'],
        '(c)'  => ['mnemonic' => '&copy;', 'numeric' => '&#169;'],
        '(C)'  => ['mnemonic' => '&copy;', 'numeric' => '&#169;'],
        '(r)'  => ['mnemonic' => '&reg;', 'numeric' => '&#174;'],
        '(R)'  => ['mnemonic' => '&reg;', 'numeric' => '&#174;'],
        '(p)'  => ['mnemonic' => '&#8471;', 'numeric' => '&#8471;'],
        '(P)'  => ['mnemonic' => '&#8471;', 'numeric' => '&#8471;'],

        // Cyrillic versions
        '(ТМ)' => ['mnemonic' => '&trade;', 'numeric' => '&#8482;'],
        '(тм)' => ['mnemonic' => '&trade;', 'numeric' => '&#8482;'],
        '(с)'  => ['mnemonic' => '&copy;', 'numeric' => '&#169;'],
        '(С)'  => ['mnemonic' => '&copy;', 'numeric' => '&#169;'],
        '(р)'  => ['mnemonic' => '&#8471;', 'numeric' => '&#8471;'],
        '(Р)'  => ['mnemonic' => '&#8471;', 'numeric' => '&#8471;'],
    ];

    $patterns = [];
    foreach ($replacements as $key => $value) {
        $patterns[$key] = '/(?<![\p{L}\p{N}])' . preg_quote($key, '/') . '(?![\p{L}\p{N}])/u';
    }

	    foreach ($patterns as $key => $pattern) {
        $replacement = $replacements[$key][$preference];
        $text = preg_replace($pattern, $replacement, $text);
    }

	    return $text;
}

$text = convertAuthorsprop($text);

function correggiNumeriValuta($text) {
    // Currency symbols and the percentage symbol
    $simboli_valuta = '€$£¥₽₩₹₡₤';
    $simbolo_percentuale = '%';

    // 1. Percentage: removes any spaces between the number and the % symbol
    $text = preg_replace('/(\d)\s+%/', '$1%', $text);

    // 2. Currency symbol after the number (e.g. 2500 $ → 2500&nbsp;$)
    $text = preg_replace('/(\d)\s*([' . preg_quote($simboli_valuta, '/') . '])/', '$1&nbsp;$2', $text);

    // 3. Currency before the number (e.g. $ 2500 → $&nbsp;2500)
    $text = preg_replace('/([' . preg_quote($simboli_valuta, '/') . '])\s*(\d)/', '$1&nbsp;$2', $text);

    return $text;
}

$text = correggiNumeriValuta($text);

function addNBSPToLegalEntities($text) {
    // Lists of common company abbreviations in various countries (excluding Chinese and Korean)
    $sigle_russe = ['ООО', 'ОАО', 'АО', 'ЗАО', 'ПАО', 'НАО', 'ИП'];
    $sigle_altre = [
        // Italian
        'S\.p\.A\.', 'S\.r\.l\.', 'S\.n\.c\.', 'S\.a\.s\.', 'S\.a\.p\.a\.', 'S\.c\.', 'S\.c\.a\.r\.l\.',
        'S\.P\.A\.', 'S\.R\.L\.', 'S\.N\.C\.', 'S\.A\.S\.', 'S\.A\.P\.A\.', 'S\.C\.', 'S\.C\.A\.R\.L\.',
        'SPA', 'SRL', 'SNC', 'SAS', 'SC', 'SCARL',
        // English
        'Ltd\.', 'PLC', 'plc', 'LLC', 'llc', 'Inc\.', 'Corp\.', 'Co\.',
        // German
        'GmbH', 'GMBH', 'Gmbh', 'AG', 'UG', 'e\.K\.', 'GmbH & Co\. KG',
        // French
        'S\.A\.', 'SARL', 'SAS', 'EURL',
        // Spanish and Portuguese
        'S\.L\.', 'S\.R\.L\.', 'Ltda\.',
        // Scandinavian
        'AB', 'AS', 'A\/S', 'Oy', 'Oyj', 'ApS',
        // Eastern Europe
        's\.r\.o\.', 'a\.s\.', 'z\.o\.o\.', 'k\.s\.', 'sp\.z\.o\.o\.',
        // Others
        'Kft\.', 'Rt\.', 'OAO', 'SA', 'NV', 'BV', 'EEIG'
    ];
    // Separate patterns
    $pattern_russe = implode('|', $sigle_russe);
    $pattern_altro = implode('|', $sigle_altre);
    // Let’s apply the substitutions line by line
    $righe = explode("\n", $text);
    foreach ($righe as &$riga) {
        // 1. Russian abbreviation followed by a name → attached to the following name
        $riga = preg_replace(
            "~\b($pattern_russe)\s+(\p{L}[\p{L}\p{M}\p{Pd}\.]{1,})\b~u",
            '$1&nbsp;$2',
            $riga
        );
        // 2. Other abbreviations → added to the previous name
        $riga = preg_replace(
			"~(\p{L}[\p{L}\p{M}\p{N}\p{Pd}\.'&]{1,})\s+($pattern_altro)(?=\s|[.,;:!?]|$)~u",
			'$1&nbsp;$2',
			$riga
		);
    }
    return implode("\n", $righe);
}
$text = addNBSPToLegalEntities($text);

// **Restoring elisions**
$text = restoreElisions($text, $elisionPlaceholders);
	
function correggiMoltiplicazione($text) {
    // Common units of measurement (add others if necessary)
    $unita_misura = ['kg', 'g', 'm', 'cm', 'mm', 'l', 'ml', 'km', 'h', 's', 'pezzi', 'lb', 'oz', 'ft', 'yd', 'gal', 'pt'];
    // Let’s create a regular expression to recognise units of measurement
    $unita_regex = implode('|', array_map('preg_quote', $unita_misura));
    // Case 1: Swapping two numbers, with or without a space around "x" or "х"
    $text = preg_replace('/(\d)\s*[xXхХ]\s*(\d)/u', '$1&nbsp;×&nbsp;$2', $text);
    // Case 2: Replacement after a unit of measurement and before a number
    $text = preg_replace('/(\d\s*(?:' . $unita_regex . '))\s*[xXхХ]\s*(\d)/u', '$1&nbsp;×&nbsp;$2', $text);
    $text = preg_replace('/(\d\s*(?:' . $unita_regex . '))\s*[xXхХ](\d)/u', '$1&nbsp;×&nbsp;$2', $text);
  	// Caso 3: Replaces x/X/х/Х with &times; if it is followed by a number and not preceded by a number
    $text = preg_replace('/(?<!\d)[xXхХ](?=\d)/u', '×', $text);

    return $text;
}
$text = correggiMoltiplicazione($text);

function bindUnitsToNumbers($text) {
    // List of units of measurement in English, Italian, French, Spanish and Russian
    $units = [
        // general
        'kg', 'g', 'gr', 'mg', 't', 'lb', 'oz', 
        // length
        'm', 'km', 'cm', 'mm', 'mi', 'in', 'ft', 'yd', 
        // area
        'm²', 'km²', 'cm²', 'mm²', 'ha', 'ac', 
        // volume
        'm³', 'cm³', 'mm³', 'l', 'ml', 'gal', 'pt', 'qt',
        // time
        's', 'sec', 'min', 'h', 'hr', 'day', 'd', 'wk', 'mo', 'yr', 'a',
        // energy
        'J', 'kJ', 'cal', 'kcal', 'W', 'kW', 'MW',
        // speed
        'm/s', 'km/h', 'mph',
        // pressure
        'Pa', 'kPa', 'bar', 'atm', 'psi',
        // units in russian
        'кг', 'г', 'т', 'м', 'км', 'см', 'мм', 'л', 'м²', 'м³', 'ч', 'мин', 'сек'
    ];

    // creating a dynamic regex with `preg_quote`
    $unitsPattern = implode('|', array_map(fn($unit) => preg_quote($unit, '/'), $units));

    // a regular expression to find a number followed by a unit of measurement
    $pattern = '/(\d+)\s+(' . $unitsPattern . ')(?!\w)/u';

    // replacement: the space between the number and the unit becomes &nbsp;
    $replacement = '$1&nbsp;$2';

    return preg_replace($pattern, $replacement, $text);
}
$text = bindUnitsToNumbers($text);

function convertToSubscript($text) {
    // Find "_numero" after a letter or a closing bracket
    $pattern = '/(?<=[\p{L}\)])_(\d+)/u';
    
    // Replace with HTML format <sub>
    $replacement = '<sub>$1</sub>';
    
    return preg_replace($pattern, $replacement, $text);
}

$text = convertToSubscript($text);

function correggiAdano($text) {
    // if the word is immediately preceded by a character that contains neither a space nor punctuation, it adds ", "
    $text = preg_replace('/(?<![\s[:punct:]])\s*\b(а|да|но)\b/u', ', $1', $text);

    // replaces the space after "а", "да" and "но" with &nbsp;
    $text = preg_replace('/\b(а|да|но)\b\s/u', '$1&nbsp;', $text);

    return $text;
}
$text = correggiAdano($text);

function correggiKoikoe($text) {
    // replaces the space after "кое" or "кой" with a single hyphen if followed by a letter
    $text = preg_replace('/\b(кое|кой)\s+([а-яё])/ui', '$1-$2', $text);

    return $text;
}
$text = correggiKoikoe($text);

function correggiIzzaizpod($text) {
    // replaces "из за" with "из-за" and "из под" with "из-под"
    $text = preg_replace('/\b(из)\s+(за)\b/ui', '$1-$2', $text);
    $text = preg_replace('/\b(из)\s+(под)\b/ui', '$1-$2', $text);

    return $text;
}
$text = correggiIzzaizpod($text);

function correggiGOSTTUPICF($text) {
    // expressions to correct
    $patterns = [
        '/\b(ГОСТ|ТУ|P\.I\.|PI|C\.F\.|CF)\s+(\d+)/ui' // expression followed by a space and a number
    ];
    $replacements = [
        '$1&nbsp;$2' // replaces the space with &nbsp;
    ];

    return preg_replace($patterns, $replacements, $text);
}
$text = correggiGOSTTUPICF($text);

    // **Conversion of special characters**
    // Mapping for mnemonic conversion
    $htmlEntitiesMapMnemonic = ['£'=>'&pound;','$'=>'&dollar;','₵'=>'&cent;','¢'=>'&cent;','€'=>'&euro;','¥'=>'&yen;','₽'=>'&#8381;','₩'=>'&#8361;','₹'=>'&#8377;','¤'=>'&curren;','’'=>'&rsquo;','™'=>'&trade;','©'=>'&copy;','®'=>'&reg;','℗'=>'&copysr;','°'=>'&deg;','¼'=>'&frac14;','½'=>'&frac12;','¾'=>'&frac34;','⅓'=>'&frac13;','⅔'=>'&frac23;','⅕'=>'&frac15;','⅖'=>'&frac25;','⅗'=>'&frac35;','⅘'=>'&frac45;','⅙'=>'&frac16;','⅚'=>'&frac56;','⅛'=>'&frac18;','⅜'=>'&frac38;','⅝'=>'&frac58;','⅞'=>'&frac78;','Á'=>'&Aacute;','á'=>'&aacute;','Ă'=>'&Abreve;','ă'=>'&abreve;','Â'=>'&Acirc;','â'=>'&acirc;','À'=>'&Agrave;','à'=>'&agrave;','Ā'=>'&Amacr;','ā'=>'&amacr;','Å'=>'&angst;','Ą'=>'&Aogon;','ą'=>'&aogon;','Å'=>'&Aring;','å'=>'&aring;','Ã'=>'&Atilde;','ã'=>'&atilde;','Ä'=>'&Auml;','ä'=>'&auml;','Č'=>'&Ccaron;','č'=>'&ccaron;','Ç'=>'&Ccedil;','ç'=>'&ccedil;','Ĉ'=>'&Ccirc;','ĉ'=>'&ccirc;','Ċ'=>'&Cdot;','ċ'=>'&cdot;','¢'=>'&cent;','Ć'=>'&Cacute;','ć'=>'&cacute;','Ď'=>'&Dcaron;','ď'=>'&dcaron;','Đ'=>'&Dstrok;','đ'=>'&dstrok;','É'=>'&Eacute;','é'=>'&eacute;','Ě'=>'&Ecaron;','ě'=>'&ecaron;','Ê'=>'&Ecirc;','ê'=>'&ecirc;','Ė'=>'&Edot;','ė'=>'&edot;','È'=>'&Egrave;','è'=>'&egrave;','Ē'=>'&Emacr;','ē'=>'&emacr;','Ŋ'=>'&ENG;','ŋ'=>'&eng;','Ę'=>'&Eogon;','ę'=>'&eogon;','ε'=>'&epsi;','Ε'=>'&Epsilon;','ε'=>'&epsilon;','ϵ'=>'&epsiv;','Η'=>'&Eta;','η'=>'&eta;','Ð'=>'&ETH;','ð'=>'&eth;','Ë'=>'&Euml;','ë'=>'&euml;','ǵ'=>'&gacute;','Ǵ'=>'&Gacute;','Γ'=>'&Gamma;','γ'=>'&gamma;','Ϝ'=>'&Gammad;','ϝ'=>'&gammad;','Ğ'=>'&Gbreve;','ğ'=>'&gbreve;','Ģ'=>'&Gcedil;','Ĝ'=>'&Gcirc;','ĝ'=>'&gcirc;','Ġ'=>'&Gdot;','ġ'=>'&gdot;','ℷ'=>'&gimel;','Ѓ'=>'&GJcy;','ѓ'=>'&gjcy;','ℏ'=>'&hbar;','Ĥ'=>'&Hcirc;','ĥ'=>'&hcirc;','𝔥'=>'&hfr;','Ħ'=>'&Hstrok;','ħ'=>'&hstrok;','Í'=>'&Iacute;','í'=>'&iacute;','Î'=>'&Icirc;','î'=>'&icirc;','İ'=>'&Idot;','𝔦'=>'&ifr;','Ì'=>'&Igrave;','ì'=>'&igrave;','ⅈ'=>'&ii;','Ī'=>'&Imacr;','ī'=>'&imacr;','Ƶ'=>'&imped;','ı'=>'&inodot;','Į'=>'&Iogon;','į'=>'&iogon;','Ĩ'=>'&Itilde;','ĩ'=>'&itilde;','Ï'=>'&Iuml;','ï'=>'&iuml;','Ĵ'=>'&Jcirc;','ĵ'=>'&jcirc;','𝔧'=>'&jfr;','ȷ'=>'&jmath;','Ķ'=>'&Kcedil;','ķ'=>'&kcedil;','Ќ'=>'&KJcy;','ќ'=>'&kjcy;','Ĺ'=>'&Lacute;','ĺ'=>'&lacute;','Ľ'=>'&Lcaron;','ľ'=>'&lcaron;','Ļ'=>'&Lcedil;','ļ'=>'&lcedil;','Ŀ'=>'&Lmidot;','ŀ'=>'&lmidot;','Ł'=>'&Lstrok;','ł'=>'&lstrok;','Ň'=>'&Ncaron;','ň'=>'&ncaron;','Ņ'=>'&Ncedil;','ņ'=>'&ncedil;','Ñ'=>'&Ntilde;','ñ'=>'&ntilde;','Ó'=>'&Oacute;','ó'=>'&oacute;','Ô'=>'&Ocirc;','ô'=>'&ocirc;','Ő'=>'&Odblac;','ő'=>'&odblac;','Ò'=>'&Ograve;','ò'=>'&ograve;','Ō'=>'&Omacr;','ō'=>'&omacr;','Ω'=>'&Omega;','ω'=>'&omega;','Ø'=>'&Oslash;','ø'=>'&oslash;','Õ'=>'&Otilde;','õ'=>'&otilde;','Ö'=>'&Ouml;','ö'=>'&ouml;','Ŕ'=>'&Racute;','ŕ'=>'&racute;','Ř'=>'&Rcaron;','ř'=>'&rcaron;','Ŗ'=>'&Rcedil;','ŗ'=>'&rcedil;','Ś'=>'&Sacute;','ś'=>'&sacute;','Š'=>'&Scaron;','š'=>'&scaron;','Ş'=>'&Scedil;','ş'=>'&scedil;','Ŝ'=>'&Scirc;','ŝ'=>'&scirc;','Ť'=>'&Tcaron;','ť'=>'&tcaron;','Ţ'=>'&Tcedil;','ţ'=>'&tcedil;','Ŧ'=>'&Tstrok;','ŧ'=>'&tstrok;','Ú'=>'&Uacute;','ú'=>'&uacute;','Ў'=>'&Ubrcy;','ў'=>'&ubrcy;','Ŭ'=>'&Ubreve;','ŭ'=>'&ubreve;','Û'=>'&Ucirc;','û'=>'&ucirc;','Ű'=>'&Udblac;','ű'=>'&udblac;','Ù'=>'&Ugrave;','ù'=>'&ugrave;','Ū'=>'&Umacr;','ū'=>'&umacr;','Ų'=>'&Uogon;','ų'=>'&uogon;','Ů'=>'&Uring;','ů'=>'&uring;','Ũ'=>'&Utilde;','ũ'=>'&utilde;','Ü'=>'&Uuml;','ü'=>'&uuml;','ϱ'=>'&varrho;','ς'=>'&varsigma;','Ŵ'=>'&Wcirc;','ŵ'=>'&wcirc;','Ý'=>'&Yacute;','ý'=>'&yacute;','Ŷ'=>'&Ycirc;','ŷ'=>'&ycirc;','Ÿ'=>'&Yuml;','ÿ'=>'&yuml;','Ź'=>'&Zacute;','ź'=>'&zacute;','Ž'=>'&Zcaron;','ž'=>'&zcaron;','Ż'=>'&Zdot;','ż'=>'&zdot;','Æ'=>'&AElig;','æ'=>'&aelig;','𝔄'=>'&Afr;','𝔞'=>'&afr;','ℵ'=>'&alefsym;','ℵ'=>'&aleph;','Α'=>'&Alpha;','α'=>'&alpha;','𝔸'=>'&Aopf;','𝕒'=>'&aopf;','𝒜'=>'&Ascr;','𝒶'=>'&ascr;','∳'=>'&awconint;','⨑'=>'&awint;','϶'=>'&backepsilon;','϶'=>'&bepsi;','ℬ'=>'&bernou;','ℬ'=>'&Bernoullis;','Β'=>'&Beta;','β'=>'&beta;','ℶ'=>'&beth;','≬'=>'&between;','𝔅'=>'&Bfr;','𝔟'=>'&bfr;','𝔹'=>'&Bopf;','𝕓'=>'&bopf;','ℬ'=>'&Bscr;','𝒷'=>'&bscr;','ⅅ'=>'&CapitalDifferentialD;','ℭ'=>'&Cayleys;','ℭ'=>'&Cfr;','𝔠'=>'&cfr;','Χ'=>'&Chi;','χ'=>'&chi;','ℂ'=>'&Copf;','𝕔'=>'&copf;','𝒞'=>'&Cscr;','𝒸'=>'&cscr;','∲'=>'&cwconint;','∱'=>'&cwint;','ℸ'=>'&daleth;','ⅅ'=>'&DD;','ⅆ'=>'&dd;','Δ'=>'&Delta;','δ'=>'&delta;','𝔇'=>'&Dfr;','𝔡'=>'&dfr;','ⅆ'=>'&DifferentialD;','ϝ'=>'&digamma;','Ђ'=>'&DJcy;','ђ'=>'&djcy;','𝔻'=>'&Dopf;','𝕕'=>'&dopf;','𝒟'=>'&Dscr;','𝒹'=>'&dscr;','Џ'=>'&DZcy;','џ'=>'&dzcy;','ⅇ'=>'&ee;','𝔈'=>'&Efr;','𝔢'=>'&efr;','ℓ'=>'&ell;','𝔼'=>'&Eopf;','𝕖'=>'&eopf;','ℰ'=>'&Escr;','ℯ'=>'&escr;','∃'=>'&exist;','∃'=>'&Exists;','ℰ'=>'&expectation;','ⅇ'=>'&ExponentialE;','ⅇ'=>'&exponentiale;','ﬃ'=>'&ffilig;','ﬀ'=>'&fflig;','ﬄ'=>'&ffllig;','𝔉'=>'&Ffr;','𝔣'=>'&ffr;','ﬁ'=>'&filig;','♭'=>'&flat;','ﬂ'=>'&fllig;','ƒ'=>'&fnof;','𝔽'=>'&Fopf;','𝕗'=>'&fopf;','ℱ'=>'&Fouriertrf;','⨍'=>'&fpartint;','ℱ'=>'&Fscr;','𝒻'=>'&fscr;','𝔊'=>'&Gfr;','𝔤'=>'&gfr;','𝔾'=>'&Gopf;','𝕘'=>'&gopf;','𝒢'=>'&Gscr;','ℊ'=>'&gscr;','ℋ'=>'&hamilt;','ℌ'=>'&Hfr;','ℋ'=>'&HilbertSpace;','ℍ'=>'&Hopf;','𝕙'=>'&hopf;','ℋ'=>'&Hscr;','𝒽'=>'&hscr;','ℏ'=>'&hslash;','ℑ'=>'&Ifr;','⨌'=>'&iiiint;','∭'=>'&iiint;','⧜'=>'&iinfin;','℩'=>'&iiota;','Ĳ'=>'&IJlig;','ĳ'=>'&ijlig;','ℑ'=>'&Im;','ℑ'=>'&image;','ⅈ'=>'&ImaginaryI;','ℐ'=>'&imagline;','ℑ'=>'&imagpart;','ı'=>'&imath;','∬'=>'&Int;','∫'=>'&int;','⊺'=>'&intcal;','ℤ'=>'&integers;','∫'=>'&Integral;','⊺'=>'&intercal;','𝕀'=>'&Iopf;','𝕚'=>'&iopf;','Ι'=>'&Iota;','ι'=>'&iota;','ℐ'=>'&Iscr;','𝒾'=>'&iscr;','𝔍'=>'&Jfr;','𝕁'=>'&Jopf;','𝕛'=>'&jopf;','𝒥'=>'&Jscr;','𝒿'=>'&jscr;','Κ'=>'&Kappa;','κ'=>'&kappa;','ϰ'=>'&kappav;','𝔎'=>'&Kfr;','𝔨'=>'&kfr;','ĸ'=>'&kgreen;','𝕂'=>'&Kopf;','𝕜'=>'&kopf;','𝒦'=>'&Kscr;','𝓀'=>'&kscr;','ℒ'=>'&lagran;','Λ'=>'&Lambda;','λ'=>'&lambda;','ℒ'=>'&Laplacetrf;','Љ'=>'&LJcy;','љ'=>'&ljcy;','ℒ'=>'&Lscr;','𝓁'=>'&lscr;','ℳ'=>'&Mellintrf;','𝔐'=>'&Mfr;','𝔪'=>'&mfr;','℧'=>'&mho;','µ'=>'&micro;','𝕄'=>'&Mopf;','𝕞'=>'&mopf;','ℳ'=>'&Mscr;','𝓂'=>'&mscr;','Μ'=>'&Mu;','μ'=>'&mu;','Ń'=>'&Nacute;','ń'=>'&nacute;','ŉ'=>'&napos;','ℕ'=>'&naturals;','𝔑'=>'&Nfr;','𝔫'=>'&nfr;','Њ'=>'&NJcy;','њ'=>'&njcy;','∤'=>'&nmid;','ℕ'=>'&Nopf;','𝕟'=>'&nopf;','Ν'=>'&Nu;','ν'=>'&nu;','Œ'=>'&OElig;','œ'=>'&oelig;','𝔒'=>'&Ofr;','𝔬'=>'&ofr;','Ω'=>'&ohm;','∮'=>'&oint;','𝕆'=>'&Oopf;','𝕠'=>'&oopf;','𝒪'=>'&Oscr;','ℴ'=>'&oscr;','𝔓'=>'&Pfr;','𝔭'=>'&pfr;','Φ'=>'&Phi;','φ'=>'&phi;','ϕ'=>'&phiv;','ℳ'=>'&phmmat;','π'=>'&pi;','ℏ'=>'&planck;','ℎ'=>'&planckh;','ℏ'=>'&plankv;','ℌ'=>'&Poincareplane;','⨕'=>'&pointint;','ℙ'=>'&Popf;','𝕡'=>'&popf;','ℙ'=>'&primes;','𝒫'=>'&Pscr;','𝓅'=>'&pscr;','Ψ'=>'&Psi;','ψ'=>'&psi;','𝔔'=>'&Qfr;','𝔮'=>'&qfr;','⨌'=>'&qint;','ℚ'=>'&Qopf;','𝕢'=>'&qopf;','𝒬'=>'&Qscr;','𝓆'=>'&qscr;','ℍ'=>'&quaternions;','⨖'=>'&quatint;','ℚ'=>'&rationals;','ℜ'=>'&Re;','ℜ'=>'&real;','ℛ'=>'&realine;','ℜ'=>'&realpart;','ℝ'=>'&reals;','ℜ'=>'&Rfr;','𝔯'=>'&rfr;','Ρ'=>'&Rho;','ρ'=>'&rho;','ϱ'=>'&rhov;','ℛ'=>'&Rscr;','𝓇'=>'&rscr;','℞'=>'&rx;','𝔖'=>'&Sfr;','𝔰'=>'&sfr;','Σ'=>'&Sigma;','σ'=>'&sigma;','ς'=>'&sigmaf;','ς'=>'&sigmav;','𝕊'=>'&Sopf;','𝕤'=>'&sopf;','𝒮'=>'&Sscr;','𝓈'=>'&sscr;','ϵ'=>'&straightepsilon;','ϕ'=>'&straightphi;','ß'=>'&szlig;','Τ'=>'&Tau;','τ'=>'&tau;','𝔗'=>'&Tfr;','𝔱'=>'&tfr;','Θ'=>'&Theta;','θ'=>'&theta;','ϑ'=>'&thetasym;','ϑ'=>'&thetav;','Þ'=>'&THORN;','þ'=>'&thorn;','∭'=>'&tint;','𝕋'=>'&Topf;','𝕥'=>'&topf;','𝒯'=>'&Tscr;','𝓉'=>'&tscr;','Ћ'=>'&TSHcy;','ћ'=>'&tshcy;','⥾'=>'&ufisht;','𝔘'=>'&Ufr;','𝔲'=>'&ufr;','ϒ'=>'&Upsi;','υ'=>'&upsi;','ϒ'=>'&upsih;','Υ'=>'&Upsilon;','υ'=>'&upsilon;','𝒰'=>'&Uscr;','𝓊'=>'&uscr;','ϰ'=>'&varkappa;','∅'=>'&varnothing;','ϕ'=>'&varphi;','ϖ'=>'&varpi;','∝'=>'&varpropto;','ϑ'=>'&vartheta;','𝔙'=>'&Vfr;','𝔳'=>'&vfr;','𝕍'=>'&Vopf;','𝕧'=>'&vopf;','𝒱'=>'&Vscr;','𝓋'=>'&vscr;','℘'=>'&weierp;','𝔚'=>'&Wfr;','𝔴'=>'&wfr;','𝕎'=>'&Wopf;','𝕨'=>'&wopf;','℘'=>'&wp;','≀'=>'&wr;','𝒲'=>'&Wscr;','𝓌'=>'&wscr;','𝔛'=>'&Xfr;','𝔵'=>'&xfr;','Ξ'=>'&Xi;','ξ'=>'&xi;','𝕏'=>'&Xopf;','𝕩'=>'&xopf;','𝒳'=>'&Xscr;','𝓍'=>'&xscr;','𝔜'=>'&Yfr;','𝔶'=>'&yfr;','𝕐'=>'&Yopf;','𝕪'=>'&yopf;','𝒴'=>'&Yscr;','𝓎'=>'&yscr;','ℨ'=>'&zeetrf;','Ζ'=>'&Zeta;','ζ'=>'&zeta;','ℨ'=>'&Zfr;','𝔷'=>'&zfr;','ℤ'=>'&Zopf;','𝕫'=>'&zopf;','𝒵'=>'&Zscr;','𝓏'=>'&zscr;','∾'=>'&ac;','∿'=>'&acd;','´'=>'&acute;','⨿'=>'&amalg;','⩓'=>'&And;','∧'=>'&and;','⩕'=>'&andand;','⩜'=>'&andd;','⩘'=>'&andslope;','⩚'=>'&andv;','∠'=>'&ang;','⦤'=>'&ange;','∠'=>'&angle;','∡'=>'&angmsd;','⦨'=>'&angmsdaa;','⦩'=>'&angmsdab;','⦪'=>'&angmsdac;','⦫'=>'&angmsdad;','⦬'=>'&angmsdae;','⦭'=>'&angmsdaf;','⦮'=>'&angmsdag;','⦯'=>'&angmsdah;','∟'=>'&angrt;','⊾'=>'&angrtvb;','⦝'=>'&angrtvbd;','∢'=>'&angsph;','⍼'=>'&angzarr;','≈'=>'&ap;','⩯'=>'&apacir;','⩰'=>'&apE;','≊'=>'&ape;','≋'=>'&apid;','≈'=>'&approx;','≊'=>'&approxeq;','≔'=>'&Assign;','≈'=>'&asymp;','≍'=>'&asympeq;','≌'=>'&backcong;','‵'=>'&backprime;','∽'=>'&backsim;','⋍'=>'&backsimeq;','⫧'=>'&Barv;','⊽'=>'&barvee;','⌆'=>'&Barwed;','⌅'=>'&barwed;','⌅'=>'&barwedge;','⎶'=>'&bbrktbrk;','≌'=>'&bcong;','∵'=>'&becaus;','∵'=>'&Because;','∵'=>'&because;','⦰'=>'&bemptyv;','⋂'=>'&bigcap;','◯'=>'&bigcirc;','⋃'=>'&bigcup;','⨀'=>'&bigodot;','⨁'=>'&bigoplus;','⨂'=>'&bigotimes;','⨆'=>'&bigsqcup;','★'=>'&bigstar;','▽'=>'&bigtriangledown;','△'=>'&bigtriangleup;','⨄'=>'&biguplus;','⤍'=>'&bkarow;','⧫'=>'&blacklozenge;','▪'=>'&blacksquare;','▴'=>'&blacktriangle;','▾'=>'&blacktriangledown;','◂'=>'&blacktriangleleft;','▸'=>'&blacktriangleright;','␣'=>'&blank;','▒'=>'&blk12;','░'=>'&blk14;','▓'=>'&blk34;','█'=>'&block;','=⃥'=>'&bne;','≡⃥'=>'&bnequiv;','⫭'=>'&bNot;','⌐'=>'&bnot;','⊥'=>'&bot;','⊥'=>'&bottom;','⋈'=>'&bowtie;','⧉'=>'&boxbox;','╗'=>'&boxDL;','╖'=>'&boxDl;','╕'=>'&boxdL;','┐'=>'&boxdl;','╔'=>'&boxDR;','╓'=>'&boxDr;','╒'=>'&boxdR;','┌'=>'&boxdr;','═'=>'&boxH;','─'=>'&boxh;','╦'=>'&boxHD;','╤'=>'&boxHd;','╥'=>'&boxhD;','┬'=>'&boxhd;','╩'=>'&boxHU;','╧'=>'&boxHu;','╨'=>'&boxhU;','┴'=>'&boxhu;','⊟'=>'&boxminus;','⊞'=>'&boxplus;','⊠'=>'&boxtimes;','╝'=>'&boxUL;','╜'=>'&boxUl;','╛'=>'&boxuL;','┘'=>'&boxul;','╚'=>'&boxUR;','╙'=>'&boxUr;','╘'=>'&boxuR;','└'=>'&boxur;','║'=>'&boxV;','│'=>'&boxv;','╬'=>'&boxVH;','╫'=>'&boxVh;','╪'=>'&boxvH;','┼'=>'&boxvh;','╣'=>'&boxVL;','╢'=>'&boxVl;','╡'=>'&boxvL;','┤'=>'&boxvl;','╠'=>'&boxVR;','╟'=>'&boxVr;','╞'=>'&boxvR;','├'=>'&boxvr;','‵'=>'&bprime;','˘'=>'&Breve;','˘'=>'&breve;','¦'=>'&brvbar;','⁏'=>'&bsemi;','∽'=>'&bsim;','⋍'=>'&bsime;','⧅'=>'&bsolb;','⟈'=>'&bsolhsub;','•'=>'&bull;','≎'=>'&bump;','⪮'=>'&bumpE;','≏'=>'&bumpe;','≎'=>'&Bumpeq;','≏'=>'&bumpeq;','⋒'=>'&Cap;','∩'=>'&cap;','⩄'=>'&capand;','⩉'=>'&capbrcup;','⩋'=>'&capcap;','⩇'=>'&capcup;','⩀'=>'&capdot;','∩︀'=>'&caps;','⁁'=>'&caret;','ˇ'=>'&caron;','⩍'=>'&ccaps;','∰'=>'&Cconint;','⩌'=>'&ccups;','⩐'=>'&ccupssm;','¸'=>'&cedil;','¸'=>'&Cedilla;','⦲'=>'&cemptyv;','✓'=>'&check;','○'=>'&cir;','ˆ'=>'&circ;','≗'=>'&circeq;','↺'=>'&circlearrowleft;','↻'=>'&circlearrowright;','⊛'=>'&circledast;','⊚'=>'&circledcirc;','⊝'=>'&circleddash;','⊙'=>'&CircleDot;','Ⓢ'=>'&circledS;','⊖'=>'&CircleMinus;','⊕'=>'&CirclePlus;','⊗'=>'&CircleTimes;','⧃'=>'&cirE;','≗'=>'&cire;','⨐'=>'&cirfnint;','⫯'=>'&cirmid;','⧂'=>'&cirscir;','∲'=>'&ClockwiseContourIntegral;','♣'=>'&clubs;','∷'=>'&Colon;','⩴'=>'&Colone;','≔'=>'&colone;','≔'=>'&coloneq;','∘'=>'&compfn;','∁'=>'&complement;','ℂ'=>'&complexes;','≅'=>'&cong;','⩭'=>'&congdot;','≡'=>'&Congruent;','∯'=>'&Conint;','∮'=>'&ContourIntegral;','∐'=>'&coprod;','∳'=>'&CounterClockwiseContourIntegral;','↵'=>'&crarr;','⨯'=>'&Cross;','✗'=>'&cross;','⫏'=>'&csub;','⫑'=>'&csube;','⫐'=>'&csup;','⫒'=>'&csupe;','⋯'=>'&ctdot;','⤸'=>'&cudarrl;','⤵'=>'&cudarrr;','⋞'=>'&cuepr;','⋟'=>'&cuesc;','↶'=>'&cularr;','⤽'=>'&cularrp;','⋓'=>'&Cup;','∪'=>'&cup;','⩈'=>'&cupbrcap;','≍'=>'&CupCap;','⩆'=>'&cupcap;','⩊'=>'&cupcup;','⊍'=>'&cupdot;','⩅'=>'&cupor;','∪︀'=>'&cups;','↷'=>'&curarr;','⤼'=>'&curarrm;','⋞'=>'&curlyeqprec;','⋟'=>'&curlyeqsucc;','↶'=>'&curvearrowleft;','↷'=>'&curvearrowright;','⋎'=>'&cuvee;','⋏'=>'&cuwed;','⌭'=>'&cylcty;','‡'=>'&Dagger;','†'=>'&dagger;','↡'=>'&Darr;','⇓'=>'&dArr;','↓'=>'&darr;','‐'=>'&dash;','⫤'=>'&Dashv;','⊣'=>'&dashv;','⤏'=>'&dbkarow;','˝'=>'&dblac;','‡'=>'&ddagger;','⇊'=>'&ddarr;','⤑'=>'&DDotrahd;','⩷'=>'&ddotseq;','∇'=>'&Del;','⦱'=>'&demptyv;','⥿'=>'&dfisht;','⥥'=>'&dHar;','⇃'=>'&dharl;','⇂'=>'&dharr;','´'=>'&DiacriticalAcute;','˙'=>'&DiacriticalDot;','`'=>'&DiacriticalGrave;','˜'=>'&DiacriticalTilde;','⋄'=>'&diam;','⋄'=>'&Diamond;','⋄'=>'&diamond;','♦'=>'&diams;','¨'=>'&die;','⋲'=>'&disin;','÷'=>'&div;','÷'=>'&divide;','⋇'=>'&divonx;','⌞'=>'&dlcorn;','⌍'=>'&dlcrop;','¨'=>'&Dot;','˙'=>'&dot;','◌⃜'=>'&DotDot;','≐'=>'&doteq;','≑'=>'&doteqdot;','≐'=>'&DotEqual;','∸'=>'&dotminus;','∔'=>'&dotplus;','⊡'=>'&dotsquare;','⌆'=>'&doublebarwedge;','∯'=>'&DoubleContourIntegral;','¨'=>'&DoubleDot;','⇓'=>'&DoubleDownArrow;','⇐'=>'&DoubleLeftArrow;','⇔'=>'&DoubleLeftRightArrow;','⫤'=>'&DoubleLeftTee;','⟸'=>'&DoubleLongLeftArrow;','⟺'=>'&DoubleLongLeftRightArrow;','⟹'=>'&DoubleLongRightArrow;','⇒'=>'&DoubleRightArrow;','⊨'=>'&DoubleRightTee;','⇑'=>'&DoubleUpArrow;','⇕'=>'&DoubleUpDownArrow;','∥'=>'&DoubleVerticalBar;','↓'=>'&DownArrow;','⇓'=>'&Downarrow;','↓'=>'&downarrow;','⤓'=>'&DownArrowBar;','⇵'=>'&DownArrowUpArrow;','◌̑'=>'&DownBreve;','⇊'=>'&downdownarrows;','⇃'=>'&downharpoonleft;','⇂'=>'&downharpoonright;','⥐'=>'&DownLeftRightVector;','⥞'=>'&DownLeftTeeVector;','↽'=>'&DownLeftVector;','⥖'=>'&DownLeftVectorBar;','⥟'=>'&DownRightTeeVector;','⇁'=>'&DownRightVector;','⥗'=>'&DownRightVectorBar;','⊤'=>'&DownTee;','↧'=>'&DownTeeArrow;','⤐'=>'&drbkarow;','⌟'=>'&drcorn;','⌌'=>'&drcrop;','⧶'=>'&dsol;','⋱'=>'&dtdot;','▿'=>'&dtri;','▾'=>'&dtrif;','⇵'=>'&duarr;','⥯'=>'&duhar;','⦦'=>'&dwangle;','⟿'=>'&dzigrarr;','⩮'=>'&easter;','≖'=>'&ecir;','≕'=>'&ecolon;','⩷'=>'&eDDot;','≑'=>'&eDot;','≒'=>'&efDot;','⪚'=>'&eg;','⪖'=>'&egs;','⪘'=>'&egsdot;','⪙'=>'&el;','∈'=>'&Element;','⏧'=>'&elinters;','⪕'=>'&els;','⪗'=>'&elsdot;','∅'=>'&empty;','◻'=>'&EmptySmallSquare;','▫'=>'&EmptyVerySmallSquare;','⋕'=>'&epar;','⧣'=>'&eparsl;','⩱'=>'&eplus;','≖'=>'&eqcirc;','≕'=>'&eqcolon;','≂'=>'&eqsim;','⪖'=>'&eqslantgtr;','⪕'=>'&eqslantless;','⩵'=>'&Equal;','='=>'&equals;','≂'=>'&EqualTilde;','≟'=>'&equest;','⇌'=>'&Equilibrium;','≡'=>'&equiv;','⩸'=>'&equivDD;','⧥'=>'&eqvparsl;','⥱'=>'&erarr;','≓'=>'&erDot;','≐'=>'&esdot;','⩳'=>'&Esim;','≂'=>'&esim;','≒'=>'&fallingdotseq;','♀'=>'&female;','◼'=>'&FilledSmallSquare;','▪'=>'&FilledVerySmallSquare;','▱'=>'&fltns;','∀'=>'&forall;','⋔'=>'&fork;','⫙'=>'&forkv;','⁄'=>'&frasl;','⌢'=>'&frown;','⪆'=>'&gap;','≧'=>'&gE;','≥'=>'&ge;','⪌'=>'&gEl;','⋛'=>'&gel;','≥'=>'&geq;','≧'=>'&geqq;','⩾'=>'&ges;','⪩'=>'&gescc;','⪀'=>'&gesdot;','⪂'=>'&gesdoto;','⪄'=>'&gesdotol;','⋛︀'=>'&gesl;','⪔'=>'&gesles;','⋙'=>'&Gg;','≫'=>'&gg;','⋙'=>'&ggg;','≷'=>'&gl;','⪥'=>'&gla;','⪒'=>'&glE;','⪤'=>'&glj;','⪊'=>'&gnap;','≩'=>'&gnE;','⪈'=>'&gne;','⋧'=>'&gnsim;','≥'=>'&GreaterEqual;','⋛'=>'&GreaterEqualLess;','≧'=>'&GreaterFullEqual;','⪢'=>'&GreaterGreater;','≷'=>'&GreaterLess;','⩾'=>'&GreaterSlantEqual;','≳'=>'&gsim;','⪎'=>'&gsime;','⪐'=>'&gsiml;','≫'=>'&Gt;','>'=>'&gt;','⪧'=>'&gtcc;','⩺'=>'&gtcir;','⋗'=>'&gtdot;','⦕'=>'&gtlPar;','⩼'=>'&gtquest;','⪆'=>'&gtrapprox;','⥸'=>'&gtrarr;','⋗'=>'&gtrdot;','⋛'=>'&gtreqless;','⪌'=>'&gtreqqless;','≷'=>'&gtrless;','≳'=>'&gtrsim;','≩︀'=>'&gvertneqq;','≩︀'=>'&gvnE;','ˇ'=>'&Hacek;',' '=>'&hairsp;','⇔'=>'&hArr;','↔'=>'&harr;','⥈'=>'&harrcir;','↭'=>'&harrw;','♥'=>'&hearts;','⊹'=>'&hercon;','⤥'=>'&hksearow;','⤦'=>'&hkswarow;','⇿'=>'&hoarr;','∻'=>'&homtht;','↩'=>'&hookleftarrow;','↪'=>'&hookrightarrow;','≎'=>'&HumpDownHump;','≏'=>'&HumpEqual;','⁃'=>'&hybull;','‐'=>'&hyphen;','¡'=>'&iexcl;','⇔'=>'&iff;','⊷'=>'&imof;','⇒'=>'&Implies;','∈'=>'&in;','℅'=>'&incare;','⧝'=>'&infintie;','⋂'=>'&Intersection;','⨗'=>'&intlarhk;','⨼'=>'&intprod;','⨼'=>'&iprod;','¿'=>'&iquest;','∈'=>'&isin;','⋵'=>'&isindot;','⋹'=>'&isinE;','⋴'=>'&isins;','⋳'=>'&isinsv;','∈'=>'&isinv;','⇚'=>'&lAarr;','⦴'=>'&laemptyv;','⟪'=>'&Lang;','⟨'=>'&lang;','⦑'=>'&langd;','⪅'=>'&lap;','↞'=>'&Larr;','⇐'=>'&lArr;','←'=>'&larr;','⇤'=>'&larrb;','⤟'=>'&larrbfs;','⤝'=>'&larrfs;','↩'=>'&larrhk;','↫'=>'&larrlp;','⤹'=>'&larrpl;','⥳'=>'&larrsim;','↢'=>'&larrtl;','⪫'=>'&lat;','⤛'=>'&lAtail;','⤙'=>'&latail;','⪭'=>'&late;','⪭︀'=>'&lates;','⤎'=>'&lBarr;','⤌'=>'&lbarr;','❲'=>'&lbbrk;','⌈'=>'&lceil;','⤶'=>'&ldca;','⥧'=>'&ldrdhar;','⥋'=>'&ldrushar;','↲'=>'&ldsh;','≦'=>'&lE;','≤'=>'&le;','⟨'=>'&LeftAngleBracket;','⇐'=>'&Leftarrow;','←'=>'&leftarrow;','⇤'=>'&LeftArrowBar;','⇆'=>'&LeftArrowRightArrow;','↢'=>'&leftarrowtail;','⌈'=>'&LeftCeiling;','⟦'=>'&LeftDoubleBracket;','⥡'=>'&LeftDownTeeVector;','⇃'=>'&LeftDownVector;','⥙'=>'&LeftDownVectorBar;','⌊'=>'&LeftFloor;','↽'=>'&leftharpoondown;','↼'=>'&leftharpoonup;','⇇'=>'&leftleftarrows;','⇔'=>'&Leftrightarrow;','↔'=>'&leftrightarrow;','⇆'=>'&leftrightarrows;','⇋'=>'&leftrightharpoons;','↭'=>'&leftrightsquigarrow;','⥎'=>'&LeftRightVector;','⊣'=>'&LeftTee;','↤'=>'&LeftTeeArrow;','⥚'=>'&LeftTeeVector;','⋋'=>'&leftthreetimes;','⊲'=>'&LeftTriangle;','⧏'=>'&LeftTriangleBar;','⊴'=>'&LeftTriangleEqual;','⥑'=>'&LeftUpDownVector;','⥠'=>'&LeftUpTeeVector;','↿'=>'&LeftUpVector;','⥘'=>'&LeftUpVectorBar;','↼'=>'&LeftVector;','⥒'=>'&LeftVectorBar;','⪋'=>'&lEg;','⋚'=>'&leg;','≤'=>'&leq;','≦'=>'&leqq;','⩽'=>'&les;','⪨'=>'&lescc;','⩿'=>'&lesdot;','⪁'=>'&lesdoto;','⪃'=>'&lesdotor;','⋚︀'=>'&lesg;','⪓'=>'&lesges;','⪅'=>'&lessapprox;','⋖'=>'&lessdot;','⋚'=>'&lesseqgtr;','⪋'=>'&lesseqqgtr;','⋚'=>'&LessEqualGreater;','≦'=>'&LessFullEqual;','≶'=>'&LessGreater;','≶'=>'&lessgtr;','⪡'=>'&LessLess;','≲'=>'&lesssim;','⩽'=>'&LessSlantEqual;','≲'=>'&LessTilde;','⥼'=>'&lfisht;','⌊'=>'&lfloor;','𝔏'=>'&Lfr;','𝔩'=>'&lfr;','≶'=>'&lg;','⪑'=>'&lgE;','⥢'=>'&lHar;','↽'=>'&lhard;','↼'=>'&lharu;','⥪'=>'&lharul;','▄'=>'&lhblk;','⋘'=>'&Ll;','≪'=>'&ll;','⇇'=>'&llarr;','⌞'=>'&llcorner;','⇚'=>'&Lleftarrow;','⥫'=>'&llhard;','◺'=>'&lltri;','⎰'=>'&lmoust;','⪉'=>'&lnapprox;','≨'=>'&lnE;','⪇'=>'&lneq;','≨'=>'&lneqq;','⋦'=>'&lnsim;','⟬'=>'&loang;','⇽'=>'&loarr;','⟦'=>'&lobrk;','⟸'=>'&Longleftarrow;','⟵'=>'&longleftarrow;','⟺'=>'&Longleftrightarrow;','⟷'=>'&longleftrightarrow;','⟼'=>'&longmapsto;','⟹'=>'&Longrightarrow;','⟶'=>'&longrightarrow;','↫'=>'&looparrowleft;','↬'=>'&looparrowright;','⦅'=>'&lopar;','𝕃'=>'&Lopf;','𝕝'=>'&lopf;','⨭'=>'&loplus;','⨴'=>'&lotimes;','↙'=>'&LowerLeftArrow;','↘'=>'&LowerRightArrow;','◊'=>'&lozenge;','⧫'=>'&lozf;','⦓'=>'&lparlt;','⇆'=>'&lrarr;','⌟'=>'&lrcorner;','⇋'=>'&lrhar;','⥭'=>'&lrhard;','‎'=>'&lrm;','⊿'=>'&lrtri;','↰'=>'&lsh;','≲'=>'&lsim;','⪍'=>'&lsime;','⪏'=>'&lsimg;','≪'=>'&Lt;','⪦'=>'&ltcc;','⩹'=>'&ltcir;','⋖'=>'&ltdot;','⋋'=>'&lthree;','⋉'=>'&ltimes;','⥶'=>'&ltlarr;','⩻'=>'&ltquest;','◃'=>'&ltri;','⊴'=>'&ltrie;','◂'=>'&ltrif;','⦖'=>'&ltrPar;','⥊'=>'&lurdshar;','⥦'=>'&luruhar;','¯'=>'&macr;','♂'=>'&male;','✠'=>'&maltese;','⤅'=>'&Map;','↦'=>'&map;','↦'=>'&mapsto;','↧'=>'&mapstodown;','↤'=>'&mapstoleft;','↥'=>'&mapstoup;','▮'=>'&marker;','⨩'=>'&mcomma;','∺'=>'&mDDot;','∡'=>'&measuredangle;',' '=>'&MediumSpace;','∣'=>'&mid;','*'=>'&midast;','⫰'=>'&midcir;','·'=>'&middot;','−'=>'&minus;','⊟'=>'&minusb;','∸'=>'&minusd;','⨪'=>'&minusdu;','⫛'=>'&mlcp;','∓'=>'&mnplus;','⊧'=>'&models;','∾'=>'&mstpos;','⊸'=>'&multimap;','∇'=>'&nabla;','≉'=>'&nap;','≉'=>'&napprox;','♮'=>'&natur;','♮'=>'&natural;','⩃'=>'&ncap;','≇'=>'&ncong;','⩂'=>'&ncup;','≠'=>'&ne;','⤤'=>'&nearhk;','⇗'=>'&neArr;','↗'=>'&nearr;','↗'=>'&nearrow;','≢'=>'&nequiv;','⤨'=>'&nesear;','≫'=>'&NestedGreaterGreater;','≪'=>'&NestedLessLess;','␊'=>'&NewLine;','∄'=>'&nexist;','∄'=>'&nexists;','≱'=>'&nge;','≱'=>'&ngeq;','≵'=>'&ngsim;','≯'=>'&ngt;','≯'=>'&ngtr;','⇎'=>'&nhArr;','↮'=>'&nharr;','⫲'=>'&nhpar;','⋼'=>'&nis;','⋺'=>'&nisd;','∋'=>'&niv;','⇍'=>'&nlArr;','↚'=>'&nlarr;','‥'=>'&nldr;','≰'=>'&nle;','⇍'=>'&nLeftarrow;','↚'=>'&nleftarrow;','⇎'=>'&nLeftrightarrow;','↮'=>'&nleftrightarrow;','≰'=>'&nleq;','≮'=>'&nless;','≴'=>'&nlsim;','≮'=>'&nlt;','⋪'=>'&nltri;','⋬'=>'&nltrie;','⫬'=>'&Not;','¬'=>'&not;','≢'=>'&NotCongruent;','≭'=>'&NotCupCap;','∦'=>'&NotDoubleVerticalBar;','∉'=>'&NotElement;','≠'=>'&NotEqual;','∄'=>'&NotExists;','≯'=>'&NotGreater;','≱'=>'&NotGreaterEqual;','≹'=>'&NotGreaterLess;','≵'=>'&NotGreaterTilde;','∉'=>'&notinva;','⋷'=>'&notinvb;','⋶'=>'&notinvc;','⋪'=>'&NotLeftTriangle;','⋬'=>'&NotLeftTriangleEqual;','≮'=>'&NotLess;','≰'=>'&NotLessEqual;','≸'=>'&NotLessGreater;','≴'=>'&NotLessTilde;','∌'=>'&notni;','∌'=>'&notniva;','⋾'=>'&notnivb;','⋽'=>'&notnivc;','⊀'=>'&NotPrecedes;','⋠'=>'&NotPrecedesSlantEqual;','∌'=>'&NotReverseElement;','⋫'=>'&NotRightTriangle;','⋭'=>'&NotRightTriangleEqual;','⋢'=>'&NotSquareSubsetEqual;','⋣'=>'&NotSquareSupersetEqual;','⊈'=>'&NotSubsetEqual;','⊁'=>'&NotSucceeds;','⋡'=>'&NotSucceedsSlantEqual;','⊉'=>'&NotSupersetEqual;','≁'=>'&NotTilde;','≄'=>'&NotTildeEqual;','≇'=>'&NotTildeFullEqual;','≉'=>'&NotTildeTilde;','∤'=>'&NotVerticalBar;','∦'=>'&npar;','∦'=>'&nparallel;','⨔'=>'&npolint;','⊀'=>'&npr;','⋠'=>'&nprcue;','⊀'=>'&nprec;','⇏'=>'&nrArr;','⇏'=>'&nRightarrow;','↛'=>'&nrightarrow;','⋫'=>'&nrtri;','⋭'=>'&nrtrie;','⋡'=>'&nsccue;','𝒩'=>'&Nscr;','𝓃'=>'&nscr;','∤'=>'&nshortmid;','∦'=>'&nshortparallel;','≁'=>'&nsim;','≄'=>'&nsimeq;','∤'=>'&nsmid;','⋢'=>'&nsqsube;','⋣'=>'&nsqsupe;','⊄'=>'&nsub;','⊈'=>'&nsubseteq;','⊁'=>'&nsucc;','⊉'=>'&nsupe;','≹'=>'&ntgl;','≸'=>'&ntlg;','⋪'=>'&ntriangleleft;','⋬'=>'&ntrianglelefteq;','⋫'=>'&ntriangleright;','⋭'=>'&ntrianglerighteq;','⊯'=>'&nVDash;','⊮'=>'&nVdash;','⊭'=>'&nvDash;','⊬'=>'&nvdash;','⤄'=>'&nvHarr;','⧞'=>'&nvinfin;','⤂'=>'&nvlArr;','⤃'=>'&nvrArr;','⤣'=>'&nwarhk;','⇖'=>'&nwArr;','↖'=>'&nwarr;','↖'=>'&nwarrow;','⤧'=>'&nwnear;','⊛'=>'&oast;','⊚'=>'&ocir;','⊝'=>'&odash;','⨸'=>'&odiv;','⊙'=>'&odot;','⦼'=>'&odsold;','⦿'=>'&ofcir;','˛'=>'&ogon;','⧁'=>'&ogt;','⦵'=>'&ohbar;','↺'=>'&olarr;','⦾'=>'&olcir;','⦻'=>'&olcross;','‾'=>'&oline;','⧀'=>'&olt;','⦶'=>'&omid;','⊖'=>'&ominus;','⦷'=>'&opar;','⦹'=>'&operp;','⊕'=>'&oplus;','⩔'=>'&Or;','∨'=>'&or;','↻'=>'&orarr;','⩝'=>'&ord;','⊶'=>'&origof;','⩖'=>'&oror;','⩗'=>'&orslope;','⩛'=>'&orv;','⊘'=>'&osol;','⨷'=>'&Otimes;','⊗'=>'&otimes;','⨶'=>'&otimesas;','⌽'=>'&ovbar;','‾'=>'&OverBar;','⏞'=>'&OverBrace;','⎴'=>'&OverBracket;','⏜'=>'&OverParenthesis;','¶'=>'&para;','∥'=>'&parallel;','⫳'=>'&parsim;','⫽'=>'&parsl;','∂'=>'&PartialD;','⊥'=>'&perp;','☎'=>'&phone;','⋔'=>'&pitchfork;','ϖ'=>'&piv;','⨣'=>'&plusacir;','⊞'=>'&plusb;','⨢'=>'&pluscir;','∔'=>'&plusdo;','⨥'=>'&plusdu;','⩲'=>'&pluse;','±'=>'&PlusMinus;','±'=>'&plusmn;','⨦'=>'&plussim;','⨧'=>'&plustwo;','⪻'=>'&Pr;','≺'=>'&pr;','⪷'=>'&prap;','⪳'=>'&prE;','⪯'=>'&pre;','≺'=>'&prec;','⪷'=>'&precapprox;','≼'=>'&preccurlyeq;','≺'=>'&Precedes;','⪯'=>'&PrecedesEqual;','≾'=>'&PrecedesTilde;','⪯'=>'&preceq;','⪹'=>'&precnapprox;','⪵'=>'&precneqq;','⋨'=>'&precnsim;','≾'=>'&precsim;','″'=>'&Prime;','′'=>'&prime;','⪹'=>'&prnap;','⪵'=>'&prnE;','⋨'=>'&prnsim;','⌒'=>'&profline;','⌓'=>'&profsurf;','∝'=>'&prop;','∷'=>'&Proportion;','∝'=>'&Proportional;','∝'=>'&propto;','≾'=>'&prsim;','⊰'=>'&prurel;','⁗'=>'&qprime;','≟'=>'&questeq;','⇛'=>'&rAarr;','∽̱'=>'&race;','√'=>'&radic;','⦳'=>'&raemptyv;','⟫'=>'&Rang;','⟩'=>'&rang;','⦒'=>'&rangd;','⦥'=>'&range;','⟩'=>'&rangle;','↠'=>'&Rarr;','⇒'=>'&rArr;','→'=>'&rarr;','⥵'=>'&rarrap;','⇥'=>'&rarrb;','⤠'=>'&rarrbfs;','⤳'=>'&rarrc;','⤞'=>'&rarrfs;','↪'=>'&rarrhk;','↬'=>'&rarrlp;','⥅'=>'&rarrpl;','⥴'=>'&rarrsim;','⤖'=>'&Rarrtl;','↣'=>'&rarrtl;','↝'=>'&rarrw;','⤜'=>'&rAtail;','⤚'=>'&ratail;','⤐'=>'&RBarr;','⤏'=>'&rBarr;','⤍'=>'&rbarr;','⤷'=>'&rdca;','⥩'=>'&rdldhar;','↳'=>'&rdsh;','∋'=>'&ReverseElement;','⇋'=>'&ReverseEquilibrium;','⥯'=>'&ReverseUpEquilibrium;','⥽'=>'&rfisht;','⌋'=>'&rfloor;','⥤'=>'&rHar;','⇁'=>'&rhard;','⇀'=>'&rharu;','⥬'=>'&rharul;','⟩'=>'&RightAngleBracket;','→'=>'&RightArrow;','⇒'=>'&Rightarrow;','→'=>'&rightarrow;','⇥'=>'&RightArrowBar;','⇄'=>'&RightArrowLeftArrow;','↣'=>'&rightarrowtail;','⌉'=>'&RightCeiling;','⟧'=>'&RightDoubleBracket;','⥝'=>'&RightDownTeeVector;','⇂'=>'&RightDownVector;','⥕'=>'&RightDownVectorBar;','⌋'=>'&RightFloor;','⇁'=>'&rightharpoondown;','⇀'=>'&rightharpoonup;','⇄'=>'&rightleftarrows;','⇌'=>'&rightleftharpoons;','⇉'=>'&rightrightarrows;','↝'=>'&rightsquigarrow;','⊢'=>'&RightTee;','↦'=>'&RightTeeArrow;','⥛'=>'&RightTeeVector;','⋌'=>'&rightthreetimes;','⊳'=>'&RightTriangle;','⧐'=>'&RightTriangleBar;','⊵'=>'&RightTriangleEqual;','⥏'=>'&RightUpDownVector;','⥜'=>'&RightUpTeeVector;','↾'=>'&RightUpVector;','⥔'=>'&RightUpVectorBar;','⇀'=>'&RightVector;','⥓'=>'&RightVectorBar;','˚'=>'&ring;','≓'=>'&risingdotseq;','⇄'=>'&rlarr;','⇌'=>'&rlhar;','‏'=>'&rlm;','⎱'=>'&rmoust;','⎱'=>'&rmoustache;','⫮'=>'&rnmid;','⟭'=>'&roang;','⇾'=>'&roarr;','⟧'=>'&robrk;','⦆'=>'&ropar;','ℝ'=>'&Ropf;','𝕣'=>'&ropf;','⨮'=>'&roplus;','⨵'=>'&rotimes;','⥰'=>'&RoundImplies;','⦔'=>'&rpargt;','⨒'=>'&rppolint;','⇉'=>'&rrarr;','⇛'=>'&Rrightarrow;','↱'=>'&rsh;','⋌'=>'&rthree;','⋊'=>'&rtimes;','▹'=>'&rtri;','⊵'=>'&rtrie;','▸'=>'&rtrif;','⧎'=>'&rtriltri;','⧴'=>'&RuleDelayed;','⥨'=>'&ruluhar;','⪼'=>'&Sc;','≻'=>'&sc;','⪸'=>'&scap;','≽'=>'&sccue;','⪴'=>'&scE;','⪰'=>'&sce;','⪺'=>'&scnap;','⪶'=>'&scnE;','⋩'=>'&scnsim;','⨓'=>'&scpolint;','≿'=>'&scsim;','⋅'=>'&sdot;','⊡'=>'&sdotb;','⩦'=>'&sdote;','⤥'=>'&searhk;','⇘'=>'&seArr;','↘'=>'&searr;','↘'=>'&searrow;','§'=>'&sect;','⤩'=>'&seswar;','✶'=>'&sext;','⌢'=>'&sfrown;','♯'=>'&sharp;','↓'=>'&ShortDownArrow;','←'=>'&ShortLeftArrow;','∣'=>'&shortmid;','∥'=>'&shortparallel;','→'=>'&ShortRightArrow;','↑'=>'&ShortUpArrow;','¬'=>'&shy;','∼'=>'&sim;','⩪'=>'&simdot;','≃'=>'&simeq;','⪞'=>'&simg;','⪠'=>'&simgE;','⪝'=>'&siml;','⪟'=>'&simlE;','≆'=>'&simne;','⨤'=>'&simplus;','⥲'=>'&simrarr;','←'=>'&slarr;','∘'=>'&SmallCircle;','⨳'=>'&smashp;','⧤'=>'&smeparsl;','⌣'=>'&smile;','⪪'=>'&smt;','⪬'=>'&smte;','⪬︀'=>'&smtes;','⧄'=>'&solb;','⌿'=>'&solbar;','♠'=>'&spades;','∥'=>'&spar;','⊓'=>'&sqcap;','⊓︀'=>'&sqcaps;','⊔'=>'&sqcup;','⊔︀'=>'&sqcups;','√'=>'&Sqrt;','⊑'=>'&sqsube;','⊏'=>'&sqsubset;','⊑'=>'&sqsubseteq;','⊒'=>'&sqsupe;','⊐'=>'&sqsupset;','□'=>'&Square;','□'=>'&square;','⊓'=>'&SquareIntersection;','⊏'=>'&SquareSubset;','⊑'=>'&SquareSubsetEqual;','⊐'=>'&SquareSuperset;','⊒'=>'&SquareSupersetEqual;','⊔'=>'&SquareUnion;','▪'=>'&squarf;','→'=>'&srarr;','⌣'=>'&ssmile;','⋆'=>'&sstarf;','☆'=>'&star;','★'=>'&starf;','¯'=>'&strns;','⋐'=>'&Sub;','⊂'=>'&sub;','⪽'=>'&subdot;','⊆'=>'&sube;','⫃'=>'&subedot;','⫁'=>'&submult;','⫋'=>'&subnE;','⊊'=>'&subne;','⪿'=>'&subplus;','⥹'=>'&subrarr;','⋐'=>'&Subset;','⊂'=>'&subset;','⊆'=>'&subseteq;','⫅'=>'&subseteqq;','⊆'=>'&SubsetEqual;','⊊'=>'&subsetneq;','⫋'=>'&subsetneqq;','⫇'=>'&subsim;','⫕'=>'&subsub;','⫓'=>'&subsup;','≻'=>'&succ;','⪸'=>'&succapprox;','≽'=>'&succcurlyeq;','≻'=>'&Succeeds;','⪰'=>'&SucceedsEqual;','≽'=>'&SucceedsSlantEqual;','≿'=>'&SucceedsTilde;','⪰'=>'&succeq;','⪺'=>'&succnapprox;','⪶'=>'&succneqq;','⋩'=>'&succnsim;','≿'=>'&succsim;','∋'=>'&SuchThat;','♪'=>'&sung;','⋑'=>'&Sup;','⊃'=>'&sup;','⪾'=>'&supdot;','⫘'=>'&supdsub;','⫆'=>'&supE;','⊇'=>'&supe;','⫄'=>'&supedot;','⊃'=>'&Superset;','⟉'=>'&suphsol;','⫗'=>'&suphsub;','⥻'=>'&suplarr;','⫂'=>'&supmult;','⫌'=>'&supnE;','⊋'=>'&supne;','⫀'=>'&supplus;','⋑'=>'&Supset;','⊃'=>'&supset;','⊇'=>'&supseteq;','⫆'=>'&supseteqq;','⊋'=>'&supsetneq;','⫈'=>'&supsim;','⫔'=>'&supsub;','⫖'=>'&supsup;','⤦'=>'&swarhk;','⇙'=>'&swArr;','↙'=>'&swarr;','⤪'=>'&swnwar;','⌖'=>'&target;','⎴'=>'&tbrk;','◌⃛'=>'&tdot;','⌕'=>'&telrec;','∴'=>'&therefore;','≈'=>'&thickapprox;','∼'=>'&thicksim;','∼'=>'&thksim;','˜'=>'&tilde;','≃'=>'&TildeEqual;','≅'=>'&TildeFullEqual;','≈'=>'&TildeTilde;','×'=>'&times;','⊠'=>'&timesb;','⨱'=>'&timesbar;','⨰'=>'&timesd;','⤨'=>'&toea;','⊤'=>'&top;','⌶'=>'&topbot;','⫱'=>'&topcir;','⫚'=>'&topfork;','⤩'=>'&tosa;','‴'=>'&tprime;','▵'=>'&triangle;','▿'=>'&triangledown;','◃'=>'&triangleleft;','⊴'=>'&trianglelefteq;','≜'=>'&triangleq;','▹'=>'&triangleright;','⊵'=>'&trianglerighteq;','◬'=>'&tridot;','≜'=>'&trie;','⨺'=>'&triminus;','◌⃛'=>'&TripleDot;','⨹'=>'&triplus;','⧍'=>'&trisb;','⨻'=>'&tritime;','⏢'=>'&trpezium;','≬'=>'&twixt;','↞'=>'&twoheadleftarrow;','↠'=>'&twoheadrightarrow;','↟'=>'&Uarr;','⇑'=>'&uArr;','↑'=>'&uarr;','⥉'=>'&Uarrocir;','⇅'=>'&udarr;','⥮'=>'&udhar;','⥣'=>'&uHar;','↿'=>'&uharl;','↾'=>'&uharr;','▀'=>'&uhblk;','⌜'=>'&ulcorner;','⌏'=>'&ulcrop;','◸'=>'&ultri;','¨'=>'&uml;','⏟'=>'&UnderBrace;','⎵'=>'&UnderBracket;','⏝'=>'&UnderParenthesis;','⋃'=>'&Union;','⊎'=>'&UnionPlus;','𝕌'=>'&Uopf;','𝕦'=>'&uopf;','↑'=>'&UpArrow;','⇑'=>'&Uparrow;','↑'=>'&uparrow;','⤒'=>'&UpArrowBar;','⇅'=>'&UpArrowDownArrow;','↕'=>'&UpDownArrow;','⇕'=>'&Updownarrow;','↕'=>'&updownarrow;','⥮'=>'&UpEquilibrium;','↿'=>'&upharpoonleft;','↾'=>'&upharpoonright;','⊎'=>'&uplus;','↖'=>'&UpperLeftArrow;','↗'=>'&UpperRightArrow;','⊥'=>'&UpTee;','↥'=>'&UpTeeArrow;','⇈'=>'&upuparrows;','⌝'=>'&urcorn;','⌎'=>'&urcrop;','◹'=>'&urtri;','⋰'=>'&utdot;','▵'=>'&utri;','▴'=>'&utrif;','⇈'=>'&uuarr;','⦧'=>'&uwangle;','⦜'=>'&vangrt;','ϵ'=>'&varepsilon;','⇕'=>'&vArr;','↕'=>'&varr;','⊊︀'=>'&varsubsetneq;','⫋︀'=>'&varsubsetneqq;','⊋︀'=>'&varsupsetneq;','⫌︀'=>'&varsupsetneqq;','⊲'=>'&vartriangleleft;','⊳'=>'&vartriangleright;','⫫'=>'&Vbar;','⫨'=>'&vBar;','⫩'=>'&vBarv;','⊫'=>'&VDash;','⊩'=>'&Vdash;','⊨'=>'&vDash;','⊢'=>'&vdash;','⫦'=>'&Vdashl;','⋁'=>'&Vee;','∨'=>'&vee;','⊻'=>'&veebar;','≚'=>'&veeeq;','⋮'=>'&vellip;','‖'=>'&Verbar;','|'=>'&verbar;','∣'=>'&VerticalBar;','|'=>'&VerticalLine;','❘'=>'&VerticalSeparator;','≀'=>'&VerticalTilde;','⊲'=>'&vltri;','∝'=>'&vprop;','⊳'=>'&vrtri;','⊪'=>'&Vvdash;','⦚'=>'&vzigzag;','⩟'=>'&wedbar;','⋀'=>'&Wedge;','∧'=>'&wedge;','≙'=>'&wedgeq;','⋂'=>'&xcap;','◯'=>'&xcirc;','⋃'=>'&xcup;','▽'=>'&xdtri;','⟺'=>'&xhArr;','⟷'=>'&xharr;','⟸'=>'&xlArr;','⟵'=>'&xlarr;','⟼'=>'&xmap;','⋻'=>'&xnis;','⨀'=>'&xodot;','⨁'=>'&xoplus;','⨂'=>'&xotime;','⟹'=>'&xrArr;','⟶'=>'&xrarr;','⨆'=>'&xsqcup;','⨄'=>'&xuplus;','△'=>'&xutri;','⋁'=>'&xvee;','⋀'=>'&xwedge;','⇝'=>'&zigrarr;','‰'=>'&permil;','‱'=>'&pertenk;','∞'=>'&infin; '];

    // Mapping for numerical conversion
    $htmlEntitiesMapNumeric = ['£'=>'&#163;','$'=>'&#36;','₵'=>'&#162;','¢'=>'&#162;','€'=>'&#8364;','¥'=>'&#165;','₽'=>'&#8381;','₩'=>'&#8361;','₹'=>'&#8377;','¤'=>'&#164;','’'=>'&#146;','™'=>'&#8482;','©'=>'&#169;','®'=>'&#174;','℗'=>'&#8471;','°'=>'&#176;','¼'=>'&#188;','½'=>'&#189;','¾'=>'&#190;','⅓'=>'&#8531;','⅔'=>'&#8532;','⅕'=>'&#8533;','⅖'=>'&#8534;','⅗'=>'&#8535;','⅘'=>'&#8536;','⅙'=>'&#8537;','⅚'=>'&#8538;','⅛'=>'&#8539;','⅜'=>'&#8540;','⅝'=>'&#8541;','⅞'=>'&#8542;','Á'=>'&#193;','á'=>'&#225;','Ă'=>'&#258;','ă'=>'&#259;','Â'=>'&#194;','â'=>'&#226;','À'=>'&#192;','à'=>'&#224;','Ā'=>'&#256;','ā'=>'&#257;','Å'=>'&#197;','Ą'=>'&#260;','ą'=>'&#261;','Å'=>'&#197;','å'=>'&#229;','Ã'=>'&#195;','ã'=>'&#227;','Ä'=>'&#196;','ä'=>'&#228;','Č'=>'&#268;','č'=>'&#269;','Ç'=>'&#199;','ç'=>'&#231;','Ĉ'=>'&#264;','ĉ'=>'&#265;','Ċ'=>'&#266;','ċ'=>'&#267;','¢'=>'&#162;','Ć'=>'&#262;','ć'=>'&#263;','Ď'=>'&#270;','ď'=>'&#271;','Đ'=>'&#272;','đ'=>'&#273;','É'=>'&#201;','é'=>'&#233;','Ě'=>'&#282;','ě'=>'&#283;','Ê'=>'&#202;','ê'=>'&#234;','Ė'=>'&#278;','ė'=>'&#279;','È'=>'&#200;','è'=>'&#232;','Ē'=>'&#274;','ē'=>'&#275;','Ŋ'=>'&#330;','ŋ'=>'&#331;','Ę'=>'&#280;','ę'=>'&#281;','ε'=>'&#949;','Ε'=>'&#917;','ε'=>'&#949;','ϵ'=>'&#1013;','Η'=>'&#919;','η'=>'&#951;','Ð'=>'&#208;','ð'=>'&#240;','Ë'=>'&#203;','ë'=>'&#235;','ǵ'=>'&#501;','Ǵ'=>'&#500;','Γ'=>'&#915;','γ'=>'&#947;','Ϝ'=>'&#988;','ϝ'=>'&#989;','Ğ'=>'&#286;','ğ'=>'&#287;','Ģ'=>'&#290;','Ĝ'=>'&#284;','ĝ'=>'&#285;','Ġ'=>'&#288;','ġ'=>'&#289;','ℷ'=>'&#8503;','Ѓ'=>'&#1027;','ѓ'=>'&#1107;','ℏ'=>'&#8463;','Ĥ'=>'&#292;','ĥ'=>'&#293;','𝔥'=>'&#120101;','Ħ'=>'&#294;','ħ'=>'&#295;','Í'=>'&#205;','í'=>'&#237;','Î'=>'&#206;','î'=>'&#238;','İ'=>'&#304;','𝔦'=>'&#120102;','Ì'=>'&#204;','ì'=>'&#236;','ⅈ'=>'&#8520;','Ī'=>'&#298;','ī'=>'&#299;','Ƶ'=>'&#437;','ı'=>'&#305;','Į'=>'&#302;','į'=>'&#303;','Ĩ'=>'&#296;','ĩ'=>'&#297;','Ï'=>'&#207;','ï'=>'&#239;','Ĵ'=>'&#308;','ĵ'=>'&#309;','𝔧'=>'&#120103;','ȷ'=>'&#567;','Ķ'=>'&#310;','ķ'=>'&#311;','Ќ'=>'&#1036;','ќ'=>'&#1116;','Ĺ'=>'&#313;','ĺ'=>'&#314;','Ľ'=>'&#317;','ľ'=>'&#318;','Ļ'=>'&#315;','ļ'=>'&#316;','Ŀ'=>'&#319;','ŀ'=>'&#320;','Ł'=>'&#321;','ł'=>'&#322;','Ň'=>'&#327;','ň'=>'&#328;','Ņ'=>'&#325;','ņ'=>'&#326;','Ñ'=>'&#209;','ñ'=>'&#241;','Ó'=>'&#211;','ó'=>'&#243;','Ô'=>'&#212;','ô'=>'&#244;','Ő'=>'&#336;','ő'=>'&#337;','Ò'=>'&#210;','ò'=>'&#242;','Ō'=>'&#332;','ō'=>'&#333;','Ω'=>'&#937;','ω'=>'&#969;','Ø'=>'&#216;','ø'=>'&#248;','Õ'=>'&#213;','õ'=>'&#245;','Ö'=>'&#214;','ö'=>'&#246;','Ŕ'=>'&#340;','ŕ'=>'&#341;','Ř'=>'&#344;','ř'=>'&#345;','Ŗ'=>'&#342;','ŗ'=>'&#343;','Ś'=>'&#346;','ś'=>'&#347;','Š'=>'&#352;','š'=>'&#353;','Ş'=>'&#350;','ş'=>'&#351;','Ŝ'=>'&#348;','ŝ'=>'&#349;','Ť'=>'&#356;','ť'=>'&#357;','Ţ'=>'&#354;','ţ'=>'&#355;','Ŧ'=>'&#358;','ŧ'=>'&#359;','Ú'=>'&#218;','ú'=>'&#250;','Ў'=>'&#1038;','ў'=>'&#1118;','Ŭ'=>'&#364;','ŭ'=>'&#365;','Û'=>'&#219;','û'=>'&#251;','Ű'=>'&#368;','ű'=>'&#369;','Ù'=>'&#217;','ù'=>'&#249;','Ū'=>'&#362;','ū'=>'&#363;','Ų'=>'&#370;','ų'=>'&#371;','Ů'=>'&#366;','ů'=>'&#367;','Ũ'=>'&#360;','ũ'=>'&#361;','Ü'=>'&#220;','ü'=>'&#252;','ϱ'=>'&#1009;','ς'=>'&#962;','Ŵ'=>'&#372;','ŵ'=>'&#373;','Ý'=>'&#221;','ý'=>'&#253;','Ŷ'=>'&#374;','ŷ'=>'&#375;','Ÿ'=>'&#376;','ÿ'=>'&#255;','Ź'=>'&#377;','ź'=>'&#378;','Ž'=>'&#381;','ž'=>'&#382;','Ż'=>'&#379;','ż'=>'&#380;','Æ'=>'&#198;','æ'=>'&#230;','𝔄'=>'&#120068;','𝔞'=>'&#120094;','ℵ'=>'&#8501;','ℵ'=>'&#8501;','Α'=>'&#913;','α'=>'&#945;','𝔸'=>'&#120120;','𝕒'=>'&#120146;','𝒜'=>'&#119964;','𝒶'=>'&#119990;','∳'=>'&#8755;','⨑'=>'&#10769;','϶'=>'&#1014;','϶'=>'&#1014;','ℬ'=>'&#8492;','ℬ'=>'&#8492;','Β'=>'&#914;','β'=>'&#946;','ℶ'=>'&#8502;','≬'=>'&#8812;','𝔅'=>'&#120069;','𝔟'=>'&#120095;','𝔹'=>'&#120121;','𝕓'=>'&#120147;','ℬ'=>'&#8492;','𝒷'=>'&#119991;','ⅅ'=>'&#8517;','ℭ'=>'&#8493;','ℭ'=>'&#8493;','𝔠'=>'&#120096;','Χ'=>'&#935;','χ'=>'&#967;','ℂ'=>'&#8450;','𝕔'=>'&#120148;','𝒞'=>'&#119966;','𝒸'=>'&#119992;','∲'=>'&#8754;','∱'=>'&#8753;','ℸ'=>'&#8504;','ⅅ'=>'&#8517;','ⅆ'=>'&#8518;','Δ'=>'&#916;','δ'=>'&#948;','𝔇'=>'&#120071;','𝔡'=>'&#120097;','ⅆ'=>'&#8518;','ϝ'=>'&#989;','Ђ'=>'&#1026;','ђ'=>'&#1106;','𝔻'=>'&#120123;','𝕕'=>'&#120149;','𝒟'=>'&#119967;','𝒹'=>'&#119993;','Џ'=>'&#1039;','џ'=>'&#1119;','ⅇ'=>'&#8519;','𝔈'=>'&#120072;','𝔢'=>'&#120098;','ℓ'=>'&#8467;','𝔼'=>'&#120124;','𝕖'=>'&#120150;','ℰ'=>'&#8496;','ℯ'=>'&#119994;','∃'=>'&#8707;','∃'=>'&#8707;','ℰ'=>'&#8496;','ⅇ'=>'&#8519;','ⅇ'=>'&#8519;','ﬃ'=>'&#64259;','ﬀ'=>'&#64256;','ﬄ'=>'&#64260;','𝔉'=>'&#120073;','𝔣'=>'&#120099;','ﬁ'=>'&#64257;','♭'=>'&#9837;','ﬂ'=>'&#64258;','ƒ'=>'&#402;','𝔽'=>'&#120125;','𝕗'=>'&#120151;','ℱ'=>'&#8497;','⨍'=>'&#10765;','ℱ'=>'&#8497;','𝒻'=>'&#119995;','𝔊'=>'&#120074;','𝔤'=>'&#120100;','𝔾'=>'&#120126;','𝕘'=>'&#120152;','𝒢'=>'&#119970;','ℊ'=>'&#8458;','ℋ'=>'&#8459;','ℌ'=>'&#8460;','ℋ'=>'&#8459;','ℍ'=>'&#120127;','𝕙'=>'&#120153;','ℋ'=>'&#8459;','𝒽'=>'&#119997;','ℏ'=>'&#8463;','ℑ'=>'&#8465;','⨌'=>'&#10764;','∭'=>'&#8749;','⧜'=>'&#10716;','℩'=>'&#8489;','Ĳ'=>'&#306;','ĳ'=>'&#307;','ℑ'=>'&#8465;','ℑ'=>'&#8465;','ⅈ'=>'&#8520;','ℐ'=>'&#8464;','ℑ'=>'&#8465;','ı'=>'&#305;','∬'=>'&#8748;','∫'=>'&#8747;','⊺'=>'&#8890;','ℤ'=>'&#8484;','∫'=>'&#8747;','⊺'=>'&#8890;','𝕀'=>'&#120128;','𝕚'=>'&#120154;','Ι'=>'&#921;','ι'=>'&#953;','ℐ'=>'&#8464;','𝒾'=>'&#119998;','𝔍'=>'&#120077;','𝕁'=>'&#120129;','𝕛'=>'&#120155;','𝒥'=>'&#119973;','𝒿'=>'&#119999;','Κ'=>'&#922;','κ'=>'&#954;','ϰ'=>'&#1008;','𝔎'=>'&#120078;','𝔨'=>'&#120104;','ĸ'=>'&#312;','𝕂'=>'&#120130;','𝕜'=>'&#120156;','𝒦'=>'&#119974;','𝓀'=>'&#120000;','ℒ'=>'&#8466;','Λ'=>'&#923;','λ'=>'&#955;','ℒ'=>'&#8466;','Љ'=>'&#1033;','љ'=>'&#1113;','ℒ'=>'&#8466;','𝓁'=>'&#120001;','ℳ'=>'&#8499;','𝔐'=>'&#120080;','𝔪'=>'&#120106;','℧'=>'&#8487;','µ'=>'&#181;','𝕄'=>'&#120132;','𝕞'=>'&#120158;','ℳ'=>'&#8499;','𝓂'=>'&#120002;','Μ'=>'&#924;','μ'=>'&#956;','Ń'=>'&#323;','ń'=>'&#324;','ŉ'=>'&#329;','ℕ'=>'&#8469;','𝔑'=>'&#120081;','𝔫'=>'&#120107;','Њ'=>'&#1034;','њ'=>'&#1114;','∤'=>'&#8740;','ℕ'=>'&#8469;','𝕟'=>'&#120159;','Ν'=>'&#925;','ν'=>'&#957;','Œ'=>'&#338;','œ'=>'&#339;','𝔒'=>'&#120082;','𝔬'=>'&#120108;','Ω'=>'&#937;','∮'=>'&#8750;','𝕆'=>'&#120134;','𝕠'=>'&#120160;','𝒪'=>'&#119978;','ℴ'=>'&#120004;','𝔓'=>'&#120083;','𝔭'=>'&#120109;','Φ'=>'&#934;','φ'=>'&#966;','ϕ'=>'&#981;','ℳ'=>'&#8499;','π'=>'&#960;','ℏ'=>'&#8463;','ℎ'=>'&#8462;','ℏ'=>'&#8463;','ℌ'=>'&#8460;','⨕'=>'&#10773;','ℙ'=>'&#8473;','𝕡'=>'&#120161;','ℙ'=>'&#8473;','𝒫'=>'&#119979;','𝓅'=>'&#120005;','Ψ'=>'&#936;','ψ'=>'&#968;','𝔔'=>'&#120084;','𝔮'=>'&#120110;','⨌'=>'&#10764;','ℚ'=>'&#8474;','𝕢'=>'&#120162;','𝒬'=>'&#119980;','𝓆'=>'&#120006;','ℍ'=>'&#8461;','⨖'=>'&#10774;','ℚ'=>'&#8474;','ℜ'=>'&#8476;','ℜ'=>'&#8476;','ℛ'=>'&#8475;','ℜ'=>'&#8476;','ℝ'=>'&#8477;','ℜ'=>'&#8476;','𝔯'=>'&#120111;','Ρ'=>'&#929;','ρ'=>'&#961;','ϱ'=>'&#1009;','ℛ'=>'&#8475;','𝓇'=>'&#120007;','℞'=>'&#8478;','𝔖'=>'&#120086;','𝔰'=>'&#120112;','Σ'=>'&#931;','σ'=>'&#963;','ς'=>'&#962;','ς'=>'&#962;','𝕊'=>'&#120138;','𝕤'=>'&#120164;','𝒮'=>'&#119982;','𝓈'=>'&#120008;','ϵ'=>'&#1013;','ϕ'=>'&#981;','ß'=>'&#223;','Τ'=>'&#932;','τ'=>'&#964;','𝔗'=>'&#120087;','𝔱'=>'&#120113;','Θ'=>'&#920;','θ'=>'&#952;','ϑ'=>'&#977;','ϑ'=>'&#977;','Þ'=>'&#222;','þ'=>'&#254;','∭'=>'&#8749;','𝕋'=>'&#120139;','𝕥'=>'&#120165;','𝒯'=>'&#119983;','𝓉'=>'&#120009;','Ћ'=>'&#1035;','ћ'=>'&#1115;','⥾'=>'&#10622;','𝔘'=>'&#120088;','𝔲'=>'&#120114;','ϒ'=>'&#978;','υ'=>'&#965;','ϒ'=>'&#978;','Υ'=>'&#933;','υ'=>'&#965;','𝒰'=>'&#119984;','𝓊'=>'&#120010;','ϰ'=>'&#1008;','∅'=>'&#8709;','ϕ'=>'&#981;','ϖ'=>'&#982;','∝'=>'&#8733;','ϑ'=>'&#977;','𝔙'=>'&#120089;','𝔳'=>'&#120115;','𝕍'=>'&#120141;','𝕧'=>'&#120167;','𝒱'=>'&#119985;','𝓋'=>'&#120011;','℘'=>'&#8472;','𝔚'=>'&#120090;','𝔴'=>'&#120116;','𝕎'=>'&#120142;','𝕨'=>'&#120168;','℘'=>'&#8472;','≀'=>'&#8768;','𝒲'=>'&#119986;','𝓌'=>'&#120012;','𝔛'=>'&#120091;','𝔵'=>'&#120117;','Ξ'=>'&#926;','ξ'=>'&#958;','𝕏'=>'&#120143;','𝕩'=>'&#120169;','𝒳'=>'&#119987;','𝓍'=>'&#120013;','𝔜'=>'&#120092;','𝔶'=>'&#120118;','𝕐'=>'&#120144;','𝕪'=>'&#120170;','𝒴'=>'&#119988;','𝓎'=>'&#120014;','ℨ'=>'&#8488;','Ζ'=>'&#918;','ζ'=>'&#950;','ℨ'=>'&#8488;','𝔷'=>'&#120119;','ℤ'=>'&#8484;','𝕫'=>'&#120171;','𝒵'=>'&#119989;','𝓏'=>'&#120015;','∾'=>'&#8766;','∿'=>'&#8767;','´'=>'&#180;','⨿'=>'&#10815;','⩓'=>'&#10835;','∧'=>'&#8743;','⩕'=>'&#10837;','⩜'=>'&#10844;','⩘'=>'&#10840;','⩚'=>'&#10842;','∠'=>'&#8736;','⦤'=>'&#10660;','∠'=>'&#8736;','∡'=>'&#8737;','⦨'=>'&#10664;','⦩'=>'&#10665;','⦪'=>'&#10666;','⦫'=>'&#10667;','⦬'=>'&#10668;','⦭'=>'&#10669;','⦮'=>'&#10670;','⦯'=>'&#10671;','∟'=>'&#8735;','⊾'=>'&#8894;','⦝'=>'&#10653;','∢'=>'&#8738;','⍼'=>'&#9084;','≈'=>'&#8776;','⩯'=>'&#10863;','⩰'=>'&#10864;','≊'=>'&#8778;','≋'=>'&#8779;','≈'=>'&#8776;','≊'=>'&#8778;','≔'=>'&#8788;','≈'=>'&#8776;','≍'=>'&#8781;','≌'=>'&#8780;','‵'=>'&#8245;','∽'=>'&#8765;','⋍'=>'&#8909;','⫧'=>'&#10983;','⊽'=>'&#8893;','⌆'=>'&#8966;','⌅'=>'&#8965;','⌅'=>'&#8965;','⎶'=>'&#9142;','≌'=>'&#10892;','∵'=>'&#8757;','∵'=>'&#8757;','∵'=>'&#8757;','⦰'=>'&#10672;','⋂'=>'&#8898;','◯'=>'&#9711;','⋃'=>'&#8899;','⨀'=>'&#10752;','⨁'=>'&#10753;','⨂'=>'&#10754;','⨆'=>'&#10758;','★'=>'&#9733;','▽'=>'&#9661;','△'=>'&#9651;','⨄'=>'&#10756;','⤍'=>'&#10509;','⧫'=>'&#10731;','▪'=>'&#9642;','▴'=>'&#9652;','▾'=>'&#9662;','◂'=>'&#9666;','▸'=>'&#9656;','␣'=>'&#9251;','▒'=>'&#9618;','░'=>'&#9617;','▓'=>'&#9619;','█'=>'&#9608;','=⃥'=>'(&bne;)','≡⃥'=>'(&bnequiv;)','⫭'=>'&#10989;','⌐'=>'&#8976;','⊥'=>'&#8869;','⊥'=>'&#8869;','⋈'=>'&#8904;','⧉'=>'&#10697;','╗'=>'&#9559;','╖'=>'&#9558;','╕'=>'&#9557;','┐'=>'&#9488;','╔'=>'&#9556;','╓'=>'&#9555;','╒'=>'&#9554;','┌'=>'&#9484;','═'=>'&#9552;','─'=>'&#9472;','╦'=>'&#9574;','╤'=>'&#9572;','╥'=>'&#9573;','┬'=>'&#9524;','╩'=>'&#9577;','╧'=>'&#9575;','╨'=>'&#9576;','┴'=>'&#9532;','⊟'=>'&#8863;','⊞'=>'&#8862;','⊠'=>'&#8864;','╝'=>'&#9565;','╜'=>'&#9564;','╛'=>'&#9563;','┘'=>'&#9496;','╚'=>'&#9562;','╙'=>'&#9561;','╘'=>'&#9560;','└'=>'&#9492;','║'=>'&#9553;','│'=>'&#9474;','╬'=>'&#9580;','╫'=>'&#9579;','╪'=>'&#9578;','┼'=>'&#9532;','╣'=>'&#9571;','╢'=>'&#9570;','╡'=>'&#9569;','┤'=>'&#9508;','╠'=>'&#9568;','╟'=>'&#9567;','╞'=>'&#9566;','├'=>'&#9500;','‵'=>'&#8245;','˘'=>'&#728;','˘'=>'&#728;','¦'=>'&#166;','⁏'=>'&#8271;','∽'=>'&#8765;','⋍'=>'&#8909;','⧅'=>'&#10693;','⟈'=>'&#10184;','•'=>'&#8226;','≎'=>'&#8782;','⪮'=>'&#10926;','≏'=>'&#8783;','≎'=>'&#8782;','≏'=>'&#8783;','⋒'=>'&#8914;','∩'=>'&#8745;','⩄'=>'&#10820;','⩉'=>'&#10825;','⩋'=>'&#10827;','⩇'=>'&#10823;','⩀'=>'&#10816;','∩︀'=>'(&caps;)','⁁'=>'&#8257;','ˇ'=>'&#711;','⩍'=>'&#10829;','∰'=>'&#8752;','⩌'=>'&#10828;','⩐'=>'&#10832;','¸'=>'&#184;','¸'=>'&#184;','⦲'=>'&#10674;','✓'=>'&#10003;','○'=>'&#9675;','ˆ'=>'&#710;','≗'=>'&#8791;','↺'=>'&#8634;','↻'=>'&#8635;','⊛'=>'&#8859;','⊚'=>'&#8858;','⊝'=>'&#8861;','⊙'=>'&#8857;','Ⓢ'=>'&#9416;','⊖'=>'&#8854;','⊕'=>'&#8853;','⊗'=>'&#8855;','⧃'=>'&#10691;','≗'=>'&#8791;','⨐'=>'&#10768;','⫯'=>'&#10991;','⧂'=>'&#10690;','∲'=>'&#8754;','♣'=>'&#9827;','∷'=>'&#8759;','⩴'=>'&#10868;','≔'=>'&#8788;','≔'=>'&#8788;','∘'=>'&#8728;','∁'=>'&#8705;','ℂ'=>'&#8450;','≅'=>'&#8773;','⩭'=>'&#10861;','≡'=>'&#8801;','∯'=>'&#8751;','∮'=>'&#8750;','∐'=>'&#8720;','∳'=>'&#8755;','↵'=>'&#8629;','⨯'=>'&#10799;','✗'=>'&#10007;','⫏'=>'&#10959;','⫑'=>'&#10961;','⫐'=>'&#10960;','⫒'=>'&#10962;','⋯'=>'&#8943;','⤸'=>'&#10552;','⤵'=>'&#10549;','⋞'=>'&#8926;','⋟'=>'&#8927;','↶'=>'&#8630;','⤽'=>'&#10557;','⋓'=>'&#8915;','∪'=>'&#8746;','⩈'=>'&#10824;','≍'=>'&#8781;','⩆'=>'&#10822;','⩊'=>'&#10826;','⊍'=>'&#8845;','⩅'=>'&#10821;','∪︀'=>'',' ↷'=>'&#8631;','⤼'=>'&#10556;','⋞'=>'&#8926;','⋟'=>'&#8927;','↶'=>'&#8630;','↷'=>'&#8631;','⋎'=>'&#8910;','⋏'=>'&#8911;','⌭'=>'&#9005;','‡'=>'&#8225;','†'=>'&#8224;','↡'=>'&#8609;','⇓'=>'&#8659;','↓'=>'&#8595;','‐'=>'&#8208;','⫤'=>'&#10980;','⊣'=>'&#8867;','⤏'=>'&#10511;','˝'=>'&#733;','‡'=>'&#8225;','⇊'=>'&#8650;','⤑'=>'&#10513;','⩷'=>'&#10871;','∇'=>'&#8711;','⦱'=>'&#10673;','⥿'=>'&#10623;','⥥'=>'&#10597;','⇃'=>'&#8643;','⇂'=>'&#8642;','´'=>'&#180;','˙'=>'&#729;','`'=>'&#96;','˜'=>'&#732;','⋄'=>'&#8900;','⋄'=>'&#8900;','⋄'=>'&#8900;','♦'=>'&#9830;','¨'=>'&#168;','⋲'=>'&#8946;','÷'=>'&#247;','÷'=>'&#247;','⋇'=>'&#8903;','⌞'=>'&#8990;','⌍'=>'&#8973;','¨'=>'&#168;','˙'=>'&#729;','◌⃜'=>'&#8412;','≐'=>'&#8784;','≑'=>'&#8785;','≐'=>'&#8784;','∸'=>'&#8760;','∔'=>'&#8724;','⊡'=>'&#8865;','⌆'=>'&#8966;','∯'=>'&#8751;','¨'=>'&#168;','⇓'=>'&#8659;','⇐'=>'&#8656;','⇔'=>'&#8660;','⫤'=>'&#10980;','⟸'=>'&#10232;','⟺'=>'&#10234;','⟹'=>'&#10233;','⇒'=>'&#8658;','⊨'=>'&#8872;','⇑'=>'&#8657;','⇕'=>'&#8661;','∥'=>'&#8741;','↓'=>'&#8595;','⇓'=>'&#8659;','↓'=>'&#8595;','⤓'=>'&#10515;','⇵'=>'&#8693;','◌̑'=>'&#785;','⇊'=>'&#8650;','⇃'=>'&#8643;','⇂'=>'&#8642;','⥐'=>'&#10576;','⥞'=>'&#10590;','↽'=>'&#8637;','⥖'=>'&#10582;','⥟'=>'&#10591;','⇁'=>'&#8641;','⥗'=>'&#10583;','⊤'=>'&#8868;','↧'=>'&#8615;','⤐'=>'&#10512;','⌟'=>'&#8991;','⌌'=>'&#8972;','⧶'=>'&#10742;','⋱'=>'&#8945;','▿'=>'&#9663;','▾'=>'&#9662;','⇵'=>'&#8693;','⥯'=>'&#10607;','⦦'=>'&#10662;','⟿'=>'&#10239;','⩮'=>'&#10862;','≖'=>'&#8790;','≕'=>'&#8789;','⩷'=>'&#10871;','≑'=>'&#8785;','≒'=>'&#8786;','⪚'=>'&#10906;','⪖'=>'&#10902;','⪘'=>'&#10904;','⪙'=>'&#10905;','∈'=>'&#8712;','⏧'=>'&#9191;','⪕'=>'&#10901;','⪗'=>'&#10903;','∅'=>'&#8709;','◻'=>'&#9723;','▫'=>'&#9643;','⋕'=>'&#8917;','⧣'=>'&#10723;','⩱'=>'&#10865;','≖'=>'&#8790;','≕'=>'&#8789;','≂'=>'&#8770;','⪖'=>'&#10902;','⪕'=>'&#10901;','⩵'=>'&#10869;','='=>'&#61;','≂'=>'&#8770;','≟'=>'&#8799;','⇌'=>'&#8652;','≡'=>'&#8801;','⩸'=>'&#10872;','⧥'=>'&#10725;','⥱'=>'&#10609;','≓'=>'&#8787;','≐'=>'&#8784;','⩳'=>'&#10867;','≂'=>'&#8770;','≒'=>'&#8786;','♀'=>'&#9792;','◼'=>'&#9724;','▪'=>'&#9642;','▱'=>'&#9649;','∀'=>'&#8704;','⋔'=>'&#8916;','⫙'=>'&#10969;','⁄'=>'&#8260;','⌢'=>'&#8994;','⪆'=>'&#10886;','≧'=>'&#8807;','≥'=>'&#8805;','⪌'=>'&#10892;','⋛'=>'&#8923;','≥'=>'&#8805;','≧'=>'&#8807;','⩾'=>'&#10878;','⪩'=>'&#10920;','⪀'=>'&#10880;','⪂'=>'&#10882;','⪄'=>'&#10884;','⋛︀'=>'(&gesl;)','⪔'=>'&#10900;','⋙'=>'&#8921;','≫'=>'&#8811;','⋙'=>'&#8921;','≷'=>'&#8823;','⪥'=>'&#10917;','⪒'=>'&#10898;','⪤'=>'&#10916;','⪊'=>'&#10890;','≩'=>'&#8809;','⪈'=>'&#10888;','⋧'=>'&#8935;','≥'=>'&#8805;','⋛'=>'&#8923;','≧'=>'&#8807;','⪢'=>'&#10914;','≷'=>'&#8823;','⩾'=>'&#10878;','≳'=>'&#8819;','⪎'=>'&#10894;','⪐'=>'&#10896;','≫'=>'&#8811;','>'=>'&#62;','⪧'=>'&#10919;','⩺'=>'&#10874;','⋗'=>'&#8919;','⦕'=>'&#10645;','⩼'=>'&#10876;','⪆'=>'&#10886;','⥸'=>'&#10616;','⋗'=>'&#8919;','⋛'=>'&#8923;','⪌'=>'&#10892;','≷'=>'&#8823;','≳'=>'&#8819;','≩︀'=>'',' ≩︀'=>'',' ˇ'=>'&#711;',' '=>'&#8202;','⇔'=>'&#8660;','↔'=>'&#8596;','⥈'=>'&#10568;','↭'=>'&#8621;','♥'=>'&#9829;','⊹'=>'&#8889;','⤥'=>'&#10533;','⤦'=>'&#10534;','⇿'=>'&#8703;','∻'=>'&#8763;','↩'=>'&#8617;','↪'=>'&#8618;','≎'=>'&#8782;','≏'=>'&#8783;','⁃'=>'&#8259;','‐'=>'&#8208;','¡'=>'&#161;','⇔'=>'&#8660;','⊷'=>'&#8887;','⇒'=>'&#8658;','∈'=>'&#8712;','℅'=>'&#8453;','⧝'=>'&#10717;','⋂'=>'&#8898;','⨗'=>'&#10775;','⨼'=>'&#10812;','⨼'=>'&#10812;','¿'=>'&#191;','∈'=>'&#8712;','⋵'=>'&#8949;','⋹'=>'&#8953;','⋴'=>'&#8948;','⋳'=>'&#8947;','∈'=>'&#8712;','⇚'=>'&#8666;','⦴'=>'&#10676;','⟪'=>'&#10218;','⟨'=>'&#9001;','⦑'=>'&#10641;','⪅'=>'&#10885;','↞'=>'&#8606;','⇐'=>'&#8656;','←'=>'&#8592;','⇤'=>'&#8676;','⤟'=>'&#10527;','⤝'=>'&#10525;','↩'=>'&#8617;','↫'=>'&#8619;','⤹'=>'&#10553;','⥳'=>'&#10611;','↢'=>'&#8610;','⪫'=>'&#10923;','⤛'=>'&#10523;','⤙'=>'&#10521;','⪭'=>'&#10925;','⪭︀'=>'&#10926;','⤎'=>'&#10510;','⤌'=>'&#10508;','❲'=>'&#10098;','⌈'=>'&#8968;','⤶'=>'&#10550;','⥧'=>'&#10599;','⥋'=>'&#10571;','↲'=>'&#8626;','≦'=>'&#8806;','≤'=>'&#8804;','⟨'=>'&#9001;','⇐'=>'&#8656;','←'=>'&#8592;','⇤'=>'&#8676;','⇆'=>'&#8646;','↢'=>'&#8610;','⌈'=>'&#8968;','⟦'=>'&#10214;','⥡'=>'&#10593;','⇃'=>'&#8643;','⥙'=>'&#10585;','⌊'=>'&#8970;','↽'=>'&#8637;','↼'=>'&#8636;','⇇'=>'&#8647;','⇔'=>'&#8660;','↔'=>'&#8596;','⇆'=>'&#8646;','⇋'=>'&#8651;','↭'=>'&#8621;','⥎'=>'&#10574;','⊣'=>'&#8867;','↤'=>'&#8612;','⥚'=>'&#10586;','⋋'=>'&#8907;','⊲'=>'&#8882;','⧏'=>'&#10703;','⊴'=>'&#8884;','⥑'=>'&#10577;','⥠'=>'&#10592;','↿'=>'&#8639;','⥘'=>'&#10584;','↼'=>'&#8636;','⥒'=>'&#10578;','⪋'=>'&#10891;','⋚'=>'&#8922;','≤'=>'&#8804;','≦'=>'&#8806;','⩽'=>'&#10877;','⪨'=>'&#10920;','⩿'=>'&#10879;','⪁'=>'&#10881;','⪃'=>'&#10883;','⋚︀'=>'',' ⪓'=>'&#10922;','⪅'=>'&#10885;','⋖'=>'&#8918;','⋚'=>'&#8922;','⪋'=>'&#10891;','⋚'=>'&#8922;','≦'=>'&#8806;','≶'=>'&#8822;','≶'=>'&#8822;','⪡'=>'&#10913;','≲'=>'&#8818;','⩽'=>'&#10877;','≲'=>'&#8818;','⥼'=>'&#10620;','⌊'=>'&#8970;','𝔏'=>'&#120079;','𝔩'=>'&#120105;','≶'=>'&#8822;','⪑'=>'&#10897;','⥢'=>'&#10594;','↽'=>'&#8637;','↼'=>'&#8636;','⥪'=>'&#10602;','▄'=>'&#9604;','⋘'=>'&#8920;','≪'=>'&#8810;','⇇'=>'&#8647;','⌞'=>'&#8990;','⇚'=>'&#8666;','⥫'=>'&#10603;','◺'=>'&#9722;','⎰'=>'&#9136;','⪉'=>'&#10889;','≨'=>'&#8808;','⪇'=>'&#10887;','≨'=>'&#8808;','⋦'=>'&#8934;','⟬'=>'&#10220;','⇽'=>'&#8701;','⟦'=>'&#10214;','⟸'=>'&#10232;','⟵'=>'&#10229;','⟺'=>'&#10234;','⟷'=>'&#10231;','⟼'=>'&#10236;','⟹'=>'&#10233;','⟶'=>'&#10230;','↫'=>'&#8619;','↬'=>'&#8620;','⦅'=>'&#10629;','𝕃'=>'&#120131;','𝕝'=>'&#120157;','⨭'=>'&#10797;','⨴'=>'&#10804;','↙'=>'&#8601;','↘'=>'&#8600;','◊'=>'&#9674;','⧫'=>'&#10731;','⦓'=>'&#10643;','⇆'=>'&#8646;','⌟'=>'&#8991;','⇋'=>'&#8651;','⥭'=>'&#10605;','‎'=>'&#8206;','⊿'=>'&#8895;','↰'=>'&#8624;','≲'=>'&#8818;','⪍'=>'&#10893;','⪏'=>'&#10895;','≪'=>'&#8810;','⪦'=>'&#10918;','⩹'=>'&#10873;','⋖'=>'&#8918;','⋋'=>'&#8907;','⋉'=>'&#8905;','⥶'=>'&#10614;','⩻'=>'&#10875;','◃'=>'&#9667;','⊴'=>'&#8884;','◂'=>'&#9666;','⦖'=>'&#10646;','⥊'=>'&#10570;','⥦'=>'&#10598;','¯'=>'&#175;','♂'=>'&#9794;','✠'=>'&#10016;','⤅'=>'&#10501;','↦'=>'&#8614;','↦'=>'&#8614;','↧'=>'&#8615;','↤'=>'&#8612;','↥'=>'&#8613;','▮'=>'&#9646;','⨩'=>'&#10793;','∺'=>'&#8762;','∡'=>'&#8737;',' '=>'&#8287;','∣'=>'&#8739;','*'=>'&#42;','⫰'=>'&#10992;','·'=>'&#183;','−'=>'&#8722;','⊟'=>'&#8863;','∸'=>'&#8760;','⨪'=>'&#10794;','⫛'=>'&#10971;','∓'=>'&#8723;','⊧'=>'&#8871;','∾'=>'&#8766;','⊸'=>'&#8888;','∇'=>'&#8711;','≉'=>'&#8777;','≉'=>'&#8777;','♮'=>'&#9838;','♮'=>'&#9838;','⩃'=>'&#10819;','≇'=>'&#8775;','⩂'=>'&#10818;','≠'=>'&#8800;','⤤'=>'&#10532;','⇗'=>'&#8663;','↗'=>'&#8599;','↗'=>'&#8599;','≢'=>'&#8802;','⤨'=>'&#10536;','≫'=>'&#8811;','≪'=>'&#8810;','␊'=>'&#10;','∄'=>'&#8708;','∄'=>'&#8708;','≱'=>'&#8817;','≱'=>'&#8817;','≵'=>'&#8821;','≯'=>'&#8815;','≯'=>'&#8815;','⇎'=>'&#8654;','↮'=>'&#8622;','⫲'=>'&#10994;','⋼'=>'&#8956;','⋺'=>'&#8954;','∋'=>'&#8715;','⇍'=>'&#8653;','↚'=>'&#8602;','‥'=>'&#8229;','≰'=>'&#8816;','⇍'=>'&#8653;','↚'=>'&#8602;','⇎'=>'&#8654;','↮'=>'&#8622;','≰'=>'&#8816;','≮'=>'&#8814;','≴'=>'&#8820;','≮'=>'&#8814;','⋪'=>'&#8938;','⋬'=>'&#8940;','⫬'=>'&#10988;','¬'=>'&#172;','≢'=>'&#8802;','≭'=>'&#8813;','∦'=>'&#8742;','∉'=>'&#8713;','≠'=>'&#8800;','∄'=>'&#8708;','≯'=>'&#8815;','≱'=>'&#8817;','≹'=>'&#8825;','≵'=>'&#8821;','∉'=>'&#8713;','⋷'=>'&#8951;','⋶'=>'&#8950;','⋪'=>'&#8938;','⋬'=>'&#8940;','≮'=>'&#8814;','≰'=>'&#8816;','≸'=>'&#8824;','≴'=>'&#8820;','∌'=>'&#8716;','∌'=>'&#8716;','⋾'=>'&#8958;','⋽'=>'&#8957;','⊀'=>'&#8832;','⋠'=>'&#8928;','∌'=>'&#8716;','⋫'=>'&#8939;','⋭'=>'&#8941;','⋢'=>'&#8930;','⋣'=>'&#8931;','⊈'=>'&#8840;','⊁'=>'&#8833;','⋡'=>'&#8929;','⊉'=>'&#8841;','≁'=>'&#8769;','≄'=>'&#8772;','≇'=>'&#8775;','≉'=>'&#8777;','∤'=>'&#8740;','∦'=>'&#8742;','∦'=>'&#8742;','⨔'=>'&#10772;','⊀'=>'&#8832;','⋠'=>'&#8928;','⊀'=>'&#8832;','⇏'=>'&#8655;','⇏'=>'&#8655;','↛'=>'&#8603;','⋫'=>'&#8939;','⋭'=>'&#8941;','⋡'=>'&#8929;','𝒩'=>'&#119977;','𝓃'=>'&#119995;','∤'=>'&#8740;','∦'=>'&#8742;','≁'=>'&#8769;','≄'=>'&#8772;','∤'=>'&#8740;','⋢'=>'&#8930;','⋣'=>'&#8931;','⊄'=>'&#8836;','⊈'=>'&#8840;','⊁'=>'&#8833;','⊉'=>'&#8841;','≹'=>'&#8825;','≸'=>'&#8824;','⋪'=>'&#8938;','⋬'=>'&#8940;','⋫'=>'&#8939;','⋭'=>'&#8941;','⊯'=>'&#8879;','⊮'=>'&#8878;','⊭'=>'&#8877;','⊬'=>'&#8876;','⤄'=>'&#10500;','⧞'=>'&#10718;','⤂'=>'&#10498;','⤃'=>'&#10499;','⤣'=>'&#10531;','⇖'=>'&#8662;','↖'=>'&#8598;','↖'=>'&#8598;','⤧'=>'&#10535;','⊛'=>'&#8859;','⊚'=>'&#8858;','⊝'=>'&#8861;','⨸'=>'&#10808;','⊙'=>'&#8857;','⦼'=>'&#10684;','⦿'=>'&#10687;','˛'=>'&#731;','⧁'=>'&#10689;','⦵'=>'&#10677;','↺'=>'&#8634;','⦾'=>'&#10686;','⦻'=>'&#10683;','‾'=>'&#8254;','⧀'=>'&#10688;','⦶'=>'&#10678;','⊖'=>'&#8854;','⦷'=>'&#10679;','⦹'=>'&#10681;','⊕'=>'&#8853;','⩔'=>'&#10836;','∨'=>'&#8744;','↻'=>'&#8635;','⩝'=>'&#10845;','⊶'=>'&#8886;','⩖'=>'&#10838;','⩗'=>'&#10839;','⩛'=>'&#10837;','⊘'=>'&#8856;','⨷'=>'&#10807;','⊗'=>'&#8855;','⨶'=>'&#10806;','⌽'=>'&#9021;','‾'=>'&#8254;','⏞'=>'&#9182;','⎴'=>'&#9140;','⏜'=>'&#9180;','¶'=>'&#182;','∥'=>'&#8741;','⫳'=>'&#10995;','⫽'=>'&#11005;','∂'=>'&#8706;','⊥'=>'&#8869;','☎'=>'&#9742;','⋔'=>'&#8916;','ϖ'=>'&#982;','⨣'=>'&#10787;','⊞'=>'&#8862;','⨢'=>'&#10786;','∔'=>'&#8724;','⨥'=>'&#10789;','⩲'=>'&#10866;','±'=>'&#177;','±'=>'&#177;','⨦'=>'&#10790;','⨧'=>'&#10791;','⪻'=>'&#10939;','≺'=>'&#8826;','⪷'=>'&#10935;','⪳'=>'&#10931;','⪯'=>'&#10927;','≺'=>'&#8826;','⪷'=>'&#10935;','≼'=>'&#8828;','≺'=>'&#8826;','⪯'=>'&#10927;','≾'=>'&#8830;','⪯'=>'&#10927;','⪹'=>'&#10937;','⪵'=>'&#10933;','⋨'=>'&#8936;','≾'=>'&#8830;','″'=>'&#34;','′'=>'&#8242;','⪹'=>'&#10937;','⪵'=>'&#10933;','⋨'=>'&#8936;','⌒'=>'&#8978;','⌓'=>'&#8979;','∝'=>'&#8733;','∷'=>'&#8759;','∝'=>'&#8733;','∝'=>'&#8733;','≾'=>'&#8830;','⊰'=>'&#8880;','⁗'=>'&#8279;','≟'=>'&#8799;','⇛'=>'&#8667;','∽̱'=>'',' √'=>'&#8730;','⦳'=>'&#10675;','⟫'=>'&#10219;','⟩'=>'&#9002;','⦒'=>'&#10642;','⦥'=>'&#10661;','⟩'=>'&#9002;','↠'=>'&#8608;','⇒'=>'&#8658;','→'=>'&#8594;','⥵'=>'&#10613;','⇥'=>'&#8677;','⤠'=>'&#10528;','⤳'=>'&#10547;','⤞'=>'&#10526;','↪'=>'&#8618;','↬'=>'&#8620;','⥅'=>'&#10565;','⥴'=>'&#10612;','⤖'=>'&#10518;','↣'=>'&#8611;','↝'=>'&#8605;','⤜'=>'&#10524;','⤚'=>'&#10522;','⤐'=>'&#10512;','⤏'=>'&#10511;','⤍'=>'&#10509;','⤷'=>'&#10551;','⥩'=>'&#10601;','↳'=>'&#8627;','∋'=>'&#8715;','⇋'=>'&#8651;','⥯'=>'&#10607;','⥽'=>'&#10621;','⌋'=>'&#8971;','⥤'=>'&#10596;','⇁'=>'&#8641;','⇀'=>'&#8640;','⥬'=>'&#10604;','⟩'=>'&#9002;','→'=>'&#8594;','⇒'=>'&#8658;','→'=>'&#8594;','⇥'=>'&#8677;','⇄'=>'&#8644;','↣'=>'&#8611;','⌉'=>'&#8969;','⟧'=>'&#10215;','⥝'=>'&#10589;','⇂'=>'&#8642;','⥕'=>'&#10581;','⌋'=>'&#8971;','⇁'=>'&#8641;','⇀'=>'&#8640;','⇄'=>'&#8644;','⇌'=>'&#8652;','⇉'=>'&#8649;','↝'=>'&#8605;','⊢'=>'&#8866;','↦'=>'&#8614;','⥛'=>'&#10587;','⋌'=>'&#8908;','⊳'=>'&#8883;','⧐'=>'&#10704;','⊵'=>'&#8885;','⥏'=>'&#10575;','⥜'=>'&#10588;','↾'=>'&#8638;','⥔'=>'&#10580;','⇀'=>'&#8640;','⥓'=>'&#10579;','˚'=>'&#730;','≓'=>'&#8787;','⇄'=>'&#8644;','⇌'=>'&#8652;','‏'=>'&#8207;','⎱'=>'&#9137;','⎱'=>'&#9137;','⫮'=>'&#10990;','⟭'=>'&#10221;','⇾'=>'&#8702;','⟧'=>'&#10215;','⦆'=>'&#10630;','ℝ'=>'&#8477;','𝕣'=>'&#120163;','⨮'=>'&#10798;','⨵'=>'&#10805;','⥰'=>'&#10608;','⦔'=>'&#10644;','⨒'=>'&#10770;','⇉'=>'&#8667;','⇛'=>'&#8667;','↱'=>'&#8625;','⋌'=>'&#8908;','⋊'=>'&#8906;','▹'=>'&#9657;','⊵'=>'&#8885;','▸'=>'&#9656;','⧎'=>'&#10702;','⧴'=>'&#10740;','⥨'=>'&#10600;','⪼'=>'&#10940;','≻'=>'&#8827;','⪸'=>'&#10936;','≽'=>'&#8829;','⪴'=>'&#10932;','⪰'=>'&#10928;','⪺'=>'&#10938;','⪶'=>'&#10934;','⋩'=>'&#8937;','⨓'=>'&#10771;','≿'=>'&#8831;','⋅'=>'&#8901;','⊡'=>'&#8865;','⩦'=>'&#10854;','⤥'=>'&#10533;','⇘'=>'&#8664;','↘'=>'&#8600;','↘'=>'&#8600;','§'=>'&#167;','⤩'=>'&#10537;','✶'=>'&#10038;','⌢'=>'&#8994;','♯'=>'&#9839;','↓'=>'&#8595;','←'=>'&#8592;','∣'=>'&#8739;','∥'=>'&#8741;','→'=>'&#8594;','↑'=>'&#8593;','¬'=>'&#172;','∼'=>'&#8764;','⩪'=>'&#10858;','≃'=>'&#8771;','⪞'=>'&#10910;','⪠'=>'&#10912;','⪝'=>'&#10909;','⪟'=>'&#10911;','≆'=>'&#8774;','⨤'=>'&#10788;','⥲'=>'&#10610;','←'=>'&#8592;','∘'=>'&#8728;','⨳'=>'&#10803;','⧤'=>'&#10724;','⌣'=>'&#8995;','⪪'=>'&#10922;','⪬'=>'&#10924;','⪬︀'=>'&#65024;','⧄'=>'&#10692;','⌿'=>'&#9023;','♠'=>'&#9824;','∥'=>'&#8741;','⊓'=>'&#8851;','⊓︀'=>'&#65024;','⊔'=>'&#8852;','⊔︀'=>'&#65024;','√'=>'&#8730;','⊑'=>'&#8849;','⊏'=>'&#8847;','⊑'=>'&#8849;','⊒'=>'&#8850;','⊐'=>'&#8848;','□'=>'&#9633;','□'=>'&#9633;','⊓'=>'&#8851;','⊏'=>'&#8847;','⊑'=>'&#8849;','⊐'=>'&#8848;','⊒'=>'&#8850;','⊔'=>'&#8852;','▪'=>'&#9642;','→'=>'&#8594;','⌣'=>'&#8995;','⋆'=>'&#8902;','☆'=>'&#9734;','★'=>'&#9733;','¯'=>'&#175;','⋐'=>'&#8912;','⊂'=>'&#8834;','⪽'=>'&#10941;','⊆'=>'&#8838;','⫃'=>'&#10947;','⫁'=>'&#10945;','⫋'=>'&#10955;','⊊'=>'&#8842;','⪿'=>'&#10943;','⥹'=>'&#10617;','⋐'=>'&#8912;','⊂'=>'&#8834;','⊆'=>'&#8838;','⫅'=>'&#10949;','⊆'=>'&#8838;','⊊'=>'&#8842;','⫋'=>'&#10955;','⫇'=>'&#10951;','⫕'=>'&#10965;','⫓'=>'&#10963;','≻'=>'&#8827;','⪸'=>'&#10936;','≽'=>'&#8829;','≻'=>'&#8827;','⪰'=>'&#10928;','≽'=>'&#8829;','≿'=>'&#8831;','⪰'=>'&#10928;','⪺'=>'&#10938;','⪶'=>'&#10934;','⋩'=>'&#8937;','≿'=>'&#8831;','∋'=>'&#8715;','♪'=>'&#9834;','⋑'=>'&#8913;','⊃'=>'&#8835;','⪾'=>'&#10942;','⫘'=>'&#10968;','⫆'=>'&#10950;','⊇'=>'&#8839;','⫄'=>'&#10948;','⊃'=>'&#8835;','⟉'=>'&#10185;','⫗'=>'&#10967;','⥻'=>'&#10619;','⫂'=>'&#10946;','⫌'=>'&#10956;','⊋'=>'&#8843;','⫀'=>'&#10944;','⋑'=>'&#8913;','⊃'=>'&#8835;','⊇'=>'&#8839;','⫆'=>'&#10950;','⊋'=>'&#8843;','⫈'=>'&#10952;','⫔'=>'&#10964;','⫖'=>'&#10966;','⤦'=>'&#10534;','⇙'=>'&#8665;','↙'=>'&#8601;','⤪'=>'&#10538;','⌖'=>'&#8982;','⎴'=>'&#9140;','◌⃛'=>'&#8411;','⌕'=>'&#8981;','∴'=>'&#8756;','≈'=>'&#8776;','∼'=>'&#8764;','∼'=>'&#8764;','˜'=>'&#732;','≃'=>'&#8771;','≅'=>'&#8773;','≈'=>'&#8776;','×'=>'&#215;','⊠'=>'&#8864;','⨱'=>'&#10801;','⨰'=>'&#10800;','⤨'=>'&#10536;','⊤'=>'&#8868;','⌶'=>'&#9014;','⫱'=>'&#10993;','⫚'=>'&#10970;','⤩'=>'&#10537;','‴'=>'&#8244;','▵'=>'&#9653;','▿'=>'&#9663;','◃'=>'&#9667;','⊴'=>'&#8884;','≜'=>'&#8796;','▹'=>'&#9657;','⊵'=>'&#8885;','◬'=>'&#9708;','≜'=>'&#8796;','⨺'=>'&#10810;','◌⃛'=>'&#8411;','⨹'=>'&#10809;','⧍'=>'&#10701;','⨻'=>'&#10811;','⏢'=>'&#9186;','≬'=>'&#8812;','↞'=>'&#8606;','↠'=>'&#8608;','↟'=>'&#8607;','⇑'=>'&#8657;','↑'=>'&#8593;','⥉'=>'&#10569;','⇅'=>'&#8645;','⥮'=>'&#10606;','⥣'=>'&#10595;','↿'=>'&#8639;','↾'=>'&#8638;','▀'=>'&#9600;','⌜'=>'&#8988;','⌏'=>'&#8975;','◸'=>'&#9720;','¨'=>'&#168;','⏟'=>'&#9183;','⎵'=>'&#9141;','⏝'=>'&#9181;','⋃'=>'&#8899;','⊎'=>'&#8846;','𝕌'=>'&#120140;','𝕦'=>'&#120166;','↑'=>'&#8593;','⇑'=>'&#8657;','↑'=>'&#8593;','⤒'=>'&#10514;','⇅'=>'&#8645;','↕'=>'&#8597;','⇕'=>'&#8661;','↕'=>'&#8597;','⥮'=>'&#10606;','↿'=>'&#8639;','↾'=>'&#8638;','⊎'=>'&#8846;','↖'=>'&#8598;','↗'=>'&#8599;','⊥'=>'&#8869;','↥'=>'&#8613;','⇈'=>'&#8648;','⌝'=>'&#8989;','⌎'=>'&#8974;','◹'=>'&#9721;','⋰'=>'&#8944;','▵'=>'&#9653;','▴'=>'&#9652;','⇈'=>'&#8648;','⦧'=>'&#10663;','⦜'=>'&#10652;','ϵ'=>'&#1013;','⇕'=>'&#8661;','↕'=>'&#8597;','⊊︀'=>'',' ⫋︀'=>'',' ⊋︀'=>'',' ⫌︀'=>'',' ⊲'=>'&#8882;','⊳'=>'&#8883;','⫫'=>'&#10987;','⫨'=>'&#10985;','⫩'=>'&#10978;','⊫'=>'&#8875;','⊩'=>'&#8873;','⊨'=>'&#8872;','⊢'=>'&#8866;','⫦'=>'&#10982;','⋁'=>'&#8897;','∨'=>'&#8744;','⊻'=>'&#8891;','≚'=>'&#8794;','⋮'=>'&#8942;','‖'=>'&#8214;','|'=>'&#124;','∣'=>'&#8739;','|'=>'&#124;','❘'=>'&#10072;','≀'=>'&#8768;','⊲'=>'&#8882;','∝'=>'&#8733;','⊳'=>'&#8883;','⊪'=>'&#8874;','⦚'=>'&#10650;','⩟'=>'&#10847;','⋀'=>'&#8896;','∧'=>'&#8743;','≙'=>'&#8793;','⋂'=>'&#8898;','◯'=>'&#9711;','⋃'=>'&#8899;','▽'=>'&#9661;','⟺'=>'&#10234;','⟷'=>'&#10231;','⟸'=>'&#10232;','⟵'=>'&#10229;','⟼'=>'&#10236;','⋻'=>'&#8955;','⨀'=>'&#10752;','⨁'=>'&#10753;','⨂'=>'&#10754;','⟹'=>'&#10233;','⟶'=>'&#10230;','⨆'=>'&#10758;','⨄'=>'&#10756;','△'=>'&#9651;','⋁'=>'&#8897;','⋀'=>'&#8896;','⇝'=>'&#10239;','‰'=>'&#8240;','‱'=>'&#8241;','∞'=>'&#8734; '];

    // Application of the conversion
    if ($htmlFormat === "numeric") {
        $text = str_replace(array_keys($htmlEntitiesMapNumeric), array_values($htmlEntitiesMapNumeric), $text);
    } else {
        $text = str_replace(array_keys($htmlEntitiesMapMnemonic), array_values($htmlEntitiesMapMnemonic), $text);
    }

    // **Converting email addresses into mailto links**
    if ($convertEmails === "yes") {
        // 1. Normalises disguised emails into standard format
        $text = preg_replace_callback(
            '/\b([a-zA-Z0-9._%+-]+)\s*(\(|\[|\{)?\s*(?:a|at)\s*(\)|\]|\})?\s*([a-zA-Z0-9.-]+\.[a-zA-Z0-9-]{2,})\b/ui',
            function ($matches) {
                return $matches[1] . '@' . $matches[4];
            },
            $text
        );

        // 2. It recognises and converts emails into links, with a false-positive filter
        $text = preg_replace_callback(
            '/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z0-9-]{2,})([.,!?;:]?)(?=\s|$)/u',
            function ($matches) {
                $email = $matches[1];
                $endingPunctuation = $matches[2];

                // Split email
                [$local, $domain] = explode('@', $email, 2);

                // Anti-false positive: the domain must contain at least one dot and at least one alphabetic character
                if (
                    substr_count($domain, '.') === 0 ||
                    !preg_match('/[a-zA-Z]/', $domain)
                ) {
                    return $email . $endingPunctuation; // it is not a real email
                }

                // Emergency exit
                $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                return '<a href="mailto:' . $safeEmail . '">' . $safeEmail . '</a>' . $endingPunctuation;
            },
            $text
        );
    }

// **Converting URLs into links**
if ($convertLinks === "yes") {

    // 1. Normalise obfuscated URLs
    $patterns = [
        '/\bhxxps?\b/i',
        '/\[\s*(dot|\.)\s*\]/i',
        '/\(\s*(dot|\.)\s*\)/i',
        '/\[\s*slash\s*\]/i',
        '/\(\s*slash\s*\)/i',
    ];

    $replacements = [
        function ($m) {
            return strtolower($m[0]) === 'hxxps' ? 'https' : 'http';
        },
        '.',
        '.',
        '/',
        '/',
    ];

    foreach ($patterns as $k => $pattern) {
        $text = preg_replace_callback(
            $pattern,
            is_callable($replacements[$k])
                ? $replacements[$k]
                : fn() => $replacements[$k],
            $text
        );
    }

    // 2. Convert URLs into links
    $text = preg_replace_callback(
        '~\b((?:https?://|www\.)[^\s<]+[^\s<\.,!?;:])~iu',
        function ($matches) {

            $url = $matches[1];

            // avoid email
            if (filter_var($url, FILTER_VALIDATE_EMAIL)) {
                return $matches[0];
            }

            // full href
            $href = (stripos($url, 'www.') === 0)
                ? 'http://' . $url
                : $url;

            // URL validation
            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                return $matches[0];
            }

            // HTML escape
            $safeUrl = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $displayText = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $displayText . '</a>';
        },
        $text
    );
}

    // **Converting bare URLs into links**
if ($convertBareDomains === "yes") {
    // List (partial but common) of valid TLDs
    $validTLDs = [
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'info', 'biz', 'io', 'co', 'me', 'tv', 'ai', 'dev',
        'it', 'fr', 'de', 'es', 'ru', 'jp', 'cn', 'us', 'uk', 'ch', 'nl', 'ca', 'au', 'br', 'in', 'pl', 'cz', 'sk'
    ];

    // Create a typical regex: (com|org|net|...)
    $tldPattern = implode('|', array_map('preg_quote', $validTLDs));

    // Updated regex: matches name + . + valid TLD
    $text = preg_replace_callback(
        '/(?<!@|\w)(?<!:\/\/)\b([a-z0-9-]+)(?:\s|&nbsp;)?\.(' . $tldPattern . ')([.,!?;:]?)(?=\s|$)/i',
        function ($matches) {
            $domain = $matches[1] . '.' . $matches[2];
            $punctuation = $matches[3];

            // Security: avoid duplicate links (by using a global variable as the context)
            if (preg_match('/<a[^>]*>' . preg_quote($domain, '/') . '<\/a>/i', $GLOBALS['text'])) {
                return $matches[0];
            }

            $href = 'https://' . $domain;
            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $domain . '</a>' . $punctuation;
        },
        $text
    );
}

// **Converting phone numbers into tel links**
if ($convertPhones === "yes") {

    $text = preg_replace_callback(

        '~(?<!\w)
        (
            (?:\+?\d{1,4}[\s.-]*)?          # optional international dialling code
            (?:\(\+?\d{1,4}\)[\s.-]*)?     # optional area code in brackets
            (?:\(?\d{2,5}\)?[\s.-]*)?      # optional local area code
            \d(?:[\s.-]*\d){5,}            # main body of the phone number
        )
        (?!\w)~ux',

        function ($matches) {
            $fullMatch = $matches[0];
            $numberPart = trim($matches[1]);

            // Text preceding the score in the full match
            $prefixText = substr(
                $fullMatch,
                0,
                strpos($fullMatch, $numberPart)
            );

            $isPhone = false;

            // 1. Explicit indicators such as "Tel." / "Cell." / "Тел."
            if (
                preg_match(
                    '/(?:Tel\.?|Cell\.?|Тел\.?|тел\.?)\s?:?\s*$/ui',
                    $prefixText
                )
            ) {
                $isPhone = true;
            }

            // 2. phone number with +
            elseif (preg_match('/^\+/', $numberPart)) {
                $isPhone = true;
            }

            // 3. phone number like "(+39)333123456"
            elseif (preg_match('/^\(\+\d{1,4}\)/u', $numberPart)) {
                $isPhone = true;
            }

            // 4. phone number like "+39 (333) 123456"
            elseif (preg_match('/^\+\d{1,4}\s*\(\d{1,4}\)/u', $numberPart)) {
                $isPhone = true;
            }

            // 5. additional security: at least 9 digits in total
            elseif (
                strlen(preg_replace('/\D/u', '', $numberPart)) >= 9
            ) {
                $isPhone = true;
            }

            // if it doesn't look like a phone → leave everything as it is
            if (!$isPhone) {
                return $fullMatch;
            }

            // =========================
            // Normalisation of href
            // =========================

            // Special case: "(+39)333123456"
            if (
                preg_match('/^\(\+(\d{1,4})\)/u', $numberPart, $parts)
            ) {

                $internationalPrefix = $parts[1];

                $rest = preg_replace('/^\(\+\d{1,4}\)/u', '', $numberPart);

                $rest = preg_replace('/[^\d]/u', '', $rest);

                $telHref = '+' . $internationalPrefix . $rest;
            }

            // General case
            else {

                $telHref = preg_replace('/[^\d+]/u', '', $numberPart);

                // If it doesn't start with +
                if (
                    $telHref !== '' &&
                    $telHref[0] !== '+'
                ) {
                    $telHref = '+' . $telHref;
                }
            }

            // HTML escape
            $safeHref = htmlspecialchars(
                $telHref,
                ENT_QUOTES,
                'UTF-8'
            );

            $safeText = htmlspecialchars(
                $numberPart,
                ENT_QUOTES,
                'UTF-8'
            );

            return '<a href="tel:' . $safeHref . '">' . $safeText . '</a>';
        },

        $text
    );
}

function convertExponents($text) {
    // protect all HTML mnemonic entities
    $text = preg_replace_callback('/&[a-zA-Z0-9]+;/', function($matches) {
        return '§§' . base64_encode($matches[0]) . '§§';
    }, $text);

    // 1) Powers written as "10^3", "e^2", etc.
    $pattern1 = '/(\d+|e|\p{L})\^(\d+)/u';
    $replacement1 = '$1<sup>$2</sup>';

    // 2) Numbers that follow a letter or a closing bracket and are followed by a space or punctuation mark
    $pattern2 = '/(?<=[\p{L}\)])(\d+)(?=[\s.,;:!?])/u';
    $replacement2 = '<sup>$1</sup>';

    // let’s apply both patterns
    $text = preg_replace($pattern1, $replacement1, $text);
    $text = preg_replace($pattern2, $replacement2, $text);

    // let’s restore the protected entities
    $text = preg_replace_callback('/§§(.*?)§§/', function($matches) {
        return base64_decode($matches[1]);
    }, $text);

    return $text;
}

$text = convertExponents($text);

function correggiPunteggiatura($text) {
    // splits the text into paragraphs (using \n as the logical separator)
    $paragraphs = preg_split('/(\R+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    foreach ($paragraphs as $i => $para) {
        // apply the correction only to the content, not to the line breaks (\n)
        if (preg_match('/^\R+$/u', $para)) continue;

        // 1. Protection of URLs / emails
        $matches = [];
        preg_match_all('/(?:https?:\/\/|www\.)[^\s]+
    |
    [a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}
    |
    \b[a-zA-Z0-9-]+\.(?:com|org|net|edu|gov|mil|int|info|biz|io|co|me|tv|ai|dev|it|fr|de|es|ru|jp|cn|us|uk|ch|nl|ca|au|br|in|pl|cz|sk)\b
    /ixu', $para, $matches);
        $placeholders = [];
        foreach ($matches[0] as $j => $match) {
            $placeholder = "###URL{$i}_{$j}###";
            $placeholders[$placeholder] = $match;
            $para = str_replace($match, $placeholder, $para);
        }

        // 2. Protection of company names
        $sigle_societarie = [
            'S\.p\.A\.', 'S\.P\.A\.', 'S\.r\.l\.', 'S\.R\.L\.', 'S\.n\.c\.', 'S\.N\.C\.', 'S\.a\.s\.', 'S\.A\.S\.',
            'S\.c\.a\.r\.l\.', 'S\.C\.A\.R\.L\.', 'S\.c\.p\.a\.', 'S\.C\.P\.A\.', 'S\.a\.p\.a\.', 'S\.A\.P\.A\.',
            'S\.r\.l\.s\.', 'S\.R\.L\.S\.', 'SPA', 'SRL', 'SNC', 'SAS', 'SCARL', 'SCPA', 'SAPA', 'SRLS',
            'ООО', 'АО', 'ЗАО', 'ИП', 'Ltd\.', 'PLC', 'Inc\.', 'Corp\.', 'GmbH', 'AG', 'UG',
            'SARL', 'SA', 'SAS', 'S\.A\.', 'S\.L\.', 'AS', 'AB', 'OY', 'ApS', 's\.r\.o\.', 'sp\.z o\.o\.',
            'Zrt\.', 'Kft\.', 'S\.A\.S\.', 'S\.A\.R\.L\.', 'Pvt\.Ltd\.', 'LLP'
        ];
        $regex_sigle = implode('|', $sigle_societarie);
        $para = preg_replace_callback("/(?<!\w)($regex_sigle)(?!\w)/u", function ($m) {
            return str_replace('.', '§§PUNTO§§', $m[1]);
        }, $para);

        // 3. Protection of special abbreviations
        $eccezioni = [
            'P.P.S.' => '§PPS§', 'P. P. S.' => '§PPS§',
			'P.S.' => '§PS§', 'P. S.' => '§PS§',
			
			'З.З.Ы.' => '§ZZY§', 'З. З. Ы.' => '§ZZY§',
			'З.Ы.' => '§ZY§', 'З. Ы.' => '§ZY§',
			
            'н.э.' => '§NE§', 'н. э.' => '§NE§',
            'т.п.' => '§TP§', 'т. п.' => '§TP§',
            'т.д.' => '§TD§', 'т. д.' => '§TD§',
            'т.ч.' => '§TCH§', 'т. ч.' => '§TCH§',
            'P.I.' => '§PI§', 'P. I.' => '§PI§',
            'C.F.' => '§CF§', 'C. F.' => '§CF§',
            'P.Iva' => '§PIV§', 'P. Iva' => '§PIV§'
        ];
        $para = str_replace(array_keys($eccezioni), array_values($eccezioni), $para);

        // 4. Initials (one or more) followed by a capital letter (Latin or Cyrillic)
        $para = preg_replace_callback('/\b((?:\p{Lu}\.\s*){1,})(?=\p{Lu})/u', function($m) {
            return preg_replace('/(\p{Lu})\.\s*/u', '$1.&nbsp;', $m[1]);
        }, $para);

        // 5. Removes spaces before punctuation marks
        $punteggiatura = '.,:;!?';
        $para = preg_replace('/\s+([' . preg_quote($punteggiatura, '/') . '])/', '$1', $para);

        // 6. Spaces after punctuation marks if followed by a letter or number
		$para = preg_replace_callback(
    '/([' . preg_quote($punteggiatura, '/') . '])(?=[\p{L}\p{N}])/u',
    function ($m) use (&$para) {

        $pos = strpos($para, $m[1]);

        if ($pos !== false) {
            $before = substr($para, 0, $pos + 1);

            // If we are inside an HTML element, DO NOT add a space
            if (preg_match('/&(?:[a-z0-9]+|#\d+);$/iu', $before)) {
                return $m[1];
            }
        }

        return $m[1] . ' ';
    },
    $para
);

        // 7. Spaces around brackets
		$para = preg_replace('/(?<!^)(?<!\s)(?<!>)([\(\[])/u', ' $1', $para);
        $para = preg_replace('/([\(\[])\s+/', '$1', $para);
        $para = preg_replace('/\s+([\)\]])/', '$1', $para);
        $para = preg_replace('/([\)\]])([' . preg_quote($punteggiatura, '/') . '])/u', '$1$2', $para);
        $para = preg_replace('/([\)\]])\s*-/', '$1&nbsp;-', $para);
		$para = preg_replace('/([\)\]])(?!$|[' . preg_quote($punteggiatura, '/') . ']|<)/u', '$1 ', $para);

        // 8. Restore special abbreviations
        $para = str_replace([
            '§PS§', '§PPS§', '§ZY§', '§ZZY§', '§NE§', '§TP§', '§TD§', '§TCH§', '§PIV§',
        ], [
            'P.S.', 'P.P.S.', 'З.Ы.', 'З.З.Ы.', 'н.&nbsp;э.', 'т.&nbsp;п.', 'т.&nbsp;д.', 'т.&nbsp;ч.', 'P.Iva&nbsp;'
        ], $para);

        // 9. Restore VAT number and tax reference number
        $para = preg_replace('/§PI§\s*(\d+)/u', 'P.I.&nbsp;$1', $para);
        $para = preg_replace('/§CF§\s*(\d+)/u', 'C.F.&nbsp;$1', $para);

        // Restore abbreviations for VAT number and tax code without a number
        $para = str_replace(['§PI§', '§CF§'], ['P.I.', 'C.F.'], $para);

        // 10. Restoring full marks in company abbreviations
        $para = str_replace('§§PUNTO§§', '.', $para);

        // 11. Do not leave a space between the hyphen and the comma
        $para = preg_replace('/-\s+,/', '-,', $para);

        // 12. Reset URL/email
        $para = str_replace(array_keys($placeholders), array_values($placeholders), $para);

        $paragraphs[$i] = $para;
    }

    // Reconstructs the full text using the original separators (\n, \r\n, etc.)
    return implode('', $paragraphs);
}
    $text = correggiPunteggiatura($text);

    // **Convert numbered lists (1., 2., 3.) or lettered lists (a., b., c., a), b), c))**
	function convertLists($text) {
        $lines = explode("\n", $text);
        $output = "";
        $inList = false;
        $isOrdered = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+|[a-z])(\.|\))\s+(.*)/i', $line, $matches)) {
                if (!$inList) {
                    $isOrdered = is_numeric($matches[1]); // If it is a number, use `<ol>`, otherwise use `<ul>`
                    $output .= $isOrdered ? "<ol>\n" : "<ul type=\"a\">\n";
                    $inList = true;
                }
                $output .= "<li>{$matches[3]}</li>\n"; // Text of the list item
            } else {
                if ($inList) {
                    $output .= $isOrdered ? "</ol>\n" : "</ul>\n";
                    $inList = false;
                }
                $output .= $line . "\n";
            }
        }

        if ($inList) {
            $output .= $isOrdered ? "</ol>\n" : "</ul>\n";
        }

        return $output;
    }

    if ($convertLists === "yes") { $text = convertLists($text); }

    // **6. Restore the text between "<" and ">" without altering it**
    $text = str_replace(array_keys($placeholders), array_values($placeholders), $text);

    // **Restoring the original HTML code**
    $text = str_replace(array_keys($htmlEntities), array_values($htmlEntities), $text);

    // **Line breaks**
    if ($lineBreaks === "br") {
        $text = nl2br($text);
    } elseif ($lineBreaks === "p") {
        $paragraphs = explode("\n", trim($text));
        $text = "<p>" . implode("</p>\n<p>", array_filter($paragraphs)) . "</p>";
    }

    // **Final spacing adjustments**
    $text = str_replace(' &nbsp;', '&nbsp;', $text);
	$text = str_replace('&nbsp; ', '&nbsp;', $text);
	$text = str_replace('  ', ' ', $text);

    echo $text;
}
?>