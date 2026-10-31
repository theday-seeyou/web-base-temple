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
use WebBase\Core\model\SystemOplog;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\HttpResponseException;

/**
 * 系统日志管理.
 * @class Oplog
 */
class Oplog extends Controller
{
    /**
     * 系统日志管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        SystemOplog::mQuery()->layTable(function () {
            $this->title = '系统日志管理';
            $columns = SystemOplog::mk()->column('action,username', 'id');
            $this->users = array_unique(array_column($columns, 'username'));
            $this->actions = array_unique(array_column($columns, 'action'));
        }, static function (QueryHelper $query) {
            $query->dateBetween('create_at')->equal('username,action')->like('content,geoip,node');
        });
    }

    /**
     * 清理系统日志.
     * @auth true
     */
    public function clear()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST 请求。');
        }
        try {
            SystemOplog::mQuery()->empty();
            sysoplog('系统运维管理', '成功清理所有日志');
            $this->success('日志清理成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            $this->error(lang('日志清理失败，%s', [$exception->getMessage()]));
        }
    }

    /**
     * 删除系统日志.
     * @auth true
     */
    public function remove()
    {
        SystemOplog::mDelete();
    }

    /**
     * 列表数据处理.
     * @throws \Exception
     */
    protected function _index_page_filter(array &$data)
    {
        foreach ($data as &$vo) {
            $vo['geoisp'] = '';
        }
    }
}
