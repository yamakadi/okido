<?php

namespace App\Providers;

use App\DataDirectory;
use App\Pokedex;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DataDirectory::class, function ($app) {
            return new DataDirectory();
        });

        $this->app->singleton(Pokedex::class, function ($app) {
            return new Pokedex(config('pokedex.properties'), config('pokedex.data'));
        });

    }
}
