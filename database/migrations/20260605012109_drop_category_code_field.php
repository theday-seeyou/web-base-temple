<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropCategoryCodeField extends AbstractMigration
{
    /**
     * Migrate Up.
     *
     * Remove unused `code` column from category table.
     */
    public function up(): void
    {
        $table = $this->table('category');
        if ($table->hasColumn('code')) {
            $table->removeIndex(['code']);
            $table->removeColumn('code');
            $table->save();
        }
    }

    /**
     * Migrate Down.
     *
     * Re-add `code` column to category table.
     */
    public function down(): void
    {
        $table = $this->table('category');
        if (!$table->hasColumn('code')) {
            $table->addColumn('code', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => null,
                'comment' => '栏目标识',
                'after' => 'title',
            ])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uniq_code'])
            ->save();
        }
    }
}
