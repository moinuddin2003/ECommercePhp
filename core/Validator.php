<?php
/**
 * core/Validator.php
 * ------------------------------------------------
 * Small, chainable validator for form data. Not fancy —
 * just enough to catch bad input before it reaches the DB.
 *
 * USAGE:
 *   $v = new Validator();
 *   $v->required($_POST['email'], 'email')
 *     ->email($_POST['email'], 'email')
 *     ->required($_POST['password'], 'password')
 *     ->minLength($_POST['password'], 'password', 6);
 *
 *   if ($v->passes()) {
 *       // safe to continue
 *   } else {
 *       $errors = $v->errors(); // ['email' => 'Enter a valid email', ...]
 *   }
 */

class Validator
{
    private $errors = [];

    public function required($value, $field, $label = null)
    {
        $label = $label ?? $field;
        if (trim((string) $value) === '') {
            $this->errors[$field] = ucfirst($label) . ' is required';
        }
        return $this;
    }

    public function email($value, $field)
    {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Enter a valid email address';
        }
        return $this;
    }

    public function minLength($value, $field, $min, $label = null)
    {
        $label = $label ?? $field;
        if (strlen((string) $value) < $min) {
            $this->errors[$field] = ucfirst($label) . " must be at least {$min} characters";
        }
        return $this;
    }

    public function matches($value, $otherValue, $field, $message = 'Values do not match')
    {
        if ($value !== $otherValue) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function numeric($value, $field, $label = null)
    {
        $label = $label ?? $field;
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = ucfirst($label) . ' must be a number';
        }
        return $this;
    }

    public function passes()
    {
        return empty($this->errors);
    }

    public function fails()
    {
        return !$this->passes();
    }

    public function errors()
    {
        return $this->errors;
    }

    /** Get a single field's error message, or null if none. */
    public function error($field)
    {
        return $this->errors[$field] ?? null;
    }
}