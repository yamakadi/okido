<?php

namespace App;

use Illuminate\Support\Collection;

class Pokédex extends Collection
{
    /**
     * Create a new Pokédex Instance
     *
     * @param array $properties
     * @param array|null    $pokémon
     */
    public function __construct(array $properties, ?array $pokémon = null)
    {
        if($pokémon) {
            parent::__construct(
                $this->assignPokémonData($properties, $pokémon)
            );
        } else {
            parent::__construct($properties);
        }
    }

    /**
     * @param array $properties
     * @param array $pokémon
     * @return \Illuminate\Support\Collection
     */
    private function assignPokémonData(array $properties, array $pokémon): Collection
    {
        return collect($pokémon)->mapWithKeys(function($pokémon) use ($properties) {

            $pokémon = collect($pokémon)->keyBy(function($data, $index) use ($properties) {
                return $properties[$index];
            });

            return [
                $pokémon->get('pokedex_number') => $pokémon
            ];
        });
    }
}