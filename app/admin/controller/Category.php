<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Category as CategoryModel;
use WebBase\Core\Controller;
use WebBase\Core\extend\DataExtend;

/**
 * 树形分类示例控制器.
 * @class Category
 */
class Category extends Controller
{
    /**
     * 栏目列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->title = '树形分类示例';
        $this->type = input('type', 'index');
        $query = $this->type === 'recycle' ? CategoryModel::mRecycleQuery() : CategoryModel::mQuery();
        $query->layTable();
    }

    /**
     * 添加栏目.
     * @auth true
     */
    public function add()
    {
        CategoryModel::mForm('form');
    }

    /**
     * 编辑栏目.
     * @auth true
     */
    public function edit()
    {
        CategoryModel::mForm('form');
    }

    /**
     * 修改栏目状态.
     * @auth true
     */
    public function state()
    {
        CategoryModel::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除栏目.
     * @auth true
     */
    public function remove()
    {
        CategoryModel::mDelete();
    }

    /**
     * 恢复回收站栏目.
     * @auth true
     */
    public function restore()
    {
        CategoryModel::mRestore();
    }

    /**
     * 永久删除回收站栏目.
     * @auth true
     */
    public function destroy()
    {
        CategoryModel::mForceDelete();
    }

    /**
     * 列表数据处理，将平级数据转换为树形表格.
     */
    protected function _index_page_filter(array &$data)
    {
        if (input('type', 'index') === 'index') {
            $data = DataExtend::arr2table(DataExtend::arr2tree($data));
        }
    }

    /**
     * 表单数据处理.
     */
    protected function _form_filter(array &$vo)
    {
        if ($this->request->isPost()) {
            $vo = array_intersect_key($vo, array_flip(['id', 'pid', 'title', 'sort', 'status', 'cover', 'icon', 'desc']));
            $id = (int)($vo['id'] ?? 0);
            $pid = (int)($vo['pid'] ?? 0);
            $vo['pid'] = $pid;
            if (trim((string)($vo['title'] ?? '')) === '' || mb_strlen((string)$vo['title']) > 100 || $pid < 0 ||
                (isset($vo['status']) && !in_array((string)$vo['status'], ['0', '1'], true)) ||
                (isset($vo['sort']) && (!ctype_digit((string)$vo['sort']) || (int)$vo['sort'] > 999999))) {
                $this->error('分类名称、上级分类、排序或状态不合法。');
            }
            if ($id > 0 && !CategoryModel::mk()->where('id', $id)->count()) {
                $this->error('分类不存在或已删除。');
            }
            $rows = CategoryModel::mk()->column('pid', 'id');
            $seen = [];
            $depth = 1;
            for ($parent = $pid; $parent > 0; $parent = (int)$rows[$parent]) {
                if ($parent === $id || isset($seen[$parent]) || !array_key_exists($parent, $rows)) {
                    $this->error('上级分类不存在或存在循环关系。');
                }
                $seen[$parent] = true;
                if (++$depth > 3) {
                    $this->error('分类最多支持三级。');
                }
            }
            // A moved subtree must fit the same depth limit.
            $frontier = $id > 0 ? [$id] : [];
            while ($frontier !== []) {
                $children = [];
                foreach ($rows as $child => $parent) {
                    if (in_array((int)$parent, $frontier, true)) {
                        $children[] = (int)$child;
                    }
                }
                if ($children !== [] && ++$depth > 3) {
                    $this->error('移动后子分类将超过三级，请先调整子分类。');
                }
                $frontier = $children;
            }
            return;
        }
        if ($this->request->isGet()) {
            $vo['pid'] = $vo['pid'] ?? input('pid', '0');
            $categories = CategoryModel::mk()->order('sort asc,id asc')->column('id,pid,title', 'id');
            $this->categories = DataExtend::arr2table(array_merge($categories, [['id' => '0', 'pid' => '-1', 'title' => '顶级栏目']]));
            foreach ($this->categories as $key => $cate) {
                if ($cate['spt'] >= 3) {
                    unset($this->categories[$key]);
                }
            }
        }
    }

    protected function _delete_filter($query)
    {
        $ids = (clone $query)->column('id');
        if ($ids && CategoryModel::mk()->whereIn('pid', $ids)->whereNotIn('id', $ids)->count()) {
            $this->error('请先删除子分类，或同时选择完整子树。');
        }
    }

    protected function _restore_filter($query)
    {
        $rows = (clone $query)->column('pid', 'id');
        foreach ($rows as $pid) {
            if ((int)$pid > 0 && !array_key_exists($pid, $rows) && !CategoryModel::mk()->where('id', $pid)->count()) {
                $this->error('请先恢复上级分类。');
            }
        }
    }

    protected function _force_delete_filter($query)
    {
        $ids = (clone $query)->column('id');
        if ($ids && CategoryModel::mk()->withTrashed()->whereIn('pid', $ids)->whereNotIn('id', $ids)->count()) {
            $this->error('请先永久删除子分类，或同时选择完整子树。');
        }
    }
}
