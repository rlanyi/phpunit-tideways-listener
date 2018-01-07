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

final class FilteredProfile
{
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
     * @var array
     */
    private $data;

    /**
     * @var string
     */
    private $testClassName;

    /**
     * @var string
     */
    private $testMethodName;

    /**
     * @var string
     */
    private $testDataDescription;

    private function __construct(string $testClassName, $testMethodName, $testDataDescription, array $data)
    {
        $this->data                = $data;
        $this->testClassName       = $testClassName;
        $this->testMethodName      = $testMethodName;
        $this->testDataDescription = $testDataDescription;
    }

    public static function fromProfile(Profile $profile): self
    {
        $data = $profile->data();

        self::filter($data);

        return new self(
            $profile->testClassName(),
            $profile->testMethodName(),
            $profile->testDataDescription(),
            $data
        );
    }

    public function data(): array
    {
        return $this->data;
    }

    public function testClassName(): string
    {
        return $this->testClassName;
    }

    public function testMethodName(): string
    {
        return $this->testMethodName;
    }

    public function testDataDescription(): string
    {
        return $this->testDataDescription;
    }

    private static function filter(array &$data): void
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

    private static function shouldBeFiltered(string $className): bool
    {
        foreach (self::FILTER as $classNamePrefix) {
            if (\strpos($className, $classNamePrefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
