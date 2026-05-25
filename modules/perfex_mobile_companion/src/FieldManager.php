<?php

namespace MPC;

use Exception;
use MPC\Fields\AudioField;
use MPC\Fields\BadgeField;
use MPC\Fields\DateField;
use MPC\Fields\Field;
use MPC\Fields\JsonField;
use MPC\Fields\PhoneField;

class FieldManager
{
    private static $instance = null;
    protected static $fields = [];

    private function __construct() {}

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function register(string $name, string $class)
    {
        if (!is_subclass_of($class, Field::class)) {
            throw new Exception("Class {$class} must extend Field.");
        }

        self::$fields[$name] = $class;
    }

    public static function get($name, string $column, string $label = null, array $options = [])
    {
        if (!isset(self::$fields[$name])) {
            throw new \Exception("Field {$name} not registered.");
        }

        // Pass the required arguments when creating the field instance
        return new self::$fields[$name]($column, $label, $options);
    }
    public static function all()
    {
        return self::$fields;
    }

    // Automatically register all field classes
    public static function autoRegister()
    {
        $path = __DIR__ . '/Fields/';
        foreach (glob($path . '*.php') as $file) {
            $class = "MPC\\\Fields\\" . pathinfo($file, PATHINFO_FILENAME);
            if (is_subclass_of($class, Field::class)) {
                self::register(strtolower(pathinfo($file, PATHINFO_FILENAME)), $class);
            }
        }
    }
}

FieldManager::register('audio', AudioField::class);
FieldManager::register('badge', BadgeField::class);
FieldManager::register('date', DateField::class);
FieldManager::register('json', JsonField::class);
FieldManager::register('phone', PhoneField::class);
