<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HalsteadController extends Controller
{
    public function index()
    {
        return view('halstead.index');
    }

    public function hitung(Request $request)
    {
        $code = '';
        $language = $request->input('language', 'auto');

        if ($request->filled('code_text')) {
            $code = $request->input('code_text');
        } elseif ($request->hasFile('source_code')) {
            $file = $request->file('source_code');
            $code = file_get_contents($file->getRealPath());
        } else {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Harap upload file atau masukkan kode langsung pada editor.'], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Harap upload file atau masukkan kode langsung pada editor.']);
        }

        if (trim($code) === '') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Kode program kosong.'], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Kode program kosong.']);
        }

        $result = $this->analyzeHalstead($code, $language);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return view('halstead.index', $result);
    }

    private function analyzeHalstead($code, $language)
    {
        $originalCode = $code;

        // 1. Strip comments depending on language
        // Block comments
        $code = preg_replace('/\\/\\*[\\s\\S]*?\\*\\//u', '', $code);
        // Single line comments (// and #)
        $code = preg_replace('/\\/\\/[^\\r\\n]*/u', '', $code);
        $code = preg_replace('/#[^\\r\\n]*/u', '', $code);
        // Python multi-line docstrings
        $code = preg_replace('/"""[\\s\\S]*?"""|\'\'\'[\\s\\S]*?\'\'\'/u', '', $code);

        // 2. Extract and replace String Literals
        $stringsMap = [];
        $stringCounter = 0;
        // Match double quotes, single quotes, and backticks
        $code = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`/u', function($matches) use (&$stringsMap, &$stringCounter) {
            $key = "__STR_LIT_{$stringCounter}__";
            $stringsMap[$key] = $matches[0];
            $stringCounter++;
            return ' ' . $key . ' ';
        }, $code);

        // 3. Extract and replace Numeric Literals
        $numbersMap = [];
        $numberCounter = 0;
        // Match floats, hexadecimals, and integers
        $code = preg_replace_callback('/\\b\\d+(?:\\.\\d+)?\\b|\\b0x[a-fA-F0-9]+\\b/u', function($matches) use (&$numbersMap, &$numberCounter) {
            $key = "__NUM_LIT_{$numberCounter}__";
            $numbersMap[$key] = $matches[0];
            $numberCounter++;
            return ' ' . $key . ' ';
        }, $code);

        // 4. Define operators and keywords
        $symbolicOperators = [
            '===', '!==', '==', '!=', '<=', '>=', '&&', '||', '+=', '-=', '*=', '/=', '%=', '++', '--', '->', '=>', '??', '::', '**',
            '+', '-', '*', '/', '%', '=', '<', '>', '!', '&', '|', '^', '~', '?', ':', ';', ',', '.', '(', ')', '[', ']', '{', '}'
        ];

        $keywords = [
            'if', 'else', 'elseif', 'elif', 'while', 'for', 'foreach', 'switch', 'case', 'default', 
            'break', 'continue', 'return', 'function', 'func', 'def', 'class', 'struct', 'interface',
            'public', 'private', 'protected', 'static', 'new', 'delete', 'throw', 'throws', 'try', 
            'catch', 'finally', 'except', 'raise', 'echo', 'print', 'include', 'require', 'use', 
            'namespace', 'import', 'export', 'from', 'as', 'const', 'let', 'var', 'global', 'array', 
            'isset', 'empty', 'unset', 'and', 'or', 'not', 'xor', 'is', 'in', 'of', 'await', 'async', 
            'typeof', 'instanceof', 'yield', 'with', 'assert', 'pass', 'defer', 'go'
        ];

        // 5. Construct token extraction regex
        $keywordsRegex = '\\b(' . implode('|', $keywords) . ')\\b';
        $escapedOps = array_map(function($op) {
            return preg_quote($op, '/');
        }, $symbolicOperators);
        $operatorsRegex = '(' . implode('|', $escapedOps) . ')';
        $placeholdersRegex = '(__STR_LIT_\\d+__|__NUM_LIT_\\d+__)';
        // Match PHP variables ($var) and general identifiers (var_name)
        $identifiersRegex = '(\\$[a-zA-Z_][a-zA-Z0-9_]*|[a-zA-Z_][a-zA-Z0-9_]*)';

        $combinedRegex = '/' . implode('|', [
            $keywordsRegex,
            $placeholdersRegex,
            $identifiersRegex,
            $operatorsRegex
        ]) . '/u';

        $operatorList = [];
        $operandList = [];

        if (preg_match_all($combinedRegex, $code, $matches)) {
            foreach ($matches[0] as $token) {
                $token = trim($token);
                if ($token === '') continue;

                // Check what type of token it is
                if (in_array($token, $keywords)) {
                    // Keyword Operator
                    if (!isset($operatorList[$token])) {
                        $operatorList[$token] = ['count' => 0, 'type' => 'Kata Kunci'];
                    }
                    $operatorList[$token]['count']++;
                } elseif (in_array($token, $symbolicOperators)) {
                    // Symbolic Operator
                    if (!isset($operatorList[$token])) {
                        $operatorList[$token] = ['count' => 0, 'type' => 'Simbol'];
                    }
                    $operatorList[$token]['count']++;
                } elseif (preg_match('/^__STR_LIT_(\\d+)__$/', $token, $subMatches)) {
                    // String literal operand
                    $origVal = $stringsMap[$token] ?? '""';
                    if (!isset($operandList[$origVal])) {
                        $operandList[$origVal] = ['count' => 0, 'type' => 'String Literal'];
                    }
                    $operandList[$origVal]['count']++;
                } elseif (preg_match('/^__NUM_LIT_(\\d+)__$/', $token, $subMatches)) {
                    // Numeric literal operand
                    $origVal = $numbersMap[$token] ?? '0';
                    if (!isset($operandList[$origVal])) {
                        $operandList[$origVal] = ['count' => 0, 'type' => 'Numeric Literal'];
                    }
                    $operandList[$origVal]['count']++;
                } else {
                    // General identifier / variable operand
                    $type = (strpos($token, '$') === 0) ? 'Variabel (PHP)' : 'Pengidentifikasi';
                    if (!isset($operandList[$token])) {
                        $operandList[$token] = ['count' => 0, 'type' => $type];
                    }
                    $operandList[$token]['count']++;
                }
            }
        }

        // Halstead calculations
        $n1 = count($operatorList);
        $n2 = count($operandList);

        $N1 = 0;
        foreach ($operatorList as $op) {
            $N1 += $op['count'];
        }

        $N2 = 0;
        foreach ($operandList as $opd) {
            $N2 += $opd['count'];
        }

        $N = $N1 + $N2;
        $n = $n1 + $n2;

        $V = ($n > 0) ? $N * log($n, 2) : 0;
        $D = ($n2 > 0) ? ($n1 / 2) * ($N2 / $n2) : 0;
        $E = $D * $V;
        $T = $E / 18;
        $B = $V / 3000;

        // Sort details descending by count
        uasort($operatorList, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        uasort($operandList, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Determine complexity rating
        $complexity = 'Rendah';
        $complexityColor = '#10b981'; // Green
        if ($D > 40) {
            $complexity = 'Sangat Tinggi (Kritis)';
            $complexityColor = '#ef4444'; // Red
        } elseif ($D > 20) {
            $complexity = 'Tinggi';
            $complexityColor = '#f59e0b'; // Amber
        } elseif ($D > 10) {
            $complexity = 'Sedang';
            $complexityColor = '#3b82f6'; // Blue
        }

        return [
            'n1' => $n1,
            'n2' => $n2,
            'N1' => $N1,
            'N2' => $N2,
            'N' => $N,
            'n' => $n,
            'V' => $V,
            'D' => $D,
            'E' => $E,
            'T' => $T,
            'B' => $B,
            'operatorsDetail' => $operatorList,
            'operandsDetail' => $operandList,
            'complexity' => $complexity,
            'complexityColor' => $complexityColor,
            'language' => $language,
            'codeLength' => strlen($originalCode)
        ];
    }
}