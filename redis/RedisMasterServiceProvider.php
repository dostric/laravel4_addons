<?php namespace LaravelAddons\Redis;

use Illuminate\Redis\RedisServiceProvider;
use LaravelAddons\Redis\DatabaseMaster;


class RedisMasterServiceProvider extends RedisServiceProvider {


    public function register() {

        $this->app['redis'] = $this->app->share(function($app)
        {
            return new DatabaseMaster($app['config']['database.redis']);
        });

    }


}