<?php

namespace App\Providers;

use App\Service\APIService;
use App\Logic\Payments\JazzPayment;
use App\Service\TransactionService;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service
        $this->app->singleton('transaction', function ($app) {
            return new TransactionService();
        });
        $this->app->singleton('jazzpayment', function ($app) {
            return new JazzPayment();
        });
        $this->app->singleton('apiservice', function ($app) {
            return new APIService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // LogViewer::auth(function ($request) {
        //     return $request->user()
        //         && in_array($request->user()->email, [
        //             'john@example.com',
        //         ]);
        // });
    }
}
