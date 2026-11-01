<?php

declare(strict_types=1);

// CLI-only test helper. It is outside public/ and refuses non-isolated databases.
if (PHP_SAPI !== 'cli' || getenv('WEBBASE_TEST_ISOLATED') !== '1') {
    exit(1);
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = new \think\App(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
\WebBase\Core\Library::$sapp = $app;
$app->initialize();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
});
$connection = $app->db->connect();
if (!preg_match('/^webbase_(verify|test)_/', (string)$connection->getConfig('database'))) {
    throw new RuntimeException('Integration helpers require a dedicated webbase_test_* or webbase_verify_* database.');
}
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
switch ($input['action']) {
    case 'query':
        $result = $connection->query($input['sql'], $input['params'] ?? []);
        break;
    case 'execute':
        $result = $connection->execute($input['sql'], $input['params'] ?? []);
        break;
    case 'session':
    case 'captcha':
        $app->session->setId($input['session']);
        $app->session->init();
        $result = $input['action'] === 'captcha'
            ? $app->cache->get($app->session->get('login_captcha', ''))
            : $app->session->all();
        break;
    case 'clear-limits':
        foreach (glob($app->getRuntimePath() . 'security/*.json') ?: [] as $file) {
            unlink($file);
        }
        $result = true;
        break;
    case 'set-config':
        $result = sysconf($input['key'], $input['value']);
        break;
    case 'model-lifecycle':
        $model = new class extends \WebBase\Core\BaseModel {
            protected $name = 'AuditProbe';
        };
        $row = $model->create(['title' => 'model audit probe']);
        $id = $row->getAttr('id');
        $check = static function (bool $valid, string $message): void {
            if (!$valid) {
                throw new RuntimeException($message);
            }
        };
        $check($id > 0 && (bool)$row->getData('created_at') && (bool)$row->getData('updated_at'), 'Model insert did not populate audit fields.');
        $model->where('id', $id)->update(['created_at' => '2000-01-01 00:00:00', 'updated_at' => '2000-01-01 00:00:00']);
        $row = $model->where('id', $id)->find();
        $row->save(['title' => 'changed title']);
        $row = $model->where('id', $id)->find();
        $check($row->getData('created_at') === '2000-01-01 00:00:00' && $row->getData('updated_at') > '2000-01-01 00:00:00', 'Model update did not preserve creation time and advance update time.');
        $row->delete();
        $check($model->where('id', $id)->find() === null, 'Deleted row leaked into normal queries.');
        $trashed = $model->onlyTrashed()->where('id', $id)->find();
        $check($trashed !== null && (bool)$trashed->getData('deleted_at'), 'Soft delete did not write deleted_at.');
        $trashed->restore();
        $row = $model->where('id', $id)->find();
        $check($row !== null && $row->getData('deleted_at') === null, 'Restore did not clear deleted_at to NULL.');
        $row->force()->delete();
        $check($model->withTrashed()->where('id', $id)->find() === null, 'Force delete did not remove the row.');
        $result = true;
        break;
    default:
        throw new InvalidArgumentException('Unknown test action.');
}
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
