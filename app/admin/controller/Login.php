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
use WebBase\Core\extend\CodeExtend;
use WebBase\Core\model\SystemUser;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\PasswordService;
use WebBase\Core\service\RateLimitService;
use WebBase\Core\service\CaptchaService;
use WebBase\Core\service\RuntimeService;
use WebBase\Core\service\SystemService;

/**
 * 用户登录管理.
 * @class Login
 */
class Login extends Controller
{
    /**
     * 后台登录入口.
     * @throws Exception
     */
    public function index()
    {
        if ($this->app->request->isGet()) {
            if (AdminService::isLogin()) {
                $this->redirect(sysuri('admin/index/index'));
            } else {
                // 加载登录模板
                $this->title = '系统登录';
                // 登录验证令牌
                $this->captchaType = 'LoginCaptcha';
                $this->captchaToken = CodeExtend::uuid();
                // 当前运行模式
                $this->runtimeMode = RuntimeService::check();
                // 后台背景处理
                $images = str2arr(sysconf('login_image|raw') ?: '', '|');
                if (empty($images)) {
                    $images = [
                        SystemService::uri('/static/theme/img/login/bg1.jpg'),
                        SystemService::uri('/static/theme/img/login/bg2.jpg'),
                    ];
                }
                $this->loginStyle = sprintf('style="background-image:url(%s)" data-bg-transition="%s"', $images[0], join(',', $images));
                $this->fetch();
            }
        } else {
            if (!RateLimitService::hit('login:ip:' . $this->request->ip(), 40, 900)) {
                $this->error('登录尝试过于频繁，请 15 分钟后重试。');
            }
            $data = $this->_vali([
                'username.require' => '登录账号不能为空!',
                'username.min:4' => '账号不能少于4位字符!',
                'password.require' => '登录密码不能为空!',
                'verify.require' => '图形验证码不能为空!',
                'uniqid.require' => '图形验证标识不能为空!',
            ], PasswordService::input($this->request));
            $challenge = $this->app->session->get('login_captcha', '');
            $this->app->session->delete('login_captcha');
            if (!hash_equals((string)$challenge, $data['uniqid']) || !CaptchaService::instance()->check($data['verify'], $data['uniqid'])) {
                $this->error('图形验证码验证失败，请重新输入!');
            }
            if (!RateLimitService::hit('login:user:' . strtolower($data['username']), 15, 900)) {
                $this->error('登录尝试过于频繁，请 15 分钟后重试。');
            }
            /* ! 用户信息验证 */
            $map = ['username' => $data['username']];
            $user = SystemUser::mk()->where($map)->findOrEmpty();
            if ($user->isEmpty()) {
                $this->app->session->set('LoginInputSessionError', true);
                $this->error('登录账号或密码错误，请重新输入!');
            }
            if (empty($user['status'])) {
                $this->app->session->set('LoginInputSessionError', true);
                $this->error('账号已经被禁用，请联系管理员!');
            }
            if (!PasswordService::verify($data['password'], (string)$user['password'])) {
                $this->app->session->set('LoginInputSessionError', true);
                $this->error('登录账号或密码错误，请重新输入!');
            }
            if (PasswordService::needsRehash((string)$user['password'])) {
                $user->save(['password' => PasswordService::hash($data['password'])]);
            }
            $this->app->session->regenerate(true);
            $this->app->session->delete('_csrf');
            $this->app->session->set('auth_password', hash('sha256', (string)$user['password']));
            $user->hidden(['sort', 'status', 'password', 'deleted_at']);
            $this->app->session->set('user', $user->toArray());
            $this->app->session->delete('LoginInputSessionError');
            // 更新登录次数
            $user->where(['id' => $user->getAttr('id')])->inc('login_num')->update([
                'login_at' => date('Y-m-d H:i:s'), 'login_ip' => $this->app->request->ip(),
            ]);
            // 刷新用户权限
            AdminService::apply(true);
            sysoplog('系统用户登录', '登录系统后台成功');
            $this->success('登录成功', sysuri('admin/index/index'));
        }
    }

    /**
     * 生成验证码
     */
    public function captcha()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST 请求。');
        }
        $input = $this->_vali([
            'type.require' => '类型不能为空!',
            'token.require' => '标识不能为空!',
        ]);
        if (!RateLimitService::hit('captcha:ip:' . $this->request->ip(), 60, 300)) {
            $this->error('验证码请求过于频繁，请稍后再试。');
        }
        $previous = $this->app->session->get('login_captcha', '');
        if ($previous) {
            $this->app->cache->delete($previous);
        }
        $image = CaptchaService::instance()->initialize();
        $this->app->session->set('login_captcha', $image->getUniqid());
        $captcha = ['image' => $image->getData(), 'uniqid' => $image->getUniqid()];
        $this->success('生成验证码成功', $captcha);
    }

    /**
     * 退出登录.
     */
    public function out()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST 请求。');
        }
        $this->app->session->destroy();
        $this->success('退出登录成功!', sysuri('admin/login/index'));
    }
}
