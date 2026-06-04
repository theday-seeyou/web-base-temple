<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Category as CategoryModel;
use think\admin\Controller;
use think\admin\extend\DataExtend;

/**
 * 栏目管理控制器.
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
        $this->title = '栏目管理';
        CategoryModel::mQuery()->layTable();
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
     * 列表数据处理，将平级数据转换为树形表格.
     */
    protected function _index_page_filter(array &$data)
    {
        $data = DataExtend::arr2table(DataExtend::arr2tree($data));
    }

    /**
     * 表单数据处理.
     */
    protected function _form_filter(array &$vo)
    {
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
}