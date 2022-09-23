<?php


namespace app\parentschool\model;

use think\Model;
use think\helper\Hash;
use think\Db;

/**
 * 后台用户模型
 * @package app\admin\model
 */
class StudyMonthyTopicModel extends Model {
	// 设置当前模型对应的完整数据表名称
	protected $table = 'ps_study_monthy_topic';

	// 设置当前模型对应的完整数据表名称

	// 自动写入时间戳
	protected $autoWriteTimestamp = true;

}
