<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class RoleHierarchyTest extends KernelTestCase
{
    public function testTheReviewersOfAConferenceAreReviewers()
    {
        $roles = static::getContainer()->get(RoleHierarchyInterface::class)->getReachableRoleNames(['ROLE_REVIEWER_LILLE']);

        $this->assertContains('ROLE_REVIEWER_LILLE', $roles);
        $this->assertContains('ROLE_REVIEWER', $roles);
    }

    public function testOtherRolesAreNot()
    {
        $roles = static::getContainer()->get(RoleHierarchyInterface::class)->getReachableRoleNames(['ROLE_SPEAKER_LILLE']);

        $this->assertNotContains('ROLE_REVIEWER', $roles);
    }
}
