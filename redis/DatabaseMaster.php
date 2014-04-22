<?php namespace LaravelAddons\Redis;

use Illuminate\Redis\Database;


class DatabaseMaster extends Database {


    public $master;



    public function __construct(array $servers = array()) {

        // detect the master server (has the master key set to true
        foreach($servers as $serverName => $serverData) {
            if (is_array($serverData) && array_key_exists('master', $serverData) && $serverData['master'] === true) {
                $this->master = $serverName;
                break;
            }
        }

        parent::__construct($servers);

    }


    /**
     * Run a command against the Redis database.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function command($method, array $parameters = array())
    {
        //default client
        $client = $this->clients['default'];

        // if we have a master connection use it for updated automatically
        if ($this->master && $this->master != 'default') {

            switch ($method) {

                case 'del':
                case 'decrby':
                case 'incrby':
                case 'expire':
                case 'set':
                    $client = $this->clients[$this->master];
                    break;

                default:

            }

        }

        return call_user_func_array(array($client, $method), $parameters);
    }

}
