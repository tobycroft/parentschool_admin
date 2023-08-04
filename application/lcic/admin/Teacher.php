<?php


namespace app\lcic\admin;

use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\lcic\model\LcicModel;
use app\parentschool\model\TeacherModel;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\facade\Hook;
use Tobycroft\AossSdk\Lcic;
use util\Tree;

/**
 * 用户默认控制器
 * @package app\user\admin
 */
class Teacher extends Admin
{
    /**
     * 用户首页
     * @return mixed
     * @throws Exception
     * @throws DbException
     */
    public function index()
    {
        // 获取排序
        $order = $this->getOrder("id desc");
        $map = $this->getMap();
        // 读取用户数据
        $data_list = LcicModel::where($map)
            ->order($order)
            ->paginate();
        $page = $data_list->render();
        $todaytime = date('Y-m-d H:i:s', strtotime(date("Y-m-d"), time()));

        $num1 = LcicModel::where("date", ">", $todaytime)
            ->count();
        $num2 = LcicModel::count();

        $btn_access = [
            'title' => '地址',
            'icon' => 'fa fa-plus',
//            'class' => 'btn btn-xs btn-default ajax-get',
            'href' => url('teacher/address', ['id' => '__id__',])
        ];

        return ZBuilder::make('table')
            ->setPageTips("总数量：" . $num2 . "    今日数量：" . $num1, 'danger')
//            ->setPageTips("总数量：" . $num2, 'danger')
            ->setPageTitle('列表')
            ->addTopButton('add')
            ->setSearch(['id' => 'ID', "teacherid" => "教师id"]) // 设置搜索参数
            ->addOrder('id')
            ->addColumn('id', 'ID')
            ->addColumn('roomid', '房间ID')
            ->addColumn('teacherid', '教师id')
            ->addColumn('name', '房间标题')
            ->addColumn('start_time', '开始时间', 'datetime')
            ->addColumn('end_time', '结束时间', 'datetime')
            ->addColumn('change_date', '修改时间')
            ->addColumn('date', '创建时间')
            ->addColumn('right_button', '操作', 'btn')
            ->addRightButton('edit') // 添加编辑按钮
            ->addRightButton('delete') //添加删除按钮
            ->addRightButtons(['教师开播地址' => $btn_access,])
            ->setRowList($data_list) // 设置表格数据
            ->setPages($page)
            ->fetch();
    }

    public function address($id)
    {
        $info = LcicModel::where('id', $id)->find();

        $lcic = new Lcic(config('upload_prefix'));
        $ret = $lcic->RoomUrl($info["teacherid"], $info['teacherid']);
        if ($ret->isSuccess()) {
            $this->redirect($ret->GetUrlWeb());
        } else {
            $this->error($ret->getError());
        }
    }


    /**
     * 新增
     * @return mixed
     * @throws Exception
     */
    public function add()
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            // 验证
            $teacherid = $data["teacherid"];
            $name = $data["name"];
            $start_time = strtotime($data['start_time']);
            $end_time = strtotime($data['end_time']);

