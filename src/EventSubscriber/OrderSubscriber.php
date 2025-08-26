<?php

namespace App\EventSubscriber;

use Endertech\EcommerceMiddlewareEvents\Core\Data\Order\MappedOrder\PostCreateDestinationOrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PostCreateDestinationOrderEvent::class => 'modifyStorisOrderBeforeInsert',
        ];
    }

    public function modifyStorisOrderBeforeInsert(PostCreateDestinationOrderEvent $event)
    {
        //$storisOrder = $event->getReturnValue();
        //if (!is_array($storisOrder)) {
        //    return;
        //}

        //$mappedOrder = $event->getMappedOrder();

        //$isPickup = false;
        //if ($mappedOrder->getDataDestinationPickupLocationId()
        //    || (isset($storisOrder['pickupLocationId']) && $storisOrder['pickupLocationId'])
        //) {
        //    $isPickup = true;
        //}

        //if (!$isPickup) {
        //    // ...
        //}

        //$storisOrder['sellLocationId'] = '';

        //$event->setReturnValue($storisOrder);
    }
}
