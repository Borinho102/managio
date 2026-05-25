<?php

namespace Modules\PerfexMobileCompanion\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PerfexMobileCompanion\Classes\FieldManager;

class FieldServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->singleton('field_manager', function () {
            return new FieldManager();
        });
    }
}
