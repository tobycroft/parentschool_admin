<?php


namespace app\parentschool\admin;

use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\parentschool\model\AreaModel;
use app\parentschool\model\BalanceModel;
use app\parentschool\model\ParentModel;
use app\parentschool\model\SchoolGradeModel;
use app\parentschool\model\SchoolModel;
use app\parentschool\model\StudentModel;
use app\parentschool\model\StudyRecordModel;
use app\parentschool\model\TeacherClassModel;
use app\user\model\Role as RoleModel;
use app\user\model\User;
use think\Db;
use think\facade\Hook;
use Tobycroft\AossSdk\Excel\Excel;
use util\Tree;

/**
 * 用户默认控制器
 * @package app\user\admin
 */
class School extends Admin
{
    /**
     * 用户首页
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function index()
    {
        // 获取排序
        $order = $this->getOrder("id desc");
        $map = $this->getMap();
        // 读取用户数据
        $data_list = SchoolModel::where($map)
            ->order($order)
            ->paginate()->each(function ($item) {
                $item["count_student"] = StudentModel::where("school_id", $item["id"])->count();

                $count_parent = StudentModel::alias("a")
                    ->rightJoin(["ps_family_member" => "b"], "a.id=b.student_id")
                    ->where("school_id", $item["id"])
                    ->where("b.is_verify", 1)
                    ->count();
                $item['count_parent'] = $count_parent;
                $item["count_daily"] = StudyRecordModel::alias("a")
                    ->where("a.type", "daily")
                    ->leftJoin(["ps_student" => "b"], "a.student_id=b.id")
                    ->where("b.school_id", $item["id"])
                    ->count();
                $item["count_weekly"] = StudyRecordModel::alias("a")
                    ->where("a.type", "weekly")
                    ->leftJoin(["ps_student" => "b"], "a.student_id=b.id")
                    ->where("b.school_id", $item["id"])
                    ->count();
                $item["count_monthy"] = StudyRecordModel::alias("a")
                    ->where("a.type", "monthy")
                    ->leftJoin(["ps_student" => "b"], "a.student_id=b.id")
                    ->where("b.school_id", $item["id"])
                    ->count();
                $parent_should_count = TeacherClassModel::where('school_id', $item['id'])->sum('num');
                $item['percent'] = round(floatval($count_parent) / (floatval($parent_should_count) + 1) * 100, 2) . "%";
            });
        $data_list->each(function ($data) {
            $json = ["school_id" => $data["id"]];
            $data['teacher_qr'] = '//api.ps.familyeducation.org.cn/v1/user/teacher/create?data=' . urlencode(json_encode($json, 320));
            return $data;
        });
        $page = $data_list->render();

        $area = AreaModel::column('id,name');

        $btn_access = [
            'title' => '对应课程',
            'icon' => 'fa fa-list',
//            'class' => 'btn btn-xs btn-default ajax-get',
            "target" => "_blank",
            'href' => "http://school.familyeducation.org.cn/admin/login?id=__id__"
        ];

        $top_upload = [
            'title' => '学校统计',
            'icon' => 'fa fa-fw fa-key',
            'href' => url('gen')
        ];

//        $todaytime = date('Y-m-d H:i:s', strtotime(date("Y-m-d"), time()));

//        $num1 = SchoolModel::where("date", ">", $todaytime)->count();
//        $num2 = SchoolModel::count();
        $grades = SchoolGradeModel::column("id,name");

        $area = AreaModel::column("id,name");

        return ZBuilder::make('table')
//            ->setPageTips("总数量：" . $num2 . "    今日数量：" . $num1, 'danger')
//            ->setPageTips("总数量：" . $num2, 'danger')
            ->addTopButton("add")
            ->setPageTitle('列表')
            ->addTopButton("学校统计", $top_upload)
//            ->setSearch(['area_id' => '区域ID']) // 设置搜索参数
            ->setSearchArea([['select', 'area_id', '区域', '', '', $area]])
            ->addOrder('id')
            ->addColumn('id', 'ID')
            ->addColumn('name', '学校名称', 'text')
            ->addColumn('percent', '注册率', 'text')
            ->addColumn('count_student', '学生数量', 'text')
            ->addColumn('count_parent', '家长数量', 'text')
            ->addColumn('count_daily', '每日', 'text')
            ->addColumn('count_weekly', '每周', 'text')
            ->addColumn('count_monthy', '每月', 'text')
            ->addColumn('grade_min', '最低年级', 'select', $grades)
            ->addColumn('grade_max', '最高年级', 'select', $grades)
            ->addColumn('remark', '技术支持', 'text.edit')
            ->addColumn('domain', '学校标签', 'text.edit')
            ->addColumn('province', '省', 'text.edit')
            ->addColumn('city', '城市', 'text.edit')
            ->addColumn('district', '区', 'text.edit')
            ->addColumn('area_id', '学校所属区域', 'select', $area)
            ->addColumn('content_type', '内容显示', 'select', ['普通' => "普通", '专属' => '专属', '混合' => '混合'])
            ->addColumn('detail', '详细说明')
            ->addColumn('sight', '可见性', 'number')
            ->addColumn('teacher_qr', '教师注册二维码', 'img_url')
            ->addColumn('icon', '学校图标', 'picture')
            ->addColumn('img', '学校宣传图', 'picture')
            ->addColumn('bg_img', '学校背景图', 'picture')
            ->addColumn('dashboard', '学校端权限', 'switch')
            ->addColumn('screen', '大屏端权限', 'switch')
            ->addColumn('rate_up', '金榜大于', 'number')
            ->addColumn('rate_down', '银榜大于', 'number')
            ->addColumn('right_button', '操作', 'btn')
            ->addRightButtons(["jump" => $btn_access])
            ->addRightButton('edit') // 添加编辑按钮
            ->addRightButton('delete') //添加删除按钮
            ->setRowList($data_list) // 设置表格数据
            ->setPages($page)
            ->fetch();
    }


    public function gen()
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            $school_id = $data['school_id'];
            $school = SchoolModel::where("id", $school_id)->find()->toArray();
            $minyear = \YearAction::CalcYear($school["grade_min"]);
            $maxyear = \YearAction::CalcYear($school["grade_max"]);
            $classes = TeacherClassModel::where("school_id", $school_id)->whereBetween("year", "$maxyear,$minyear")->order("year asc, class_id asc")->select()->toArray();
            $datas = [];
            foreach ($classes as $class) {
                $schoolid = $class["school_id"];
                $year = $class["year"];
                $class_id = $class["class_id"];
                $parents = Db::query("SELECT *,count(0) as count FROM `ps_study_record`a left join ps_student b on b.id=a.student_id where school_id=$schoolid and year=$year and class=$class_id group by student_id order by count desc limit 3");
                $datas[$schoolid . "_" . $year . "_" . $class_id] = $parents;
            }


//            foreach ($datas as $key => $value) {
//                $str = explode("_", $key);
//                $school_id = $str[0];
//                $year = $str[1];
//                $class_id = $str[2];
//                $grade = \YearAction::CalcGrade($year);
//                echo $grade . "年" . $class_id . "班" . "<br/>";
//                $int = 1;
//
//                foreach ($value as $k => $v) {
//                    $parent = ParentModel::where("id", $v["uid"])->find();
//                    $score = 0;
//                    $fenshu = BalanceModel::where("uid", $v["uid"])->where("student_id", $v["student_id"])->find();
//                    if ($fenshu) {
//                        $score = $fenshu["balance"];
//                    }
//                    echo "第" . $int . "名:" . $v["name"] . " 家长姓名:" . $parent["wx_name"] .
//                        "   学习量:" . $v["count"] .
//                        "   分数:" . floor($score) .
//                        "<br>";
//                    $int++;
//                }
//                echo "<br>";
//            }
            $excel = [];
            $excel[] = [
                '年级' => "年级",
                '班级' => "班级",
                '班级排名' => "班级排名",
                '学生姓名' => "学生姓名",
                '家长姓名' => "家长姓名",
                '学习量' => "学习量",
                '分数' => "分数",
            ];
            foreach ($datas as $key => $value) {
                $str = explode("_", $key);
                $year = $str[1];
                $class_id = $str[2];
                $grade = \YearAction::CalcGrade($year);
                $int = 1;

                foreach ($value as $k => $v) {
                    $parent = ParentModel::where('id', $v['uid'])->find();
                    $score = 0;
                    $fenshu = BalanceModel::where('student_id', $v['student_id'])->sum("balance");
                    $excel[] = [
                        '年级' => $grade,
                        '班级' => $class_id,
                        "班级排名" => $int,
                        "学生姓名" => $v['name'],
                        "家长姓名" => $parent['wx_name'],
                        "学习量" => $v['count'],
                        "分数" => $fenshu,
                    ];
                    $int++;
                }
            }

            $Aoss = new Excel(config('upload_prefix'));
            $ret = $Aoss->create_excel_fileurl($excel);
            $this->success('成功', $ret->file_url(), '_blank');

        }


        $schools = SchoolModel::column('id,name');
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['select', 'school_id', '学校', '', $schools],
            ])
//            ->assign("file_upload_url", "https://upload.familyeducation.org.cn:444/v1/excel/index/index?token=fsa")
            ->fetch();
    }


    /**
     * 新增
     * @return mixed
     * @throws \think\Exception
     */
    public
    function add()
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

