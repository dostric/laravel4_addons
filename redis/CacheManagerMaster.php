<?php namespace LaravelAddons\Redis;

use Illuminate\Cache\CacheManager;

class CacheManagerMaster extends CacheManager {


    /**
     * Create an instance of the Redis cache driver.
     *
     * @return \Illuminate\Cache\RedisStore
     */
    protected function createRedisDriver()
    {
        $redis = $this->app['redis'];

        return $this->repository(new RedisMasterStore($redis, $this->getPrefix()));
    }


}