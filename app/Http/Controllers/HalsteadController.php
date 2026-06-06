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
        $fileName = 'CodeInput';

        if ($request->filled('code_text')) {
            $code = $request->input('code_text');

            // Baca nama file asli dari hidden input jika ada
            if ($request->filled('file_name')) {
                $fileName = $request->input('file_name');
            } else {
                if ($language === 'php') $fileName = 'CodeInput.php';
                elseif ($language === 'javascript') $fileName = 'CodeInput.js';
                elseif ($language === 'python') $fileName = 'CodeInput.py';
                elseif ($language === 'c_cpp_java') $fileName = 'CodeInput.cpp';
                else $fileName = 'CodeInput.txt';
            }
        } elseif ($request->hasFile('source_code')) {
            $file = $request->file('source_code');
            $code = file_get_contents($file->getRealPath());
            $fileName = $file->getClientOriginalName();
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

        try {
            $result = $this->analyzeHalstead($code, $language, $fileName);
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Terjadi kesalahan saat menganalisis: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return view('halstead.index', $result);
    }

    private function analyzeHalstead($code, $language, $fileName = 'source_code', $parseFunctions = true)
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

        $strippedCode = $code;

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

        $result = [
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

        // Parse functions and compute metrics if requested
        if ($parseFunctions) {
            $extracted = $this->extractFunctions($strippedCode, $language);
            $functionsData = [];

            foreach ($extracted as $func) {
                $fCC = $this->calculateCyclomaticComplexity($func['code']);
                // Call analyzeHalstead with $parseFunctions = false to prevent recursion
                $fHalstead = $this->analyzeHalstead($func['code'], $language, $fileName, false);
                $fV = $fHalstead['V'];

                $lnV = log(max(1.0, $fV));
                $lnLOC = log(max(1.0, $func['loc']));
                $miRaw = 171 - (5.2 * $lnV) - (0.23 * $fCC) - (16.2 * $lnLOC);
                $fMI = max(0, min(100, round(($miRaw * 100) / 171)));

                $fStatus = 'GOOD';
                if ($fMI < 50) {
                    $fStatus = 'BAD';
                } elseif ($fMI < 65) {
                    $fStatus = 'WARN';
                }

                $functionsData[] = [
                    'file' => $fileName,
                    'name' => $func['name'],
                    'cc' => $fCC,
                    'hv' => round($fV),
                    'mi' => $fMI,
                    'status' => $fStatus
                ];
            }

            $totalFunctions = count($functionsData);
            if ($totalFunctions > 0) {
                $sumCC = 0;
                $maxCC = 0;
                $sumMI = 0;
                foreach ($functionsData as $f) {
                    $sumCC += $f['cc'];
                    $sumMI += $f['mi'];
                    if ($f['cc'] > $maxCC) {
                        $maxCC = $f['cc'];
                    }
                }
                $avgCC = round($sumCC / $totalFunctions, 1);
                $avgMI = round($sumMI / $totalFunctions);
            } else {
                // If no functions are found, treat the whole file as a single function block
                $avgCC = $this->calculateCyclomaticComplexity($strippedCode);
                $maxCC = $avgCC;

                $lnV = log(max(1.0, $V));
                $linesCount = count(explode("\n", trim($strippedCode)));
                $lnLOC = log(max(1.0, $linesCount));
                $miRaw = 171 - (5.2 * $lnV) - (0.23 * $avgCC) - (16.2 * $lnLOC);
                $avgMI = max(0, min(100, round(($miRaw * 100) / 171)));
                $totalFunctions = 1;

                $functionsData[] = [
                    'file' => $fileName,
                    'name' => 'global_scope',
                    'cc' => $avgCC,
                    'hv' => round($V),
                    'mi' => $avgMI,
                    'status' => ($avgMI < 50) ? 'BAD' : (($avgMI < 65) ? 'WARN' : 'GOOD')
                ];
            }

            $fileStatus = 'GOOD';
            if ($avgMI < 50) {
                $fileStatus = 'BAD';
            } elseif ($avgMI < 65) {
                $fileStatus = 'WARN';
            }

            $filesData = [
                [
                    'file' => $fileName,
                    'functions' => $totalFunctions,
                    'avgCC' => $avgCC,
                    'maxCC' => $maxCC,
                    'avgMI' => $avgMI,
                    'estBugs' => round($B, 3),
                    'status' => $fileStatus
                ]
            ];

            $result['functions'] = $functionsData;
            $result['files'] = $filesData;
        }

        return $result;
    }

    private function extractFunctions($code, $language)
    {
        $functions = [];

        // Normalize line endings FIRST
        $code = str_replace("\r\n", "\n", $code);
        // Calculate length AFTER normalization
        $len = strlen($code);

        // Auto detect language
        if ($language === 'auto') {
            if (strpos($code, '<?php') !== false || strpos($code, 'namespace ') !== false) {
                $language = 'php';
            } elseif (strpos($code, 'def ') !== false && strpos($code, ':') !== false) {
                $language = 'python';
            } else {
                $language = 'javascript';
            }
        }

        $skipNames = ['if', 'while', 'for', 'foreach', 'switch', 'catch', 'elseif', 'else',
                      'function', 'class', 'try', 'do', 'match', 'finally'];

        if (in_array($language, ['php', 'javascript', 'c_cpp_java'])) {
            // Pola yang lebih sederhana: cari nama diikuti ( ... ) dan { — hindari backtracking
            $pattern = '/\b(function|public|private|protected|static|async)\s+(?:function\s+)?([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]{0,500}\)\s*(?::[^{]{0,100})?\s*\{/u';

            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[2] as $index => $nameMatch) {
                    $funcName = $nameMatch[0];
                    $matchOffset = $nameMatch[1];

                    if (in_array($funcName, $skipNames)) {
                        continue;
                    }

                    // Cari posisi { yang mengikuti pattern ini
                    $fullMatch = $matches[0][$index][0];
                    $bracePos = $matches[0][$index][1] + strlen($fullMatch) - 1;

                    if ($bracePos >= $len) continue;

                    // Telusuri matching closing brace
                    $braceCount = 1;
                    $i = $bracePos + 1;
                    $safeLimit = min($len, $bracePos + 50000); // Batasi 50KB per fungsi

                    while ($i < $safeLimit && $braceCount > 0) {
                        $char = $code[$i];
                        if ($char === '{') {
                            $braceCount++;
                        } elseif ($char === '}') {
                            $braceCount--;
                        }
                        $i++;
                    }

                    if ($braceCount === 0) {
                        $funcBody = substr($code, $bracePos, $i - $bracePos);
                        $lines = explode("\n", trim($funcBody));
                        $loc = count(array_filter($lines, function($line) {
                            return trim($line) !== '';
                        }));

                        $functions[] = [
                            'name' => $funcName,
                            'code' => $funcBody,
                            'loc'  => max(1, $loc)
                        ];
                    }
                }
            }

            // Juga tangkap fungsi standalone: function nama(...)
            $pattern2 = '/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]{0,500}\)\s*\{/u';
            if (preg_match_all($pattern2, $code, $matches2, PREG_OFFSET_CAPTURE)) {
                $existingNames = array_column($functions, 'name');
                foreach ($matches2[1] as $index => $nameMatch) {
                    $funcName = $nameMatch[0];
                    if (in_array($funcName, $skipNames)) continue;
                    if (in_array($funcName, $existingNames)) continue; // Jangan duplikat

                    $fullMatch = $matches2[0][$index][0];
                    $bracePos = $matches2[0][$index][1] + strlen($fullMatch) - 1;

                    if ($bracePos >= $len) continue;

                    $braceCount = 1;
                    $i = $bracePos + 1;
                    $safeLimit = min($len, $bracePos + 50000);

                    while ($i < $safeLimit && $braceCount > 0) {
                        $char = $code[$i];
                        if ($char === '{') $braceCount++;
                        elseif ($char === '}') $braceCount--;
                        $i++;
                    }

                    if ($braceCount === 0) {
                        $funcBody = substr($code, $bracePos, $i - $bracePos);
                        $lines = explode("\n", trim($funcBody));
                        $loc = count(array_filter($lines, fn($l) => trim($l) !== ''));

                        $functions[] = [
                            'name' => $funcName,
                            'code' => $funcBody,
                            'loc'  => max(1, $loc)
                        ];
                        $existingNames[] = $funcName;
                    }
                }
            }

        } elseif ($language === 'python') {
            $lines = explode("\n", $code);
            $numLines = count($lines);
            for ($i = 0; $i < $numLines; $i++) {
                $line = $lines[$i];
                if (preg_match('/^\s*def\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*:/u', $line, $matches)) {
                    $funcName = $matches[1];

                    preg_match('/^\s*/u', $line, $indentMatch);
                    $baseIndent = strlen($indentMatch[0]);

                    $funcBodyLines = [$line];
                    $j = $i + 1;
                    while ($j < $numLines) {
                        $nextLine = $lines[$j];
                        if (trim($nextLine) === '') {
                            $funcBodyLines[] = $nextLine;
                            $j++;
                            continue;
                        }
                        preg_match('/^\s*/u', $nextLine, $nextIndentMatch);
                        $nextIndent = strlen($nextIndentMatch[0]);
                        if ($nextIndent > $baseIndent) {
                            $funcBodyLines[] = $nextLine;
                            $j++;
                        } else {
                            break;
                        }
                    }

                    $funcBody = implode("\n", $funcBodyLines);
                    $loc = count(array_filter($funcBodyLines, fn($l) => trim($l) !== ''));

                    $functions[] = [
                        'name' => $funcName,
                        'code' => $funcBody,
                        'loc'  => max(1, $loc)
                    ];
                }
            }
        }

        return $functions;
    }

    private function calculateCyclomaticComplexity($code)
    {
        $cc = 1;

        // Hitung decision points dari keyword
        $count = preg_match_all('/\b(if|elseif|elif|while|for|foreach|case|catch)\b/u', $code, $m);
        $cc += ($count !== false) ? $count : 0;

        // Hitung logical operators
        $count2 = preg_match_all('/&&|\|\||\?\?/u', $code, $m2);
        $cc += ($count2 !== false) ? $count2 : 0;

        // Ternary operator (spasi ? spasi)
        $count3 = preg_match_all('/\s\?\s/u', $code, $m3);
        $cc += ($count3 !== false) ? $count3 : 0;

        return max(1, $cc);
    }
}