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
use WebBase\Core\model\SystemFile;
use WebBase\Core\service\AdminService;
use WebBase\Core\Storage;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统文件管理.
 * @class File
 */
class File extends Controller
{
    /**
     * 存储类型.
     * @var array
     */
    protected $types;

    /**
     * 系统文件管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $this->scope = input('scope', 'index');
        $query = $this->scope === 'recycle' ? SystemFile::mRecycleQuery() : SystemFile::mQuery();
        $query->layTable(function () {
            $this->title = '系统文件管理';
            $this->xexts = SystemFile::mk()->distinct()->column('xext');
        }, function (QueryHelper $query) {
            $query->like('name,hash,xext')->equal('type')->dateBetween('created_at');
            $query->where(['issafe' => 0, 'status' => 2])->where($this->ownerWhere());
        });
    }

    /**
     * 编辑系统文件.
     * @auth true
     */
    public function edit()
    {
        SystemFile::mForm('form', 'id', $this->ownerWhere());
    }

    /**
     * 删除系统文件.
     * @auth true
     */
    public function remove()
    {
        SystemFile::mDelete('', $this->ownerWhere());
    }

    /**
     * 恢复回收站文件记录.
     * @auth true
     */
    public function restore()
    {
        SystemFile::mRestore('', $this->ownerWhere());
    }

    /**
     * 永久删除回收站文件记录.
     * @auth true
     */
    public function destroy()
    {
        SystemFile::mForceDelete('', $this->ownerWhere());
    }

    private function ownerWhere(): array
    {
        return AdminService::isSuper() ? [] : ['uuid' => AdminService::getUserId()];
    }

    protected function _form_filter(array &$data)
    {
        if ($this->request->isPost()) {
            $data = array_intersect_key($data, array_flip(['id', 'name', 'tags']));
        }
    }

    /**
     * 清理重复文件.
     * @auth true
     * @throws DbException
     */
    public function distinct()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST 请求。');
        }
        $map = ['issafe' => 0, 'uuid' => AdminService::getUserId()];
        // 使用派生表包装子查询，避免直接引用同一表
        $keepSubQuery = SystemFile::mk()->fieldRaw('MAX(id) AS id')->where($map)->group('type, xkey')->buildSql();
        // 使用 whereNotExists 配合派生表子查询删除，避免 1093 错误和 whereIn
        SystemFile::mk()->alias('source')->where($map)->whereNotExists(function ($query) use ($keepSubQuery) {
            $query->table("({$keepSubQuery})")->alias('f2')->whereRaw('f2.id = source.id');
        })->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->success('清理重复文件成功！');
    }

    /**
     * 控制器初始化.
     */
    protected function initialize()
    {
        $this->types = Storage::types();
    }

    /**
     * 数据列表处理.
     */
    protected function _page_filter(array &$data)
    {
        foreach ($data as &$vo) {
            $vo['ctype'] = $this->types[$vo['type']] ?? $vo['type'];
        }
    }
}
