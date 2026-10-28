<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * Minimal RFC 5234 ABNF parser supporting exactly the subset used by the
 * vendored Arazzo runtime-expression grammars:
 *
 *   rulelist        = 1*( rule *crlf )
 *   rule            = rulename *wsp "=" *wsp elements *crlf
 *   elements        = alternation
 *   alternation     = concatenation *( *wsp "/" *wsp concatenation )
 *   concatenation   = repetition *( 1*wsp repetition )
 *   repetition      = [ repeat ] element
 *   repeat          = ( *DIGIT / *DIGIT "=" *DIGIT / *DIGIT "=" DIGIT ) /
 *                     ( DIGIT "=" DIGIT )
 *   element         = rulename / group / option / char-val / num-val /
 *                     prose-val
 *   group           = "(" *wsp alternation *wsp ")"
 *   option          = "[" *wsp alternation *wsp "]"
 *   char-val        = %x / DQUOTE *( %x / DIGIT ) DQUOTE   ; case-insensitive
 *   num-val         = %x / HEXDIG / DQUOTE HEXDIG *( DOT HEXDIG ) DQUOTE
 *   prose-val       = %x / DQUOTE *( %x / CHAR ) DQUOTE
 *
 * Unsupported constructs (e.g. prose values with free text) throw a
 * descriptive exception; there is no silent misparse.
 */
final class AbnfGrammar
{
    /** @var list<AbnfRule> */
    private array $rules = [];

    /** @var array<string, int> rule name -> index in */
    private array $ruleIndex = [];

    private function __construct(private string $source) {}

    public static function parse(string $abnf): self
    {
        $lexer = new AbnfLexer(self::stripComments($abnf));
        $grammar = new self($abnf);
        $grammar->parseRulelist($lexer);

        return $grammar;
    }

