<?php

namespace Corbital\Rightful\Classes;

class ConfigHandler
{
    private static ?ConfigHandler $instance = null;
    private array $config                   = [];

    // Private constructor to prevent direct instantiation
    private function __construct()
    {
        // Load default configuration from Item.php
        $this->loadDefaultConfig();
    }

    // Singleton pattern to ensure a single instance
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Set a configuration value dynamically
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    // Get a configuration value by key, with an optional default
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    // Get all configuration values
    public function getAll(): array
    {
        return $this->config;
    }

    // Load default configuration values from Item.php
    private function loadDefaultConfig(): void
    {
        $config     = [];
        $configPath = __DIR__.'/../Config/Item.php';

        if (file_exists($configPath)) {
            require $configPath;
            if (isset($config) && \is_array($config)) {
                $this->config = $config;
            } else {
                throw new \Exception('Configuration file does not define a valid config array.');
            }
        } else {
            throw new \Exception("Configuration file not found at path: {$configPath}");
        }
    }
}
