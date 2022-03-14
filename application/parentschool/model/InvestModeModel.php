<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DThinkPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://DThinkPHP.com
// +----------------------------------------------------------------------

namespace app\parentschool\model;

use think\Model;
use think\helper\Hash;
use think\Db;

/**
 * 后台用户模型
 * @package app\admin\model
 */
class InvestModeModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'invest_mode';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;


}
