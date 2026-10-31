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
    default:
        throw new InvalidArgumentException('Unknown test action.');
}
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
