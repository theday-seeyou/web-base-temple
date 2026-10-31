<?php

declare(strict_types=1);

namespace app\admin\model;

use WebBase\Core\BaseModel;

/**
 * 树形分类示例模型.
 *
 * @property int $id
 * @property int $pid 上级栏目ID
 * @property int $sort 排序
 * @property int $status 状态(0禁用,1启用)
 * @property string $cover 封面图
 * @property string $create_at 创建时间
 * @property string $deleted_at 删除时间
 * @property string $desc 描述
 * @property string $icon 图标
 * @property string $title 栏目名称
 * @property string $update_at 更新时间
 * @class Category
 */
class Category extends BaseModel
{
    /**
     * 操作日志类型.
     * @var string
     */
    protected $oplogType = '树形分类示例';

    /**
     * 操作日志名称.
     * @var string
     */
    protected $oplogName = '栏目';
}