            $teacherinfo = TeacherModel::where("uid", $teacherid)->findOrEmpty();
            if ($teacherinfo->isEmpty()) {
                $this->error("教师信息不存在");
            }
            $lcic = new Lcic(config('upload_prefix'));
            $ret_create_user = $lcic->CreateUser($teacherinfo["name"], $teacherid, $teacherinfo["img"]);
            if (!$ret_create_user->isSuccess()) {
                $this->error($ret_create_user->getError());
            }
            $ret_room_info = $lcic->RoomCreate($teacherid, $start_time, $end_time, $name);
            if (!$ret_room_info->isSuccess()) {
                $this->error($ret_room_info->getError());
            }
            $ret_room_url = $lcic->RoomUrl($teacherid, $teacherid);
            if (!$ret_room_url->isSuccess()) {
                $this->error($ret_room_url->getError());
            }
            if ($user = LcicModel::create([
                'teacherid' => $teacherid,
                'name' => $name,
                'roomid' => $ret_room_info->GetRoomId(),
                'start_time' => $start_time,
                'end_time' => $end_time,
                "weburl" => $ret_room_url->GetUrlWeb(),
                "pcurl" => $ret_room_url->GetUrlPc(),
            ])) {
                Hook::listen('user_add', $user);
                // 记录行为
                action_log('user_add', 'admin_user', $user['id'], UID);
                $this->success('新增成功', url('index'));
            } else {
                $this->error('新增失败');
            }
        }

        $teacher = TeacherModel::column("uid,name");
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['select', 'teacherid', '教师id', '', $teacher],
                ['datetime', 'start_time', '开始时间', '开始时间必须大于当前时间'],
                ['datetime', 'end_time', '结束时间', '结束时间不能超过开始时间5个小时'],
                ['text', 'name', '房间名称'],
            ])
            ->fetch();
    }

    /**
     * 编辑
     * @param null $id 用户id
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function edit($id = null)
    {
        if ($id === null)
            $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = LcicModel::where('role', 'in', $role_list)
                ->column('id');
            if (!in_array($id, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }
        $info = LcicModel::where('id', $id)->find();
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 非超级管理需要验证可选择角色
            $teacherid = $data['teacherid'];
            $name = $data['name'];
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);

            $start_time = ($data['start_time']);
            $end_time = ($data['end_time']);

            $teacherinfo = TeacherModel::where('uid', $teacherid)->findOrEmpty();
            if ($teacherinfo->isEmpty()) {
                $this->error('教师信息不存在');
            }
            $lcic = new Lcic(config('upload_prefix'));
            $ret_room_info = $lcic->RoomModify($info["roomid"], $teacherid, $start_time, $end_time, $name);
            if (!$ret_room_info->isSuccess()) {
                $this->error($ret_room_info->getError());
            }

            if (LcicModel::update($data)) {
                // 记录行为
                action_log('edit_data', 'user', $id, UID, json_encode(input('post.'), 320));
                $this->success('编辑成功');
            } else {
                $this->error('编辑失败');
            }
        }

        $teacher = TeacherModel::column('uid,name');
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['text', 'roomid', '房间号'],
                ['select', 'teacherid', '教师id', '', $teacher],
                ['datetime', 'start_time', '开始时间', '开始时间必须大于当前时间'],
                ['datetime', 'end_time', '结束时间', '结束时间不能超过开始时间5个小时'],
                ['text', 'name', '房间名称'],
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }

    /**
     * 授权
     * @param string $module 模块名
     * @param int $uid 用户id
     * @param string $tab 分组tab
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    public function access($module = '', $uid = 0, $tab = '')
    {
        if ($uid === 0)
            $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = LcicModel::where('role', 'in', $role_list)
                ->column('id');
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
                    if (false === AccessModel::where($map)
                            ->delete()) {
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
                    if (false === AccessModel::where($map)
                            ->delete()) {
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

                    $nodes = Db::name($curr_access_nodes['table_name'])
                        ->order($curr_access_nodes['primary_key'])
                        ->field($fields)
                        ->select();
                    $tree_config = [
                        'title' => $curr_access_nodes['node_name'],
                        'id' => $curr_access_nodes['primary_key'],
                        'pid' => $curr_access_nodes['parent_id']
                    ];
                    $nodes = Tree::config($tree_config)
                        ->toLayer($nodes);
                }

                // 查询当前用户的权限
                $map = [
                    'module' => $module,
                    'tag' => $tab,
                    'uid' => $uid
                ];
                $node_access = AccessModel::where($map)
                    ->select();
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
     * 删除用户
     * @param array $ids 用户id
     * @throws Exception
     * @throws PDOException
     */
    public function delete($ids = [])
    {
        $info = LcicModel::where('id', "in", $ids)->select();
        $lcic = new Lcic(config('upload_prefix'));
        foreach ($info as $item) {
            $ret_room_info = $lcic->RoomDelete($info['roomid']);
            if (!$ret_room_info->isSuccess()) {
                $this->error($ret_room_info->getError());
            }
        }
        Hook::listen('user_delete', $ids);
        action_log('user_delete', 'user', $ids, UID);
        return $this->setStatus('delete');
    }

    /**
     * 设置用户状态：删除、禁用、启用
     * @param string $type 类型：delete/enable/disable
     * @param array $record
     * @throws Exception
     * @throws PDOException
     */
    public function setStatus($type = '', $record = [])
    {
        $ids = $this->request->isPost() ? input('post.ids/a') : input('param.ids');
        $ids = (array)$ids;

        switch ($type) {
            case 'enable':
                if (false === LcicModel::where('id', 'in', $ids)
                        ->setField('status', 1)) {
                    $this->error('启用失败');
                }
                break;
            case 'disable':
                if (false === LcicModel::where('id', 'in', $ids)
                        ->setField('status', 0)) {
                    $this->error('禁用失败');
                }
                break;
            case 'delete':
                if (false === LcicModel::where('id', 'in', $ids)
                        ->delete()) {
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
     * 构建jstree代码
     * @param array $nodes 节点
     * @param array $curr_access 当前授权信息
     * @param array $user_access 用户授权信息
     * @return string
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
     * 启用用户
     * @param array $ids 用户id
     * @throws Exception
     * @throws PDOException
     */
    public function enable($ids = [])
    {
        Hook::listen('user_enable', $ids);
        return $this->setStatus('enable');
    }

    /**
     * 禁用用户
     * @param array $ids 用户id
     * @throws Exception
     * @throws PDOException
     */
    public function disable($ids = [])
    {
        Hook::listen('user_disable', $ids);
        return $this->setStatus('disable');
    }

    public function quickEdit($record = [])
    {
        $field = input('post.name', '');
        $value = input('post.value', '');
        $type = input('post.type', '');
        $id = input('post.pk', '');

        switch ($type) {
            // 日期时间需要转为时间戳
            case 'combodate':
                $value = strtotime($value);
                break;
            // 开关
            case 'switch':
                $value = $value == 'true' ? 1 : 0;
                break;
            // 开关
            case 'password':
                $value = Hash::make((string)$value);
                break;
        }
        // 非超级管理员检查可操作的用户
        if (session('user_auth.role') != 1) {
            $role_list = Role::getChildsId(session('user_auth.role'));
            $user_list = \app\user\model\User::where('role', 'in', $role_list)
                ->column('id');
            if (!in_array($id, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }
        $result = \app\user\model\User::where("id", $id)
            ->setField($field, $value);
        if (false !== $result) {
            action_log('edit_data', 'user', $id, UID, json_encode(input('post.'), 320));
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
}
