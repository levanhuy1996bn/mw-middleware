<?php

namespace App\EventSubscriber;

use Endertech\EcommerceMiddleware\Driver\Storis\Event\CalculateAvailableInventoryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorisCalculateInventorySubscriber implements EventSubscriberInterface
{
    private $enabled = true;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CalculateAvailableInventoryEvent::class => 'onCalculateAvailableInventory',
        ];
    }

    public function onCalculateAvailableInventory($event)
    {
        if (!$this->enabled || !$event instanceof CalculateAvailableInventoryEvent) {
            return;
        }

        //$product = $event->getProduct();
        // $product['id'];  // sku
        // $product['inventory']['locations'][$i]['locationId'];
        // $product['inventory']['locations'][$i]['quantityAvailable'];
        // $product['inventory']['netQuantityAvailable'];
        // $product['categoryId'];
        // $product['webCategoryIds'];
        // $product['availableOnWeb'];

        //$productAvailableInventory = $event->getProductAvailableInventory(); // integer
        //$locationsInventory = $event->getLocationsInventory(); // [ locationId => quantity, ... ]
        //$locationsWhereInventoryIsAvailable = $event->getLocationsWhereInventoryIsAvailable(); // [ 0 => [ "locationId" => locationId, "quantityAvailable" => quantity ], 1 => ... ]

        //$newProductAvailableInventory = 0;
        //$newLocationsInventory = [];
        //$newLocationsWhereInventoryIsAvailable = [];

        // ...

        //$event->setProductAvailableInventory($newProductAvailableInventory);
        //$event->setLocationsInventory($newLocationsInventory);
        //$event->setLocationsWhereInventoryIsAvailable($newLocationsWhereInventoryIsAvailable);
    }
}
