<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | WebBase CMS Component
 * +----------------------------------------------------------------------
 * | Copyright (c) 2026 WebBase contributors
 * +----------------------------------------------------------------------
 * | 官方网站: https://github.com/theday-seeyou/web-base-temple
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * +----------------------------------------------------------------------
 * | github 代码仓库：https://github.com/theday-seeyou/web-base-temple
 * +----------------------------------------------------------------------
 */

namespace app\admin\controller;

use WebBase\Core\Controller;
use WebBase\Core\helper\QueryHelper;
use WebBase\Core\model\SystemAuth;
use WebBase\Core\model\SystemBase;
use WebBase\Core\model\SystemUser;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\PasswordService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统用户管理.
 * @class User
 */
class User extends Controller
{
    /**
     * 系统用户管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        $query = $this->type === 'recycle' ? SystemUser::mRecycleQuery() : SystemUser::mQuery();
        $query->layTable(function () {
            $this->title = '系统用户管理';
            $this->bases = SystemBase::items('身份权限');
        }, function (QueryHelper $query) {
            // 关联用户身份资料
            /* @var \think\model\Relation|\think\db\Query $query */
            $query->with(['userinfo' => static function ($query) {
                $query->field('code,name,content');
            }]);

            // 数据列表搜索过滤
            $query->equal('status,usertype')->dateBetween('login_at,create_at');
            $query->like('username|nickname#username,contact_phone#phone,contact_mail#mail');
        });
    }

    /**
     * 添加系统用户.
     * @auth true
     */
    public function add()
    {
        SystemUser::mForm('form');
    }

    /**
     * 编辑系统用户.
     * @auth true
     */
    public function edit()
    {
        SystemUser::mForm('form');
    }

    /**
     * 修改用户密码
     * @auth true
     */
    public function pass()
    {
        $this->_applyFormToken();
        if ($this->request->isGet()) {
            $this->verify = false;
            SystemUser::mForm('pass');
        } else {
            $data = $this->_vali([
                'id.require' => '用户ID不能为空！',
                'password.require' => '登录密码不能为空！',
                'repassword.require' => '重复密码不能为空！',
                'repassword.confirm:password' => '两次输入的密码不一致！',
            ], PasswordService::input($this->request));
            $user = SystemUser::mk()->findOrEmpty($data['id']);
            try {
                $hash = PasswordService::hash($data['password']);
            } catch (\InvalidArgumentException $exception) {
                $this->error($exception->getMessage());
            }
            if ($user->isExists() && $user->save(['password' => $hash])) {
                // 修改密码同步事件处理
                $this->app->event->trigger('PluginAdminChangePassword', [
                    'uuid' => $data['id'],
                ]);
                sysoplog('系统用户管理', "修改用户[{$data['id']}]密码成功");
                $this->success('密码修改成功，请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 修改用户状态
     * @auth true
     */
    public function state()
    {
        $this->_checkInput();
        SystemUser::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除系统用户.
     * @auth true
     */
    public function remove()
    {
        $this->_checkInput();
        SystemUser::mDelete();
    }

    /**
     * 恢复回收站用户.
     * @auth true
     */
    public function restore()
    {
        $this->_checkInput();
        SystemUser::mRestore();
    }

    /**
     * 永久删除回收站用户.
     * @auth true
     */
    public function destroy()
    {
        $this->_checkInput();
        SystemUser::mForceDelete();
    }

    /**
     * 表单数据处理.
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function _form_filter(array &$data)
    {
        if ($this->request->isPost()) {
            $data = array_intersect_key($data, array_flip(['id', 'username', 'nickname', 'headimg', 'authorize', 'usertype', 'contact_qq', 'contact_phone', 'contact_mail', 'describe', 'status']));
            // 检查资料是否完整
            empty($data['username']) && $this->error('登录账号不能为空！');
            if ($data['username'] !== AdminService::getSuperName()) {
                empty($data['authorize']) && $this->error('未配置权限！');
            }
            // 处理上传的权限格式
            $roles = $data['authorize'] ?? [];
            $data['authorize'] = arr2str(is_array($roles) ? $roles : str2arr((string)$roles));
            if (empty($data['id'])) {
                // 检查账号是否重复
                $map = ['username' => $data['username']];
                if (SystemUser::mk()->withTrashed()->where($map)->count() > 0) {
                    $this->error('账号已经存在，请使用其它账号！');
                }
                try {
                    $data['password'] = PasswordService::hash($this->request->post('password/s', '', null));
                } catch (\InvalidArgumentException $exception) {
                    $this->error($exception->getMessage());
                }
            } else {
                unset($data['username'], $data['password']);
            }
        } else {
            // 权限绑定处理
            $data['authorize'] = str2arr($data['authorize'] ?? '');
            $this->auths = SystemAuth::items();
            $this->bases = SystemBase::items('身份权限');
            $this->super = AdminService::getSuperName();
        }
    }

    /**
     * 检查输入变量.
     */
    private function _checkInput()
    {
        if (in_array('10000', str2arr(input('id', '')))) {
            $this->error('系统超级账号禁止删除！');
        }
    }
}
