<?php

function todo($message) {
    throw new ErrorException("TODO: " . $message);
}

function php7_str_starts_with($haystack, $needle)
{
    return strpos($haystack, $needle) === 0;
}

function php7_str_ends_with($haystack, $needle)
{
    $count = strlen($needle);
    if ($count === 0) {
        return true;
    }
    return substr($haystack, -$count) === $needle;
}

class Loc {
    public $file_path;
    public $row;
    public $col;

    public function __construct($file_path, $row, $col) {
        $this->file_path = $file_path;
        $this->row = $row;
        $this->col = $col;
    }

    public function display() {
        return sprintf("%s:%d:%d", $this->file_path, $this->row + 1, $this->col + 1);
    }
}

// #include <stdio.h>\nint main(void) {\n...
//                     ^      ^
define("TOKEN_NAME", "TOKEN_NAME");
define("TOKEN_OPAREN", "TOKEN_OPAREN");
define("TOKEN_CPAREN", "TOKEN_CPAREN");
define("TOKEN_OCURLY", "TOKEN_OCURLY");
define("TOKEN_CCURLY", "TOKEN_CCURLY");
define("TOKEN_COMMA", "TOKEN_COMMA");
define("TOKEN_SEMICOLON", "TOKEN_SEMICOLON");
define("TOKEN_NUMBER", "TOKEN_NUMBER");
define("TOKEN_STRING", "TOKEN_STRING");
define("TOKEN_RETURN", "TOKEN_RETURN");

class Token {
    public $type;
    public $value;
    public $loc;

    public function __construct($loc, $type, $value) {
        $this->loc = $loc;
        $this->type = $type;
        $this->value = $value;
    }
}

class Lexer {
    public $file_path;
    public $source;
    public $cur;
    public $bol;
    public $row;

    public function __construct($file_path, $source) {
        $this->file_path = $file_path;
        $this->source = $source;
        $this->cur = 0;
        $this->bol = 0;
        $this->row = 0;
    }

    function is_not_empty() {
        return $this->cur < strlen($this->source);
    }

    function is_empty() {
        return !$this->is_not_empty();
    }

    function chop_char() {
        if ($this->is_not_empty()) {
            $x = $this->source[$this->cur];
            $this->cur += 1;
            if ($x === "\n") {
                $this->bol = $this->cur;
                $this->row += 1;
            }
        }
    }

    function loc() {
        return new Loc($this->file_path, $this->row, $this->cur - $this->bol);
    }

    function trim_left() {
        while ($this->is_not_empty() && ctype_space($this->source[$this->cur])) {
            $this->chop_char();
        }
    }

    function drop_line() {
        while ($this->is_not_empty() && $this->source[$this->cur] !== "\n") {
            $this->chop_char();
        }
        if ($this->is_not_empty()) {
            $this->chop_char();
        }
    }

