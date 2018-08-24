<?php

namespace Grav\Plugin\TecartReviewWorkflow;

use Grav\Plugin\GitSync\Helper as GitHelper;

class JiraHelper {

    public static function getCurlOptions($userpwd)
    {
        $httpHeader = array('Accept: application/json','Content-Type: application/json');
        $options    = array(CURLOPT_RETURNTRANSFER => true,
                            // CURLOPT_POST		   => true,
                            CURLOPT_VERBOSE        => true,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_TIMEOUT        => 120,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_USERPWD	       => $userpwd,
                            CURLOPT_HTTPHEADER     => $httpHeader);

        return $options;
    }

    public static function postRestAPI($url, $data, $userpwd)
    {
        try {
            $curl = curl_init($url);

            curl_setopt_array($curl, self::getCurlOptions($userpwd));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            
            $result = curl_exec($curl);
            $curl_error = curl_error($curl);

            if ($curl_error == '') {
                $response = $result;
            } else {
                $response = json_encode(array('error' => $ch_error));
            }

        } catch(Exception $e) {
            $response = json_encode(array('error' => $e->getMessage()));
        }

        return json_decode($response);
    }

    public static function putRestAPI($url, $data, $userpwd)
    {

        try {
            $curl = curl_init($url);

            curl_setopt_array($curl, self::getCurlOptions($userpwd));
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

            $result     = curl_exec($curl);
            $curl_error = curl_error($curl);

            if ($curl_error == '') {
                $response = $result;
            } else {
                $response = json_encode(array('error' => $ch_error));
            }
        } catch(Exception $e) {
            $response = json_encode(array('error' => $e->getMessage()));
        }

        return json_decode($response);
    }
}