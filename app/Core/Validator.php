<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Compact rule based validator: required|string|max:255 style definitions.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->run();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): Validator
    {
        return new self($data, $rules, $labels);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $validator = new self($data, $rules, $labels);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        return $validator->validated();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = array_filter(array_map('trim', explode('|', $ruleString)));
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);
            $isEmpty  = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($required && $isEmpty) {
                $this->addError($field, 'is required.');
                continue;
            }
            if ($isEmpty) {
                if ($nullable || !$required) {
                    $this->validated[$field] = $nullable && $value === '' ? null : $value;
                }
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $result = $this->applyRule($field, $name, $param, $value);
                if ($result === false) {
                    continue 2;
                }
                if ($result !== true) {
                    $value = $result;
                }
            }
            $this->validated[$field] = $value;
        }
    }

    private function applyRule(string $field, string $name, ?string $param, mixed $value): mixed
    {
        switch ($name) {
            case 'string':
                if (!is_scalar($value)) {
                    $this->addError($field, 'must be text.');
                    return false;
                }
                return (string) $value;

            case 'int':
            case 'integer':
                if (!is_int($value) && !preg_match('/^-?\\d+$/', (string) $value)) {
                    $this->addError($field, 'must be a whole number.');
                    return false;
                }
                return (int) $value;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, 'must be a number.');
                    return false;
                }
                return (float) $value;

            case 'bool':
            case 'boolean':
                return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;

            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'must be a valid email address.');
                    return false;
                }
                return strtolower((string) $value);

            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'must be a valid URL.');
                    return false;
                }
                return true;

            case 'min':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value < (float) $param) {
                        $this->addError($field, 'must be at least ' . $param . '.');
                        return false;
                    }
                } elseif (mb_strlen((string) $value) < (int) $param) {
                    $this->addError($field, 'must be at least ' . $param . ' characters.');
                    return false;
                }
                return true;

            case 'max':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value > (float) $param) {
                        $this->addError($field, 'may not be greater than ' . $param . '.');
                        return false;
                    }
                } elseif (mb_strlen((string) $value) > (int) $param) {
                    $this->addError($field, 'may not be longer than ' . $param . ' characters.');
                    return false;
                }
                return true;

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $param), 2, '0');
                if ((float) $value < (float) $low || (float) $value > (float) $high) {
                    $this->addError($field, 'must be between ' . $low . ' and ' . $high . '.');
                    return false;
                }
                return true;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, 'must be one of: ' . implode(', ', $allowed) . '.');
                    return false;
                }
                return true;

            case 'regex':
                if (!preg_match('/' . str_replace('/', '\/', (string) $param) . '/u', (string) $value)) {
                    $this->addError($field, 'has an invalid format.');
                    return false;
                }
                return true;

            case 'alpha_dash':
                if (!preg_match('/^[\p{L}\p{N}_\- ]+$/u', (string) $value)) {
                    $this->addError($field, 'may only contain letters, numbers, spaces, dashes and underscores.');
                    return false;
                }
                return true;

            case 'slug':
                if (!preg_match('/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/', (string) $value)) {
                    $this->addError($field, 'may only contain lowercase letters, numbers, dashes and underscores.');
                    return false;
                }
                return true;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, 'must be a valid date.');
                    return false;
                }
                return true;

            case 'confirmed':
                if ((string) ($this->data[$field . '_confirmation'] ?? '') !== (string) $value) {
                    $this->addError($field, 'confirmation does not match.');
                    return false;
                }
                return true;

            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, 'must be a list.');
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $this->label($field) . ' ' . $message;
        }
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }
        return ucfirst(str_replace('_', ' ', $field));
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }
}