    function next_token() {
        $this->trim_left();
        while ($this->is_not_empty()) {
            $s = substr($this->source, $this->cur);
            if (!php7_str_starts_with($s, "#") && !php7_str_starts_with($s, "//")) break;
            $this->drop_line();
            $this->trim_left();
        }

        if ($this->is_empty()) {
            return false;
        }

        $loc = $this->loc();
        $first = $this->source[$this->cur];

        if (ctype_alpha($first)) {
            $index = $this->cur;
            while ($this->is_not_empty() && ctype_alnum($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = substr($this->source, $index, $this->cur - $index);
            return new Token($loc, TOKEN_NAME, $value);
        }

        $literal_tokens = array(
            "(" => TOKEN_OPAREN,
            ")" => TOKEN_CPAREN,
            "{" => TOKEN_OCURLY,
            "}" => TOKEN_CCURLY,
            "," => TOKEN_COMMA,
            ";" => TOKEN_SEMICOLON,
        );
        if (isset($literal_tokens[$first])) {
            $this->chop_char();
            return new Token($loc, $literal_tokens[$first], $first);
        }

        if ($first === '"') {
            $this->chop_char();
            $start = $this->cur;
            $literal = "";
            while ($this->is_not_empty()) {
                $ch = $this->source[$this->cur];
                switch ($ch) {
                case '"': break 2;
                case '\\': {
                    $this->chop_char();
                    if ($this->is_empty()) {
                        print("{$lexer->loc()}: ERROR: unfinished escape sequence\n");
                        exit(69);
                    }

                    $escape = $this->source[$this->cur];
                    switch ($escape) {
                    case 'n':
                        $literal .= "\n";
                        $this->chop_char();
                        break;

                    case '"':
                        $literal .= "\"";
                        $this->chop_char();
                        break;

                    default:
                        print("{$this->loc()}: ERROR: unknown escape sequence starts with {$escape}\n");
                    }

                } break;

                default:
                    $literal .= $ch;
                    $this->chop_char();
                }
            }

            if ($this->is_not_empty()) {
                $this->chop_char();
                return new Token($loc, TOKEN_STRING, $literal);
            }

            echo sprintf("%s: ERROR: unclosed string literal\n", $loc->display());
            exit(69);
        }

        if (ctype_digit($first)) {
            $start = $this->cur;
            while ($this->is_not_empty() && ctype_digit($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = (int)substr($this->source, $start, $this->cur - $start);
            return new Token($loc, TOKEN_NUMBER, $value);
        }

        print("{$loc->display()}: ERROR: unknown token starts with {$first}\n");
        exit(69);
    }
}

define("TYPE_INT", "TYPE_INT");

class FuncallStmt {
    public $name;
    public $args;

    public function __construct($name, $args) {
        $this->name = $name;
        $this->args = $args;
    }
}

class RetStmt {
    public $expr;

    public function __construct($expr) {
        $this->expr = $expr;
    }
}

class Func {
    public $name;
    public $body;

    public function __construct($name, $body) {
        $this->name = $name;
        $this->body = $body;
    }
}

function expect_token($lexer, ...$types) {
    $token = $lexer->next_token();
    if (!$token) {
        echo sprintf("%s: ERROR: expected %s but got end of file\n",
            $lexer->loc()->display(), join(" or ", $types));
        return false;
    }

    foreach($types as &$type) {
        if ($token->type === $type) {
            return $token;
        }
    }

    echo sprintf("%s: ERROR: expected %s but got %s\n",
        $lexer->loc()->display(),
        join(" or ", $types),
        $token->type);
    return false;
}

function parse_type($lexer) {
    $return_type = expect_token($lexer, TOKEN_NAME);
    if ($return_type->value !== "int") {
        echo sprintf("%s: ERROR: unexpected type %s",
            $return_type->loc->display(),
            $return_type->value);
        return false;
    }
    return TYPE_INT;
}

function parse_arglist($lexer) {
    if (!expect_token($lexer, TOKEN_OPAREN)) return false;
    $arglist = array();

    // First argument (optional).
    $expr = expect_token($lexer, TOKEN_STRING, TOKEN_NUMBER, TOKEN_CPAREN);
    if (!$expr) return false;
    if ($expr->type == TOKEN_CPAREN) {
        // Call with no arguments.
        return $arglist;
    }
    array_push($arglist, $expr->value);

    // Second, third, etc. arguments (optional).
    while (true) {
        $expr = expect_token($lexer, TOKEN_CPAREN, TOKEN_COMMA);
        if (!$expr) return false;
        if ($expr->type == TOKEN_CPAREN) break;

        $expr = expect_token($lexer, TOKEN_STRING, TOKEN_NUMBER);
        if (!$expr) return false;
        array_push($arglist, $expr->value);
    }

    return $arglist;
}

function parse_block($lexer) {
    if (!expect_token($lexer, TOKEN_OCURLY)) return false;

    $block = array();

    while (true) {
        $name = expect_token($lexer, TOKEN_NAME, TOKEN_CCURLY);
        if (!$name) return false;
        if ($name->type == TOKEN_CCURLY) break;

        if ($name->value == "return") {
            $expr = expect_token($lexer, TOKEN_NUMBER, TOKEN_STRING);
            if (!$expr) return false;
            array_push($block, new RetStmt($expr->value));
        } else {
            $arglist = parse_arglist($lexer);
            if (!$arglist) return false;
            array_push($block, new FuncallStmt($name, $arglist));
        }

        if (!expect_token($lexer, TOKEN_SEMICOLON)) return false;
    }

    return $block;
}

function parse_function($lexer) {
    $return_type = parse_type($lexer);
    if (!$return_type) return false;
    assert($return_type === TYPE_INT);

    $name = expect_token($lexer, TOKEN_NAME);
    if (!$name) return false;

    if (!expect_token($lexer, TOKEN_OPAREN)) return false;
    if (!expect_token($lexer, TOKEN_CPAREN)) return false;

    $body = parse_block($lexer);

    return new Func($name, $body);
}

function generate_python3($func) {
    function literal_to_py($value) {
        if (is_string($value)) {
            return "\"" . str_replace("\n", "\\n", $value) . "\"";
        } else {
            return (string)$value;
        }
    }

    foreach($func->body as &$stmt) {
        if ($stmt instanceof FuncallStmt) {
            if ($stmt->name->value === "printf") {
                $format = $stmt->args[0];
                if (count($stmt->args) <= 1) {
                    if (php7_str_ends_with($format, "\\n")) {
                        // Optimization: print("x") is faster than print("x\n", end="").
                        $format_without_newline = substr($format, 0, strlen($format) - 2);
                        echo sprintf("print(%s)\n", literal_to_py($format_without_newline));
                    } else {
                        // Optimization: Don't invoke Python's % operator if it's unnecessary.
                        echo sprintf("print(%s, end=\"\")\n", literal_to_py($format));
                    }
                } else {
                    $substitutions = " % (";
                    foreach ($stmt->args as $i => $arg) {
                        if ($i === 0) continue;  // Skip format string.
                        $substitutions .= literal_to_py($arg) . ",";
                    }
                    $substitutions .= ")";
                    echo sprintf("print(%s%s, end=\"\")\n", literal_to_py($format), $substitutions);
                }
            } else {
                echo sprintf("%s: ERROR: unknown function %s\n",
                    $stmt->name->loc->display(),
                    $stmt->name->value);
                exit(69);
            }
        }
    }
}

function generate_fasm_x86_64_linux($func) {
    print("format ELF64 executable 3\n");
    print("segment readable executable\n");
    print("entry start\n");
    print("start:\n");
    $strings = array();
    foreach($func->body as &$stmt) {
        if ($stmt instanceof RetStmt) {
            print("    mov rax, 60\n");
            print("    mov rdi, {$stmt->expr}\n");
            print("    syscall\n");
        } else if ($stmt instanceof FuncallStmt) {
            if ($stmt->name->value === "printf") {
                $arity = count($stmt->args);
                if ($arity !== 1) {
                    print("{$stmt->name->loc->display()}: ERROR: expected 1 argument but got {$arity}\n");
                    exit(69);
                }

                $format = $stmt->args[0];
                $type = gettype($format);
                if ($type !== "string") {
                    print("{$stmt->name->loc->display()}: ERROR: expected string argument but got {$type}\n");
                    exit(69);
                }

                $n = count($strings);
                $m = strlen($format);
                print("    mov rax, 1\n");
                print("    mov rdi, 1\n");
                print("    mov rsi, str_{$n}\n");
                print("    mov rdx, {$m}\n");
                print("    syscall\n");

                array_push($strings, $format);
            } else {
                echo sprintf("%s: ERROR: unknown function %s\n",
                    $stmt->name->loc->display(),
                    $stmt->name->value);
                exit(69);
            }
        } else {
            die("unreachable");
        }
    }

    print("segment readable writable\n");
    foreach($strings as $n => $string) {
        print("str_{$n} db ");
        $m = strlen($string);
        for ($i = 0; $i < $m; ++$i) {
            $c = ord($string[$i]);
            if ($i > 0) print(",");
            print("{$c}");
        }
        print("\n");
    }
}

function main($argv) {
    $platforms = array(
        "python3",
        "fasm-x86_64-linux"
    );

    $program = array_shift($argv);
    $input = null;
    $platform = $platforms[0];

    while (sizeof($argv) > 0) {
        $flag = array_shift($argv);
        switch ($flag) {
        case "-target": {
            if (sizeof($argv) === 0) {
                print("ERROR: no value was provided for flag $flag\n");
                exit(69);
            }

            $arg = array_shift($argv);

            if ($arg === "list") {
                print("Available targets:\n");
                foreach ($platforms as $p) {
                    print("    $p\n");
                }
                exit(69);
            }

            if (in_array($arg, $platforms)) {
                $platform = $arg;
            } else {
                print("ERROR: unknown target $arg\n");
                exit(69);
            }
        } break;
        default: {
            $input = $flag;
        }
        }
    }

    if ($input === null) {
        echo "ERROR: no input is provided\n";
        exit(69);
    }

    $file_path = $input;
    $source = file_get_contents($file_path);
    if (!$source) exit(69);
    $lexer = new Lexer($file_path, $source);
    $func = parse_function($lexer);
    if (!$func) exit(69);

    switch ($platform) {
    case "python3": generate_python3($func); break;
    case "fasm-x86_64-linux": generate_fasm_x86_64_linux($func); break;
    default: todo("unreachable"); break;
    }
}

main($argv);
