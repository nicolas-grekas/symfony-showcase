<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Replaces the role hierarchy of the firewall to let the reviewers of a single conference, e.g. ROLE_REVIEWER_LILLE, reach ROLE_REVIEWER.
 */
#[AsDecorator('security.role_hierarchy')]
final class ReviewerRoleHierarchy extends RoleHierarchy
{
    /**
     * @param array<string, list<string>> $hierarchy
     */
    public function __construct(
        #[Autowire('%security.role_hierarchy.roles%')]
        array $hierarchy,
    ) {
        parent::__construct($hierarchy);
    }

    public function getReachableRoleNames(array $roles): array
    {
        foreach ($roles as $role) {
            if (str_starts_with($role, 'ROLE_REVIEWER_')) {
                $roles[] = 'ROLE_REVIEWER';
                break;
            }
        }

        return parent::getReachableRoleNames($roles);
    }
}
