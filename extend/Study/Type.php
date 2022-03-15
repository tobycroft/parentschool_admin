<?php

namespace Study;

class Type
{
    public static function get_type()
    {
        return [
            'daily' => '每日一课',
            'weekly' => '每周一做',
            'monthy' => '每月一练',
        ];
    }

    public static function get_attach_type()
    {
        return [
            'img' => "图片",
            'record' => "录音",
            'video' => "视频",
            'none' => "无"
        ];
    }
}