            if ($user = SchoolModel::create($data)) {
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

        $area = AreaModel::column('id,name');

        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['text', 'name', '学校名称', ''],
                ['text', 'remark', '技术支持', ''],
                ['text', 'domain', '学校网址标签', '例如yunxiao,进入的时候就用www.网址.com/admin/login?domain=这个标签，来进入'],
                ['select', 'area_id', '学校所在id', '', $area],
                ['textarea', 'detail', '学校详细信息', ''],
                ['number', 'sight', '学校家长活动的可见性', ''],
                ['image', 'icon', '学校图标', ''],
                ['image', 'img', '学校图片', ''],
                ['image', 'bg_img', '学校背景图', ''],
                ['switch', 'dashboard', '学校端权限', ''],
                ['switch', 'screen', '大屏端权限', ''],
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
     */
    public
    function edit($id = null)
    {
        if ($id === null)
            $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = User::where('role', 'in', $role_list)
                ->column('id');
            if (!in_array($id, $user_list)) {
                $this->error('权限不足，没有可操作的用户');
            }
        }

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 非超级管理需要验证可选择角色


            if (SchoolModel::update($data)) {
                $user = SchoolModel::get($data['id']);
                // 记录行为
                action_log('edit_data', 'user', $id, UID, json_encode(input('post.'), 320));
                $this->success('编辑成功');
            } else {
                $this->error('编辑失败');
            }
        }

