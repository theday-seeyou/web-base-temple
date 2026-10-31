<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class UnifySoftDeleteFields extends AbstractMigration
{
    /**
     * Convert the legacy delete flags to one nullable deleted_at column.
     */
    public function up(): void
    {
        $system_base = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_base'));
        $system_user = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_user'));
        $now = date('Y-m-d H:i:s');

        $base = $this->table('system_base');
        if ($base->hasColumn('deleted_at')) {
            if ($base->hasColumn('deleted')) {
                $this->execute("UPDATE {$system_base} SET `deleted_at` = CASE WHEN `deleted` = 1 THEN COALESCE(NULLIF(`deleted_at`, ''), '{$now}') ELSE NULL END");
            }
            $base->changeColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
            ]);
        } else {
            $base->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
            ]);
        }
        foreach (['deleted', 'deleted_by'] as $column) {
            if ($base->hasColumn($column)) {
                $base->removeColumn($column);
            }
        }
        $base->update();

        $user = $this->table('system_user');
        if (!$user->hasColumn('deleted_at')) {
            $user->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
            ])->update();
            $user = $this->table('system_user');
        }
        if ($user->hasColumn('is_deleted')) {
            $this->execute("UPDATE {$system_user} SET `deleted_at` = '{$now}' WHERE `is_deleted` = 1");
            $user->removeColumn('is_deleted')->update();
        }

        $category = $this->table('category');
        if ($category->hasColumn('deleted_at')) {
            $category->changeColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
            ])->update();
        }
    }

    /**
     * The legacy fields are intentionally not restored.
     */
    public function down(): void
    {
        $system_base = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_base'));
        $system_user = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_user'));
        throw new IrreversibleMigrationException(
            'Legacy soft-delete fields are permanently replaced by deleted_at.'
        );
    }
}
