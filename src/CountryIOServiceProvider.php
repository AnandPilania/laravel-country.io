<?php

namespace KSPEdu\CountryIO;

use Illuminate\Support\ServiceProvider;

class CountryIOServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/countryio.php' => config_path('countryio.php'),
        ], 'countryio-config');

        /*$this->loadMigrationsFrom(__DIR__.'/../database/migrations');*/
        $this->publishes([
            __DIR__ . '/../stubs/countryio_table.stub' =>
                database_path('migrations' . DIRECTORY_SEPARATOR . date('Y_m_d_His') . '_create_' . config('countryio.table_name', 'countryio') . '_table.php')
        ], 'countryio-migration');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CountryIOCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/countryio.php', 'countryio'
        );
    }
}
