<?php

declare(strict_types=1);

namespace app\common\model;

use app\admin\helper\DeleteHelper;

/**
 * 应用模型基类.
 *
 * 所有应用模块下的新模型应继承此类。
 * 内置 SoftDelete 特性，使用 deleted_at（datetime）字段实现软删除。
 *
 * 使用方式：
 *   1. 数据库表需包含 deleted_at 字段（datetime, 默认 NULL）
 *   2. 模型继承此类即可自动支持软删除查询过滤
 *   3. mDelete() 方法会调用自定义 DeleteHelper，写入 deleted_at 时间戳
 *
 * @class BaseModel
 * @mixin \think\db\Query
 */
abstract class BaseModel extends \WebBase\Core\Model
{
    use \think\model\concern\SoftDelete;

    /**
     * 软删除字段名.
     * @var string
     */
    protected $deleteTime = 'deleted_at';

    /**
     * 软删除字段默认值.
     * @var null
     */
    protected $defaultSoftDelete = null;

    /**
     * 快捷删除操作.
     *
     * 路由到自定义 DeleteHelper，支持 deleted_at（datetime）软删除方式。
     * 子类可覆盖此方法以实现自定义删除逻辑。
     *
     * @param string $field 操作数据主键
     * @param mixed $where 额外更新条件
     * @return bool|void
     */
    public static function mDelete(string $field = '', $where = [])
    {
        return DeleteHelper::instance()->init(static::class, $field, $where);
    }
}
