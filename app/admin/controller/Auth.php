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
use WebBase\Core\model\SystemNode;
use WebBase\Core\Plugin;
use WebBase\Core\service\AdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统权限管理.
 * @class Auth
 */
class Auth extends Controller
{
    /**
     * 系统权限管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        SystemAuth::mQuery()->layTable(function () {
            $this->title = '系统权限管理';
        }, static function (QueryHelper $query) {
            $query->like('title,desc')->equal('status,utype')->dateBetween('create_at');
        });
    }

    /**
     * 修改权限状态
     * @auth true
     */
    public function state()
    {
        SystemAuth::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除系统权限.
     * @auth true
     */
    public function remove()
    {
        SystemAuth::mDelete();
    }

    /**
     * 添加系统权限.
     * @auth true
     */
    public function add()
    {
        SystemAuth::mForm('form');
    }

    /**
     * 编辑系统权限.
     * @auth true
     */
    public function edit()
    {
        SystemAuth::mForm('form');
    }

    /**
     * 表单后置数据处理.
     */
    protected function _form_filter(array $data)
    {
        if ($this->request->isGet()) {
            $this->title = empty($data['title']) ? '添加访问授权' : "编辑【{$data['title']}】授权";
        } elseif ($this->request->post('action') === 'json') {
            if ($this->app->isDebug()) {
                AdminService::clear();
            }
            $ztree = AdminService::getTree(empty($data['id']) ? [] : SystemNode::mk()->where(['auth' => $data['id']])->column('node'));
            usort($ztree, static function ($a, $b) {
                if (explode('-', $a['node'])[0] !== explode('-', $b['node'])[0]) {
                    if (stripos($a['node'], 'plugin-') === 0) {
                        return 1;
                    }
                }
                return $a['node'] === $b['node'] ? 0 : ($a['node'] > $b['node'] ? 1 : -1);
            });
            [$ps, $cs] = [Plugin::get(), (array)$this->app->config->get('app.app_names', [])];
            foreach ($ztree as &$n) {
                $n['title'] = lang($cs[$n['node']] ?? (($ps[$n['node']] ?? [])['name'] ?? $n['title']));
            }
            $this->success('获取权限节点成功！', $ztree);
        } elseif (empty($data['nodes'])) {
            $this->error('未配置功能节点！');
        }
    }

    /**
     * 节点更新处理.
     */
    protected function _form_result(bool $state, array $post)
    {
        if ($state && $this->request->post('action') === 'save') {
            [$map, $data] = [['auth' => $post['id']], []];
            foreach ($post['nodes'] ?? [] as $node) {
                $data[] = $map + ['node' => $node];
            }
            SystemNode::mk()->where($map)->delete();
            count($data) > 0 && SystemNode::mk()->insertAll($data);
            sysoplog('系统权限管理', "配置系统权限[{$map['auth']}]授权成功");
            $this->success('权限修改成功！', 'javascript:history.back()');
        }
    }
}
