<?php

namespace Aoss;

class Aoss
{
    public $send_url = "";

    public function send($real_path, $mime_type, $file_name)
    {
        return self::send_file2($this->send_url, $real_path, $mime_type, $file_name);
    }

    public static function send_file($send_url, $real_path, $mime_type, $file_name)
    {
        $postData = [
            'file' => new \CURLFile(realpath($real_path), $mime_type, $file_name)
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $send_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);
        if (empty($json) || !isset($json["code"])) {
            return false;
        }
        if ($json["code"] == "0") {
            return $json["data"];
        } else {
            return false;
        }
    }

    public static function send_file2($send_url, $real_path, $mime_type, $file_name)
    {
        $postData = [
            'file' => new \CURLFile(realpath($real_path), $mime_type, $file_name)
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $send_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);
        if (empty($json) || !isset($json["code"])) {
            return false;
        }
        return $json;
    }
}