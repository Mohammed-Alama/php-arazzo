# Laravel Arazzo — AI Generator Design

**Status**: Draft
**Created**: 2026-07-14
**Package**: `alama/laravel-arazzo`
**Namespace**: `Alama\LaravelArazzo\Generator`
**Slice**: AI-assisted Arazzo Spec Generation.

---

## 1. Goals & Non-Goals

### Goals

- Generate Arazzo `1.0.0` YAML workflows from OpenAPI specifications and natural-language goals.
- Implement an LLM-agnostic `LlmDriver` interface.
- Use a "Reflection Loop": Validate the LLM's generated YAML using our existing `Arazzo::validate()`, feeding errors back to the LLM until the document is valid.
- Expose the generator via a CLI command and a programmatic API (for future React Flow UI integration).

### Non-Goals

- Building the React Flow UI itself (that is a separate layer).
- Generating OpenAPI specs (we only consume them to generate Arazzo).
- Hardcoding a specific LLM SDK (we will use a generic HTTP client to hit standard chat completion APIs).

---

## 2. Architecture & Interfaces

The Generator relies on a prompt builder, an LLM driver, and an orchestrator that manages the reflection loop.

```
┌─────────────────────────────────────────────────────────┐
│                    ArazzoGenerator                      │
│   (Orchestrator: manages the reflection loop)           │
└────────┬─────────────────────┬─────────────────┬────────┘
         │                     │                 │
┌────────▼────────┐   ┌────────▼────────┐   ┌────▼──────┐
│  PromptBuilder  │   │    LlmDriver    │   │ Validator │
│  (Extracts OpenAPI  │   (Interface)   │   │  (Core)   │
│   & formats prompt) │                 │   └───────────┘
└─────────────────┘   └────────┬────────┘
                               │
                      ┌────────▼────────┐
                      │ DefaultHttpDriver │
                      └─────────────────┘
```

### Core Interfaces

```php
interface LlmDriver
{
    /**
     * Sends messages to the LLM and returns the string response.
     */
    public function chat(array $messages): string;
}

class ArazzoGenerator
{
    /**
     * @throws GenerationException
     */
    public function generate(string $openApiPath, string $goal): string;
}
```

---

## 3. The Reflection Loop

The true power of this generator is its ability to self-correct using the strict Arazzo Validator.

1. **Initial Prompt**: `PromptBuilder` loads the OpenAPI document, extracts available endpoints, and combines this with the `$goal` and a `system_prompt`.
2. **Generation**: `LlmDriver` generates the initial YAML.
3. **Validation**: The generator writes the YAML to memory/temp file and runs `Arazzo::validate()`.
4. **Reflection**: 
   - If `ValidationResult->isValid()` is true, return the YAML.
   - If false, the generator formats the validation errors (code, message, path) and sends a follow-up message: *"Your document failed validation. Fix these errors and return the full YAML: [Errors]"*.
5. **Termination**: The loop repeats up to `config('arazzo.generator.max_retries', 3)`. If it still fails, the generator can either throw a `GenerationException` or return the flawed YAML wrapped in a result object for manual correction.

---

## 4. CLI & Framework Integration

### CLI Command

```
php artisan arazzo:generate {openapi} {--goal=} {--output=}
```
- If `--goal` is missing, it interactively prompts the user.
- Displays a console spinner while generating and iterating through the reflection loop.
- Writes the valid Arazzo YAML to the specified output path (or stdout).

### Facade API

```php
Arazzo::generate(string $openApiPath, string $goal): string;
```
This programmatic entry point will be used by the future React Flow UI backend to request AI generation of workflows based on UI interactions.

### Configuration

`config/arazzo.php` will be updated to include generator settings:

```php
'generator' => [
    'driver' => 'http',
    'http' => [
        'endpoint' => env('ARAZZO_LLM_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('ARAZZO_LLM_API_KEY'),
        'model' => env('ARAZZO_LLM_MODEL', 'gpt-4o'),
    ],
    'max_retries' => 3,
    'system_prompt' => '...', // Publishable for user customization
],
```

---

## 5. Dependencies

No new heavy dependencies are strictly required.
- Laravel's `Http` facade is sufficient for the `DefaultHttpDriver`.
- We already have `cebe/php-openapi` from the SourceResolver slice to parse and summarize the OpenAPI document for the prompt.
