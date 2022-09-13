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
use app\parentschool\model\StudyDailyModel;
use app\parentschool\model\StudyModel;
use app\parentschool\model\StudyMonthyModel;
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
class Study extends Admin
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
        $data_list = StudyModel::where($map)->order($order)->paginate()->each(function ($item, $key) {
            switch ($item["study_type"]) {
                case "daily":
                    $data = StudyDailyModel::where("id", $item["study_id"]);
                    $item["title"] = $data["title"];
                    $item["slogan"] = $data["slogan"];
                    return $item;
                case "weekly":
                    $data = StudyWeeklyModel::where("id", $item["study_id"]);
                    $item["title"] = $data["title"];
                    $item["slogan"] = $data["slogan"];
                    return $item;
                case "monthy":
                    $data = StudyMonthyModel::where("id", $item["study_id"]);
                    $item["title"] = $data["title"];
                    $item["slogan"] = $data["slogan"];
                    return $item;

            }
        });
        $page = $data_list->render();

        return ZBuilder::make('table')
            ->addTopButton("add")
            ->setPageTitle('列表')
            ->setSearch(['id' => 'ID', "title" => "标题", 'slogan' => 'slogan']) // 设置搜索参数
            ->addOrder('id')
            ->addColumns([
                ['id', 'ID'],
                ['area_id', '对应区域', 'number'],
                ['school_id', '学校id', 'number'],
                ['grade', '年级', 'number'],
                ['class', '班级', 'number'],
                ['title', '标题'],
                ['slogan', '推荐金句'],
                ['can_push', '是否可以推送', 'switch'],
                ['can_show', '是否展示', 'switch'],
                ['push_date', '推送日期', 'text.edit'],
                ['show_date', '展示日期', 'text.edit'],
                ['end_date', '结束展示日期', 'text.edit'],
            ])
            ->addColumn('right_button', '操作', 'btn')
//            ->addRightButton('新建', $btn_access2)
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
            $special_tag = $data["special_tag"];
            $common_tag = $data["common_tag"];
            unset($data["special_tag"]);
            unset($data["common_tag"]);
            if ($user = StudyModel::create($data)) {
                $lastid = $user->id;
                if ($special_tag) {
                    foreach ($special_tag as $id) {
                        StudyTagModel::create([
                            "study_id" => $lastid,
                            "study_type" => "monthy",
                            "tag_id" => $id,
                        ]);
                    }
                }
                if ($common_tag) {
                    foreach ($common_tag as $id) {
                        StudyTagModel::create([
                            "study_id" => $lastid,
                            "study_type" => "monthy",
                            "tag_id" => $id,
                        ]);
                    }
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
                ['number', 'grade', '年级'],
                ['number', 'class', '班级'],
                ['number', 'area_id', '对应区域'],
                ['number', 'school_id', '学校id'],
                ['text', 'title', '标题'],
                ['text', 'slogan', '推荐金句'],
                ['checkbox', 'special_tag', '特殊标签', "", $tag_special],
                ['checkbox', 'common_tag', '普通/推荐标签', "", $tag_common],
                ['ueditor', 'content', '内容'],
                ['switch', 'can_push', '是否可以推送'],
                ['datetime', 'push_date', '推送日期'],
                ['datetime', 'show_date', '展示日期'],
                ['text', 'show_to', '展示给谁', "填写爸爸妈妈爷爷奶奶"],
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

            StudyTagModel::where("study_id", $data["id"])->where("study_type", "monthy")->delete();
            if (isset($data["special_tag"])) {
                $special_tag = $data["special_tag"];
                foreach ($special_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $data["id"],
                        "study_type" => "monthy",
                        "tag_id" => $id,
                    ]);
                }
            }
            if (isset($data["common_tag"])) {
                $common_tag = $data["common_tag"];
                foreach ($common_tag as $id) {
                    StudyTagModel::create([
                        "study_id" => $data["id"],
                        "study_type" => "monthy",
                        "tag_id" => $id,
                    ]);
                }
            }
            if (StudyModel::update($data)) {
                $user = StudyModel::get($data['id']);
                // 记录行为
                action_log('user_edit', 'user', $id, UID);
                $this->success('编辑成功');
            } else {
                $this->error('编辑失败');
            }
        }

        // 获取数据
        $info = StudyModel::where('id', $id)->find();


        // 使用ZBuilder快速创建表单

        $tag_common = TagModel::where("tag_type", "common")->column("id,name");
        $tag_special = TagModel::where("tag_type", "special")->column("id,name");
        $tag_choose = StudyTagModel::where("study_id", $id)->where("study_type", "monthy")->column("tag_id");
        $info["special_tag"] = null;
        $info["common_tag"] = null;

        $data = ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['number', 'grade', '年级'],
                ['number', 'class', '班级'],
                ['number', 'area_id', '对应区域'],
                ['number', 'school_id', '学校id'],
                ['text', 'title', '标题'],
                ['text', 'slogan', '推荐金句'],
                ['checkbox', 'special_tag', '特殊标签', "", $tag_special, $tag_choose],
                ['checkbox', 'common_tag', '普通/推荐标签', "", $tag_common, $tag_choose],
                ['ueditor', 'content', '内容'],
                ['switch', 'can_push', '是否可以推送'],
                ['datetime', 'push_date', '推送日期'],
                ['datetime', 'show_date', '展示日期'],
                ['text', 'show_to', '展示给谁', "填写爸爸妈妈爷爷奶奶"],
            ]);

        return $data
            ->setFormData($info) // 设置表单数据
            ->fetch();;
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
                if (false === StudyModel::where('id', 'in', $ids)->setField('status', 1)) {
                    $this->error('启用失败');
                }
                break;
            case 'disable':
                if (false === StudyModel::where('id', 'in', $ids)->setField('status', 0)) {
                    $this->error('禁用失败');
                }
                break;
            case 'delete':
                if (false === StudyModel::where('id', 'in', $ids)->delete()) {
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
        $result = StudyModel::where("id", $id)->setField($field, $value);
        if (false !== $result) {
            action_log('user_edit', 'user', $id, UID);
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
}