    /** Remove `;` comments, normalize line endings, and drop blank lines. */
    private static function stripComments(string $abnf): string
    {
        $abnf = str_replace("\r\n", "\n", $abnf);
        $abnf = str_replace("\r", "\n", $abnf);
        $lines = explode("\n", $abnf);
        $out = [];
        foreach ($lines as $line) {
            $semi = strpos($line, ';');
            if ($semi !== false) {
                $line = substr($line, 0, $semi);
            }
            if (trim($line) !== '') {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    public function source(): string
    {
        return $this->source;
    }

    /** @return list<AbnfRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    public function hasRule(string $name): bool
    {
        return isset($this->ruleIndex[strtolower($name)]);
    }

    public function rule(string $name): ?AbnfRule
    {
        return $this->rules[$this->ruleIndex[strtolower($name)]] ?? null;
    }

    /**
     * Returns the names of every rule referenced by any rule body but never
     * defined. Empty when the grammar is self-contained.
     *
     * @return list<string>
     */
    public function undefinedRules(): array
    {
        $defined = array_keys($this->ruleIndex);
        $referenced = [];

        foreach ($this->rules as $rule) {
            $this->collectReferences($rule->elements, $referenced);
        }

        $undefined = [];
        foreach ($referenced as $name => $_) {
            if (!isset($this->ruleIndex[strtolower($name)])) {
                $undefined[] = $name;
            }
        }

        sort($undefined);

        return $undefined;
    }

    /**
     * @param  list<mixed>  $elements
     * @param  array<string, true>  $referenced
     */
    private function collectReferences(array $elements, array &$referenced): void
    {
        foreach ($elements as $element) {
            if (is_string($element)) {
                $referenced[$element] = true;
            } elseif (is_array($element)) {
                $this->collectReferences($element, $referenced);
            }
        }
    }

    private function parseRulelist(AbnfLexer $lexer): void
    {
        while ($lexer->peek() !== null) {
            $this->rules[] = $this->parseRule($lexer);
            $this->consumeOptionalCrlf($lexer);
        }

        $this->rebuildIndex();
    }

    private function consumeOptionalCrlf(AbnfLexer $lexer): void
    {
        while ($lexer->peek() !== null && ($lexer->peek() === "\r" || $lexer->peek() === "\n")) {
            $lexer->next();
        }
    }

    private function parseRule(AbnfLexer $lexer): AbnfRule
    {
        $name = $this->expectRuleName($lexer);
        $this->expectWsp($lexer);
        $this->expectChar($lexer, '=');
        $this->expectWsp($lexer);
        $elements = $this->parseAlternation($lexer);

        return new AbnfRule($name, $elements);
    }

    private function expectRuleName(AbnfLexer $lexer): string
    {
        $ch = $lexer->peek();
        if ($ch === null || !preg_match('/[A-Za-z]/', $ch)) {
            throw new AbnfGrammarException('Expected rule name at position '.$lexer->position());
        }

        $name = '';
        while (($ch = $lexer->peek()) !== null && preg_match('/[A-Za-z0-9-]/', $ch)) {
            $name .= $ch;
            $lexer->next();
        }

        return $name;
    }

    private function expectWsp(AbnfLexer $lexer): void
    {
        while (($ch = $lexer->peek()) !== null && preg_match('/[\s]/', $ch)) {
            $lexer->next();
        }
    }

    private function expectChar(AbnfLexer $lexer, string $expected): void
    {
        $ch = $lexer->peek();
        if ($ch !== $expected) {
            throw new AbnfGrammarException(
                "Expected '{$expected}' at position ".$lexer->position().", got '".($ch ?? 'EOF')."'",
            );
        }
        $lexer->next();
    }

    /**
     * @return list<list<mixed>> one entry per concatenation (alternation branch)
     */
    private function parseAlternation(AbnfLexer $lexer): array
    {
        $elements = [];

        do {
            $this->expectWsp($lexer);
            $elements[] = $this->parseConcatenation($lexer);

            $ch = $lexer->peek();
            if ($ch === null || $ch !== '/') {
                break;
            }

            $lexer->next();
        } while (true);

        return $elements;
    }

    /**
     * @return list<mixed>
     */
    private function parseConcatenation(AbnfLexer $lexer): array
    {
        $elements = [];

        do {
            $this->expectWspNoNewline($lexer);
            $ch = $lexer->peek();
            if ($ch === null || $ch === '/' || $ch === ')' || $ch === ']' || $ch === "\n" || $ch === "\r") {
                break;
            }

            $elements[] = $this->parseRepetition($lexer);
        } while (true);

        return $elements;
    }

    /** Skip spaces and tabs only (not newlines). */
    private function expectWspNoNewline(AbnfLexer $lexer): void
    {
        while (($ch = $lexer->peek()) !== null && ($ch === ' ' || $ch === "\t")) {
            $lexer->next();
        }
    }

    /**
     * @return array{list<mixed>} | string | int | AbnfCharVal | AbnfNumVal
     */
    private function parseRepetition(AbnfLexer $lexer)
    {
        $save = $lexer->clone();

        $min = null;
        $max = null;

        $ch = $lexer->peek();
        if ($ch !== null && preg_match('/\d/', $ch)) {
            $digits = $this->readDigits($lexer);
            $min = (int) $digits;

            if ($lexer->peek() === '=') {
                $lexer->next();
                $next = $lexer->peek();
                if ($next !== null && preg_match('/\d/', $next)) {
                    $max = (int) $this->readDigits($lexer);
                } else {
                    $max = null; // n* means n or more
                }
            } elseif ($lexer->peek() === '*') {
                $lexer->next();
                if ($min === 0) {
                    $min = null; // * means zero or more
                }
            }
        } elseif ($ch === '*') {
            $lexer->next();
            $min = 0; // * means zero or more
            $max = null;
        }

        if ($min === null && $max === null) {
            $lexer->restore($save);
        }

        $element = $this->parseElement($lexer);

        if ($min === null && $max === null) {
            return $element;
        }

        return ['repeat' => [$min, $max], 'element' => $element];
    }

    private function readDigits(AbnfLexer $lexer): string
    {
        $digits = '';
        while (($ch = $lexer->peek()) !== null && preg_match('/\d/', $ch)) {
            $digits .= $ch;
            $lexer->next();
        }

        if ($digits === '') {
            throw new AbnfGrammarException('Expected digit at position '.$lexer->position());
        }

        return $digits;
    }

    /**
     * @return array{list<mixed>} | string | AbnfCharVal | AbnfNumVal
     */
    private function parseElement(AbnfLexer $lexer)
    {
        $ch = $lexer->peek();

        if ($ch === '(') {
            return $this->parseGroup($lexer);
        }

        if ($ch === '[') {
            return $this->parseOption($lexer);
        }

        if ($ch === '"') {
            return $this->parseCharVal($lexer);
        }

        if ($ch === '%') {
            return $this->parseNumVal($lexer);
        }

        // rulename
        if ($ch !== null && preg_match('/[A-Za-z]/', $ch)) {
            return $this->expectRuleName($lexer);
        }

        throw new AbnfGrammarException('Unexpected character "'.($ch ?? 'EOF').'" at position '.$lexer->position());
    }

    /**
     * @return array{list<mixed>}
     */
    private function parseGroup(AbnfLexer $lexer): array
    {
        $this->expectChar($lexer, '(');
        $this->expectWsp($lexer);
        $elements = $this->parseAlternation($lexer);
        $this->expectWsp($lexer);
        $this->expectChar($lexer, ')');

        return ['group' => $elements];
    }

    /**
     * @return array{list<mixed>}
     */
    private function parseOption(AbnfLexer $lexer): array
    {
        $this->expectChar($lexer, '[');
        $this->expectWsp($lexer);
        $elements = $this->parseAlternation($lexer);
        $this->expectWsp($lexer);
        $this->expectChar($lexer, ']');

        return ['option' => $elements];
    }

    private function parseCharVal(AbnfLexer $lexer): AbnfCharVal
    {
        $this->expectChar($lexer, '"');
        $chars = '';
        while (($ch = $lexer->peek()) !== null && $ch !== '"') {
            $chars .= $ch;
            $lexer->next();
        }
        if ($ch !== '"') {
            throw new AbnfGrammarException('Unterminated quoted string at position '.$lexer->position());
        }
        $lexer->next(); // consume closing "

        return new AbnfCharVal($chars);
    }

    private function parseNumVal(AbnfLexer $lexer): AbnfNumVal
    {
        $this->expectChar($lexer, '%');

        $ch = $lexer->peek();
        if ($ch === 'x') {
            $lexer->next();

            return new AbnfNumVal($this->readHexValues($lexer));
        }

        throw new AbnfGrammarException('Expected %x at position '.$lexer->position());
    }

    /**
     * @return array{int|string}
     */
    private function readHexValues(AbnfLexer $lexer): array
    {
        $values = [];
        do {
            $this->expectWspNoNewline($lexer);
            $hex = $this->readHexDigits($lexer);
            $this->expectWspNoNewline($lexer);

            if ($lexer->peek() === '-') {
                $lexer->next(); // consume range dash
                $this->expectWspNoNewline($lexer);
                $end = $this->readHexDigits($lexer);
                $values[] = ['from' => hexdec($hex), 'to' => hexdec($end)];
                $this->expectWspNoNewline($lexer);
            } else {
                $values[] = hexdec($hex);
            }
        } while ($lexer->peek() === '.');

        if ($lexer->peek() === '.') {
            $lexer->next(); // consume dot
            $values[] = $this->readHexDigits($lexer); // dotted range end
        }

        return $values;
    }

    private function readHexDigits(AbnfLexer $lexer): string
    {
        $hex = '';
        while (($ch = $lexer->peek()) !== null && preg_match('/[0-9A-Fa-f]/', $ch)) {
            $hex .= $ch;
            $lexer->next();
        }

        if ($hex === '') {
            throw new AbnfGrammarException('Expected hex digit at position '.$lexer->position());
        }

        return $hex;
    }

    private function rebuildIndex(): void
    {
        $this->ruleIndex = [];
        foreach ($this->rules as $i => $rule) {
            $this->ruleIndex[strtolower($rule->name)] = $i;
        }
    }
}
