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
use WebBase\Core\model\SystemQueue;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\ProcessService;
use WebBase\Core\service\QueueService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\HttpResponseException;

/**
 * 系统任务管理.
 * @class Queue
 */
class Queue extends Controller
{
    /**
     * 系统任务管理.
     * @auth true
     * @menu true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        SystemQueue::mQuery()->layTable(function () {
            $this->title = '系统任务管理';
            $this->iswin = ProcessService::iswin();
            if ($this->super = AdminService::isSuper()) {
                $this->command = ProcessService::think('webbase:queue start');
                if (!$this->iswin && !empty($_SERVER['USER'])) {
                    $this->command = "sudo -u {$_SERVER['USER']} {$this->command}";
                }
            }
        }, static function (QueryHelper $query) {
            $query->equal('status')->like('code|title#title,command');
            $query->timeBetween('enter_time,exec_time')->dateBetween('create_at');
        });
    }

    /**
     * 重启系统任务
     * @auth true
     */
    public function redo()
    {
        try {
            $data = $this->_vali(['code.require' => '任务编号不能为空！']);
            $queue = QueueService::instance()->initialize($data['code'])->reset();
            $queue->progress(1, '>>> 任务重置成功 <<<', '0.00');
            $this->success('任务重置成功！', $queue->code);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            $this->error($exception->getMessage());
        }
    }

    /**
     * 清理运行数据.
     * @auth true
     */
    public function clean()
    {
        $this->_queue('定时清理系统运行数据', 'webbase:queue clean', 0, [], 0, 3600);
    }

    /**
     * 删除系统任务
     * @auth true
     */
    public function remove()
    {
        SystemQueue::mDelete();
    }

    /**
     * 分页数据回调处理.
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function _index_page_filter(array $data, array &$result)
    {
        $result['extra'] = ['dos' => 0, 'pre' => 0, 'oks' => 0, 'ers' => 0];
        SystemQueue::mk()->field('status,count(1) count')->group('status')->select()->map(static function ($item) use (&$result) {
            if (intval($item['status']) === 1) {
                $result['extra']['pre'] = $item['count'];
            }
            if (intval($item['status']) === 2) {
                $result['extra']['dos'] = $item['count'];
            }
            if (intval($item['status']) === 3) {
                $result['extra']['oks'] = $item['count'];
            }
            if (intval($item['status']) === 4) {
                $result['extra']['ers'] = $item['count'];
            }
        });
    }
}
