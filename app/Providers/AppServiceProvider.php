<?php

namespace App\Providers;

use App\DataDirectory;
use App\Pokédex;
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

        $this->app->singleton(Pokédex::class, function ($app) {
            return new Pokédex(config('pokédex.properties'), config('pokédex.data'));
        });

    }
}
