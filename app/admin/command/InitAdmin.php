<?php

declare(strict_types=1);

namespace app\admin\command;

use WebBase\Core\model\SystemUser;
use WebBase\Core\service\AdminService;
use WebBase\Core\service\PasswordService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class InitAdmin extends Command
{
    protected function configure()
    {
        $this->setName('admin:init')->setDescription('Explicitly initialize the first administrator; never overwrite an account');
    }

    protected function execute(Input $input, Output $output)
    {
        if (SystemUser::mk()->withTrashed()->count() > 0) {
            throw new \RuntimeException('Users already exist. This command does not reset existing credentials.');
        }
        $password = getenv('WEBBASE_ADMIN_PASSWORD');
        if ($password === false || $password === '') {
            $password = $output->askHidden($input, 'Administrator password (12–72 bytes)');
        }
        $hash = PasswordService::hash((string)$password);
        SystemUser::mk()->save([
            'id' => 10000,
            'username' => AdminService::getSuperName(),
            'nickname' => '超级管理员',
            'password' => $hash,
            'status' => 1,
            'headimg' => '/static/theme/img/headimg.png',
        ]);
        $output->writeln('<info>Administrator initialized. Keep the password in your password manager.</info>');
        return 0;
    }
}
