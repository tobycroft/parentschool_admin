<?php


namespace app\index\controller;

/**
 * 前台首页控制器
 * @package app\index\controller
 */
class Index extends Home
{
    public function index()
    {
        // 默认跳转模块
        if (config('home_default_module') != '' && config('home_default_module') != 'index') {
            $this->redirect("/admin.php/" . config('home_default_module') . '/index/index');
        }
        return '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:)</h1><p> ' . config("dolphin.product_name") . ' ' . config("dolphin.product_version") . '<br/><span style="font-size:30px">极速 · 极简 · 极致</span></p></div>';
    }
}
