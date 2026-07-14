<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final readonly class RuleSet
{
    /**
     * @param list<Rule>    $rules
     * @param list<string>  $disabled
     */
    public function __construct(
        public array $rules,
        public array $disabled = [],
        public bool $strict = true,
    ) {}

    /**
     * @param list<string> $disabled
     */
    public static function default(array $disabled = [], bool $strict = true): self
    {
        return new self([], $disabled, $strict);
    }

    public function withRule(Rule $rule): self
    {
        return new self([...$this->rules, $rule], $this->disabled, $this->strict);
    }

    /** @return list<Rule> */
    public function rules(): array { return $this->rules; }

    /** @return list<Rule> */
    public function activeRules(): array
    {
        return array_values(array_filter(
            $this->rules,
            fn(Rule $r) => !in_array($r->code(), $this->disabled, true),
        ));
    }

    public function isStrict(): bool { return $this->strict; }
}
