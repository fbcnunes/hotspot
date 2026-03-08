<?php
class MockPhpStream {
    public $context;
    private static $content;

    public static function register(string $content): void
    {
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', __CLASS__);
        self::$content = $content;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr(self::$content, 0, $count);
        self::$content = substr(self::$content, $count);
        return $ret;
    }

    public function stream_eof()
    {
        return self::$content === '';
    }
}

MockPhpStream::register(json_encode(['username' => '9100']));
$_SERVER['HTTP_X_AUTH_TOKEN'] = 'change-me';
$_SERVER['REMOTE_ADDR'] = '192.168.121.1';
$_SERVER['REQUEST_URI'] = '/api/check';
$_SERVER['REQUEST_METHOD'] = 'POST';
require __DIR__ . '/../public/index.php';
