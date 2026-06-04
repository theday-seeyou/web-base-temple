<?php

declare(strict_types=1);

namespace app\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Model;

/**
 * 自定义删除管理器.
 *
 * 支持 deleted_at（datetime）软删除方式，
 * 替代官方 deleted/is_deleted（0/1）方案。
 *
 * @class DeleteHelper
 */
class DeleteHelper extends Helper
{
    /**
     * 逻辑器初始化.
     *
     * 当表包含 deleted_at 字段时，执行软删除（设置 deleted_at = 当前时间）；
     * 否则执行硬删除（直接从数据库移除记录）。
     *
     * @param BaseQuery|Model|string $dbQuery 查询对象或模型类名
     * @param string $field 操作数据主键
     * @param mixed $where 额外更新条件
     * @return bool|void
     * @throws DbException
     */
    public function init($dbQuery, string $field = '', $where = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $this->app->request->post($field);

        // 查询限制处理
        if (!empty($where)) {
            $query->where($where);
        }
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
        }

        // 前置回调处理
        if ($this->class->callback('_delete_filter', $query, $where) === false) {
            return false;
        }

        // 阻止无条件的危险删除操作
        if (!$query->getOptions('where')) {
            $this->class->error('数据删除失败！');
        }

        // 检测 deleted_at 字段，决定软删除或硬删除
        $data = [];
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            if (in_array('deleted_at', $fields)) {
                // 存在 deleted_at 字段，使用 datetime 软删除
                $data['deleted_at'] = date('Y-m-d H:i:s');
            }
        }

        // 执行删除操作
        $result = empty($data) ? $query->delete() : $query->update($data);
        if ($result !== false) {
            // 模型自定义事件回调
            $model = $query->getModel();
            if ($model instanceof \think\admin\Model) {
                $model->onAdminDelete(strval($value));
            }
        }

        // 结果回调处理
        if ($this->class->callback('_delete_result', $result) === false) {
            return $result;
        }

        // 回复返回结果
        if ($result !== false) {
            $this->class->success('数据删除成功！', '');
        } else {
            $this->class->error('数据删除失败！');
        }
    }
}
