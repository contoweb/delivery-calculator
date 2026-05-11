<?php

namespace Contoweb\DeliveryCalculator;

use Illuminate\Support\ServiceProvider;

class DeliveryCalculatorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');

        $this->publishes([
            __DIR__.'/../config/delivery-calculator.php' => config_path('delivery-calculator.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/delivery-calculator.php',
            'delivery-calculator'
        );
    }
}
