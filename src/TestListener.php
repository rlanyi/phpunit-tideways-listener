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
     * @var string[]
     */
    private const FILTER = [
        'Composer\Autoload',
        'DeepCopy',
        'PHPUnit',
        'Prophecy',
        'phpDocumentor\Reflection',
        'Doctrine\Instantiator',
        'SebastianBergmann\CodeCoverage',
        'SebastianBergmann\Comparator',
        'SebastianBergmann\Diff',
        'SebastianBergmann\Environment',
        'SebastianBergmann\Exporter',
        'SebastianBergmann\GlobalState',
        'SebastianBergmann\Invoker',
        'SebastianBergmann\RecursionContext',
        'SebastianBergmann\Timer',
        'SebastianBergmann\Version',
        'File_Iterator',
        'PHP_Invoker',
        'PHP_Timer',
        'PHP_Token',
        'Text_Template'
    ];

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

        \tideways_xhprof_enable(\TIDEWAYS_XHPROF_FLAGS_MEMORY | \TIDEWAYS_XHPROF_FLAGS_CPU);
    }

    public function endTest(Test $test, $time): void
    {
        if (!$test instanceof TestCase) {
            return;
        }

        $data = \tideways_xhprof_disable();

        $this->filter($data);

        \file_put_contents($this->fileName($test), \json_encode($data));
    }

    /**
     * @throws TidewaysExtensionNotLoadedException
     */
    private function ensureProfilerIsAvailable(): void
    {
        if (!\extension_loaded('tideways_xhprof')) {
            throw new TidewaysExtensionNotLoadedException;
        }
    }

    /**
     * @throws InvalidTargetDirectoryException
     */
    private function ensureTargetDirectoryIsWritable(string $directory): void
    {
        if (!@\mkdir($directory) && !\is_dir($directory)) {
            throw new InvalidTargetDirectoryException;
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

    private function filter(array &$data): void
    {
        foreach (\array_keys($data) as $key) {
            if ($key === 'main()') {
                continue;
            }

            [$caller, $callee] = \explode('==>', $key);

            if ($callee === 'spl_autoload_call' || self::shouldBeFiltered($caller) || self::shouldBeFiltered($callee)) {
                unset($data[$key]);
            }
        }
    }

    private function shouldBeFiltered(string $className): bool
    {
        foreach (self::FILTER as $classNamePrefix) {
            if (\strpos($className, $classNamePrefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
