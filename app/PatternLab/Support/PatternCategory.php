<?php

namespace App\PatternLab\Support;

enum PatternCategory: string
{
    case Creational = 'creational';
    case Structural = 'structural';
    case Behavioral = 'behavioral';
    case LaravelSpecific = 'laravel-specific';

    public function name(): string
    {
        return match ($this) {
            self::Creational => 'Creational',
            self::Structural => 'Structural',
            self::Behavioral => 'Behavioral',
            self::LaravelSpecific => 'Laravel Specific',
        };
    }

    public function nameCn(): string
    {
        return match ($this) {
            self::Creational => '创建型',
            self::Structural => '结构型',
            self::Behavioral => '行为型',
            self::LaravelSpecific => 'Laravel 常用模式',
        };
    }

    public function directory(): string
    {
        return match ($this) {
            self::Creational => 'Creational',
            self::Structural => 'Structural',
            self::Behavioral => 'Behavioral',
            self::LaravelSpecific => 'LaravelSpecific',
        };
    }
}
