<?php namespace LaravelAddons\Redis;

use Illuminate\Cache\CacheServiceProvider;

class CacheServiceProviderMaster extends CacheServiceProvider {



    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['cache'] = $this->app->share(function($app)
        {
            return new CacheManagerMaster($app);
        });

        $this->app['memcached.connector'] = $this->app->share(function()
        {
            return new MemcachedConnector;
        });

        $this->registerCommands();
    }


}