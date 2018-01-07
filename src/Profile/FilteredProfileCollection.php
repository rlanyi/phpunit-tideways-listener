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

final class FilteredProfileCollection implements \IteratorAggregate
{
    /**
     * @var FilteredProfile[]
     */
    private $profiles = [];

    public static function fromProfileCollection(ProfileCollection $profiles): self
    {
        $filteredProfiles = new self;

        foreach ($profiles as $profile) {
            $filteredProfiles->add(FilteredProfile::fromProfile($profile));
        }

        return $filteredProfiles;
    }

    /**
     * @return FilteredProfile[]
     */
    public function asArray(): array
    {
        return $this->profiles;
    }

    public function getIterator(): FilteredProfileCollectionIterator
    {
        return new FilteredProfileCollectionIterator($this);
    }

    private function add(FilteredProfile $profile): void
    {
        $this->profiles[] = $profile;
    }
}