        // 获取数据
        $info = SchoolModel::where('id', $id)->find();
        $area = AreaModel::column("id,name");

        // 使用ZBuilder快速创建表单
        $data = ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['text', 'name', '学校名称', ''],
                ['text', 'remark', '技术支持', ''],
                ['text', 'domain', '学校网址标签', '例如yunxiao,进入的时候就用www.网址.com/admin/login?domain=这个标签，来进入'],
                ['select', 'area_id', '学校所在id', '', $area],
                ['textarea', 'detail', '学校详细信息', ''],
                ['number', 'sight', '学校家长活动的可见性', ''],
                ['image', 'icon', '学校图标', ''],
                ['image', 'img', '学校Banner', ''],
                ['image', 'bg_img', '学校背景图', ''],
                ['switch', 'dashboard', '学校端权限', ''],
                ['switch', 'screen', '大屏端权限', ''],
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
     */
    public
    function access($module = '', $uid = 0, $tab = '')
    {
        if ($uid === 0)
            $this->error('缺少参数');

        // 非超级管理员检查可编辑用户
        if (session('user_auth.role') != 1) {
            $role_list = RoleModel::getChildsId(session('user_auth.role'));
            $user_list = User::where('role', 'in', $role_list)
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
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public
    function delete($ids = [])
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
     */
    public
    function setStatus($type = '', $record = [])
    {
        $ids = $this->request->isPost() ? input('post.ids/a') : input('param.ids');
        $ids = (array)$ids;

        switch ($type) {
            case 'enable':
                if (false === SchoolModel::where('id', 'in', $ids)
                        ->setField('status', 1)) {
                    $this->error('启用失败');
                }
                break;
            case 'disable':
                if (false === SchoolModel::where('id', 'in', $ids)
                        ->setField('status', 0)) {
                    $this->error('禁用失败');
                }
                break;
            case 'delete':
                if (false === SchoolModel::where('id', 'in', $ids)
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
    private
    function buildJsTree($nodes = [], $curr_access = [], $user_access = [])
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
     */
    public
    function enable($ids = [])
    {
        Hook::listen('user_enable', $ids);
        return $this->setStatus('enable');
    }

    /**
     * 禁用用户
     * @param array $ids 用户id
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public
    function disable($ids = [])
    {
        Hook::listen('user_disable', $ids);
        return $this->setStatus('disable');
    }

    public
    function quickEdit($record = [])
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
        $result = SchoolModel::where("id", $id)
            ->setField($field, $value);
        if (false !== $result) {
            action_log('edit_data', 'user', $id, UID, json_encode(input('post.'), 320));
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
}
