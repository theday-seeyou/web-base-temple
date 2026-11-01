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
use WebBase\Core\Exception;
use WebBase\Core\model\SystemUser;
use WebBase\Core\service\AdminService;
use app\admin\service\AdminLanguageService;
use WebBase\Core\service\PasswordService;
use WebBase\Core\service\MenuService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 后台界面入口.
 * @class Index
 */
class Index extends Controller
{
    /**
     * 显示后台首页.
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        /* ! 根据运行模式刷新权限 */
        AdminService::apply($this->app->isDebug());
        /* ! 读取当前用户权限菜单树 */
        $this->menus = MenuService::getTree();
        /* ! 判断当前用户的登录状态 */
        $this->login = AdminService::isLogin();
        /* ! 菜单为空且未登录跳转到登录页 */
        if (empty($this->menus) && empty($this->login)) {
            $this->redirect(sysuri('admin/login/index'));
        } else {
            $this->title = '系统管理后台';
            $this->super = AdminService::isSuper();
            $this->theme = AdminService::getUserTheme();
            $this->adminLocale = AdminLanguageService::current();
            $this->adminLanguages = array_intersect_key(AdminLanguageService::catalog(), array_flip(AdminLanguageService::settings()['enabled']));
            $this->fetch();
        }
    }

    /**
     * 切换后台全局语言。
     * @login true
     */
    public function locale()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'info' => lang('请使用 POST 请求。')], 405);
        }
        try {
            $locale = AdminLanguageService::select($this->request->post('locale'));
        } catch (\InvalidArgumentException $exception) {
            return json(['code' => 0, 'info' => lang($exception->getMessage())], 422);
        }
        return json(['code' => 1, 'info' => lang('语言已切换。'), 'data' => ['locale' => $locale]]);
    }

    /**
     * 后台全局语言设置。
     * @login true
     */
    public function languages()
    {
        if (!AdminService::isSuper()) {
            return json(['code' => 0, 'info' => lang('请使用超管账号操作！')], 403);
        }
        if ($this->request->isGet()) {
            $this->title = '全局语言设置';
            $this->catalog = AdminLanguageService::catalog();
            $this->languages = AdminLanguageService::settings();
            $this->fetch();
        }
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'info' => lang('请使用 POST 请求。')], 405);
        }
        try {
            AdminLanguageService::saveSettings($this->request->post('enabled', []), $this->request->post('default'));
        } catch (\InvalidArgumentException $exception) {
            return json(['code' => 0, 'info' => lang($exception->getMessage())], 422);
        }
        sysoplog('全局语言设置', '更新后台启用语言与默认语言');
        $this->success('数据保存成功！', 'javascript:location.reload()');
    }

    /**
     * 后台主题切换.
     * @login true
     * @throws Exception
     */
    public function theme()
    {
        if ($this->request->isGet()) {
            $this->theme = AdminService::getUserTheme();
            $this->themes = Config::themes;
            $this->fetch();
        } else {
            $data = $this->_vali(['site_theme.require' => '主题名称不能为空！']);
            if (AdminService::setUserTheme($data['site_theme'])) {
                $this->success('主题配置保存成功！');
            } else {
                $this->error('主题配置保存失败！');
            }
        }
    }

    /**
     * 修改用户资料.
     * @login true
     */
    public function info()
    {
        $id = $this->request->param('id');
        if (AdminService::getUserId() == intval($id)) {
            SystemUser::mForm('user/form', 'id', [], ['id' => $id]);
        } else {
            $this->error('只能修改自己的资料！');
        }
    }

    /**
     * 修改当前用户密码
     * @login true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function pass()
    {
        $id = $this->request->param('id');
        if (AdminService::getUserId() !== intval($id)) {
            $this->error('禁止修改他人密码！');
        }
        if ($this->app->request->isGet()) {
            $this->verify = true;
            SystemUser::mForm('user/pass', 'id', [], ['id' => $id]);
        } else {
            $data = $this->_vali([
                'password.require' => '登录密码不能为空！',
                'repassword.require' => '重复密码不能为空！',
                'oldpassword.require' => '旧的密码不能为空！',
                'password.confirm:repassword' => '两次输入的密码不一致！',
            ], PasswordService::input($this->request));
            $user = SystemUser::mk()->find($id);
            if (empty($user)) {
                $this->error('用户不存在！');
            }
            if (!PasswordService::verify($data['oldpassword'], (string)$user['password'])) {
                $this->error('旧密码验证失败，请重新输入！');
            }
            try {
                $hash = PasswordService::hash($data['password']);
            } catch (\InvalidArgumentException $exception) {
                $this->error($exception->getMessage());
            }
            if ($user->save(['password' => $hash])) {
                sysoplog('系统用户管理', "修改用户[{$user['id']}]密码成功");
                // 修改密码同步事件处理
                $this->app->event->trigger('PluginAdminChangePassword', [
                    'uuid' => intval($user['id']),
                ]);
                $this->app->session->destroy();
                $this->success('密码修改成功，请重新登录！', sysuri('admin/login/index'));
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 资料修改表单处理.
     */
    protected function _info_form_filter(array &$data)
    {
        if ($this->request->isPost()) {
            $data = array_intersect_key($data, array_flip(['id', 'nickname', 'headimg', 'contact_phone', 'contact_mail', 'contact_qq', 'describe']));
        }
    }

    /**
     * 资料修改结果处理.
     */
    protected function _info_form_result(bool $status)
    {
        if ($status) {
            $this->success('用户资料修改成功！', 'javascript:location.reload()');
        }
    }
}
