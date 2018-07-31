<?php declare(strict_types=1);
/*
 * This file is part of the phpunit-tideways-listener.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Tideways;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener as TestListenerInterface;
use PHPUnit\Framework\TestListenerDefaultImplementation;

final class TestListener implements TestListenerInterface
{
    use TestListenerDefaultImplementation;

    /**
     * @var string
     */
    private $targetDirectory;

    /**
     * @throws InvalidTargetDirectoryException
     * @throws TidewaysExtensionNotLoadedException
     */
    public function __construct(string $targetDirectory = '/tmp')
    {
        $this->ensureTargetDirectoryIsWritable($targetDirectory);
        $this->ensureProfilerIsAvailable();

        $this->targetDirectory = \realpath($targetDirectory);
    }

    public function startTest(Test $test): void
    {
        if (!$test instanceof TestCase) {
            return;
        }

        if (!\extension_loaded('tideways_xhprof')) {
            return;
        }

        if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $_SERVER['REQUEST_TIME_FLOAT'] = \microtime(true);
        }

        \tideways_xhprof_enable(\TIDEWAYS_XHPROF_FLAGS_MEMORY | \TIDEWAYS_XHPROF_FLAGS_CPU);
    }

    public function endTest(Test $test, $time): void
    {
        if (!$test instanceof TestCase) {
            return;
        }

        if (!\extension_loaded('tideways_xhprof')) {
            return;
        }

        $data['profile'] = \tideways_xhprof_disable();

        // ignore_user_abort(true) allows your PHP script to continue executing, even if the user has terminated their request.
        // Further Reading: http://blog.preinheimer.com/index.php?/archives/248-When-does-a-user-abort.html
        // flush() asks PHP to send any data remaining in the output buffers. This is normally done when the script completes, but
        // since we're delaying that a bit by dealing with the xhprof stuff, we'll do it now to avoid making the user wait.
        \ignore_user_abort(true);
        \flush();
        if (\function_exists('fastcgi_finish_request')) {
            \fastcgi_finish_request();
        }

        $uri = \array_key_exists('REQUEST_URI', $_SERVER)
            ? $_SERVER['REQUEST_URI']
            : null;
        if (empty($uri) && isset($_SERVER['argv'])) {
            $cmd = \basename($_SERVER['argv'][0]);
            $uri = $cmd . ' ' . \implode(' ', \array_slice($_SERVER['argv'], 1));
        }

        $time = \array_key_exists('REQUEST_TIME', $_SERVER)
            ? $_SERVER['REQUEST_TIME']
            : \time();

        $time_float = \array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)
            ? $_SERVER['REQUEST_TIME_FLOAT']
            : \microtime();

        // In some cases there is comma instead of dot
        $requestTimeFloat = \explode('.', (string)$time_float);
        if (!isset($requestTimeFloat[1])) {
            $requestTimeFloat[1] = 0;
        }

        $requestTs = array('sec' => $time, 'usec' => 0);
        $requestTsMicro = array('sec' => $requestTimeFloat[0], 'usec' => $requestTimeFloat[1]);

        $data['meta'] = array(
            'url' => $uri,
            'SERVER' => $_SERVER,
            'get' => $_GET,
            'env' => $_ENV,
            'simple_url' => $uri,
            'request_ts' => $requestTs,
            'request_ts_micro' => $requestTsMicro,
            'request_date' => date('Y-m-d', $time),
        );

        \file_put_contents($this->fileName($test), \json_encode($data));
    }

    /**
     * @throws TidewaysExtensionNotLoadedException
     */
    private function ensureProfilerIsAvailable(): void
    {
        if (!\extension_loaded('tideways_xhprof')) {
            echo "\033[31mExtension tideways_xhprof is not loaded, won't produce profiling output.\033[0m\n";
        }
    }

    /**
     * @throws InvalidTargetDirectoryException
     */
    private function ensureTargetDirectoryIsWritable(string $directory): void
    {
        if (!@\mkdir($directory) && !\is_dir($directory)) {
            printf("\033[31mData directory %s doesn't exists or is not writalbe, won't produce profiling output.\033[0m\n", $directory);
        }
    }

    private function fileName(TestCase $test): string
    {
        $id = \str_replace('\\', '_', \get_class($test)) . '::' . $test->getName(false);

        if (!empty($test->dataDescription())) {
            $id .= '#' . \str_replace(' ', '_', $test->dataDescription());
        }

        return $this->targetDirectory . DIRECTORY_SEPARATOR . $id . '.json';
    }
}
