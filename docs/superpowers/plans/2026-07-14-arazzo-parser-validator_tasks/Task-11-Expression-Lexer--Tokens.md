### Task 11: Expression Lexer + Tokens

**Files:**
- Create: `src/Expression/Token.php`, `src/Expression/TokenKind.php`, `src/Expression/Lexer.php`
- Create: `src/Expression/ExpressionSyntaxException.php`
- Create: `tests/Expression/LexerTest.php`

**Interfaces:**
- Produces:
  - `TokenKind` enum: `Dollar, Dot, Hash, Slash, Name, PointerSegment, Keyword` (keywords: `inputs`, `outputs`, `steps`, `workflows`, `sourceDescriptions`, `components`, `response`, `request`, `url`, `method`, `statusCode`, `body`, `header`).
  - `Token(TokenKind $kind, string $value, int $offset)`.
  - `Lexer::tokenize(string $raw): list<Token>` — strips surrounding `{$...}` first; throws `ExpressionSyntaxException` on illegal chars.
  - `ExpressionSyntaxException extends ArazzoException`.

- [ ] **Step 1: Write failing test**

Create `tests/Expression/LexerTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\Lexer;
use Alama\LaravelArazzo\Expression\TokenKind;

it('tokenises inputs.userId', function (): void {
    $tokens = (new Lexer())->tokenize('{$inputs.userId}');
    $kinds = array_map(fn($t) => $t->kind, $tokens);
    $values = array_map(fn($t) => $t->value, $tokens);

    expect($kinds)->toBe([TokenKind::Keyword, TokenKind::Dot, TokenKind::Name])
        ->and($values)->toBe(['inputs', '.', 'userId']);
});

it('tokenises response.body with json pointer', function (): void {
    $t = (new Lexer())->tokenize('{$response.body#/data/0/id}');
    expect($t[0]->kind)->toBe(TokenKind::Keyword)
        ->and($t[0]->value)->toBe('response')
        ->and($t[2]->kind)->toBe(TokenKind::Keyword)
        ->and($t[2]->value)->toBe('body')
        ->and($t[3]->kind)->toBe(TokenKind::Hash);
});

it('tokenises steps.fetch.outputs.user', function (): void {
    $t = (new Lexer())->tokenize('{$steps.fetch.outputs.user}');
    expect(count($t))->toBe(7);
});

it('rejects missing braces', function (): void {
    (new Lexer())->tokenize('$inputs.x');
})->throws(ExpressionSyntaxException::class);

it('rejects illegal characters', function (): void {
    (new Lexer())->tokenize('{$inputs.na me}');
})->throws(ExpressionSyntaxException::class);
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Expression/TokenKind.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

enum TokenKind
{
    case Dollar;
    case Dot;
    case Hash;
    case Slash;
    case Name;
    case PointerSegment;
    case Keyword;
}
```

`src/Expression/Token.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
```

`src/Expression/ExpressionSyntaxException.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

use Alama\LaravelArazzo\Exceptions\ArazzoException;

final class ExpressionSyntaxException extends ArazzoException {}
```

`src/Expression/Lexer.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final class Lexer
{
    private const KEYWORDS = [
        'inputs', 'outputs', 'steps', 'workflows', 'sourceDescriptions',
        'components', 'response', 'request', 'url', 'method', 'statusCode',
        'body', 'header',
    ];

    /** @return list<Token> */
    public function tokenize(string $raw): array
    {
        if (!str_starts_with($raw, '{$') || !str_ends_with($raw, '}')) {
            throw new ExpressionSyntaxException(
                "Expression must be wrapped in {\$...}: {$raw}",
                '', 'expr.syntax',
            );
        }
        $inner = substr($raw, 2, -1);
        if ($inner === '') {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", '', 'expr.syntax');
        }

        $tokens = [];
        $len = strlen($inner);
        $i = 0;
        $inPointer = false;

        while ($i < $len) {
            $ch = $inner[$i];

            if ($ch === '.') { $tokens[] = new Token(TokenKind::Dot, '.', $i); $i++; continue; }
            if ($ch === '#') { $tokens[] = new Token(TokenKind::Hash, '#', $i); $i++; $inPointer = true; continue; }
            if ($ch === '/') { $tokens[] = new Token(TokenKind::Slash, '/', $i); $i++; continue; }

            if (preg_match('/[A-Za-z0-9_\-~]/', $ch) === 1) {
                $start = $i;
                while ($i < $len && preg_match('/[A-Za-z0-9_\-~]/', $inner[$i]) === 1) $i++;
                $word = substr($inner, $start, $i - $start);
                if ($inPointer) {
                    $tokens[] = new Token(TokenKind::PointerSegment, $word, $start);
                } elseif (in_array($word, self::KEYWORDS, true)) {
                    $tokens[] = new Token(TokenKind::Keyword, $word, $start);
                } else {
                    $tokens[] = new Token(TokenKind::Name, $word, $start);
                }
                continue;
            }

            throw new ExpressionSyntaxException(
                "Illegal character '{$ch}' at offset {$i} in expression: {$raw}",
                '', 'expr.syntax',
            );
        }

        return $tokens;
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: expression lexer and tokens"
```

---

