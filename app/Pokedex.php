<?php

namespace App;

use Illuminate\Support\Collection;

class Pokedex extends Collection
{
    /**
     * Create a new Pokedex Instance
     *
     * @param array $properties
     * @param array|null    $pokemon
     */
    public function __construct(array $properties, ?array $pokemon = null)
    {
        if($pokemon) {
            parent::__construct(
                $this->assignPokemonData($properties, $pokemon)
            );
        } else {
            parent::__construct($properties);
        }
    }

    /**
     * @param array $properties
     * @param array $pokemon
     * @return \Illuminate\Support\Collection
     */
    private function assignPokemonData(array $properties, array $pokemon): Collection
    {
        return collect($pokemon)->mapWithKeys(function($pokemon) use ($properties) {

            $pokemon = collect($pokemon)->keyBy(function($data, $index) use ($properties) {
                return $properties[$index];
            });

            return [
                $pokemon->get('pokedex_number') => $pokemon
            ];
        });
    }
}