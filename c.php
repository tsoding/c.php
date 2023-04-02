<?php declare(strict_types=1);

function todo(string $message) : void {
    throw new ErrorException("TODO: " . $message);
}

class Loc {
    public function __construct(
		public string $file_path,
		public int    $row,
		public int    $col
	) {}

    public function display() : string {
        return sprintf("%s:%d:%d", $this->file_path, $this->row + 1, $this->col + 1);
    }
}

// #include <stdio.h>\nint main(void) {\n...
//                     ^      ^

enum TokenType {
	case NAME;
	case OPAREN;
	case CPAREN;
	case OCURLY;
	case CCURLY;
	case SEMICOLON;
	case NUMBER;
	case STRING;
	case RETURN;
}

class Token {
    public function __construct(
		public Loc        $loc,
		public TokenType  $type,
		public int|string $value
	) {}
}

class Lexer {
    public int $cur;
    public int $bol;
    public int $row;

    public function __construct(
			public string $file_path,
			public string $source
		) {
        $this->cur = 0;
        $this->bol = 0;
        $this->row = 0;
    }

    public function is_not_empty() : bool {
        return $this->cur < strlen($this->source);
    }

    public function is_empty() : bool {
        return !$this->is_not_empty();
    }

    public function chop_char() : void {
        if ($this->is_not_empty()) {
            $x = $this->source[$this->cur];
            $this->cur += 1;
            if ($x === "\n") {
                $this->bol = $this->cur;
                $this->row += 1;
            }
        }
    }

    public function loc() : ?Loc {
        return new Loc($this->file_path, $this->row, $this->cur - $this->bol);
    }

    public function trim_left() : void {
        while ($this->is_not_empty() && ctype_space($this->source[$this->cur])) {
            $this->chop_char();
        }
    }

    public function drop_line() : void {
        while ($this->is_not_empty() && $this->source[$this->cur] !== "\n") {
            $this->chop_char();
        }
        if ($this->is_not_empty()) {
            $this->chop_char();
        }
    }

    public function next_token() : ?Token {
        $this->trim_left();
        while ($this->is_not_empty() && $this->source[$this->cur] === "#") {
            $this->drop_line();
            $this->trim_left();
        }

        if ($this->is_empty()) {
            return null;
        }

        $loc   = $this->loc();
        $first = $this->source[$this->cur];

        if (ctype_alpha($first)) {
            $index = $this->cur;
            while ($this->is_not_empty() && ctype_alnum($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = substr($this->source, $index, $this->cur - $index);
            return new Token($loc, TokenType::NAME, $value);
        }

        $literal_tokens = [
            "(" => TokenType::OPAREN,
            ")" => TokenType::CPAREN,
            "{" => TokenType::OCURLY,
            "}" => TokenType::CCURLY,
            ";" => TokenType::SEMICOLON,
		];

        if (isset($literal_tokens[$first])) {
            $this->chop_char();
            return new Token($loc, $literal_tokens[$first], $first);
        }

        if ($first === '"') {
            $this->chop_char();
            $start = $this->cur;
            while ($this->is_not_empty() && $this->source[$this->cur] !== '"') {
                $this->chop_char();
            }

            if ($this->is_not_empty()) {
                $value = substr($this->source, $start, $this->cur - $start);
                $this->chop_char();
                return new Token($loc, TokenType::STRING, $value);
            }

            echo sprintf("%s: ERROR: unclosed string literal\n", $loc->display());
            return null;
        }

        if (ctype_digit($first)) {
            $start = $this->cur;
            while ($this->is_not_empty() && ctype_digit($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = (int)substr($this->source, $start, $this->cur - $start);
            return new Token($loc, TokenType::NUMBER, $value);
        }

        todo("next_token");
    }
}

enum Type {
	case INT;
}

class FuncallStmt {
    public function __construct(
		public Token $name,
		public array $args
	) {}
}

class RetStmt {

    public function __construct(
    	public int $expr
	) {}
}

class Func {
    public function __construct(
		public $name,
		public $body
	) {}
}

function expect_token(Lexer $lexer, TokenType ...$types) : ?Token {
    $token = $lexer->next_token();

    if (!$token) {
        echo sprintf("%s: ERROR: expected %s but got end of file\n", 
            $lexer->loc()->display(), $type);
        return null;
    }

    foreach($types as $type) {
        if ($token->type === $type) {
            return $token;
        }
    }

    echo sprintf("%s: ERROR: expected %s but got %s\n", 
        $lexer->loc()->display(),
        join(" or ", $types),
        $token->type
	);
 
	return null;
}

function parse_type(Lexer $lexer) : ?Type {
    $return_type = expect_token($lexer, TokenType::NAME);
    if ($return_type->value !== "int") {
        echo sprintf("%s: ERROR: unexpected type %s", 
            $return_type->loc->display(),
            $return_type->value);
        return null;
    }
    return Type::INT;
}

function parse_arglist(Lexer $lexer) : ?array {
    if (!expect_token($lexer, TokenType::OPAREN)) return null;
    $arglist = [];
    while (true) {
        $expr = expect_token($lexer, TokenType::STRING, TokenType::NUMBER, TokenType::CPAREN);
        if (!$expr) return null;
        if ($expr->type == TokenType::CPAREN) break;
        array_push($arglist, $expr->value);
    }
    return $arglist;
}

function parse_block(Lexer $lexer) : ?array {
    if (!expect_token($lexer, TokenType::OCURLY)) return null;

    $block = [];

    while (true) {
        $name = expect_token($lexer, TokenType::NAME, TokenType::CCURLY);
        if (!$name) return null;
        if ($name->type == TokenType::CCURLY) break;

        if ($name->value == "return") {
            $expr = expect_token($lexer, TokenType::NUMBER, TokenType::STRING);
            if (!$expr) return null;
            array_push($block, new RetStmt($expr->value));
        } else {
            $arglist = parse_arglist($lexer);
            if (!$arglist) return null;
            array_push($block, new FuncallStmt($name, $arglist));
        }

        if (!expect_token($lexer, TokenType::SEMICOLON)) return null;
    }

    return $block;
}

function parse_function(Lexer $lexer) : ?Func {
    $return_type = parse_type($lexer);
    if (!$return_type) return null;
    assert($return_type === Type::INT);

    $name = expect_token($lexer, TokenType::NAME);
    if (!$name) return null;

    if (!expect_token($lexer, TokenType::OPAREN)) return false;
    if (!expect_token($lexer, TokenType::CPAREN)) return false;

    $body = parse_block($lexer);

    return new Func($name, $body);
}

if ($argc < 2) {
    echo "ERROR: no input is provided\n";
    exit(69);
}

$file_path = $argv[1];
$source    = file_get_contents($file_path);

if (!$source) exit(69);

$lexer = new Lexer($file_path, $source);
$func  = parse_function($lexer);
if (!$func) exit(69);

foreach($func->body as $stmt) {
    if ($stmt instanceof FuncallStmt) {
        if ($stmt->name->value === "printf") {
            echo sprintf("print(\"%s\")\n", join(", ", $stmt->args));
        } else {
            echo sprintf("%s: ERROR: unknown function %s\n", 
                $stmt->name->loc->display(),
                $stmt->name->value);
            exit(69);
        }
    }
}