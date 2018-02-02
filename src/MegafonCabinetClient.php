<?php

namespace unapi\gsm\megafon;

use GuzzleHttp\Client;

class MegafonCabinetClient extends Client
{
    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config['base_uri'] = 'https://api.megafon.ru/';
        $config['headers'] = ['User-Agent' => 'MLK iOS Phone 2.5.0'];
        $config['cookies'] = true;

        parent::__construct($config);
    }
}