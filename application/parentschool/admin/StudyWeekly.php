<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DThinkPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://DThinkPHP.com
// +----------------------------------------------------------------------

namespace app\parentschool\admin;

use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\parentschool\model\StudyTagModel;
use app\parentschool\model\StudyWeeklyModel;
use app\parentschool\model\TagModel;
use app\user\model\Role as RoleModel;
use app\user\model\User;
use think\Db;
use think\facade\Hook;
use util\Tree;

/**
 * 用户默认控制器
 * @package app\user\admin
 */
class StudyWeekly extends Admin
{
    /**
     * 用户首页
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function index()
    {
        // 获取排序
        $order = $this->getOrder();
        $map = $this->getMap();
        // 读取用户数据
        $data_list = StudyWeeklyModel::where($map)->order($order)->paginate()->each(function ($item, $key) {
            echo "<br>";
            echo json_encode($item, 320);
            $item["common_tag"] = StudyTagModel::alias("a")->leftJoin(["ps_tag" => "b"], "a.tag_id=b.id")->where("study_id", $item["id"])->where("b.tag_type", "common")->column("name");
            $item["special_tag"] = StudyTagModel::alias("a")->leftJoin(["ps_tag" => "b"], "a.tag_id=b.id")->where("study_id", $item["id"])->where("b.tag_type", "special_tag")->column("name");
            $item["common_tag"] = join(",", $item["common_tag"]);
            $item["special_tag"] = join(",", $item["special_tag"]);
            return $item;
        });
        $todaytime = date('Y-m-d H:i:s', strtotime(date("Y-m-d"), time()));

        $num1 = StudyWeeklyModel::where("date", ">", $todaytime)->count();
        $num2 = StudyWeeklyModel::count();

        $page = $data_list->render();

        return ZBuilder::make('table')
            ->setPageTips("总数量：" . $num2 . "    今日数量：" . $num1, 'danger')
//            ->setPageTips("总数量：" . $num2, 'danger')
            ->addTopButton("add")
            ->setPageTitle('列表')
            ->setSearch(['id' => 'ID', "pid" => "上级UID", 'username' => '用户名']) // 设置搜索参数
            ->addOrder('id')
            ->addColumns([
                ['id', 'ID'],
                ['grade', '年级', 'number'],
                ['area_id', '对应区域', 'number'],
                ['school_id', '学校id', 'number'],
                ['title', '标题'],
//                ['slogan', '推荐金句'],
                ['special_tag', '特殊标签'],
                ['common_tag', '特殊标签'],
//                ['img', '小图头图', "picture"],
//                ['img_intro', '简介图', "picture"],
                ['can_push', '是否可以推送', 'switch'],
                ['push_date', '推送日期', 'text.edit'],
                ['show_date', '展示日期', 'text.edit'],
                ['attach_type', '附件类型', 'text'],
                ['show_to', '展示给谁'],
                ['change_date', '修改时间'],
                ['date', '创建时间'],
            ])
            ->addColumn('right_button', '操作', 'btn')
            ->addRightButton('edit') // 添加编辑按钮
            ->addRightButton('delete') //添加删除按钮
            ->setRowList($data_list) // 设置表格数据
            ->setPages($page)
            ->fetch();
    }

    /**
     * 新增
     * @return mixed
     * @throws \think\Exception
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function add()
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            // 非超级管理需要验证可选择角色
            if (session('user_auth.role') != 1) {
                if ($data['role'] == session('user_auth.role')) {
                    $this->error('禁止创建与当前角色同级的用户');
                }
                $role_list = RoleModel::getChildsId(session('user_auth.role'));
                if (!in_array($data['role'], $role_list)) {
                    $this->error('权限不足，禁止创建非法角色的用户');
                }

                if (isset($data['roles'])) {
                    $deny_role = array_diff($data['roles'], $role_list);
                    if ($deny_role) {
                        $this->error('权限不足，附加角色设置错误');
                    }
                }
            }

            $data['roles'] = isset($data['roles']) ? implode(',', $data['roles']) : '';

            if ($user = StudyWeeklyModel::create($data)) {
                StudyTagModel::where("study_id", $user->getLastInsID())->delete();
                $special_tag = $data["special_tag"];
                foreach ($special_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $user->getLastInsID(),
                        "study_type" => "weekly",
                        "tag_id" => $id,
                    ]);
                }
                $common_tag = $data["common_tag"];
                foreach ($common_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $user->getLastInsID(),
                        "study_type" => "weekly",
                        "tag_id" => $id,
                    ]);
                }
                Hook::listen('user_add', $user);
                // 记录行为
                action_log('user_add', 'admin_user', $user['id'], UID);
                $this->success('新增成功', url('index'));
            } else {
                $this->error('新增失败');
            }
        }

        // 角色列表
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getTree(null, false, session('user_auth.role'));
        } else {
            $role_list = RoleModel::getTree(null, false);
        }

        $tag_common = TagModel::where("tag_type", "common")->column("id,name");
        foreach ($tag_common as $key => $value) {
            $tag_common[strval($key)] = $value;
        }
        $tag_special = TagModel::where("tag_type", "special")->column("id,name");
        foreach ($tag_special as $key => $value) {
            $tag_special[strval($key)] = $value;
        }

        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['text', 'grade', '年级', 'number'],
                ['number', 'area_id', '对应区域'],
                ['number', 'school_id', '学校id'],
                ['text', 'title', '标题'],
                ['text', 'slogan', '推荐金句'],
                ['checkbox', 'special_tag', '特殊标签', "", $tag_special],
                ['checkbox', 'common_tag', '普通/推荐标签', "", $tag_common],
                ['ueditor', 'content', '内容'],
                ['image', 'img', '小图头图', "picture"],
                ['image', 'img_intro', '简介图', "picture"],
                ['ueditor', 'howto', '实践方法'],
                ['ueditor', 'notify', '特别提醒'],
                ['switch', 'can_push', '是否可以推送'],
                ['datetime', 'push_date', '推送日期'],
                ['datetime', 'show_date', '展示日期'],
                ['select', 'attach_type', '附件类型', '', \Study\Type::get_attach_type()],
                ['file', 'attach_url', '附件类型'],
                ['text', 'show_to', '展示给谁', "填写爸爸妈妈爷爷奶奶"],

                ['text', 'tick_need', '需要打卡几次', "需要打卡几次"],
                ['select', 'tick_mode', '打卡模式', "打卡模式", ['default' => "未选择", 'daily' => "每日打卡", 'weekly' => "每周打卡", 'monthy' => "每月打卡"]],
                ['text', 'tick_word', '打卡说明', "打卡说明"],

                ['text', 'tick_y', '打卡Y轴', "打卡Y轴"],
                ['text', 'tick_x', '打卡X轴', "打卡X轴"],
                ['text', 'tick_location', '打卡点名字', "打卡点名字"],
                ['text', 'tick_area', '打卡范围m', "打卡范围m"],
            ])
            ->fetch();
    }

    /**
     * 编辑
     * @param null $id 用户id
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = User::where('role', 'in', $role_list)->column('id');
            if (!in_array($id, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 非超级管理需要验证可选择角色

            StudyTagModel::where("study_id", $data["id"])->delete();
            if (isset($data["special_tag"])) {
                $special_tag = $data["special_tag"];
                foreach ($special_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $data["id"],
                        "study_type" => "weekly",
                        "tag_id" => $id,
                    ]);
                }
            }
            if (isset($data["common_tag"])) {
                $common_tag = $data["common_tag"];
                foreach ($common_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $data["id"],
                        "study_type" => "weekly",
                        "tag_id" => $id,
                    ]);
                }
            }
            if (StudyWeeklyModel::update($data)) {
                $user = StudyWeeklyModel::get($data['id']);
                // 记录行为
                action_log('user_edit', 'user', $id, UID);
                $this->success('编辑成功');
            } else {
                $this->error('编辑失败');
            }
        }

        // 获取数据
        $info = StudyWeeklyModel::where('id', $id)->find();
        // 使用ZBuilder快速创建表单

        $tag_common = TagModel::where("tag_type", "common")->column("id,name");
        $tag_special = TagModel::where("tag_type", "special")->column("id,name");
        $tag_choose = StudyTagModel::where("study_id", $id)->column("tag_id");

        $info["special_tag"] = null;
        $info["common_tag"] = null;

        $data = ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['text', 'grade', '年级', 'number'],
                ['number', 'area_id', '对应区域'],
                ['number', 'school_id', '学校id'],
                ['text', 'title', '标题'],
                ['text', 'slogan', '推荐金句'],
                ['checkbox', 'special_tag', '特殊标签', "", $tag_special, $tag_choose],
                ['checkbox', 'common_tag', '普通/推荐标签', "提示", $tag_common, $tag_choose],
                ['ueditor', 'content', '内容'],
                ['image', 'img', '小图头图', "picture"],
                ['image', 'img_intro', '简介图', "picture"],
                ['ueditor', 'howto', '实践方法'],
                ['ueditor', 'notify', '特别提醒'],
                ['switch', 'can_push', '是否可以推送'],
                ['datetime', 'push_date', '推送日期'],
                ['datetime', 'show_date', '展示日期'],
                ['select', 'attach_type', '附件类型', '', \Study\Type::get_attach_type()],
                ['file', 'attach_url', '附件类型'],
                ['text', 'show_to', '展示给谁', "填写爸爸妈妈爷爷奶奶"],

                ['text', 'tick_need', '需要打卡几次', "需要打卡几次"],
                ['select', 'tick_mode', '打卡模式', "打卡模式", ['default' => "未选择", 'daily' => "每日打卡", 'weekly' => "每周打卡", 'monthy' => "每月打卡"]],
                ['text', 'tick_word', '打卡说明', "打卡说明"],

                ['text', 'tick_y', '打卡Y轴', "打卡Y轴"],
                ['text', 'tick_x', '打卡X轴', "打卡X轴"],
                ['text', 'tick_location', '打卡点名字', "打卡点名字"],
                ['text', 'tick_area', '打卡范围m', "打卡范围m"],
            ]);

        return $data
            ->setFormData($info) // 设置表单数据
            ->fetch();;
    }


    /**
     * 授权
     * @param string $module 模块名
     * @param int $uid 用户id
     * @param string $tab 分组tab
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function access($module = '', $uid = 0, $tab = '')
    {
        if ($uid === 0) $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = User::where('role', 'in', $role_list)->column('id');
            if (!in_array($uid, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }

        // 获取所有授权配置信息
        $list_module = ModuleModel::where('access', 'neq', '')
            ->where('access', 'neq', '')
            ->where('status', 1)
            ->column('name,title,access');

        if ($list_module) {
            // tab分组信息
            $tab_list = [];
            foreach ($list_module as $key => $value) {
                $list_module[$key]['access'] = json_decode($value['access'], true);
                // 配置分组信息
                $tab_list[$value['name']] = [
                    'title' => $value['title'],
                    'url' => url('access', [
                        'module' => $value['name'],
                        'uid' => $uid
                    ])
                ];
            }
            $module = $module == '' ? current(array_keys($list_module)) : $module;
            $this->assign('tab_nav', [
                'tab_list' => $tab_list,
                'curr_tab' => $module
            ]);

            // 读取授权内容
            $access = $list_module[$module]['access'];
            foreach ($access as $key => $value) {
                $access[$key]['url'] = url('access', [
                    'module' => $module,
                    'uid' => $uid,
                    'tab' => $key
                ]);
            }

            // 当前分组
            $tab = $tab == '' ? current(array_keys($access)) : $tab;
            // 当前授权
            $curr_access = $access[$tab];
            if (!isset($curr_access['nodes'])) {
                $this->error('模块：' . $module . ' 数据授权配置缺少nodes信息');
            }
            $curr_access_nodes = $curr_access['nodes'];

            $this->assign('tab', $tab);
            $this->assign('access', $access);

            if ($this->request->isPost()) {
                $post = $this->request->param();
                if (isset($post['nodes'])) {
                    $data_node = [];
                    foreach ($post['nodes'] as $node) {
                        list($group, $nid) = explode('|', $node);
                        $data_node[] = [
                            'module' => $module,
                            'group' => $group,
                            'uid' => $uid,
                            'nid' => $nid,
                            'tag' => $post['tag']
                        ];
                    }

                    // 先删除原有授权
                    $map['module'] = $post['module'];
                    $map['tag'] = $post['tag'];
                    $map['uid'] = $post['uid'];
                    if (false === AccessModel::where($map)->delete()) {
                        $this->error('清除旧授权失败');
                    }

                    // 添加新的授权
                    $AccessModel = new AccessModel;
                    if (!$AccessModel->saveAll($data_node)) {
                        $this->error('操作失败');
                    }

                    // 调用后置方法
                    if (isset($curr_access_nodes['model_name']) && $curr_access_nodes['model_name'] != '') {
                        if (strpos($curr_access_nodes['model_name'], '/')) {
                            list($module, $model_name) = explode('/', $curr_access_nodes['model_name']);
                        } else {
                            $model_name = $curr_access_nodes['model_name'];
                        }
                        $class = "app\\{$module}\\model\\" . $model_name;
                        $model = new $class;
                        try {
                            $model->afterAccessUpdate($post);
                        } catch (\Exception $e) {
                        }
                    }

                    // 记录行为
                    $nids = implode(',', $post['nodes']);
                    $details = "模块($module)，分组(" . $post['tag'] . ")，授权节点ID($nids)";
                    action_log('user_access', 'admin_user', $uid, UID, $details);
                    $this->success('操作成功', url('access', ['uid' => $post['uid'], 'module' => $module, 'tab' => $tab]));
                } else {
                    // 清除所有数据授权
                    $map['module'] = $post['module'];
                    $map['tag'] = $post['tag'];
                    $map['uid'] = $post['uid'];
                    if (false === AccessModel::where($map)->delete()) {
                        $this->error('清除旧授权失败');
                    } else {
                        $this->success('操作成功');
                    }
                }
            } else {
                $nodes = [];
                if (isset($curr_access_nodes['model_name']) && $curr_access_nodes['model_name'] != '') {
                    if (strpos($curr_access_nodes['model_name'], '/')) {
                        list($module, $model_name) = explode('/', $curr_access_nodes['model_name']);
                    } else {
                        $model_name = $curr_access_nodes['model_name'];
                    }
                    $class = "app\\{$module}\\model\\" . $model_name;
                    $model = new $class;

                    try {
                        $nodes = $model->access();
                    } catch (\Exception $e) {
                        $this->error('模型：' . $class . "缺少“access”方法");
                    }
                } else {
                    // 没有设置模型名，则按表名获取数据
                    $fields = [
                        $curr_access_nodes['primary_key'],
                        $curr_access_nodes['parent_id'],
                        $curr_access_nodes['node_name']
                    ];

                    $nodes = Db::name($curr_access_nodes['table_name'])->order($curr_access_nodes['primary_key'])->field($fields)->select();
                    $tree_config = [
                        'title' => $curr_access_nodes['node_name'],
                        'id' => $curr_access_nodes['primary_key'],
                        'pid' => $curr_access_nodes['parent_id']
                    ];
                    $nodes = Tree::config($tree_config)->toLayer($nodes);
                }

                // 查询当前用户的权限
                $map = [
                    'module' => $module,
                    'tag' => $tab,
                    'uid' => $uid
                ];
                $node_access = AccessModel::where($map)->select();
                $user_access = [];
                foreach ($node_access as $item) {
                    $user_access[$item['group'] . '|' . $item['nid']] = 1;
                }

                $nodes = $this->buildJsTree($nodes, $curr_access_nodes, $user_access);
                $this->assign('nodes', $nodes);
            }

            $page_tips = isset($curr_access['page_tips']) ? $curr_access['page_tips'] : '';
            $tips_type = isset($curr_access['tips_type']) ? $curr_access['tips_type'] : 'info';
            $this->assign('page_tips', $page_tips);
            $this->assign('tips_type', $tips_type);
        }

        $this->assign('module', $module);
        $this->assign('uid', $uid);
        $this->assign('tab', $tab);
        $this->assign('page_title', '数据授权');
        return $this->fetch();
    }

    /**
     * 构建jstree代码
     * @param array $nodes 节点
     * @param array $curr_access 当前授权信息
     * @param array $user_access 用户授权信息
     * @return string
     * @author 蔡伟明 <314013107@qq.com>
     */
    private function buildJsTree($nodes = [], $curr_access = [], $user_access = [])
    {
        $result = '';
        if (!empty($nodes)) {
            $option = [
                'opened' => true,
                'selected' => false
            ];
            foreach ($nodes as $node) {
                $key = $curr_access['group'] . '|' . $node[$curr_access['primary_key']];
                $option['selected'] = isset($user_access[$key]) ? true : false;
                if (isset($node['child'])) {
                    $curr_access_child = isset($curr_access['child']) ? $curr_access['child'] : $curr_access;
                    $result .= '<li id="' . $key . '" data-jstree=\'' . json_encode($option) . '\'>' . $node[$curr_access['node_name']] . $this->buildJsTree($node['child'], $curr_access_child, $user_access) . '</li>';
                } else {
                    $result .= '<li id="' . $key . '" data-jstree=\'' . json_encode($option) . '\'>' . $node[$curr_access['node_name']] . '</li>';
                }
            }
        }

        return '<ul>' . $result . '</ul>';
    }

