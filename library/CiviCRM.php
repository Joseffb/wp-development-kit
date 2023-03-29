<?php

namespace WDK;
/**
 * CiviCRM class allows your application tp pass customer data back and forth from a centralized CiviCRM install.
 */

use GOVERNANCE\Class_CiviCRM_Factory;
use RuntimeException;

class CiviCRM
{
    public static $instance = null;
    private static $server = null;
    private static $path = null;
    private static $site_key = null;
    private static $api_key = null;
    private static $entity = null;

    /**
     * @throws \RuntimeException
     */
    private function __construct(
        $entity = null,
        $server = null,
        $path = null,
        $site_key = null,
        $api_key = null
    )
    {
        $path = $path ?: '/wp-content/plugins/civicrm/civicrm/extern/rest.php';
        self::$server = $server ?: get_option('civi_server');
        self::$path = $path ?: get_option('civi_path');
        self::$site_key = $site_key ?: get_option('civi_site_key');
        self::$api_key = $api_key ?: get_option('civi_api_key');
        self::$entity = $entity ?: self::$entity; //use new entity or the existing entity -- i.e. re-instance
        if (!self::$entity) {
            throw new \RuntimeException('Entity not provided to CiviAPI factory.');
        }

        if (self::check_connection(self::$server . self::$path )) {
            $msg = 'The server ' . self::$server . self::$path . ' is not available';
            MLA_Log($msg);
            throw new \RuntimeException($msg);
        }
    }

    public static function check_connection($url): bool
    {
        $timeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 3); // wait 3 seconds for a response.
        stream_context_set_default( [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $review = get_headers($url) ?: false;
        ini_set('default_socket_timeout', $timeout); // reset to default timeout.

        $no_connection = (empty($review) || empty($review[0]));
        return $no_connection || strpos($review[0], '200 OK') === false;
    }

    /**
     * @param $entity
     * @return CiviCRM
     */
    public static function instance($entity = null):CiviCRM
    {
        self::$entity = !empty($entity)?$entity:'contact';
        if (self::$instance === null) {
            self::$instance = new self($entity);
        }
        return self::$instance;
    }

    /**
     * @param string $method
     * @param array $params
     * @return array|\WP_Error
     * @throws \RuntimeException
     */
    public function run(array $params = [], string $method = 'get')
    {
        /*
         * http://www.example.com/sites/all/modules/civicrm/extern/rest.php?api_key=t0ps3cr3t
              &key=an07h3rs3cr3t
              &json=1
              &debug=1
              &version=3
              &entity=Contact
              &action=get
              &first_name=Alice
              &last_name=Roberts
         * */
        $parameters = array_merge($params['params'],
            [
                'version' => 3,
                'json' => 1,
                'key' => self::$site_key,
                'api_key' => self::$api_key,
                'entity' => $params['entity'],
                'action' => $params['action']
            ]
        );

        switch ($method) {
            case "put":
            case "post":
            case "delete":
                $data = json_decode($this->json_send_post(self::$server . self::$path, $parameters), TRUE, 512, JSON_THROW_ON_ERROR);
                break;
            case "get":
            default:
//                add_action('http_api_debug', function ($r, $response, $Requests, $parsed_args, $url) {
//                    MLA_Log($url);
//                    MLA_Log($parsed_args);
//                }, 10 , 5);

                $json = file_get_contents(self::$server . self::$path . "?" . http_build_query($parameters));
                $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
            //MLA_Log($data);
        }
        return $data;
    }

    /**
     * @throws \RuntimeException
     */
    public function action($action, $parameters)
    {
        switch ($action) {
            case 'create':
            case 'delete':
            case 'update':
                $method = 'post';
                break;
            default:
            case 'get':
                $method = 'get';
                break;
        }
        if (!empty($parameters['method'])) {
            $method = $parameters['method'];
            unset($parameters['method']);
        }

        return $this->run(['entity' => self::$entity, 'action' => $action, 'params' => $parameters], $method);
    }

    /**
     * @throws \RuntimeException
     */
    public function create($parameters)
    {
        return $this->action('create', $parameters);
    }

    /**
     * @throws \RuntimeException
     */
    public function get($parameters)
    {
        return $this->action('get', $parameters);
    }

    /**
     * @throws \RuntimeException
     */
    public function update($parameters)
    {
        return $this->action('update', $parameters);
    }

    /**
     * @throws \RuntimeException
     */
    public function delete($parameters)
    {
        return $this->action('delete', $parameters);
    }

    public function json_send_post($url, $data, $headers = [])
    {
        $curl = curl_init($url . "?" . http_build_query($data));
        curl_setopt($curl, CURLOPT_URL, $url . "?" . http_build_query($data));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(array(
            "Content-Type: application/json",
        ), $headers));

        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        //for debug only!
        //https://github.com/kalessil/phpinspectionsea/blob/master/docs/security.md#ssl-server-spoofin
        $env = 'prod';
        if(defined('WP_ENV')) {
            $env = WP_ENV;
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, WP_DEBUG||strtolower($env)==='stage'?0:2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, WP_DEBUG||strtolower($env)==='stage'?0:1);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        $information = curl_getinfo($curl);
        // end debug
        //MLA_Log($information);
        $resp = curl_exec($curl);
        //MLA_Log($resp);
        curl_close($curl);
        return $resp;
    }
}