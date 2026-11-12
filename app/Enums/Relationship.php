<?php

namespace App\Enums;

enum Relationship: string
{
    case Son = 'hijo';
    case Daughter = 'hija';
    case Father = 'padre';
    case Mother = 'madre';
    case Husband = 'esposo';
    case Wife = 'esposa';
    case Brother = 'hermano';
    case Sister = 'hermana';
    case Grandfather = 'abuelo';
    case Grandmother = 'abuela';
    case Grandson = 'nieto';
    case Granddaughter = 'nieta';
    case Uncle = 'tio';
    case Aunt = 'tia';
    case Nephew = 'sobrino';
    case Niece = 'sobrina';
    case MaleCousin = 'primo';
    case FemaleCousin = 'prima';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Son => 'Hijo',
            self::Daughter => 'Hija',
            self::Father => 'Padre',
            self::Mother => 'Madre',
            self::Husband => 'Esposo',
            self::Wife => 'Esposa',
            self::Brother => 'Hermano',
            self::Sister => 'Hermana',
            self::Grandfather => 'Abuelo',
            self::Grandmother => 'Abuela',
            self::Grandson => 'Nieto',
            self::Granddaughter => 'Nieta',
            self::Uncle => 'Tío',
            self::Aunt => 'Tía',
            self::Nephew => 'Sobrino',
            self::Niece => 'Sobrina',
            self::MaleCousin => 'Primo',
            self::FemaleCousin => 'Prima',
            self::Other => 'Otro',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $relationship): array => [
            'value' => $relationship->value,
            'label' => $relationship->label(),
        ], self::cases());
    }
}
