<?php
class Validator
{

    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePassword($password)
    {
        return strlen($password) >= PASSWORD_MIN_LENGTH;
    }

    public static function validatePhone($phone)
    {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
    }

    public static function validateRequired($value)
    {
        return !empty(trim($value));
    }

    public static function validateNumeric($value)
    {
        return is_numeric($value);
    }

    public static function validatePositiveNumber($value)
    {
        return is_numeric($value) && $value > 0;
    }

    public static function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public static function validateEnum($value, $allowedValues)
    {
        return in_array($value, $allowedValues);
    }

    public static function sanitizeString($string)
    {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeEmail($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function sanitizeFloat($value)
    {
        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function validateUserRegistration($data)
    {
        $errors = [];

        if (!self::validateRequired($data['full_name'])) {
            $errors[] = "Full name is required";
        }

        if (!self::validateEmail($data['email'])) {
            $errors[] = "Valid email is required";
        }

        if (!self::validatePassword($data['password'])) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
        }

        if (isset($data['mobile']) && !empty($data['mobile']) && !self::validatePhone($data['mobile'])) {
            $errors[] = "Valid phone number is required";
        }

        if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
            $errors[] = "Passwords do not match";
        }

        return $errors;
    }

    public static function validateTransaction($data)
    {
        $errors = [];

        if (!self::validatePositiveNumber($data['amount'])) {
            $errors[] = "Amount must be a positive number";
        }

        if (!self::validateEnum($data['type'], ['income', 'expense', 'transfer'])) {
            $errors[] = "Invalid transaction type";
        }

        if (!self::validateDate($data['transaction_date'])) {
            $errors[] = "Invalid transaction date";
        }

        if (!self::validateRequired($data['description'])) {
            $errors[] = "Description is required";
        }

        return $errors;
    }

    public static function validateBudget($data)
    {
        $errors = [];

        if (!self::validatePositiveNumber($data['amount'])) {
            $errors[] = "Budget amount must be a positive number";
        }

        if (!self::validateEnum($data['period'], ['weekly', 'monthly', 'yearly'])) {
            $errors[] = "Invalid budget period";
        }

        if (!self::validateDate($data['start_date'])) {
            $errors[] = "Invalid start date";
        }

        if (!self::validateDate($data['end_date'])) {
            $errors[] = "Invalid end date";
        }

        if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            $errors[] = "End date must be after start date";
        }

        return $errors;
    }

    public static function validateGoal($data)
    {
        $errors = [];

        if (!self::validateRequired($data['title'])) {
            $errors[] = "Goal title is required";
        }

        if (!self::validatePositiveNumber($data['target_amount'])) {
            $errors[] = "Target amount must be a positive number";
        }

        if (isset($data['target_date']) && !empty($data['target_date']) && !self::validateDate($data['target_date'])) {
            $errors[] = "Invalid target date";
        }

        if (isset($data['priority']) && !self::validateEnum($data['priority'], ['low', 'medium', 'high'])) {
            $errors[] = "Invalid priority level";
        }

        return $errors;
    }
}
