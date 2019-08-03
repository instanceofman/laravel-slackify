<?php


namespace Isofman\LaravelSlackify;


use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

class SlackifyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        
    }

    public function register()
    {
        app('log')->extend('slackify', function($app, $config) {
            return new Logger('slackify', [
                (new SlackifyWebhookHandler($config))->setFormatter(new SlackFormatter)
            ]);
        });
    }
}