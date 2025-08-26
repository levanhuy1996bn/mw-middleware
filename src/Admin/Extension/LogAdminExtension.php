<?php

namespace App\Admin\Extension;

use Endertech\EcommerceMiddlewareAdminBundle\Admin\LogAdmin;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;

class LogAdminExtension extends AbstractAdminExtension
{
    public function configureDefaultSortValues(AdminInterface $admin, array &$sortValues): void
    {
        if (!$admin instanceof LogAdmin) {
            return;
        }

        $sortValues['_sort_order'] = 'DESC';
        $sortValues['_sort_by'] = 'createdAt';
    }

    public function configureDefaultFilterValues(AdminInterface $admin, array &$filterValues): void
    {
        if (!$admin instanceof LogAdmin) {
            return;
        }

        // limit the requested logs by default to today to prevent page timeouts
        $start = new \DateTime('today 00:00:00 America/Los_Angeles');
        $end = new \DateTime('tomorrow 00:00:00 America/Los_Angeles');

        $filterValues['createdAt']['value']['start'] = $start->format('m/d/Y g:i a');
        $filterValues['createdAt']['value']['end'] = $end->format('m/d/Y g:i a');
    }
}
