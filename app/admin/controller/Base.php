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
use WebBase\Core\model\SystemBase;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 数据字典管理.
 * @class Base
 */
class Base extends Controller
{
    /**
     * 数据字典管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        SystemBase::mQuery()->layTable(function () {
            $this->title = '数据字典管理';
            $this->types = SystemBase::types();
            $this->type = $this->get['type'] ?? ($this->types[0] ?? '-');
        }, static function (QueryHelper $query) {
            $query->where(['deleted' => 0])->equal('type');
            $query->like('code,name,status')->dateBetween('create_at');
        });
    }

    /**
     * 添加数据字典.
     * @auth true
     */
    public function add()
    {
        SystemBase::mForm('form');
    }

    /**
     * 编辑数据字典.
     * @auth true
     */
    public function edit()
    {
        SystemBase::mForm('form');
    }

    /**
     * 修改数据状态
     * @auth true
     */
    public function state()
    {
        SystemBase::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除数据记录.
     * @auth true
     */
    public function remove()
    {
        SystemBase::mDelete();
    }

    /**
     * 表单数据处理.
     * @throws DbException
     */
    protected function _form_filter(array &$data)
    {
        if ($this->request->isGet()) {
            $this->types = SystemBase::types();
            $this->types[] = '--- ' . lang('新增类型') . ' ---';
            $this->type = $this->get['type'] ?? ($this->types[0] ?? '-');
        } else {
            $map = [];
            $map[] = ['deleted', '=', 0];
            $map[] = ['code', '=', $data['code']];
            $map[] = ['type', '=', $data['type']];
            $map[] = ['id', '<>', $data['id'] ?? 0];
            if (SystemBase::mk()->where($map)->count() > 0) {
                $this->error('数据编码已经存在！');
            }
        }
    }
}
