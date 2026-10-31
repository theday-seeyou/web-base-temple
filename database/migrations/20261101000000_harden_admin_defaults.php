<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class HardenAdminDefaults extends AbstractMigration
{
    public function up(): void
    {
        $this->table('system_user')->changeColumn('password', 'string', [
            'limit' => 255, 'default' => '', 'null' => true, 'comment' => '用户密码散列',
        ])->update();
        $config = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_config'));
        $this->execute("UPDATE {$config} SET value='wangEditor' WHERE type='base' AND name='editor' AND value='ckeditor5'");
        $this->execute("UPDATE {$config} SET value='pdf,doc,docx,gif,ico,jpg,jpeg,webp,mp3,mp4,png,zip,rar,xls,xlsx' WHERE type='storage' AND name='allow_exts' AND value='doc,gif,ico,jpg,mp3,mp4,p12,pem,png,zip,rar,xls,xlsx'");
        \WebBase\Core\Library::$sapp->cache->delete('SystemConfig');
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException('Modern password hashes cannot be truncated back to MD5 storage.');
    }
}
