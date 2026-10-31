<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCategoryMenu extends AbstractMigration
{
    /**
     * Insert "内容管理" and "栏目管理" menu items into system_menu.
     */
    public function up(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $now = date('Y-m-d H:i:s');

        // 内容管理（一级菜单）
        $this->table('system_menu')->insert([
            [
                'pid'       => 0,
                'sort'      => 100,
                'status'    => 1,
                'title'     => '内容管理',
                'icon'      => '',
                'node'      => '',
                'url'       => '#',
                'params'    => '',
                'target'    => '_self',
                'create_at' => $now,
            ],
        ])->save();

        // 获取刚插入的一级菜单 ID
        $parent = $this->fetchRow("SELECT id FROM {$system_menu} WHERE title='内容管理' AND pid=0 ORDER BY id DESC LIMIT 1");
        $parentId = $parent['id'];

        // 栏目管理（二级菜单）
        $this->table('system_menu')->insert([
            [
                'pid'       => $parentId,
                'sort'      => 100,
                'status'    => 1,
                'title'     => '栏目管理',
                'icon'      => 'layui-icon layui-icon-app',
                'node'      => 'admin/category/index',
                'url'       => 'admin/category/index',
                'params'    => '',
                'target'    => '_self',
                'create_at' => $now,
            ],
        ])->save();
    }

    /**
     * Remove "栏目管理" and "内容管理" menu items.
     */
    public function down(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $this->execute("DELETE FROM {$system_menu} WHERE node='admin/category/index' AND title='栏目管理'");
        $this->execute("DELETE FROM {$system_menu} WHERE title='内容管理' AND pid=0 AND id NOT IN (SELECT pid FROM (SELECT pid FROM {$system_menu} WHERE pid > 0) AS tmp)");
    }
}
