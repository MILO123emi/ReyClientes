<?php

namespace Database\Factories\Concerns;

trait FakesHonduranData
{
    protected function fakeFullName(): string
    {
        $names = ['Ana', 'José', 'María', 'Carlos', 'Sofía', 'Luis', 'Lucía', 'Óscar', 'Rosa', 'Jorge', 'Carmen', 'Daniel'];
        $surnames = ['García', 'López', 'Hernández', 'Martínez', 'Rodríguez', 'Flores', 'Mejía', 'Cruz', 'Reyes', 'Castro', 'Zelaya', 'Pineda'];

        return fake()->randomElement($names).' '.fake()->randomElement($surnames).' '.fake()->randomElement($surnames);
    }

    protected function fakeDni(): string
    {
        return sprintf('%02d%02d%04d%05d',
            fake()->numberBetween(1, 18),
            fake()->numberBetween(1, 20),
            fake()->numberBetween(1950, 2008),
            fake()->unique()->numberBetween(1, 99999),
        );
    }

    protected function fakePhone(): string
    {
        return fake()->randomElement(['3', '8', '9']).sprintf('%07d', fake()->numberBetween(0, 9999999));
    }
}