    /**
     * 删除用户
     * @param array $ids 用户id
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function delete($ids = [])
    {
        Hook::listen('user_delete', $ids);
        action_log('user_delete', 'user', $ids, UID);
        return $this->setStatus('delete');
    }

    /**
     * 启用用户
     * @param array $ids 用户id
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function enable($ids = [])
    {
        Hook::listen('user_enable', $ids);
        return $this->setStatus('enable');
    }

    /**
     * 禁用用户
     * @param array $ids 用户id
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function disable($ids = [])
    {
        Hook::listen('user_disable', $ids);
        return $this->setStatus('disable');
    }

    /**
     * 设置用户状态：删除、禁用、启用
     * @param string $type 类型：delete/enable/disable
     * @param array $record
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function setStatus($type = '', $record = [])
    {
        $ids = $this->request->isPost() ? input('post.ids/a') : input('param.ids');
        $ids = (array)$ids;

        switch ($type) {
            case 'enable':
                if (false === StudyWeeklyModel::where('id', 'in', $ids)->setField('status', 1)) {
                    $this->error('启用失败');
                }
                break;
            case 'disable':
                if (false === StudyWeeklyModel::where('id', 'in', $ids)->setField('status', 0)) {
                    $this->error('禁用失败');
                }
                break;
            case 'delete':
                if (false === StudyWeeklyModel::where('id', 'in', $ids)->delete()) {
                    $this->error('删除失败');
                }
                break;
            default:
                $this->error('非法操作');
        }

        action_log('user_' . $type, 'admin_user', '', UID);

        $this->success('操作成功');
    }

    /**
     * 快速编辑
     * @param array $record 行为日志
     * @return mixed
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function quickEdit($record = [])
    {
        $id = input('post.pk', '');
        $field = input('post.name', '');
        $value = input('post.value', '');

        // 非超级管理员检查可操作的用户
        if (session('user_auth.role') != 1) {
            $role_list = Role::getChildsId(session('user_auth.role'));
            $user_list = \app\user\model\User::where('role', 'in', $role_list)->column('id');
            if (!in_array($id, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }
        $result = StudyWeeklyModel::where("id", $id)->setField($field, $value);
        if (false !== $result) {
            action_log('user_edit', 'user', $id, UID);
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
}
