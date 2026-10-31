<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameCategoryDemoMenu extends AbstractMigration
{
    public function up(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $this->execute(
            "UPDATE {$system_menu} SET title='树形分类示例' " .
            "WHERE node='admin/category/index' AND title='栏目管理'"
        );
        $this->execute(
            "UPDATE {$system_menu} SET title='示例模块' " .
            "WHERE pid=0 AND title='内容管理' AND id IN (" .
            "SELECT parent_id FROM (" .
            "SELECT DISTINCT pid AS parent_id FROM {$system_menu} WHERE node='admin/category/index'" .
            ") AS category_parents)"
        );
    }

    public function down(): void
    {
        $system_menu = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_menu'));
        $this->execute(
            "UPDATE {$system_menu} SET title='栏目管理' " .
            "WHERE node='admin/category/index' AND title='树形分类示例'"
        );
        $this->execute(
            "UPDATE {$system_menu} SET title='内容管理' " .
            "WHERE pid=0 AND title='示例模块' AND id IN (" .
            "SELECT parent_id FROM (" .
            "SELECT DISTINCT pid AS parent_id FROM {$system_menu} WHERE node='admin/category/index'" .
            ") AS category_parents)"
        );
    }
}
