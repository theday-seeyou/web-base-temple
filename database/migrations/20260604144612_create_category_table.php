<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCategoryTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('category', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
            'comment' => '栏目管理',
        ]);

        $table
            ->addColumn('pid', 'integer', [
                'limit' => 10,
                'signed' => false,
                'default' => 0,
                'comment' => '上级栏目ID',
            ])
            ->addColumn('title', 'string', [
                'limit' => 100,
                'default' => '',
                'comment' => '栏目名称',
            ])
            ->addColumn('code', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => null,
                'comment' => '栏目标识',
            ])
            ->addColumn('icon', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
                'comment' => '栏目图标',
            ])
            ->addColumn('cover', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'comment' => '封面图',
            ])
            ->addColumn('desc', 'text', [
                'null' => true,
                'default' => null,
                'comment' => '描述',
            ])
            ->addColumn('sort', 'integer', [
                'limit' => 10,
                'signed' => false,
                'default' => 0,
                'comment' => '排序',
            ])
            ->addColumn('status', 'boolean', [
                'signed' => false,
                'default' => 1,
                'comment' => '状态(1启用,0禁用)',
            ])
            ->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
            ])
            ->addColumn('create_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '创建时间',
            ])
            ->addColumn('update_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '更新时间',
            ])
            ->addIndex(['pid'], ['name' => 'idx_pid'])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uniq_code'])
            ->create();
    }
}
