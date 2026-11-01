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
use WebBase\Core\extend\DataExtend;
use WebBase\Core\model\SystemMenu;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\MenuService;
use WebBase\Core\service\NodeService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统菜单管理.
 * @class Menu
 */
class Menu extends Controller
{
    /**
     * 系统菜单管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $this->title = '系统菜单管理';
        $this->type = $this->get['type'] ?? 'index';
        // 获取顶级菜单ID
        $this->pid = $this->get['pid'] ?? '';

        // 查询顶级菜单集合
        $this->menupList = SystemMenu::mk()->where(['pid' => 0, 'status' => 1])->order('sort desc,id asc')->column('id,pid,title', 'id');

        $query = $this->type === 'recycle' ? SystemMenu::mRecycleQuery() : SystemMenu::mQuery();
        $query->layTable();
    }

    /**
     * 添加系统菜单.
     * @auth true
     */
    public function add()
    {
        $this->_applyFormToken();
        SystemMenu::mForm('form');
    }

    /**
     * 编辑系统菜单.
     * @auth true
     */
    public function edit()
    {
        $this->_applyFormToken();
        SystemMenu::mForm('form');
    }

    /**
     * 修改菜单状态
     * @auth true
     */
    public function state()
    {
        SystemMenu::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除系统菜单.
     * @auth true
     */
    public function remove()
    {
        SystemMenu::mDelete();
    }

    /**
     * 恢复回收站菜单.
     * @auth true
     */
    public function restore()
    {
        SystemMenu::mRestore();
    }

    /**
     * 永久删除回收站菜单.
     * @auth true
     */
    public function destroy()
    {
        SystemMenu::mForceDelete();
    }

    /**
     * 列表数据处理.
     */
    protected function _index_page_filter(array &$data)
    {
        if ($this->type === 'recycle') {
            foreach ($data as &$row) {
                $row['spl'] = '';
            }
            unset($row);
        } else {
            $data = DataExtend::arr2table(DataExtend::arr2tree($data));
        }

        // 过滤非当前顶级菜单的下级菜单,并重新索引数组
        if ($this->type === 'index' && $this->pid) {
            $data = array_values(array_filter($data, function ($item) {
                return strpos($item['spp'], ",{$this->pid},") !== false;
            }));
        }

        foreach ($data as &$vo) {
            if ($vo['url'] !== '#' && !preg_match('/^(https?:)?(\/\/|\\\)/i', $vo['url'])) {
                $vo['url'] = trim(url($vo['url']) . ($vo['params'] ? "?{$vo['params']}" : ''), '\/');
            }
        }
    }

    /**
     * 表单数据处理.
     */
    protected function _form_filter(array &$vo)
    {
        if ($this->request->isPost()) {
            $vo = array_intersect_key($vo, array_flip(['id', 'pid', 'title', 'icon', 'node', 'url', 'params', 'target', 'sort', 'status']));
            $id = (int)($vo['id'] ?? 0);
            $pid = (int)($vo['pid'] ?? 0);
            $vo['pid'] = $pid;
            if (trim((string)($vo['title'] ?? '')) === '' || mb_strlen((string)$vo['title']) > 100 || $pid < 0) {
                $this->error('菜单名称或上级菜单不合法。');
            }
            $rows = SystemMenu::mk()->column('pid', 'id');
            $seen = [];
            $depth = 1;
            for ($parent = $pid; $parent > 0; $parent = (int)$rows[$parent]) {
                if ($parent === $id || isset($seen[$parent]) || !array_key_exists($parent, $rows)) {
                    $this->error('上级菜单不存在或存在循环关系。');
                }
                $seen[$parent] = true;
                if (++$depth > 3) {
                    $this->error('菜单最多支持三级。');
                }
            }
            $frontier = $id > 0 ? [$id] : [];
            while ($frontier !== []) {
                $children = [];
                foreach ($rows as $child => $parent) {
                    if (in_array((int)$parent, $frontier, true)) {
                        $children[] = (int)$child;
                    }
                }
                if ($children !== [] && ++$depth > 3) {
                    $this->error('移动后子菜单将超过三级。');
                }
                $frontier = $children;
            }
            return;
        }
        if ($this->request->isGet()) {
            $debug = $this->app->isDebug();
            /* 清理权限节点 */
            $debug && AdminService::clear();
            /* 读取系统功能节点 */
            $this->auths = [];
            $this->nodes = MenuService::getList($debug);
            foreach (NodeService::getMethods($debug) as $node => $item) {
                if ($item['isauth'] && substr_count($node, '/') >= 2) {
                    $this->auths[] = ['node' => $node, 'title' => $item['title']];
                }
            }
            /* 选择自己上级菜单 */
            $vo['pid'] = $vo['pid'] ?? input('pid', '0');
            /* 列出可选上级菜单 */
            $menus = SystemMenu::mk()->order('sort desc,id asc')->column('id,pid,icon,url,node,title,params,target,sort,status', 'id');
            $this->menus = DataExtend::arr2table(array_merge($menus, [['id' => '0', 'pid' => '-1', 'url' => '#', 'title' => '顶部菜单']]));
            if (isset($vo['id'])) {
                foreach ($this->menus as $menu) {
                    if ($menu['id'] === $vo['id']) {
                        $vo = $menu;
                    }
                }
            }
            foreach ($this->menus as $key => $menu) {
                if ($menu['spt'] >= 3 || $menu['url'] !== '#') {
                    unset($this->menus[$key]);
                }
            }
            if (isset($vo['spt'], $vo['spc']) && in_array($vo['spt'], [1, 2]) && $vo['spc'] > 0) {
                foreach ($this->menus as $key => $menu) {
                    if ($vo['spt'] <= $menu['spt']) {
                        unset($this->menus[$key]);
                    }
                }
            }
        }
    }

    protected function _delete_filter($query)
    {
        $ids = (clone $query)->column('id');
        if ($ids && SystemMenu::mk()->whereIn('pid', $ids)->whereNotIn('id', $ids)->count()) {
            $this->error('请先删除子菜单，或同时选择完整子树。');
        }
    }

    protected function _restore_filter($query)
    {
        $rows = (clone $query)->whereNotNull('deleted_at')->column('pid', 'id');
        foreach ($rows as $pid) {
            if ((int)$pid > 0 && !array_key_exists($pid, $rows) && !SystemMenu::mk()->where('id', $pid)->count()) {
                $this->error('请先恢复上级菜单。');
            }
        }
    }

    protected function _force_delete_filter($query)
    {
        $ids = (clone $query)->whereNotNull('deleted_at')->column('id');
        if ($ids && SystemMenu::mk()->withTrashed()->whereIn('pid', $ids)->whereNotIn('id', $ids)->count()) {
            $this->error('请先永久删除子菜单，或同时选择完整子树。');
        }
    }
}
