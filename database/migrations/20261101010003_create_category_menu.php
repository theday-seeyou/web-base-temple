<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCategoryMenu extends AbstractMigration
{
    /**
     * Insert "示例模块" and "树形分类示例" menu items into system_menu.
     */
    public function up(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $now = date('Y-m-d H:i:s');

        // 示例模块（一级菜单）
        $this->table('system_menu')->insert([
            [
                'pid'       => 0,
                'sort'      => 100,
                'status'    => 1,
                'title'     => '示例模块',
                'icon'      => '',
                'node'      => '',
                'url'       => '#',
                'params'    => '',
                'target'    => '_self',
                'created_at' => $now,
            ],
        ])->save();

        // 获取刚插入的一级菜单 ID
        $parent = $this->fetchRow("SELECT id FROM {$system_menu} WHERE title='示例模块' AND pid=0 ORDER BY id DESC LIMIT 1");
        $parentId = $parent['id'];

        // 树形分类示例（二级菜单）
        $this->table('system_menu')->insert([
            [
                'pid'       => $parentId,
                'sort'      => 100,
                'status'    => 1,
                'title'     => '树形分类示例',
                'icon'      => 'layui-icon layui-icon-app',
                'node'      => 'admin/category/index',
                'url'       => 'admin/category/index',
                'params'    => '',
                'target'    => '_self',
                'created_at' => $now,
            ],
        ])->save();
    }

    /**
     * Remove "树形分类示例" and "示例模块" menu items.
     */
    public function down(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $this->execute("DELETE FROM {$system_menu} WHERE node='admin/category/index' AND title='树形分类示例'");
        $this->execute("DELETE FROM {$system_menu} WHERE title='示例模块' AND pid=0 AND id NOT IN (SELECT pid FROM (SELECT pid FROM {$system_menu} WHERE pid > 0) AS tmp)");
    }
}
