<?php
namespace KSPEdu\CountryIO;

use Illuminate\Support\ServiceProvider;

class CountryIOServiceProvider extends ServiceProvider {
	public function boot()
	{
		/*$this->publishes([
			__DIR__.'/../config/countryio.php' => config_path('countryio.php'),
		]);
	
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
		$this->publishes([
			__DIR__.'/../database/migrations/' => database_path('migrations')
		], 'migrations');*/
		
		if ($this->app->runningInConsole()) {
			$this->commands([
				Console\CountryIOCommand::class,
			]);
		}
	}
	
	public function register()
	{
		$this->mergeConfigFrom(
			__DIR__.'/../config/countryio.php', 'countryio'
		);
	}
}